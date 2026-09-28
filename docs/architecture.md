# Architecture

## Authority and projection

Remote providers own billing truth. The four package tables are a normalized projection optimized for Laravel application concerns. A local row is not evidence that a remote operation succeeded; it is updated only from a successful driver result, a verified webhook, or reconciliation.

Remote HTTP work happens before local locking. Projection updates use unique constraints, transactions, and row locks where an existing resource can be mutated concurrently. Provider identifiers are always scoped by driver.

## Layers

1. `BillingManager` registers and resolves named drivers.
2. `BillingDriver` describes identity and capabilities only.
3. Small contracts expose customer, subscription, transaction, webhook, and reconciliation operations.
4. Immutable command/result DTOs cross the core-driver boundary.
5. `BillingSynchronizer` updates configurable local Eloquent models and change-gates lifecycle events shared by webhooks and reconciliation.
6. `Billable` and `SubscriptionBuilder` provide the application API.
7. The webhook ledger authenticates, deduplicates, queues, applies, and audits provider events.
8. Reconciliation repairs drift from provider-authoritative DTOs.

Reconciliation ownership is a core concern. Existing `billing_customers`, `billing_subscriptions`, and `billing_transactions` rows map provider IDs back to billables. An application resolver handles never-before-seen provider resources because only the application knows conventions such as an account ID embedded in provider metadata. Unresolvable orphans are reported and skipped so one bad resource cannot abort a sweep.

## Stable normalized concepts

The core normalizes customer, subscription, and transaction references; decimal money; billing intervals; lifecycle statuses; cancellation modes; capabilities; and a bounded set of billing events. It intentionally does not standardize provider checkout sessions, tax engines, invoice presentation, payment instruments, fraud payloads, or merchant-of-record rules.

Provider-specific options travel through `providerOptions`. Sanitized provider responses remain accessible through `rawProviderData()`. Applications can resolve the underlying driver for functionality that should not become a core abstraction.

## Multiple providers

Every resource has a `driver` column. Customer uniqueness is per billable and driver; subscription type uniqueness is per billable, driver, and type. A single user may therefore have independent `default` subscriptions with several providers.

The manager caches resolved driver instances for a request. Extensions must return a driver whose `name()` exactly matches the registration key.

## Failure model

Drivers translate SDK exceptions into the stable package hierarchy. `RetryableProviderOperation` means queue retry may succeed. `PaymentDeclined` is a non-retryable provider failure. Conflicts, missing resources, invalid requests, invalid signatures, and unsupported capabilities remain distinct.

Webhook failures are recorded before retryable errors are rethrown. Non-retryable processing failures remain visible in the ledger for inspection or explicit retry. Pruning only targets completed or ignored events older than the configured retention period.
