<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Data;

use Andriichuk\LaravelBilling\Data\Events\NormalizedEvent;

final readonly class NormalizedWebhook
{
    /** @param list<NormalizedEvent> $events */
    public function __construct(public array $events) {}
}
