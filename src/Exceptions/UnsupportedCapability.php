<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Exceptions;
use Andriichuk\LaravelBilling\Contracts\BillingDriver;
use Andriichuk\LaravelBilling\Enums\Capability;
final class UnsupportedCapability extends BillingException
{
    public static function for(BillingDriver $driver, Capability $capability): self
    {
        return new self(sprintf('Billing driver [%s] does not support the [%s] capability.', $driver->name(), $capability->value));
    }
}
