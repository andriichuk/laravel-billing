# Migration from Cashier BlueSnap (planning only)

No BlueSnap code is moved during the core milestone. The purpose of the first external driver is to test these abstractions against real existing behavior without contaminating the core with BlueSnap payloads.

## Proposed sequence

1. Inventory the existing `cashier-bluesnap` public API, database columns, webhook behavior, exception mapping, and SDK calls.
2. Map each operation to a core capability contract. Keep unsupported or BlueSnap-only methods on the external driver.
3. Implement a new BlueSnap driver package using `bluesnap-php-sdk`; do not add the SDK to this core package.
4. Map vaulted shoppers to `CustomerData`, recurring subscriptions to `SubscriptionData`, and charges/refunds to `TransactionData`.
5. Define conservative status mappings and retain the original BlueSnap status in sanitized provider data.
6. Implement signature verification over the untouched webhook body and deterministic normalization fixtures.
7. Run the reusable driver compliance suite plus BlueSnap-specific tests using a mock HTTP transport.
8. Compare the resulting contracts with existing applications and adjust pre-1.0 core contracts only where the provider demonstrates a general concept.
9. Write opt-in data migrations that add driver identifiers and transform existing local rows without deleting legacy tables.
10. Provide an application rollout plan: dual-read validation, webhook cutover, reconciliation, rollback window, then removal of the old integration.

## Compatibility decisions to make later

- Which legacy Cashier-style aliases merit a deprecation bridge in the driver package.
- How existing numeric BlueSnap IDs are serialized as opaque string references.
- Whether legacy raw-response columns are safe enough to migrate into `provider_data` or must be discarded.
- How historical transactions and canceled subscriptions are reconciled.
- How duplicate legacy webhook rows map to stable driver event keys.

The current `cashier-bluesnap` and `bluesnap-php-sdk` repositories remain unchanged until the core contracts pass this review.
