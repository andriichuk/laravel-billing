<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Tests;
use Andriichuk\LaravelBilling\BillingManager;
use Andriichuk\LaravelBilling\Enums\Capability;
use Andriichuk\LaravelBilling\Exceptions\UnsupportedCapability;
use Andriichuk\LaravelBilling\Testing\FakeDriver;
use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Attributes\Test;
final class BillingManagerTest extends TestCase
{
    #[Test] public function it_resolves_default_and_named_drivers_once(): void { $manager = app(BillingManager::class); $calls = 0; $manager->extend('secondary', function (Container $app, array $config) use (&$calls): FakeDriver { $calls++; return new FakeDriver('secondary'); }); self::assertSame('fake', $manager->driver()->name()); self::assertSame($manager->driver('secondary'), $manager->driver('secondary')); self::assertSame(1, $calls); }
    #[Test] public function unsupported_capabilities_have_a_useful_exception(): void { $manager = app(BillingManager::class); $manager->extend('limited', fn (Container $app, array $config): FakeDriver => new FakeDriver('limited', [Capability::Customers])); $this->expectException(UnsupportedCapability::class); $this->expectExceptionMessage('limited'); $this->expectExceptionMessage('refunds'); $manager->require(Capability::Refunds, 'limited'); }
}
