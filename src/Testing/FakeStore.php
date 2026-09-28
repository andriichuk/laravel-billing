<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Testing;

use Andriichuk\LaravelBilling\Data\CustomerData;
use Andriichuk\LaravelBilling\Data\ReconciliationResult;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\Data\TransactionData;
use Throwable;

final class FakeStore
{
    /** @var array<string,CustomerData> */
    public array $customers = [];

    /** @var array<string,SubscriptionData> */
    public array $subscriptions = [];

    /** @var array<string,TransactionData> */
    public array $transactions = [];

    /** @var list<array{operation:string,data:mixed}> */
    public array $requests = [];

    /** @var list<ReconciliationResult> */
    public array $reconciliationResults = [];

    /** @var array<string,Throwable> */
    public array $failures = [];

    public int $customerSequence = 0;

    public int $subscriptionSequence = 0;

    public function record(string $operation, mixed $data): void
    {
        $this->requests[] = ['operation' => $operation, 'data' => $data];
        if (isset($this->failures[$operation])) {
            $failure = $this->failures[$operation];
            unset($this->failures[$operation]);
            throw $failure;
        }
    }
}
