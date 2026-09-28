<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Data;

use Andriichuk\LaravelBilling\ValueObjects\CustomerReference;
use Andriichuk\LaravelBilling\ValueObjects\PaymentMethodReference;

final readonly class CreateSubscriptionData
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $providerOptions
     */
    public function __construct(
        public string $billableType,
        public string $billableId,
        public string $type,
        public string $price,
        public int $quantity = 1,
        public ?int $trialDays = null,
        public ?CustomerReference $customer = null,
        public ?PaymentMethodReference $paymentMethod = null,
        public array $metadata = [],
        public array $providerOptions = [],
        public ?string $idempotencyKey = null
    ) {}
}
