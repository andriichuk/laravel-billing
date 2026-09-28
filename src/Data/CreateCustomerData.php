<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Data;

final readonly class CreateCustomerData
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $providerOptions
     */
    public function __construct(
        public string $billableType,
        public string $billableId,
        public ?string $name = null,
        public ?string $email = null,
        public array $metadata = [],
        public array $providerOptions = [],
        public ?string $idempotencyKey = null
    ) {}
}
