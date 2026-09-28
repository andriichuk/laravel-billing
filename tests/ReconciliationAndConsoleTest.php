<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Tests;

use Andriichuk\LaravelBilling\BillingManager;
use Andriichuk\LaravelBilling\Contracts\ResolvesReconciliationBillables;
use Andriichuk\LaravelBilling\Data\CustomerData;
use Andriichuk\LaravelBilling\Data\ReconciliationRequest;
use Andriichuk\LaravelBilling\Data\ReconciliationResult;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\Data\TransactionData;
use Andriichuk\LaravelBilling\Enums\SubscriptionStatus;
use Andriichuk\LaravelBilling\Events\SubscriptionUpdated;
use Andriichuk\LaravelBilling\Models\Subscription;
use Andriichuk\LaravelBilling\Support\BillingSynchronizer;
use Andriichuk\LaravelBilling\Support\ReconciliationService;
use Andriichuk\LaravelBilling\Testing\FakeDriver;
use Andriichuk\LaravelBilling\Tests\Fixtures\User;
use Andriichuk\LaravelBilling\ValueObjects\SubscriptionReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;

final class ReconciliationAndConsoleTest extends TestCase
{
    #[Test]
    public function an_argument_free_sweep_reconciles_multiple_results(): void
    {
        $user = User::query()->create(['name' => 'Ada']);
        $this->resolveOrphansTo($user);
        $driver = $this->fakeDriver();
        $driver->addReconciliationResult($this->subscriptionResult('remote-1', 'price-1'));
        $driver->addReconciliationResult($this->subscriptionResult('remote-2', 'price-2', 'premium'));

        $summary = app(ReconciliationService::class)->run('fake', new ReconciliationRequest);

        self::assertSame(2, $summary->reconciled);
        self::assertSame(0, $summary->unchanged);
        self::assertSame(0, $summary->skippedUnresolvable);
        self::assertDatabaseCount('billing_subscriptions', 2);
        $request = collect($driver->recordedRequests())->firstWhere('operation', 'reconcile')['data'] ?? null;
        self::assertInstanceOf(ReconciliationRequest::class, $request);
        self::assertNull($request->id);
    }

    #[Test]
    public function reconciliation_resolves_a_billable_from_an_existing_provider_row(): void
    {
        $user = User::query()->create(['name' => 'Ada']);
        app(BillingSynchronizer::class)->subscription($user, 'fake', $this->subscription('remote-1', 'old-price'));
        $this->fakeDriver()->addReconciliationResult($this->subscriptionResult('remote-1', 'new-price'));

        $summary = app(ReconciliationService::class)->run('fake', new ReconciliationRequest('subscription'));

        self::assertSame(1, $summary->reconciled);
        self::assertSame('new-price', Subscription::query()->firstOrFail()->provider_price_id);
    }

    #[Test]
    public function an_application_resolver_handles_resources_without_local_rows(): void
    {
        $user = User::query()->create(['name' => 'Ada']);
        $this->resolveOrphansTo($user);
        $this->fakeDriver()->addReconciliationResult($this->subscriptionResult('remote-1', 'price-1'));

        $summary = app(ReconciliationService::class)->run('fake', new ReconciliationRequest('subscription'));
        $key = $user->getKey();
        self::assertIsInt($key);

        self::assertSame(1, $summary->reconciled);
        self::assertDatabaseHas('billing_subscriptions', [
            'provider_subscription_id' => 'remote-1',
            'billable_type' => $user->getMorphClass(),
            'billable_id' => (string) $key,
        ]);
    }

    #[Test]
    public function unresolvable_resources_are_skipped_without_failing_the_sweep(): void
    {
        $this->fakeDriver()->addReconciliationResult($this->subscriptionResult('orphan', 'price-1'));

        $this->artisanCommand('billing:reconcile')
            ->expectsOutputToContain('Skipped unresolvable subscription orphan')
            ->expectsOutputToContain('skipped-unresolvable: 1')
            ->assertSuccessful();

        self::assertDatabaseCount('billing_subscriptions', 0);
    }

    #[Test]
    public function reconciliation_events_are_change_gated_and_force_can_replay_them(): void
    {
        $user = User::query()->create(['name' => 'Ada']);
        $this->resolveOrphansTo($user);
        $this->fakeDriver()->addReconciliationResult($this->subscriptionResult('remote-1', 'price-1'));
        Event::fake([SubscriptionUpdated::class]);
        $service = app(ReconciliationService::class);

        $first = $service->run('fake', new ReconciliationRequest('subscription'));
        $second = $service->run('fake', new ReconciliationRequest('subscription'));

        self::assertSame(1, $first->reconciled);
        self::assertSame(1, $second->unchanged);
        Event::assertDispatchedTimes(SubscriptionUpdated::class, 1);
        Event::assertDispatched(
            SubscriptionUpdated::class,
            static fn (SubscriptionUpdated $event): bool => $event->source->providerResourceId() === 'remote-1',
        );

        $forced = $service->run('fake', new ReconciliationRequest(model: 'subscription', force: true));

        self::assertSame(1, $forced->unchanged);
        Event::assertDispatchedTimes(SubscriptionUpdated::class, 2);
    }

    #[Test]
    public function dry_runs_detect_changes_without_writes_or_events(): void
    {
        $user = User::query()->create(['name' => 'Ada']);
        $this->resolveOrphansTo($user);
        $this->fakeDriver()->addReconciliationResult($this->subscriptionResult('remote-1', 'price-1'));
        Event::fake([SubscriptionUpdated::class]);

        $this->artisanCommand('billing:reconcile', ['--model' => 'subscription', '--dry-run' => true])
            ->expectsOutputToContain('Would reconcile subscription remote-1')
            ->expectsOutputToContain('Reconciled: 1; unchanged: 0; skipped-unresolvable: 0.')
            ->assertSuccessful();

        self::assertDatabaseCount('billing_subscriptions', 0);
        Event::assertNotDispatched(SubscriptionUpdated::class);
    }

    #[Test]
    public function routes_and_safe_console_defaults_are_registered(): void
    {
        self::assertNotNull(app('router')->getRoutes()->getByName('billing.webhooks'));
        $this->artisanCommand('billing:webhooks:prune', ['--dry-run' => true])->assertSuccessful();
        $this->artisanCommand('billing:webhooks:retry', ['--dry-run' => true])->assertSuccessful();
    }

    private function fakeDriver(): FakeDriver
    {
        $driver = app(BillingManager::class)->driver();
        self::assertInstanceOf(FakeDriver::class, $driver);

        return $driver;
    }

    private function subscriptionResult(string $id, string $price, string $type = 'default'): ReconciliationResult
    {
        return new ReconciliationResult('subscription', $this->subscription($id, $price, $type));
    }

    private function subscription(string $id, string $price, string $type = 'default'): SubscriptionData
    {
        return new SubscriptionData(new SubscriptionReference($id), $type, SubscriptionStatus::Active, priceId: $price);
    }

    private function resolveOrphansTo(Model $billable): void
    {
        app()->instance(ResolvesReconciliationBillables::class, new readonly class($billable) implements ResolvesReconciliationBillables
        {
            public function __construct(private Model $billable) {}

            public function resolve(string $driver, string $model, CustomerData|SubscriptionData|TransactionData $resource): Model
            {
                return $this->billable;
            }
        });
    }
}
