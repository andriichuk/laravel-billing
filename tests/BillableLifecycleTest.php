<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Tests;

use Andriichuk\LaravelBilling\BillingManager;
use Andriichuk\LaravelBilling\Data\TransactionData;
use Andriichuk\LaravelBilling\Enums\CancellationMode;
use Andriichuk\LaravelBilling\Enums\Capability;
use Andriichuk\LaravelBilling\Enums\SubscriptionStatus;
use Andriichuk\LaravelBilling\Enums\TransactionStatus;
use Andriichuk\LaravelBilling\Exceptions\InvalidBillingPayload;
use Andriichuk\LaravelBilling\Exceptions\UnsupportedCapability;
use Andriichuk\LaravelBilling\Support\BillingSynchronizer;
use Andriichuk\LaravelBilling\Testing\FakeDriver;
use Andriichuk\LaravelBilling\Tests\Fixtures\CustomSubscription;
use Andriichuk\LaravelBilling\Tests\Fixtures\User;
use Andriichuk\LaravelBilling\ValueObjects\Money;
use Andriichuk\LaravelBilling\ValueObjects\TransactionReference;
use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Attributes\Test;

final class BillableLifecycleTest extends TestCase
{
    #[Test]
    public function a_billable_can_create_customers_and_subscriptions_with_multiple_drivers(): void
    {
        $manager = app(BillingManager::class);
        $manager->extend('second', fn (Container $app, array $config): FakeDriver => new FakeDriver('second'));
        $user = User::query()->create(['name' => 'Ada', 'email' => 'ada@example.test']);
        $firstCustomer = $user->createBillingCustomer(name: $user->name, email: $user->email);
        $secondCustomer = $user->createBillingCustomer('second', $user->name, $user->email);
        self::assertNotSame($firstCustomer->driver, $secondCustomer->driver);
        self::assertCount(2, $user->billingCustomers()->get());
        $subscription = $user->newSubscription('default', 'price-123')->quantity(5)->trialDays(14)->withProviderOptions(['opaque' => 'value'])->create();
        $second = $user->newSubscription('default', 'price-456')->driver('second')->create();
        self::assertSame(5, $subscription->quantity);
        self::assertTrue($subscription->onTrial());
        self::assertTrue($user->subscribed('default', 'price-123'));
        self::assertSame('second', $second->driver);
    }

    #[Test]
    public function builder_validation_and_trial_capability_are_enforced(): void
    {
        $user = User::query()->create(['name' => 'Ada']);
        $this->expectException(InvalidBillingPayload::class);
        $user->newSubscription('default', 'price')->quantity(0);
    }

    #[Test]
    public function unsupported_trial_capability_is_rejected(): void
    {
        app(BillingManager::class)->extend('no-trials', fn (Container $app, array $config): FakeDriver => new FakeDriver('no-trials', [Capability::Subscriptions]));
        $user = User::query()->create(['name' => 'Ada']);
        $this->expectException(UnsupportedCapability::class);
        $user->newSubscription('default', 'price')->driver('no-trials')->trialDays(1)->create();
    }

    #[Test]
    public function subscriptions_support_explicit_cancellation_modes(): void
    {
        $user = User::query()->create(['name' => 'Ada']);
        $subscription = $user->newSubscription('default', 'price')->create();
        $canceled = $subscription->cancel(CancellationMode::Immediately);
        self::assertSame(SubscriptionStatus::Canceled, $canceled->status);
        self::assertFalse($canceled->onGracePeriod());
    }

    #[Test]
    public function transaction_synchronization_is_idempotent(): void
    {
        $user = User::query()->create(['name' => 'Ada']);
        $data = new TransactionData(new TransactionReference('txn-1'), TransactionStatus::Succeeded, amount: new Money('10.00', 'USD'));
        $synchronizer = app(BillingSynchronizer::class);
        $synchronizer->transaction($user, 'fake', $data);
        $synchronizer->transaction($user, 'fake', $data);
        self::assertDatabaseCount('billing_transactions', 1);
    }

    #[Test]
    public function custom_models_are_respected(): void
    {
        config()->set('billing.models.subscription', CustomSubscription::class);
        $user = User::query()->create(['name' => 'Ada']);
        $subscription = $user->newSubscription('default', 'price')->create();
        self::assertInstanceOf(CustomSubscription::class, $subscription);
    }
}
