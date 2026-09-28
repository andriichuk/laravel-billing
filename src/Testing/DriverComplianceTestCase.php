<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Testing;

use Andriichuk\LaravelBilling\Contracts\BillingDriver;
use Andriichuk\LaravelBilling\Enums\Capability;
use PHPUnit\Framework\TestCase;

/**
 * A small, reusable baseline for external driver packages. Driver packages may
 * extend this class and add capability-specific lifecycle and webhook tests.
 */
abstract class DriverComplianceTestCase extends TestCase
{
    abstract protected function driver(): BillingDriver;

    public function test_driver_identity_is_stable(): void
    {
        self::assertNotSame('', trim($this->driver()->name()));
    }

    public function test_reported_capabilities_are_unique_enum_values(): void
    {
        $capabilities = $this->driver()->capabilities();
        self::assertCount(count(array_unique(array_map(static fn (Capability $capability): string => $capability->value, $capabilities))), $capabilities);

        foreach ($capabilities as $capability) {
            self::assertTrue($this->driver()->supports($capability));
        }
    }
}
