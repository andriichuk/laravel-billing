<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Webhooks;

use Andriichuk\LaravelBilling\BillingManager;
use Andriichuk\LaravelBilling\Contracts\HandlesWebhookProbes;
use Andriichuk\LaravelBilling\Data\WebhookRequest;
use Andriichuk\LaravelBilling\Exceptions\InvalidWebhookSignature;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class WebhookController
{
    public function __invoke(Request $request, string $driver, WebhookProcessor $processor, BillingManager $billing): Response
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = array_map('strval', $values);
        }

        $webhookRequest = new WebhookRequest($request->getMethod(), $request->getContent(), $headers, $request->ip(), new DateTimeImmutable);

        if ($request->isMethod('GET')) {
            $instance = $billing->driver($driver);

            return $instance instanceof HandlesWebhookProbes && $instance->handlesWebhookProbe($webhookRequest)
                ? response('', 200)
                : response('', 405);
        }

        try {
            $processor->receive($driver, $webhookRequest);
        } catch (InvalidWebhookSignature) {
            return response('', 400);
        }

        return response('', 204);
    }
}
