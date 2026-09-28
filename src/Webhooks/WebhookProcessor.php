<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Webhooks;

use Andriichuk\LaravelBilling\BillingManager;
use Andriichuk\LaravelBilling\Contracts\ProcessesWebhooks;
use Andriichuk\LaravelBilling\Data\Events\NormalizedEvent;
use Andriichuk\LaravelBilling\Data\WebhookRequest;
use Andriichuk\LaravelBilling\Enums\Capability;
use Andriichuk\LaravelBilling\Enums\WebhookStatus;
use Andriichuk\LaravelBilling\Events\WebhookFailed;
use Andriichuk\LaravelBilling\Events\WebhookIgnored;
use Andriichuk\LaravelBilling\Events\WebhookProcessed;
use Andriichuk\LaravelBilling\Events\WebhookReceived;
use Andriichuk\LaravelBilling\Exceptions\RetryableProviderOperation;
use Andriichuk\LaravelBilling\Exceptions\UnsupportedCapability;
use Andriichuk\LaravelBilling\Jobs\ProcessWebhook;
use Andriichuk\LaravelBilling\Models\WebhookEvent;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Bus;
use Throwable;

final class WebhookProcessor
{
    public function __construct(private readonly BillingManager $billing, private readonly WebhookHandlerRegistry $handlers, private readonly Dispatcher $events) {}

    public function receive(string $driverName, WebhookRequest $request): WebhookEvent
    {
        $driver = $this->billing->require(Capability::Webhooks, $driverName);
        if (! $driver instanceof ProcessesWebhooks) { throw UnsupportedCapability::for($driver, Capability::Webhooks); }
        $driver->verifyWebhook($request);
        $parsed = $driver->parseWebhook($request);
        $class = config('billing.models.webhook_event', WebhookEvent::class);
        if (! is_string($class) || ! is_a($class, WebhookEvent::class, true)) { $class = WebhookEvent::class; }
        $normalized = array_map(fn (NormalizedEvent $event): array => ['class' => $event::class, 'resource_id' => $event->providerResourceId(), 'data' => $event->data()], $parsed->events);
        try {
            /** @var WebhookEvent $webhook */
            $webhook = $class::query()->create(['driver' => $driverName, 'event_key' => $parsed->eventKey, 'event_type' => $parsed->eventType, 'provider_resource_id' => $parsed->providerResourceId, 'status' => WebhookStatus::Received, 'attempts' => 0, 'payload' => ['provider' => $parsed->sanitizedPayload, 'normalized' => $normalized], 'received_at' => $request->receivedAt]);
        } catch (QueryException) {
            /** @var WebhookEvent $webhook */
            $webhook = $class::query()->where('driver', $driverName)->where('event_key', $parsed->eventKey)->firstOrFail();
            return $webhook;
        }
        $this->events->dispatch(new WebhookReceived($webhook));
        try {
            $webhook->forceFill(['status' => WebhookStatus::Queued, 'queued_at' => now()])->save();
            $key = $webhook->getKey(); if (! is_int($key) && ! is_string($key)) { throw new \UnexpectedValueException('Persisted webhook has no scalar key.'); }
            $job = new ProcessWebhook($key);
            $connection = config('billing.webhooks.connection'); if (is_string($connection) && $connection !== '') { $job->onConnection($connection); }
            $queue = config('billing.webhooks.queue'); if (is_string($queue) && $queue !== '') { $job->onQueue($queue); }
            Bus::dispatch($job);
        } catch (Throwable $exception) {
            $webhook->forceFill(['status' => WebhookStatus::Failed, 'failed_at' => now(), 'error' => mb_substr($exception->getMessage(), 0, 65535)])->save();
            throw $exception;
        }
        return $webhook;
    }

    public function process(WebhookEvent $webhook): void
    {
        if (in_array($webhook->status, [WebhookStatus::Processed, WebhookStatus::Ignored], true)) { return; }
        $webhook->forceFill(['status' => WebhookStatus::Processing, 'attempts' => $webhook->attempts + 1, 'error' => null])->save();
        try {
            $payload = $webhook->payload; $serialized = $payload['normalized'] ?? [];
            if (! is_array($serialized) || $serialized === []) { $webhook->forceFill(['status' => WebhookStatus::Ignored, 'processed_at' => now(), 'failed_at' => null])->save(); $this->events->dispatch(new WebhookIgnored($webhook)); return; }
            foreach ($serialized as $item) { if (! is_array($item)) { continue; } $event = $this->restoreEvent($item); $this->handlers->apply($webhook->driver, $event); }
            $webhook->forceFill(['status' => WebhookStatus::Processed, 'processed_at' => now(), 'failed_at' => null])->save();
            $this->events->dispatch(new WebhookProcessed($webhook));
        } catch (Throwable $exception) {
            $webhook->forceFill(['status' => WebhookStatus::Failed, 'failed_at' => now(), 'error' => mb_substr($exception->getMessage(), 0, 65535)])->save();
            $this->events->dispatch(new WebhookFailed($webhook, $exception));
            if ($exception instanceof RetryableProviderOperation) { throw $exception; }
        }
    }

    /** @param array<mixed, mixed> $item */
    private function restoreEvent(array $item): NormalizedEvent
    {
        $class = $item['class'] ?? null; $id = $item['resource_id'] ?? null; $data = $item['data'] ?? [];
        if (! is_string($class) || ! str_starts_with($class, 'Andriichuk\\LaravelBilling\\Data\\Events\\') || ! is_a($class, NormalizedEvent::class, true) || ! is_string($id) || ! is_array($data)) { throw new \UnexpectedValueException('Stored normalized webhook event is invalid.'); }
        /** @var NormalizedEvent $event */ $event = new $class($id, $data); return $event;
    }
}
