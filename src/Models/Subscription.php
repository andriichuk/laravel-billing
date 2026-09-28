<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Models;

use Andriichuk\LaravelBilling\BillingManager;
use Andriichuk\LaravelBilling\Contracts\ManagesSubscriptions;
use Andriichuk\LaravelBilling\Contracts\SupportsPlanChanges;
use Andriichuk\LaravelBilling\Contracts\SupportsQuantityChanges;
use Andriichuk\LaravelBilling\Contracts\SupportsSubscriptionPausing;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\Enums\BillingInterval;
use Andriichuk\LaravelBilling\Enums\CancellationMode;
use Andriichuk\LaravelBilling\Enums\Capability;
use Andriichuk\LaravelBilling\Enums\SubscriptionStatus;
use Andriichuk\LaravelBilling\Exceptions\UnsupportedCapability;
use Andriichuk\LaravelBilling\Support\BillingSynchronizer;
use Andriichuk\LaravelBilling\ValueObjects\SubscriptionReference;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $driver
 * @property string $type
 * @property string $provider_subscription_id
 * @property string|null $provider_customer_id
 * @property string|null $provider_product_id
 * @property string|null $provider_price_id
 * @property SubscriptionStatus $status
 * @property int $quantity
 * @property bool $auto_renew
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $next_charge_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable|null $paused_at
 * @property array<string, mixed>|null $provider_data
 * @property array<string, mixed>|null $metadata
 */
class Subscription extends Model
{
    protected $table = 'billing_subscriptions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => SubscriptionStatus::class, 'billing_interval' => BillingInterval::class, 'quantity' => 'integer', 'billing_interval_count' => 'integer', 'auto_renew' => 'boolean', 'trial_ends_at' => 'immutable_datetime', 'next_charge_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'paused_at' => 'immutable_datetime', 'provider_data' => 'array', 'metadata' => 'array'];
    }

    /** @return MorphTo<Model, $this> */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function active(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }

    public function trialing(): bool
    {
        return $this->status === SubscriptionStatus::Trialing;
    }

    public function onTrial(): bool
    {
        return $this->trialing() && $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    public function canceled(): bool
    {
        return $this->status === SubscriptionStatus::Canceled;
    }

    public function paused(): bool
    {
        return $this->status === SubscriptionStatus::Paused;
    }

    public function onGracePeriod(): bool
    {
        return $this->canceled() && $this->ends_at !== null && $this->ends_at->isFuture();
    }

    public function recurring(): bool
    {
        return $this->auto_renew && ! $this->canceled() && ! $this->paused() && $this->status !== SubscriptionStatus::Finished;
    }

    public function valid(): bool
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod() || $this->status === SubscriptionStatus::PastDue;
    }

    public function syncFromProvider(): self
    {
        $driver = $this->subscriptionDriver();

        return $this->synchronize($driver->retrieveSubscription(new SubscriptionReference($this->provider_subscription_id)));
    }

    public function cancel(CancellationMode $mode = CancellationMode::AtPeriodEnd): self
    {
        $driver = $this->subscriptionDriver();

        return $this->synchronize($driver->cancelSubscription(new SubscriptionReference($this->provider_subscription_id), $mode));
    }

    /** @param array<string, mixed> $providerOptions */
    public function changePlan(string $price, array $providerOptions = []): self
    {
        $driver = app(BillingManager::class)->require(Capability::PlanChanges, $this->driver);
        if (! $driver instanceof SupportsPlanChanges) {
            throw UnsupportedCapability::for($driver, Capability::PlanChanges);
        }

        return $this->synchronize($driver->changePlan(new SubscriptionReference($this->provider_subscription_id), $price, $providerOptions));
    }

    /** @param array<string, mixed> $providerOptions */
    public function changeQuantity(int $quantity, array $providerOptions = []): self
    {
        $driver = app(BillingManager::class)->require(Capability::QuantityChanges, $this->driver);
        if (! $driver instanceof SupportsQuantityChanges) {
            throw UnsupportedCapability::for($driver, Capability::QuantityChanges);
        }

        return $this->synchronize($driver->changeQuantity(new SubscriptionReference($this->provider_subscription_id), $quantity, $providerOptions));
    }

    public function pause(): self
    {
        $driver = $this->pausingDriver();

        return $this->synchronize($driver->pauseSubscription(new SubscriptionReference($this->provider_subscription_id)));
    }

    public function resume(): self
    {
        $driver = $this->pausingDriver();

        return $this->synchronize($driver->resumeSubscription(new SubscriptionReference($this->provider_subscription_id)));
    }

    private function subscriptionDriver(): ManagesSubscriptions
    {
        $driver = app(BillingManager::class)->require(Capability::Subscriptions, $this->driver);
        if (! $driver instanceof ManagesSubscriptions) {
            throw UnsupportedCapability::for($driver, Capability::Subscriptions);
        }

        return $driver;
    }

    private function pausingDriver(): SupportsSubscriptionPausing
    {
        $driver = app(BillingManager::class)->require(Capability::SubscriptionPausing, $this->driver);
        if (! $driver instanceof SupportsSubscriptionPausing) {
            throw UnsupportedCapability::for($driver, Capability::SubscriptionPausing);
        }

        return $driver;
    }

    private function synchronize(SubscriptionData $data): self
    {
        $billable = $this->billable()->first();
        if (! $billable instanceof Model) {
            throw new \LogicException('Subscription has no billable model.');
        }

        return app(BillingSynchronizer::class)->subscription($billable, $this->driver, $data);
    }
}
