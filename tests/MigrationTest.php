<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Tests;

use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

final class MigrationTest extends TestCase
{
    #[Test]
    public function package_migrations_roll_forward_and_backward(): void
    {
        self::assertTrue(Schema::hasTable('billing_customers'));
        $migration = require __DIR__.'/../database/migrations/2026_01_01_000000_create_billing_tables.php';
        $migration->down();
        self::assertFalse(Schema::hasTable('billing_webhook_events'));
        self::assertFalse(Schema::hasTable('billing_customers'));
        $migration->up();
        self::assertTrue(Schema::hasTable('billing_webhook_events'));
    }
}
