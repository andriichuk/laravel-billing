# Laravel Billing

A vendor-agnostic subscription billing core for Laravel 13 and PHP 8.5. It provides a Cashier-like application API while keeping Stripe, Paddle, BlueSnap, and every other provider in separate driver packages.

> **Stability:** This package is pre-1.0. Its contracts are intentionally being validated against the first external BlueSnap driver before a stable release. Do not publish it to Packagist yet.

## Architecture

The payment provider is authoritative. Laravel Billing stores a normalized local projection for application queries, authorization, UI, reporting, webhook recovery, and reconciliation. The core knows stable billing concepts only and has no provider SDK dependency.

Drivers expose small capability contracts instead of one mandatory mega-interface. Every stored customer, subscription, transaction, and webhook includes its driver name, so one billable model can use several providers simultaneously. Provider responses retain sanitized raw data, command DTOs accept `providerOptions`, and `Billing::driver()` provides an intentional escape hatch to a driver.

See [docs/architecture.md](docs/architecture.md) for the full design.

## Requirements and installation

- PHP 8.5+
- Laravel 13
- A configured queue worker for production webhooks

Install from the repository while the package is pre-release:

```bash
composer config repositories.laravel-billing vcs https://github.com/andriichuk/laravel-billing
composer require andriichuk/laravel-billing:dev-main
```

Laravel package discovery registers the service provider. Publish configuration and migrations, then migrate:

```bash
php artisan vendor:publish --tag=billing-config
php artisan vendor:publish --tag=billing-migrations
php artisan migrate
```

The published configuration selects a default driver, driver-specific configuration, replaceable model classes, webhook queue routing, retention, and reconciliation chunk size.

## Billable models

Add the trait to an Eloquent model:

```php
use Andriichuk\LaravelBilling\Concerns\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

The local query API is driver-aware:

```php
$user->billingCustomer();
$user->billingCustomer('stripe');

$user->subscriptions()->latest()->get();
$user->subscription('default', driver: 'bluesnap');
$user->subscribed('default', price: 'price-123', driver: 'stripe');
```

## Installing and selecting drivers

Provider packages register themselves with Laravel's extension mechanism:

```php
use Andriichuk\LaravelBilling\Billing;

Billing::extend('acme', function ($app, array $config) {
    return new AcmeBillingDriver(/* provider dependencies */);
});
```

Set `BILLING_DRIVER=acme` for the default, or choose explicitly:

```php
$driver = Billing::driver();
$acme = Billing::driver('acme');
$same = $user->billing('acme');
```

## Customers

Creating or synchronizing a customer calls the selected provider and updates the local projection:

```php
$customer = $user->createBillingCustomer(
    driver: 'acme',
    name: $user->name,
    email: $user->email,
    metadata: ['account_tier' => 'pro'],
    providerOptions: ['locale' => 'en'],
);

$customer = $user->syncBillingCustomer('acme', name: 'Updated Name');
```

## Subscriptions

The builder validates common input, creates typed command data, and delegates remote work to `ManagesSubscriptions`:

```php
$subscription = $user->newSubscription('default', 'price-123')
    ->driver('acme')
    ->quantity(5)
    ->trialDays(14)
    ->paymentMethod('pm-opaque-reference')
    ->withMetadata(['team' => 'platform'])
    ->withProviderOptions(['provider_specific_key' => 'value'])
    ->idempotencyKey('subscription:user:'.$user->getKey())
    ->create();
```

Money uses validated decimal strings and uppercase ISO 4217 codes. Floats are intentionally not accepted. Cancellation drivers receive an explicit `CancellationMode::AtPeriodEnd` or `CancellationMode::Immediately`; pause, proration, quantity changes, and plan changes are separate optional capabilities.

Normalized subscription states are `active`, `trialing`, `past_due`, `paused`, `canceled`, `finished`, `incomplete`, and `unknown`. Local models expose `active()`, `onTrial()`, `onGracePeriod()`, `recurring()`, and `valid()` helpers without claiming that every provider has identical lifecycle behavior.

## Capability checks

```php
use Andriichuk\LaravelBilling\Enums\Capability;

if (Billing::driver('acme')->supports(Capability::Refunds)) {
    // Resolve the driver and use its SupportsRefunds contract.
}
```

Core orchestration throws `UnsupportedCapability` with the driver and capability names when an unavailable operation is attempted.

## Webhooks and queues

The package registers:

```text
POST /billing/webhooks/{driver}
```

The driver verifies the exact raw body before anything is persisted. Valid events are sanitized, stored idempotently in `billing_webhook_events`, and handed to a unique queue job. The job applies normalized events under database transactions and records attempts, status, errors, and timestamps. Unknown valid events remain available with `ignored` status and dispatch `WebhookIgnored`.

Configure a durable queue in production and run a worker:

```dotenv
QUEUE_CONNECTION=redis
BILLING_DRIVER=acme
```

```bash
php artisan queue:work --queue=billing,default
php artisan billing:webhooks:retry --driver=acme
php artisan billing:webhooks:prune --dry-run
php artisan billing:webhooks:prune --days=90 --force
```

See [docs/webhooks.md](docs/webhooks.md) before exposing a provider endpoint.

## Reconciliation

Reconciliation refreshes the local projection from authoritative provider data:

```bash
php artisan billing:reconcile
php artisan billing:reconcile --driver=acme
php artisan billing:reconcile --model=subscription
php artisan billing:reconcile --model=subscription --id=123
php artisan billing:reconcile --driver=acme --dry-run
```

Drivers implement `ReconcilesResources` and return normalized reconciliation results. The core owns selection, validation, reporting, and local synchronization.

## Events

Laravel lifecycle events are independent of provider event names:

- `WebhookReceived`, `WebhookProcessed`, `WebhookIgnored`, `WebhookFailed`
- `SubscriptionUpdated`
- `TransactionUpdated`

Drivers may normalize customer, subscription, transaction, renewal, payment-failure, refund, and chargeback events. Applications can listen to lifecycle events without depending on provider payload formats.

## Provider escape hatches

- DTO results expose `rawProviderData()` containing sanitized provider data.
- Customer and subscription commands accept `providerOptions`.
- `Billing::driver('name')` returns the underlying driver for provider-only APIs.
- Payment methods are opaque `PaymentMethodReference` values; the core never normalizes or stores raw card, bank, or wallet payloads.

## Security

Drivers must remove authorization headers, signature secrets, PANs, CVVs, and sensitive payment data before returning provider data or parsed webhook payloads. Never log raw credentials or payment instruments. Verify webhook signatures against the untouched request bytes, use HTTPS, isolate provider secrets in environment-backed configuration, and use a durable non-sync queue in production.

## Development

```bash
composer install
composer test
composer analyse
composer check
composer validate --strict
```

The bundled fake driver records requests in memory, simulates failures, supports signed webhooks and reconciliation, and enables full lifecycle tests without network calls. External drivers can extend `DriverComplianceTestCase`; see [docs/driver-authoring.md](docs/driver-authoring.md).

## License

MIT. See [LICENSE.md](LICENSE.md).
