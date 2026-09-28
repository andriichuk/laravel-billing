<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Support;

use Andriichuk\LaravelBilling\Data\CustomerData;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\Data\TransactionData;
use Andriichuk\LaravelBilling\Models\Customer;
use Andriichuk\LaravelBilling\Models\Subscription;
use Andriichuk\LaravelBilling\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class BillingSynchronizer
{
    public function customer(Model $billable, string $driver, CustomerData $data): Customer
    {
        $class = $this->modelClass('customer', Customer::class);
        /** @var Customer $model */
        $model = $class::query()->updateOrCreate(
            ['billable_type' => $billable->getMorphClass(), 'billable_id' => $this->key($billable), 'driver' => $driver],
            ['provider_customer_id' => $data->reference->id, 'name' => $data->name, 'email' => $data->email, 'trial_ends_at' => $data->trialEndsAt, 'provider_data' => $data->rawProviderData(), 'metadata' => $data->metadata],
        );
        return $model;
    }

    public function subscription(Model $billable, string $driver, SubscriptionData $data): Subscription
    {
        $class = $this->modelClass('subscription', Subscription::class);
        /** @var Subscription $model */
        $model = $class::query()->updateOrCreate(
            ['driver' => $driver, 'provider_subscription_id' => $data->reference->id],
            ['billable_type' => $billable->getMorphClass(), 'billable_id' => $this->key($billable), 'type' => $data->type, 'provider_customer_id' => $data->customerId, 'provider_product_id' => $data->productId, 'provider_price_id' => $data->priceId, 'status' => $data->status, 'quantity' => $data->quantity, 'currency' => $data->recurringAmount?->currency, 'recurring_amount' => $data->recurringAmount?->amount, 'billing_interval' => $data->billingInterval, 'billing_interval_count' => $data->billingIntervalCount, 'auto_renew' => $data->autoRenew, 'trial_ends_at' => $data->trialEndsAt, 'next_charge_at' => $data->nextChargeAt, 'ends_at' => $data->endsAt, 'paused_at' => $data->pausedAt, 'provider_data' => $data->rawProviderData(), 'metadata' => $data->metadata],
        );
        return $model;
    }

    public function transaction(Model $billable, string $driver, TransactionData $data): Transaction
    {
        $class = $this->modelClass('transaction', Transaction::class);
        /** @var Transaction $model */
        $model = $class::query()->updateOrCreate(
            ['driver' => $driver, 'provider_transaction_id' => $data->reference->id],
            ['billable_type' => $billable->getMorphClass(), 'billable_id' => $this->key($billable), 'provider_subscription_id' => $data->subscriptionId, 'type' => $data->type, 'status' => $data->status, 'amount' => $data->amount?->amount, 'currency' => $data->amount?->currency, 'billed_at' => $data->billedAt, 'provider_data' => $data->rawProviderData(), 'metadata' => $data->metadata],
        );
        return $model;
    }

    /** @param class-string<Model> $default @return class-string<Model> */
    private function modelClass(string $key, string $default): string
    {
        $class = config("billing.models.{$key}", $default);
        if (! is_string($class) || ! is_a($class, $default, true)) { throw new InvalidArgumentException("Configured billing model [{$key}] must extend {$default}."); }
        return $class;
    }
    private function key(Model $model): string { $key = $model->getKey(); if (! is_int($key) && ! is_string($key)) { throw new InvalidArgumentException('The billable model must be persisted.'); } return (string) $key; }
}
