<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Tests;

use Andriichuk\LaravelBilling\BillingManager;
use Andriichuk\LaravelBilling\Enums\SubscriptionStatus;
use Andriichuk\LaravelBilling\Enums\WebhookStatus;
use Andriichuk\LaravelBilling\Models\Subscription;
use Andriichuk\LaravelBilling\Models\WebhookEvent;
use Andriichuk\LaravelBilling\Data\Events\SubscriptionUpdated as NormalizedSubscriptionUpdated;
use Andriichuk\LaravelBilling\Data\Events\NormalizedEvent;
use Andriichuk\LaravelBilling\Exceptions\RetryableProviderOperation;
use Andriichuk\LaravelBilling\Jobs\ProcessWebhook;
use Andriichuk\LaravelBilling\Webhooks\WebhookHandlerRegistry;
use Andriichuk\LaravelBilling\Webhooks\WebhookProcessor;
use Illuminate\Support\Facades\Bus;
use Andriichuk\LaravelBilling\Testing\FakeDriver;
use Andriichuk\LaravelBilling\Data\WebhookRequest;
use Andriichuk\LaravelBilling\Tests\Fixtures\User;
use PHPUnit\Framework\Attributes\Test;

final class WebhookTest extends TestCase
{
    #[Test] public function signatures_are_verified_against_the_exact_raw_body_and_events_are_applied(): void
    {
        $user = User::query()->create(['name' => 'Ada']); $key = $user->getKey(); self::assertIsInt($key); $body = '{ "id": "evt-1", "type": "subscription.updated", "resource_id": "sub-1", "normalized": [{"class":"SubscriptionUpdated","resource_id":"sub-1","data":{"billable_type":"'.addslashes($user->getMorphClass()).'","billable_id":"'.$key.'","type":"default","status":"active","provider_price_id":"price-1"}}]}';
        $signature = hash_hmac('sha256', $body, 'fake-secret'); $this->call('POST', '/billing/webhooks/fake', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_BILLING_SIGNATURE' => $signature], $body)->assertNoContent();
        self::assertDatabaseCount('billing_webhook_events', 1); self::assertSame(WebhookStatus::Processed, WebhookEvent::query()->firstOrFail()->status); self::assertSame(SubscriptionStatus::Active, Subscription::query()->firstOrFail()->status);
        $driver = app(BillingManager::class)->driver(); self::assertInstanceOf(FakeDriver::class, $driver); $verified = collect($driver->recordedRequests())->firstWhere('operation', 'verifyWebhook'); self::assertNotNull($verified); $verifiedRequest = $verified['data']; self::assertInstanceOf(WebhookRequest::class, $verifiedRequest); self::assertSame($body, $verifiedRequest->rawBody);
    }
    #[Test] public function invalid_signatures_are_rejected_before_persistence(): void { $body = '{"id":"evt-bad","type":"unknown"}'; $this->call('POST', '/billing/webhooks/fake', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_BILLING_SIGNATURE' => 'wrong'], $body)->assertBadRequest(); self::assertDatabaseCount('billing_webhook_events', 0); }
    #[Test] public function duplicate_deliveries_are_idempotent_and_unknown_events_are_ignored(): void { $body = '{"id":"evt-unknown","type":"future.event","secret":"must-not-persist"}'; $signature = hash_hmac('sha256', $body, 'fake-secret'); foreach ([1, 2] as $_) { $this->call('POST', '/billing/webhooks/fake', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_BILLING_SIGNATURE' => $signature], $body)->assertNoContent(); } self::assertDatabaseCount('billing_webhook_events', 1); $event = WebhookEvent::query()->firstOrFail(); self::assertSame(WebhookStatus::Ignored, $event->status); $providerPayload = $event->payload['provider'] ?? null; self::assertIsArray($providerPayload); self::assertArrayNotHasKey('secret', $providerPayload); }
    #[Test] public function a_successful_queue_handoff_leaves_the_ledger_durable(): void
    {
        Bus::fake(); $body = '{"id":"evt-queued","type":"future.event"}'; $signature = hash_hmac('sha256', $body, 'fake-secret'); $this->call('POST', '/billing/webhooks/fake', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_BILLING_SIGNATURE' => $signature], $body)->assertNoContent(); Bus::assertDispatched(ProcessWebhook::class); self::assertSame(WebhookStatus::Queued, WebhookEvent::query()->firstOrFail()->status);
    }
    #[Test] public function retryable_processing_failures_are_recorded_before_rethrow(): void
    {
        app(WebhookHandlerRegistry::class)->register(NormalizedSubscriptionUpdated::class, function (string $driver, NormalizedEvent $event): void { throw new RetryableProviderOperation('temporary outage'); });
        $event = WebhookEvent::query()->create(['driver' => 'fake', 'event_key' => 'evt-retry', 'event_type' => 'subscription.updated', 'provider_resource_id' => 'sub-1', 'status' => WebhookStatus::Queued, 'attempts' => 0, 'payload' => ['provider' => [], 'normalized' => [['class' => NormalizedSubscriptionUpdated::class, 'resource_id' => 'sub-1', 'data' => []]]], 'received_at' => now()]);
        try { app(WebhookProcessor::class)->process($event); self::fail('Expected retryable processing to rethrow.'); } catch (RetryableProviderOperation) {}
        $event->refresh(); self::assertSame(WebhookStatus::Failed, $event->status); self::assertSame(1, $event->attempts); self::assertSame('temporary outage', $event->error);
    }
}
