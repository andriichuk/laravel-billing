<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Data\Events;

abstract readonly class NormalizedEventData implements NormalizedEvent
{
    /** @param array<string,mixed> $attributes */
    public function __construct(
        public string $resourceId,
        public array $attributes = []
    ) {}

    public function providerResourceId(): string
    {
        return $this->resourceId;
    }

    /** @return array<string,mixed> */
    public function data(): array
    {
        return $this->attributes;
    }
}
