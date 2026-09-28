<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Exceptions;

final class InvalidBillingPayload extends BillingException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
