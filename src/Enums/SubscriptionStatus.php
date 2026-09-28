<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Trialing = 'trialing';
    case PastDue = 'past_due';
    case Paused = 'paused';
    case Canceled = 'canceled';
    case Finished = 'finished';
    case Incomplete = 'incomplete';
    case Unknown = 'unknown';
}
