<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Contracts;

use Andriichuk\LaravelBilling\ValueObjects\SubscriptionReference;

interface SupportsUsageBilling
{
    /** @param array<string,mixed> $providerOptions */
    public function reportUsage(SubscriptionReference $subscription, int $quantity, array $providerOptions = []): void;
}
