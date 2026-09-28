<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Contracts;

use Andriichuk\LaravelBilling\Data\TransactionData;
use Andriichuk\LaravelBilling\ValueObjects\Money;
use Andriichuk\LaravelBilling\ValueObjects\TransactionReference;

interface SupportsRefunds
{
    /** @param array<string,mixed> $providerOptions */
    public function refund(TransactionReference $transaction, ?Money $amount = null, array $providerOptions = []): TransactionData;
}
