<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Concerns;

use Andriichuk\LaravelBilling\BillingManager;
use Andriichuk\LaravelBilling\Builders\SubscriptionBuilder;
use Andriichuk\LaravelBilling\Contracts\BillingDriver;
use Andriichuk\LaravelBilling\Contracts\ManagesCustomers;
use Andriichuk\LaravelBilling\Data\CreateCustomerData;
use Andriichuk\LaravelBilling\Data\UpdateCustomerData;
use Andriichuk\LaravelBilling\Enums\Capability;
use Andriichuk\LaravelBilling\Exceptions\UnsupportedCapability;
use Andriichuk\LaravelBilling\Models\Customer;
use Andriichuk\LaravelBilling\Models\Subscription;
use Andriichuk\LaravelBilling\Models\Transaction;
use Andriichuk\LaravelBilling\Support\BillingSynchronizer;
use Andriichuk\LaravelBilling\ValueObjects\CustomerReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait Billable
{
    public function billing(?string $driver = null): BillingDriver
    {
        return app(BillingManager::class)->driver($driver);
    }

    /** @return MorphMany<Customer, $this> */
    public function billingCustomers(): MorphMany
    {
        return $this->morphMany($this->billingModelClass('customer', Customer::class), 'billable');
    }

    public function billingCustomer(?string $driver = null): ?Customer
    {
        $driver ??= app(BillingManager::class)->defaultDriver();

        return $this->billingCustomers()->where('driver', $driver)->first();
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $providerOptions
     */
    public function createBillingCustomer(?string $driver = null, ?string $name = null, ?string $email = null, array $metadata = [], array $providerOptions = []): Customer
    {
        $manager = app(BillingManager::class);
        $resolved = $manager->require(Capability::Customers, $driver);
        if (! $resolved instanceof ManagesCustomers) {
            throw UnsupportedCapability::for($resolved, Capability::Customers);
        }
        $key = $this->getKey();
        if (! is_int($key) && ! is_string($key)) {
            throw new \LogicException('The billable model must be persisted.');
        }
        $result = $resolved->createCustomer(new CreateCustomerData($this->getMorphClass(), (string) $key, $name, $email, $metadata, $providerOptions));

        return app(BillingSynchronizer::class)->customer($this, $resolved->name(), $result);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $providerOptions
     */
    public function syncBillingCustomer(?string $driver = null, ?string $name = null, ?string $email = null, array $metadata = [], array $providerOptions = []): Customer
    {
        $manager = app(BillingManager::class);
        $resolved = $manager->require(Capability::Customers, $driver);
        if (! $resolved instanceof ManagesCustomers) {
            throw UnsupportedCapability::for($resolved, Capability::Customers);
        }
        $customer = $this->billingCustomer($resolved->name());
        if ($customer === null) {
            return $this->createBillingCustomer($resolved->name(), $name, $email, $metadata, $providerOptions);
        }
        $result = $resolved->updateCustomer(new CustomerReference($customer->provider_customer_id), new UpdateCustomerData($name, $email, $metadata, $providerOptions));

        return app(BillingSynchronizer::class)->customer($this, $resolved->name(), $result);
    }

    /** @return MorphMany<Subscription, $this> */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany($this->billingModelClass('subscription', Subscription::class), 'billable');
    }

    public function subscription(string $type = 'default', ?string $driver = null): ?Subscription
    {
        $driver ??= app(BillingManager::class)->defaultDriver();

        return $this->subscriptions()->where('driver', $driver)->where('type', $type)->first();
    }

    public function subscribed(string $type = 'default', ?string $price = null, ?string $driver = null): bool
    {
        $subscription = $this->subscription($type, $driver);

        return $subscription !== null && $subscription->valid() && ($price === null || $subscription->provider_price_id === $price);
    }

    public function newSubscription(string $type, string $price): SubscriptionBuilder
    {
        return new SubscriptionBuilder($this, $type, $price);
    }

    /** @return MorphMany<Transaction, $this> */
    public function billingTransactions(): MorphMany
    {
        return $this->morphMany($this->billingModelClass('transaction', Transaction::class), 'billable');
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $default
     * @return class-string<TModel>
     */
    private function billingModelClass(string $key, string $default): string
    {
        $configured = config("billing.models.{$key}", $default);
        if (! is_string($configured) || ! is_a($configured, $default, true)) {
            throw new \LogicException("Configured billing model [{$key}] must extend {$default}.");
        }

        return $configured;
    }
}
