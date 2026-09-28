<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Support;

use Andriichuk\LaravelBilling\BillingManager;
use Andriichuk\LaravelBilling\Contracts\ReconcilesResources;
use Andriichuk\LaravelBilling\Contracts\ResolvesReconciliationBillables;
use Andriichuk\LaravelBilling\Data\CustomerData;
use Andriichuk\LaravelBilling\Data\ReconciliationRequest;
use Andriichuk\LaravelBilling\Data\ReconciliationResult;
use Andriichuk\LaravelBilling\Data\ReconciliationSummary;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\Data\TransactionData;
use Andriichuk\LaravelBilling\Enums\Capability;
use Andriichuk\LaravelBilling\Exceptions\UnsupportedCapability;
use Andriichuk\LaravelBilling\Models\Customer;
use Andriichuk\LaravelBilling\Models\Subscription;
use Andriichuk\LaravelBilling\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class ReconciliationService
{
    public function __construct(
        private readonly BillingManager $billing,
        private readonly BillingSynchronizer $synchronizer,
        private readonly ResolvesReconciliationBillables $resolver,
    ) {}

    public function run(string $driverName, ReconciliationRequest $request, ?callable $reporter = null): ReconciliationSummary
    {
        $driver = $this->billing->require(Capability::Reconciliation, $driverName);

        if (! $driver instanceof ReconcilesResources) {
            throw UnsupportedCapability::for($driver, Capability::Reconciliation);
        }

        $reconciled = 0;
        $unchanged = 0;
        $skipped = 0;

        foreach ($driver->reconcile($request) as $result) {
            if ($request->model !== null && $request->model !== $result->model) {
                continue;
            }

            if ($request->id !== null && (string) $request->id !== $result->resource->reference->id) {
                continue;
            }

            $billable = $this->billable($driverName, $result);

            if ($billable === null) {
                $processed = $result->skipped(sprintf(
                    'No billable could be resolved for %s [%s].',
                    $result->model,
                    $result->resource->reference->id,
                ));
                $skipped++;

                if ($reporter !== null) {
                    call_user_func($reporter, $processed, $request->dryRun);
                }

                continue;
            }

            $changed = $request->dryRun
                ? $this->wouldChange($billable, $driverName, $result)
                : $this->apply($billable, $driverName, $result, $request->force);
            $processed = $result->processed($changed);

            if ($changed) {
                $reconciled++;
            } else {
                $unchanged++;
            }

            if ($reporter !== null) {
                call_user_func($reporter, $processed, $request->dryRun);
            }
        }

        return new ReconciliationSummary($reconciled, $unchanged, $skipped);
    }

    private function apply(Model $billable, string $driver, ReconciliationResult $result, bool $force): bool
    {
        $model = match (true) {
            $result->resource instanceof CustomerData => $this->synchronizer->customer($billable, $driver, $result->resource),
            $result->resource instanceof SubscriptionData => $this->synchronizer->subscription($billable, $driver, $result->resource, force: $force),
            $result->resource instanceof TransactionData => $this->synchronizer->transaction($billable, $driver, $result->resource, force: $force),
        };

        return $model->wasRecentlyCreated || $model->wasChanged();
    }

    private function wouldChange(Model $billable, string $driver, ReconciliationResult $result): bool
    {
        return match (true) {
            $result->resource instanceof CustomerData => $this->synchronizer->customerWouldChange($billable, $driver, $result->resource),
            $result->resource instanceof SubscriptionData => $this->synchronizer->subscriptionWouldChange($billable, $driver, $result->resource),
            $result->resource instanceof TransactionData => $this->synchronizer->transactionWouldChange($billable, $driver, $result->resource),
        };
    }

    private function billable(string $driver, ReconciliationResult $result): ?Model
    {
        $row = match ($result->model) {
            'customer' => $this->providerRow('customer', Customer::class, 'provider_customer_id', $driver, $result->resource->reference->id),
            'subscription' => $this->providerRow('subscription', Subscription::class, 'provider_subscription_id', $driver, $result->resource->reference->id),
            'transaction' => $this->providerRow('transaction', Transaction::class, 'provider_transaction_id', $driver, $result->resource->reference->id),
            default => null,
        };

        if ($row instanceof Customer || $row instanceof Subscription || $row instanceof Transaction) {
            $billable = $row->billable()->first();

            return $billable instanceof Model ? $billable : null;
        }

        return $this->resolver->resolve($driver, $result->model, $result->resource);
    }

    /**
     * @param  class-string<Model>  $default
     */
    private function providerRow(string $key, string $default, string $providerColumn, string $driver, string $providerId): ?Model
    {
        $class = config("billing.models.{$key}", $default);

        if (! is_string($class) || ! is_a($class, $default, true)) {
            throw new InvalidArgumentException("Configured billing model [{$key}] must extend {$default}.");
        }

        return $class::query()->where('driver', $driver)->where($providerColumn, $providerId)->first();
    }
}
