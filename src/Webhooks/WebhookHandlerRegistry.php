<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Webhooks;

use Andriichuk\LaravelBilling\Data\Events\CustomerCreated;
use Andriichuk\LaravelBilling\Data\Events\CustomerDeleted;
use Andriichuk\LaravelBilling\Data\Events\CustomerUpdated;
use Andriichuk\LaravelBilling\Data\Events\ChargebackOpened;
use Andriichuk\LaravelBilling\Data\Events\ChargebackUpdated;
use Andriichuk\LaravelBilling\Data\Events\NormalizedEvent;
use Andriichuk\LaravelBilling\Data\Events\SubscriptionCanceled;
use Andriichuk\LaravelBilling\Data\Events\SubscriptionCreated;
use Andriichuk\LaravelBilling\Data\Events\SubscriptionPaymentFailed;
use Andriichuk\LaravelBilling\Data\Events\SubscriptionRenewalScheduled;
use Andriichuk\LaravelBilling\Data\Events\SubscriptionUpdated as NormalizedSubscriptionUpdated;
use Andriichuk\LaravelBilling\Data\Events\TransactionFailed;
use Andriichuk\LaravelBilling\Data\Events\TransactionPending;
use Andriichuk\LaravelBilling\Data\Events\TransactionRefunded;
use Andriichuk\LaravelBilling\Data\Events\TransactionSucceeded;
use Andriichuk\LaravelBilling\Enums\SubscriptionStatus;
use Andriichuk\LaravelBilling\Events\SubscriptionUpdated;
use Andriichuk\LaravelBilling\Events\TransactionUpdated;
use Andriichuk\LaravelBilling\Models\Customer;
use Andriichuk\LaravelBilling\Models\Subscription;
use Andriichuk\LaravelBilling\Models\Transaction;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Closure;

final class WebhookHandlerRegistry
{
    /** @var array<class-string<NormalizedEvent>, Closure(string, NormalizedEvent): void> */
    private array $customHandlers = [];
    public function __construct(private readonly ConnectionInterface $database, private readonly Dispatcher $events) {}

    /** @param class-string<NormalizedEvent> $eventClass @param Closure(string, NormalizedEvent): void $handler */
    public function register(string $eventClass, Closure $handler): self { $this->customHandlers[$eventClass] = $handler; return $this; }

    public function apply(string $driver, NormalizedEvent $event): void
    {
        $custom = $this->customHandlers[$event::class] ?? null;
        if ($custom instanceof Closure) { $custom($driver, $event); return; }
        $this->database->transaction(function () use ($driver, $event): void {
            if ($event instanceof CustomerCreated || $event instanceof CustomerUpdated || $event instanceof CustomerDeleted) { $this->customer($driver, $event); return; }
            if ($event instanceof SubscriptionCreated || $event instanceof NormalizedSubscriptionUpdated || $event instanceof SubscriptionCanceled || $event instanceof SubscriptionRenewalScheduled || $event instanceof SubscriptionPaymentFailed) { $this->subscription($driver, $event); return; }
            if ($event instanceof TransactionSucceeded || $event instanceof TransactionPending || $event instanceof TransactionFailed || $event instanceof TransactionRefunded || $event instanceof ChargebackOpened || $event instanceof ChargebackUpdated) { $this->transaction($driver, $event); }
        }, 3);
    }

    private function customer(string $driver, NormalizedEvent $event): void
    {
        $class = $this->modelClass('customer', Customer::class);
        $query = $class::query()->where('driver', $driver)->where('provider_customer_id', $event->providerResourceId())->lockForUpdate();
        if ($event instanceof CustomerDeleted) { $query->delete(); return; }
        $data = $this->safeAttributes($event->data(), ['name', 'email', 'trial_ends_at', 'provider_data', 'metadata']);
        $model = $query->first();
        if ($model === null) { $data += $this->billableKeys($event->data()); $data += ['driver' => $driver, 'provider_customer_id' => $event->providerResourceId()]; $class::query()->create($data); } else { $model->fill($data)->save(); }
    }

    private function subscription(string $driver, NormalizedEvent $event): void
    {
        $class = $this->modelClass('subscription', Subscription::class);
        /** @var Subscription|null $model */
        $model = $class::query()->where('driver', $driver)->where('provider_subscription_id', $event->providerResourceId())->lockForUpdate()->first();
        $data = $this->safeAttributes($event->data(), ['type', 'provider_customer_id', 'provider_product_id', 'provider_price_id', 'status', 'quantity', 'currency', 'recurring_amount', 'billing_interval', 'billing_interval_count', 'auto_renew', 'trial_ends_at', 'next_charge_at', 'ends_at', 'paused_at', 'provider_data', 'metadata']);
        if ($event instanceof SubscriptionCanceled) { $data['status'] ??= SubscriptionStatus::Canceled->value; }
        if ($event instanceof SubscriptionPaymentFailed) { $data['status'] ??= SubscriptionStatus::PastDue->value; }
        if ($model === null) { $data += $this->billableKeys($event->data()); $data += ['driver' => $driver, 'provider_subscription_id' => $event->providerResourceId(), 'type' => 'default', 'status' => SubscriptionStatus::Unknown->value, 'quantity' => 1, 'auto_renew' => true]; /** @var Subscription $model */ $model = $class::query()->create($data); } else { $model->fill($data)->save(); }
        $this->events->dispatch(new SubscriptionUpdated($model, $event));
    }

    private function transaction(string $driver, NormalizedEvent $event): void
    {
        $class = $this->modelClass('transaction', Transaction::class);
        /** @var Transaction|null $model */
        $model = $class::query()->where('driver', $driver)->where('provider_transaction_id', $event->providerResourceId())->lockForUpdate()->first();
        $data = $this->safeAttributes($event->data(), ['provider_subscription_id', 'type', 'status', 'amount', 'currency', 'billed_at', 'provider_data', 'metadata']);
        $data['status'] ??= match (true) { $event instanceof TransactionSucceeded => 'succeeded', $event instanceof TransactionPending => 'pending', $event instanceof TransactionFailed => 'failed', $event instanceof TransactionRefunded => 'refunded', $event instanceof ChargebackOpened, $event instanceof ChargebackUpdated => 'disputed', default => 'unknown' };
        if ($model === null) { $data += $this->billableKeys($event->data()); $data += ['driver' => $driver, 'provider_transaction_id' => $event->providerResourceId()]; /** @var Transaction $model */ $model = $class::query()->create($data); } else { $model->fill($data)->save(); }
        $this->events->dispatch(new TransactionUpdated($model, $event));
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $allowed
     * @return array<string, mixed>
     */
    private function safeAttributes(array $data, array $allowed): array { return array_intersect_key($data, array_flip($allowed)); }
    /**
     * @param array<string, mixed> $data
     * @return array{billable_type: string, billable_id: string}
     */
    private function billableKeys(array $data): array { if (! is_string($data['billable_type'] ?? null) || (! is_string($data['billable_id'] ?? null) && ! is_int($data['billable_id'] ?? null))) { throw new InvalidArgumentException('A new webhook resource requires billable_type and billable_id.'); } return ['billable_type' => $data['billable_type'], 'billable_id' => (string) $data['billable_id']]; }
    /** @param class-string<\Illuminate\Database\Eloquent\Model> $default @return class-string<\Illuminate\Database\Eloquent\Model> */
    private function modelClass(string $key, string $default): string { $class = config("billing.models.{$key}", $default); if (! is_string($class) || ! is_a($class, $default, true)) { throw new InvalidArgumentException("Invalid billing model [{$key}]."); } return $class; }
}
