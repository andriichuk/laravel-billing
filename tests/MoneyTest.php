<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Tests;
use Andriichuk\LaravelBilling\Exceptions\InvalidBillingPayload;
use Andriichuk\LaravelBilling\ValueObjects\Money;
use PHPUnit\Framework\Attributes\Test;
final class MoneyTest extends TestCase
{
    #[Test] public function it_normalizes_and_serializes_decimal_money_without_floats(): void { $money = new Money('0010.5000', 'usd'); self::assertSame('10.5', $money->amount); self::assertSame('USD', $money->currency); self::assertTrue($money->equals(new Money('10.50', 'USD'))); self::assertSame('{"amount":"10.5","currency":"USD"}', json_encode($money, JSON_THROW_ON_ERROR)); }
    #[Test] public function it_rejects_float_like_or_invalid_input(): void { $this->expectException(InvalidBillingPayload::class); new Money('1e3', 'USD'); }
    #[Test] public function it_rejects_invalid_currency_codes(): void { $this->expectException(InvalidBillingPayload::class); new Money('10', 'US'); }
}
