<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\ValueObjects;

final readonly class SubscriptionReference
{
    public function __construct(public string $id) {}

    public function __toString(): string
    {
        return $this->id;
    }
}
