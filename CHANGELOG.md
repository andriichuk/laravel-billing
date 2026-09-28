# Changelog

## 0.2.1 - 2026-09-28

- Adopt the canonical Laravel Pint preset and enforce multiline member PHPDoc formatting.

## 0.2.0 - 2026-09-28

- Add cursor-, page-size-, and provider-time-aware sweep reconciliation while retaining targeted model/ID reconciliation.
- Resolve billables in the core from local provider-ID rows, with an application-bindable resolver for previously unseen resources; unresolved orphans are reported and skipped.
- Dispatch subscription and transaction lifecycle events from the shared synchronizer only when projection state changes, with an explicit force-replay option. Applications with existing listeners will now receive these events for reconciliation repairs.
- Add changed/unchanged/skipped reconciliation results and meaningful no-write, no-event dry runs.
