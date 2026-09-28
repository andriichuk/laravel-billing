<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Contracts;

use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\ValueObjects\SubscriptionReference;

interface SupportsSubscriptionPausing
{
    public function pauseSubscription(SubscriptionReference $subscription): SubscriptionData;

    public function resumeSubscription(SubscriptionReference $subscription): SubscriptionData;
}
