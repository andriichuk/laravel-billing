<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Data;

use Andriichuk\LaravelBilling\Enums\TransactionStatus;
use Andriichuk\LaravelBilling\ValueObjects\Money;
use Andriichuk\LaravelBilling\ValueObjects\TransactionReference;
use DateTimeImmutable;

final readonly class TransactionData
{
    /**
     * @param  array<string, mixed>  $providerData
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(public TransactionReference $reference, public TransactionStatus $status, public ?string $subscriptionId = null, public ?string $type = null, public ?Money $amount = null, public ?DateTimeImmutable $billedAt = null, private array $providerData = [], public array $metadata = []) {}

    /** @return array<string,mixed> */
    public function rawProviderData(): array
    {
        return $this->providerData;
    }
}
