<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Contracts;

use Andriichuk\LaravelBilling\Data\CustomerData;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\Data\TransactionData;
use Illuminate\Database\Eloquent\Model;

interface ResolvesReconciliationBillables
{
    public function resolve(
        string $driver,
        string $model,
        CustomerData|SubscriptionData|TransactionData $resource,
    ): ?Model;
}
