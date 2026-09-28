<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\ValueObjects;

use Andriichuk\LaravelBilling\Exceptions\InvalidBillingPayload;
use JsonSerializable;

final readonly class Money implements JsonSerializable
{
    public string $amount;

    public string $currency;

    public function __construct(string|int $amount, string $currency)
    {
        $this->amount = self::normalizeAmount($amount);
        $currency = strtoupper(trim($currency));

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw InvalidBillingPayload::because('Currency must be a three-letter ISO 4217 code.');
        }

        $this->currency = $currency;
    }

    public static function normalizeAmount(string|int $amount): string
    {
        $value = trim((string) $amount);

        if (preg_match('/^-?\d+(?:\.\d+)?$/', $value) !== 1) {
            throw InvalidBillingPayload::because('Money amount must be a decimal string or integer.');
        }

        $negative = str_starts_with($value, '-');
        $unsigned = ltrim($value, '-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $fraction = rtrim($fraction, '0');
        $normalized = ltrim($whole, '0');
        $normalized = $normalized === '' ? '0' : $normalized;
        $normalized .= $fraction === '' ? '' : '.'.$fraction;

        return $negative && $normalized !== '0' ? '-'.$normalized : $normalized;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    /**
     * @return array{amount: string, currency: string}
     */
    public function jsonSerialize(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency];
    }
}
