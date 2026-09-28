<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Data;

final readonly class UpdateSubscriptionData
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $providerOptions
     */
    public function __construct(
        public ?string $price = null,
        public ?int $quantity = null,
        public array $metadata = [],
        public array $providerOptions = []
    ) {}
}
