# Driver authoring

## Registration

A provider package should register a named driver from its service provider:

```php
Billing::extend('acme', function (Application $app, array $config): AcmeDriver {
    return new AcmeDriver(
        client: $app->make(AcmeClient::class),
        webhookSecret: (string) $config['webhook_secret'],
    );
});
```

Keep SDK packages and credentials in the driver package. The core package must remain installable without them.

## Required and optional contracts

Every driver implements `BillingDriver`: a stable name, a unique list of `Capability` cases, and `supports()`. Implement only applicable operation contracts:

- `ManagesCustomers`
- `ManagesSubscriptions`
- `ManagesTransactions`
- `ProcessesWebhooks`
- `ReconcilesResources`
- optional `Supports*` contracts for trials, plan/quantity changes, proration, pause/resume, checkout, refunds, invoices, usage, and payment-method updates

Capability reporting and implemented contracts must agree. Never advertise behavior that silently degrades. The core throws `UnsupportedCapability`; drivers should do the same for conditional restrictions discovered at runtime.

## DTO mapping

Accept core command DTOs and map them to the provider SDK at the edge. Return normalized result DTOs after a confirmed provider response. Use decimal strings for money. Keep provider payment methods opaque. Store the provider's original normalized status and useful non-sensitive fields in `rawProviderData()`, while mapping an honest core status (including `unknown` when needed).

Treat `providerOptions` as an explicit escape hatch. Validate supported keys and pass them only to the intended provider call. Do not mutate the input DTO.

## Exception translation

Catch SDK exceptions at the driver boundary and translate them:

- malformed application data → `InvalidBillingPayload`
- general remote failure → `ProviderRequestFailed`
- timeouts, rate limits, and temporary outages → `RetryableProviderOperation`
- confirmed payment refusal → `PaymentDeclined`
- absent remote object → `BillingResourceNotFound`
- concurrency/idempotency conflict → `BillingConflict`

Preserve the original throwable as the exception previous value when it is safe and useful. Never expose credentials or raw payment data in messages.

## Webhook verification and normalization

`verifyWebhook(WebhookRequest)` receives the exact raw body, normalized multi-value headers, source IP, method, and receipt time. Verify signatures before parsing or persistence. Throw `InvalidWebhookSignature` on failure.

`parseWebhook()` returns a stable provider event key, provider event type, optional resource identifier, sanitized payload, and zero or more normalized events. Stable keys must deduplicate redelivery forever within a driver. Unknown valid events should return zero normalized events; the core stores and marks them ignored.

Map provider payloads to the typed normalized events in `Data\Events`. New resources must include `billable_type` and `billable_id`; existing resources can be matched by driver and provider identifier. Attribute names must match the normalized database projection.

## Sanitization

Before returning provider data or webhook payloads, recursively remove:

- authorization headers and bearer/basic credentials
- API keys, webhook secrets, and signature secrets
- PANs, CVVs/CVCs, magnetic-stripe or cryptogram data
- unredacted bank-account or identity data
- provider fields that the application does not need

Prefer an allowlist over a denylist. Sanitization is a driver responsibility because only the driver understands its payload.

## Idempotency and concurrency

Use provider idempotency keys for creates and other retryable mutations. A repeated command must not create additional remote resources. Do not hold application database locks while calling the provider. Webhook normalization should be deterministic for the same event bytes.

Reconciliation must return authoritative normalized resources without directly mutating core tables. Honor model and ID filters when the provider API can do so efficiently.

## Compliance tests

Extend `Andriichuk\LaravelBilling\Testing\DriverComplianceTestCase`, then add capability-specific tests covering:

- identity and capability consistency
- complete customer and subscription lifecycles
- cancellation modes and optional capability behavior
- decimal money and provider option forwarding
- exception translation and retryability
- exact raw-body signature verification
- deterministic event keys and normalized mappings
- sanitization of sensitive fixtures
- idempotent creates and webhook redelivery
- reconciliation results

No compliance test should make an uncontrolled real network call. Use SDK fakes, mock transports, or recorded sanitized fixtures.
