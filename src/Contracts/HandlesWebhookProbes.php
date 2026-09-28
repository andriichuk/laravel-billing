<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Contracts;

use Andriichuk\LaravelBilling\Data\WebhookRequest;

interface HandlesWebhookProbes
{
    public function handlesWebhookProbe(WebhookRequest $request): bool;
}
