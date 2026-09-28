<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Data;

use Andriichuk\LaravelBilling\Data\Events\NormalizedEvent;

final readonly class ParsedWebhook
{
    /**
     * @param  array<string, mixed>  $sanitizedPayload
     * @param  list<NormalizedEvent>  $events
     */
    public function __construct(public string $eventKey, public string $eventType, public ?string $providerResourceId, public array $sanitizedPayload, public array $events = []) {}
}
