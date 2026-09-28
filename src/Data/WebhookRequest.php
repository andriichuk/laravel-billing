<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Data;

use DateTimeImmutable;

final readonly class WebhookRequest
{
    /** @param array<string,list<string>> $headers */
    public function __construct(public string $method, public string $rawBody, public array $headers, public ?string $sourceIp, public DateTimeImmutable $receivedAt) {}

    public function firstHeader(string $name): ?string
    {
        foreach ($this->headers as $key => $values) {
            if (strcasecmp($key, $name) === 0) {
                return $values[0] ?? null;
            }
        }

        return null;
    }
}
