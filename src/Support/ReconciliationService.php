<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Support;

use Andriichuk\LaravelBilling\BillingManager;
use Andriichuk\LaravelBilling\Contracts\ReconcilesResources;
use Andriichuk\LaravelBilling\Data\CustomerData;
use Andriichuk\LaravelBilling\Data\ReconciliationRequest;
use Andriichuk\LaravelBilling\Data\ReconciliationResult;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\Data\TransactionData;
use Andriichuk\LaravelBilling\Enums\Capability;
use Andriichuk\LaravelBilling\Exceptions\BillingResourceNotFound;
use Andriichuk\LaravelBilling\Exceptions\UnsupportedCapability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

final class ReconciliationService
{
    public function __construct(private readonly BillingManager $billing, private readonly BillingSynchronizer $synchronizer) {}

    public function run(string $driverName, ReconciliationRequest $request, ?callable $reporter = null): int
    {
        $driver = $this->billing->require(Capability::Reconciliation, $driverName);
        if (! $driver instanceof ReconcilesResources) {
            throw UnsupportedCapability::for($driver, Capability::Reconciliation);
        }
        $count = 0;
        foreach ($driver->reconcile($request) as $result) {
            if ($request->model !== null && $request->model !== $result->model) {
                continue;
            } if ($reporter !== null) {
                call_user_func($reporter, $result, $request->dryRun);
            } if (! $request->dryRun) {
                $this->apply($driverName, $result);
            } $count++;
        }

        return $count;
    }

    private function apply(string $driver, ReconciliationResult $result): void
    {
        $billable = $this->billable($result);
        match (true) {
            $result->resource instanceof CustomerData => $this->synchronizer->customer($billable, $driver, $result->resource),
            $result->resource instanceof SubscriptionData => $this->synchronizer->subscription($billable, $driver, $result->resource),
            $result->resource instanceof TransactionData => $this->synchronizer->transaction($billable, $driver, $result->resource),
        };
    }

    private function billable(ReconciliationResult $result): Model
    {
        if ($result->billableType === null || $result->billableId === null) {
            throw new BillingResourceNotFound('Reconciliation result must identify its billable model.');
        }
        $class = Relation::getMorphedModel($result->billableType) ?? $result->billableType;
        if (! is_a($class, Model::class, true)) {
            throw new BillingResourceNotFound("Billable model [{$result->billableType}] is invalid.");
        }
        /** @var Model|null $model */ $model = $class::query()->find($result->billableId);

        return $model ?? throw new BillingResourceNotFound("Billable [{$result->billableType}:{$result->billableId}] was not found.");
    }
}
