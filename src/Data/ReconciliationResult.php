<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Data;

final readonly class ReconciliationResult
{
    public function __construct(public string $model, public CustomerData|SubscriptionData|TransactionData $resource, public ?string $billableType = null, public ?string $billableId = null) {}
}
