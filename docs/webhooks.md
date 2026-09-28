# Webhooks

## Request pipeline

The endpoint `POST /billing/webhooks/{driver}` resolves the named driver and preserves `Request::getContent()` without re-encoding. The driver authenticates those bytes, then parses and sanitizes the event. Invalid signatures return HTTP 400 before database persistence.

A unique `(driver, event_key)` ledger row is created before queue handoff. Concurrent duplicate inserts converge on the existing row through the database unique constraint. The response is HTTP 204 only after the dispatch call succeeds. A dispatch failure is recorded and propagated.

`ProcessWebhook` is unique by driver and event key. It marks processing attempts, restores the stored normalized events, and applies each event in a database transaction. Local mutations lock matching rows and use unique identifiers for idempotency. Retryable failures are recorded and rethrown to Laravel's queue. Unknown valid events are marked ignored and dispatch `WebhookIgnored`.

## Production configuration

Use a durable queue backend. Configure `billing.webhooks.connection` and `billing.webhooks.queue` when billing work should be isolated. Keep the endpoint outside browser CSRF middleware, but retain provider signature verification and any appropriate rate limits.

Monitor failed queue jobs and the webhook ledger. Recover failed or stuck rows with:

```bash
php artisan billing:webhooks:retry --dry-run
php artisan billing:webhooks:retry --driver=acme --limit=100
```

Pruning has a non-destructive preview and confirmation by default:

```bash
php artisan billing:webhooks:prune --dry-run
php artisan billing:webhooks:prune --days=90
php artisan billing:webhooks:prune --days=90 --force
```

Only processed and ignored rows are pruned. Failed or in-flight evidence is retained.

## Security checklist

- Verify signatures against the exact raw body before decoding.
- Use constant-time signature comparison where relevant.
- Support provider timestamp/replay protections.
- Sanitize before returning `ParsedWebhook`.
- Never include signature secrets, authorization headers, PANs, CVVs, or raw payment instruments.
- Use HTTPS and rotate webhook secrets according to provider procedures.
- Treat payload data as untrusted even after signature verification.
- Keep handlers idempotent and expect duplicates, delays, and out-of-order delivery.
