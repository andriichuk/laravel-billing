<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Webhooks;
use Andriichuk\LaravelBilling\Data\WebhookRequest;
use Andriichuk\LaravelBilling\Exceptions\InvalidWebhookSignature;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
final class WebhookController
{
    public function __invoke(Request $request, string $driver, WebhookProcessor $processor): Response
    {
        $headers = []; foreach ($request->headers->all() as $name => $values) { $headers[$name] = array_map('strval', $values); }
        try { $processor->receive($driver, new WebhookRequest($request->getMethod(), $request->getContent(), $headers, $request->ip(), new DateTimeImmutable())); }
        catch (InvalidWebhookSignature) { return response('', 400); }
        return response('', 204);
    }
}
