# ANA-07 — Section-scoped Analyzer enrichments

## Goal and boundaries

Run Comments and Thumbnail patterns through owner-authorized JSON requests and local section polling without redirecting or reloading the Analyzer page.

## Acceptance

- POST start endpoints return `202` for queued/processing and `200` for a reusable completed result; status endpoints return typed terminal/active payloads.
- AsyncSection immediately shows queued/loading, disables duplicates, polls one section, stops on terminal/unmount, and restores state after manual refresh.
- Scroll, active tab, focus, expanded rows, and comment page remain unchanged.
- Errors are safe, inline, retryable, and accessible through `aria-busy`/`aria-live`.
- Ownership, quota ledger, idempotency, retry behavior, and no-quota thumbnail analysis remain protected.

## Applicable decisions

- D-005–D-013, D-030–D-034, D-050–D-054.

## Initial inspection targets

- Analyzer comment/thumbnail controllers, jobs/actions/read models, routes, show-page polling, section components, quota shared props and tests.

## Relevant contracts and UI

- `AsyncSectionStatus = idle | queued | processing | completed | failed`.
- JSON includes status, optional data, safe error, quota summary where applicable, poll URL, and retry interval.

## Focused verification

- Exact feature tests for 200/202/error/foreign/duplicate/quota/job states.
- Exact frontend tests for no Inertia reload, scoped polling, preserved context, retry, cleanup, and announcements.

## Exact reference links

- [Decision D-050](../10_DECISIONS.md#d-050--analyzer-enrichments-update-only-their-section)
- [AsyncSection component contract](../reference/RDSN_LIQUID_GLASS_DESIGN_CONTRACT.md#asyncsection)
- [Analyzer async-state prototype](../reference/RDSN_RESPONSIVE_PROTOTYPES.md#asyncsection-state-prototypes)
- [RDSN-02 brief](RDSN-02.md)
- [Working rules](../00_WORKING_RULES.md)
