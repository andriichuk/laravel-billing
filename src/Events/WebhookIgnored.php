<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Events;

use Andriichuk\LaravelBilling\Models\WebhookEvent;

final readonly class WebhookIgnored
{
    public function __construct(public WebhookEvent $webhook) {}
}
