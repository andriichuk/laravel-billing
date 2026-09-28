<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Events;

use Andriichuk\LaravelBilling\Data\Events\NormalizedEvent;
use Andriichuk\LaravelBilling\Models\Transaction;

final readonly class TransactionUpdated
{
    public function __construct(
        public Transaction $transaction,
        public NormalizedEvent $source
    ) {}
}
