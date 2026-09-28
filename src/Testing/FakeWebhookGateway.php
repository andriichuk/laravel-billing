<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Testing;

use Andriichuk\LaravelBilling\Contracts\ProcessesWebhooks;
use Andriichuk\LaravelBilling\Data\Events\NormalizedEvent;
use Andriichuk\LaravelBilling\Data\ParsedWebhook;
use Andriichuk\LaravelBilling\Data\WebhookRequest;
use Andriichuk\LaravelBilling\Exceptions\InvalidBillingPayload;
use Andriichuk\LaravelBilling\Exceptions\InvalidWebhookSignature;

final class FakeWebhookGateway implements ProcessesWebhooks
{
    public function __construct(
        private readonly FakeStore $store,
        private readonly string $secret
    ) {}

    public function verifyWebhook(WebhookRequest $request): void
    {
        $this->store->record('verifyWebhook', $request);
        $expected = hash_hmac('sha256', $request->rawBody, $this->secret);

        if (! hash_equals($expected, (string) $request->firstHeader('x-billing-signature'))) {
            throw new InvalidWebhookSignature('The fake webhook signature is invalid.');
        }
    }

    public function parseWebhook(WebhookRequest $request): ParsedWebhook
    {
        $this->store->record('parseWebhook', $request);
        $decoded = json_decode($request->rawBody, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw InvalidBillingPayload::because('Webhook body must decode to an object.');
        }

        $eventKey = $decoded['id'] ?? null;
        $eventType = $decoded['type'] ?? null;

        if (! is_string($eventKey) || ! is_string($eventType)) {
            throw InvalidBillingPayload::because('Fake webhook requires string id and type fields.');
        }

        $events = [];
        $normalized = $decoded['normalized'] ?? [];

        if (! is_array($normalized)) {
            $normalized = [];
        }

        foreach ($normalized as $item) {
            if (! is_array($item)) {
                continue;
            }

            $className = $item['class'] ?? null;
            $resourceId = $item['resource_id'] ?? null;
            $data = $item['data'] ?? [];

            if (! is_string($className) || ! is_string($resourceId) || ! is_array($data)) {
                continue;
            }

            $class = 'Andriichuk\\LaravelBilling\\Data\\Events\\'.$className;

            if (! is_a($class, NormalizedEvent::class, true)) {
                continue;
            }

            $events[] = new $class($resourceId, $data);
        }

        $resourceId = is_string($decoded['resource_id'] ?? null) ? $decoded['resource_id'] : null;
        $sanitized = $decoded;
        unset($sanitized['secret'], $sanitized['authorization'], $sanitized['card'], $sanitized['cvv']);

        return new ParsedWebhook($eventKey, $eventType, $resourceId, $sanitized, $events);
    }
}
