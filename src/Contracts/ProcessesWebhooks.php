<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Contracts;

use Andriichuk\LaravelBilling\Data\ParsedWebhook;
use Andriichuk\LaravelBilling\Data\WebhookRequest;

interface ProcessesWebhooks
{
    public function verifyWebhook(WebhookRequest $request): void;

    public function parseWebhook(WebhookRequest $request): ParsedWebhook;
}
