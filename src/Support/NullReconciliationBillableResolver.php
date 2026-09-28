<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Support;

use Andriichuk\LaravelBilling\Contracts\ResolvesReconciliationBillables;
use Andriichuk\LaravelBilling\Data\CustomerData;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\Data\TransactionData;
use Illuminate\Database\Eloquent\Model;

final readonly class NullReconciliationBillableResolver implements ResolvesReconciliationBillables
{
    public function resolve(
        string $driver,
        string $model,
        CustomerData|SubscriptionData|TransactionData $resource,
    ): ?Model {
        return null;
    }
}
