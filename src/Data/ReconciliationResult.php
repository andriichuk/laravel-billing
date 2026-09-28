<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Data;
final readonly class ReconciliationResult { /** @param CustomerData|SubscriptionData|TransactionData $resource */ public function __construct(public string $model, public CustomerData|SubscriptionData|TransactionData $resource, public ?string $billableType = null, public ?string $billableId = null) {} }
