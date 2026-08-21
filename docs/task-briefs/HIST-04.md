# HIST-04 — Searchable history and direct snapshot comparison

## Goal and boundaries

Extend the existing owner-scoped history of completed research runs with bounded, deterministic search and filters for market, status, score, confidence, date, project, and workspace. Render frozen parameters in a readable form. Allow direct checkbox selection of exactly two compatible runs and compare their stored score, confidence, median velocity, competition, reachability, sample overlap, added/removed videos, new breakout channels, and stability.

When a compatible prior snapshot is unavailable, offer `Repeat with same parameters` behind an explicit frozen-parameter confirmation. The repeat action must not call a provider until the user confirms, and it must prevent duplicate submissions.

Do not mutate completed runs or snapshots, infer missing deltas, expose another user's history, or imply that a comparison is a causal result or recommendation.

## Acceptance

- History search, filters, pagination, readable frozen parameters, and direct pair selection are authenticated, owner-scoped, and deterministic.
- Exactly two distinct compatible runs can be compared; incompatible versions or parameters show explicit warnings and do not produce misleading like-for-like claims.
- Comparison shows only stored values and null-safe deltas for score, confidence, median velocity, competition, reachability, sample overlap, video membership changes, breakout channels, and stability.
- A missing compatible prior run produces a complete empty/incompatible state with an explicit frozen-parameter repeat confirmation; no provider work is dispatched before confirmation and duplicate submits are safe.
- UI has loading, empty, success, partial-data, incompatible, and error states; it is keyboard operable and preserves exact values without relying on color alone.
- Focused backend and frontend tests cover ownership, filter/pagination bounds, pair validation, nullable/version guards, confirmed repeat, and duplicate-submit protection.

## Applicable decisions

- D-005–D-008: history reads, comparisons, and repeat creation remain authenticated and owner-safe.
- D-014–D-018: completed research runs, snapshots, score inputs, and versions are immutable historical evidence.
- D-038: desktop accessibility, keyboard behavior, non-color indicators, and exact values are required.
- D-041–D-042: confidence and evidence-quality states remain explicit, stored, and null-safe in history comparisons.

## Initial inspection targets

- Existing History controllers, requests, policies, routes, read models, Inertia pages, and history feature tests.
- Existing research-run comparison/compatibility services and repeat-run actions.
- Existing history frontend interface tests and shared pagination/filter components.

## Focused verification

- Feature tests for owner isolation, deterministic filtering/pagination, direct pair selection, compatibility warnings, nullable deltas, and confirmed repeat idempotency.
- Frontend tests for readable frozen parameters, selection/focus behavior, exact comparison values, and complete empty/partial/incompatible/error states.
- Formatter, lint, type, PHPStan, and Pint checks only for changed files.

## Completion record

- Completed 2026-08-21.
- Delivered owner-scoped bounded history filters and pagination, readable frozen parameters, keyboard-operable direct two-run selection, stored-only overlap/rank-stability comparison values, explicit unavailable breakout-channel evidence, and confirmed idempotent repeats.
- Focused checks passed: `php artisan test tests/Feature/History/HistoryInterfaceTest.php tests/Feature/History/HistoryComparisonServicesTest.php`; `node --test tests/Frontend/history-interface.test.mjs`; scoped Pint, Prettier, and ESLint.
- Manual verification required: `composer test`; `npm run check`; filter history, compare two snapshots, and confirm a repeat from the unavailable-history state.

## References

- [Decision workflow redesign — HIST-04](../13_DECISION_WORKFLOW_REDESIGN.md#hist-04--searchable-history-and-direct-snapshot-comparison)
- [Backlog — Phase 19](../08_BACKLOG.md#phase-19--decision-workflow-redesign)
- [D-005–D-008](../10_DECISIONS.md#d-005--authentication-and-registration-use-laravel-session-auth)
- [D-014–D-018](../10_DECISIONS.md#d-014--research-runs-are-immutable-snapshots)
- [D-038](../10_DECISIONS.md#d-038--desktop-accessibility-and-exact-values-are-a-release-requirement)
- [D-041–D-042](../10_DECISIONS.md#d-041--research-evidence-quality-is-a-versioned-stored-profile)
