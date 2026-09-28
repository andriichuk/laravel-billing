<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Support;

use Andriichuk\LaravelBilling\Data\CustomerData;
use Andriichuk\LaravelBilling\Data\Events\NormalizedEvent;
use Andriichuk\LaravelBilling\Data\Events\SubscriptionUpdated as NormalizedSubscriptionUpdated;
use Andriichuk\LaravelBilling\Data\Events\TransactionUpdated as NormalizedTransactionUpdated;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\Data\TransactionData;
use Andriichuk\LaravelBilling\Events\SubscriptionUpdated;
use Andriichuk\LaravelBilling\Events\TransactionUpdated;
use Andriichuk\LaravelBilling\Models\Customer;
use Andriichuk\LaravelBilling\Models\Subscription;
use Andriichuk\LaravelBilling\Models\Transaction;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class BillingSynchronizer
{
    public function __construct(private readonly Dispatcher $events) {}

    public function customer(Model $billable, string $driver, CustomerData $data): Customer
    {
        $class = $this->modelClass('customer', Customer::class);
        /** @var Customer $model */
        $model = $class::query()->updateOrCreate(
            ['billable_type' => $billable->getMorphClass(), 'billable_id' => $this->key($billable), 'driver' => $driver],
            $this->customerAttributes($data),
        );

        return $model;
    }

    public function subscription(Model $billable, string $driver, SubscriptionData $data, ?NormalizedEvent $source = null, bool $force = false): Subscription
    {
        $class = $this->modelClass('subscription', Subscription::class);
        /** @var Subscription $model */
        $model = $class::query()->updateOrCreate(
            ['driver' => $driver, 'provider_subscription_id' => $data->reference->id],
            $this->subscriptionAttributes($billable, $data),
        );
        $changed = $model->wasRecentlyCreated || $model->wasChanged();
        $this->subscriptionUpdated(
            $model,
            $source ?? new NormalizedSubscriptionUpdated($data->reference->id),
            $changed,
            $force,
        );

        return $model;
    }

    public function transaction(Model $billable, string $driver, TransactionData $data, ?NormalizedEvent $source = null, bool $force = false): Transaction
    {
        $class = $this->modelClass('transaction', Transaction::class);
        /** @var Transaction $model */
        $model = $class::query()->updateOrCreate(
            ['driver' => $driver, 'provider_transaction_id' => $data->reference->id],
            $this->transactionAttributes($billable, $data),
        );
        $changed = $model->wasRecentlyCreated || $model->wasChanged();
        $this->transactionUpdated(
            $model,
            $source ?? new NormalizedTransactionUpdated($data->reference->id),
            $changed,
            $force,
        );

        return $model;
    }

    public function customerWouldChange(Model $billable, string $driver, CustomerData $data): bool
    {
        $class = $this->modelClass('customer', Customer::class);
        $model = $class::query()->where([
            'billable_type' => $billable->getMorphClass(),
            'billable_id' => $this->key($billable),
            'driver' => $driver,
        ])->first();

        return $model === null || $model->fill($this->customerAttributes($data))->isDirty();
    }

    public function subscriptionWouldChange(Model $billable, string $driver, SubscriptionData $data): bool
    {
        $class = $this->modelClass('subscription', Subscription::class);
        $model = $class::query()->where('driver', $driver)->where('provider_subscription_id', $data->reference->id)->first();

        return $model === null || $model->fill($this->subscriptionAttributes($billable, $data))->isDirty();
    }

    public function transactionWouldChange(Model $billable, string $driver, TransactionData $data): bool
    {
        $class = $this->modelClass('transaction', Transaction::class);
        $model = $class::query()->where('driver', $driver)->where('provider_transaction_id', $data->reference->id)->first();

        return $model === null || $model->fill($this->transactionAttributes($billable, $data))->isDirty();
    }

    public function subscriptionUpdated(Subscription $subscription, NormalizedEvent $source, bool $changed, bool $force = false): void
    {
        if ($changed || $force) {
            $this->events->dispatch(new SubscriptionUpdated($subscription, $source));
        }
    }

    public function transactionUpdated(Transaction $transaction, NormalizedEvent $source, bool $changed, bool $force = false): void
    {
        if ($changed || $force) {
            $this->events->dispatch(new TransactionUpdated($transaction, $source));
        }
    }

    /** @return array<string, mixed> */
    private function customerAttributes(CustomerData $data): array
    {
        return ['provider_customer_id' => $data->reference->id, 'name' => $data->name, 'email' => $data->email, 'trial_ends_at' => $data->trialEndsAt, 'provider_data' => $data->rawProviderData(), 'metadata' => $data->metadata];
    }

    /** @return array<string, mixed> */
    private function subscriptionAttributes(Model $billable, SubscriptionData $data): array
    {
        return ['billable_type' => $billable->getMorphClass(), 'billable_id' => $this->key($billable), 'type' => $data->type, 'provider_customer_id' => $data->customerId, 'provider_product_id' => $data->productId, 'provider_price_id' => $data->priceId, 'status' => $data->status, 'quantity' => $data->quantity, 'currency' => $data->recurringAmount?->currency, 'recurring_amount' => $data->recurringAmount?->amount, 'billing_interval' => $data->billingInterval, 'billing_interval_count' => $data->billingIntervalCount, 'auto_renew' => $data->autoRenew, 'trial_ends_at' => $data->trialEndsAt, 'next_charge_at' => $data->nextChargeAt, 'ends_at' => $data->endsAt, 'paused_at' => $data->pausedAt, 'provider_data' => $data->rawProviderData(), 'metadata' => $data->metadata];
    }

    /** @return array<string, mixed> */
    private function transactionAttributes(Model $billable, TransactionData $data): array
    {
        return ['billable_type' => $billable->getMorphClass(), 'billable_id' => $this->key($billable), 'provider_subscription_id' => $data->subscriptionId, 'type' => $data->type, 'status' => $data->status, 'amount' => $data->amount?->amount, 'currency' => $data->amount?->currency, 'billed_at' => $data->billedAt, 'provider_data' => $data->rawProviderData(), 'metadata' => $data->metadata];
    }

    /** @param class-string<Model> $default @return class-string<Model> */
    private function modelClass(string $key, string $default): string
    {
        $class = config("billing.models.{$key}", $default);

        if (! is_string($class) || ! is_a($class, $default, true)) {
            throw new InvalidArgumentException("Configured billing model [{$key}] must extend {$default}.");
        }

        return $class;
    }

    private function key(Model $model): string
    {
        $key = $model->getKey();

        if (! is_int($key) && ! is_string($key)) {
            throw new InvalidArgumentException('The billable model must be persisted.');
        }

        return (string) $key;
    }
}
