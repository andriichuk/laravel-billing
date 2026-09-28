<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Data;

use Andriichuk\LaravelBilling\ValueObjects\CustomerReference;
use DateTimeImmutable;

final readonly class CustomerData
{
    /**
     * @param  array<string, mixed>  $providerData
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public CustomerReference $reference,
        public ?string $name = null,
        public ?string $email = null,
        public ?DateTimeImmutable $trialEndsAt = null,
        private array $providerData = [],
        public array $metadata = []
    ) {}

    /** @return array<string,mixed> */
    public function rawProviderData(): array
    {
        return $this->providerData;
    }
}
