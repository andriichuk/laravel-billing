<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Data;

use Andriichuk\LaravelBilling\Enums\BillingInterval;
use Andriichuk\LaravelBilling\Enums\SubscriptionStatus;
use Andriichuk\LaravelBilling\ValueObjects\Money;
use Andriichuk\LaravelBilling\ValueObjects\SubscriptionReference;
use DateTimeImmutable;

final readonly class SubscriptionData
{
    /**
     * @param  array<string, mixed>  $providerData
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(public SubscriptionReference $reference, public string $type, public SubscriptionStatus $status, public ?string $customerId = null, public ?string $productId = null, public ?string $priceId = null, public int $quantity = 1, public ?Money $recurringAmount = null, public ?BillingInterval $billingInterval = null, public ?int $billingIntervalCount = null, public bool $autoRenew = true, public ?DateTimeImmutable $trialEndsAt = null, public ?DateTimeImmutable $nextChargeAt = null, public ?DateTimeImmutable $endsAt = null, public ?DateTimeImmutable $pausedAt = null, private array $providerData = [], public array $metadata = []) {}

    /** @return array<string,mixed> */
    public function rawProviderData(): array
    {
        return $this->providerData;
    }
}
