# Live Task Status

## Purpose

This file is the live source of truth for task state. `docs/08_BACKLOG.md` defines task scope and acceptance criteria; this file records execution state, verification evidence, and the one task Codex must work on next.

## Rules for Codex

1. Read this file before making changes.
2. There must be exactly one `In progress` task, unless the project is blocked.
3. Work only on the task marked `In progress`.
4. Mark a task `Completed` only after every relevant acceptance criterion and Codex-scoped verification command passes. Full PHP and React/frontend suites are manual-only and are not a Codex completion gate.
5. On completion, add the date, changed scope, focused verification evidence, and a short manual verification checklist; update the matching checkbox in `08_BACKLOG.md`.
6. Then mark the next unblocked task `In progress`. Do not implement it until the next user instruction or task turn.
7. If tests fail or a dependency is missing, retain `In progress` and record the blocker. Do not advance the sequence.
8. Never mark a task complete based only on code being written or a page appearing visually.
9. Codex must never run aggregate/full suites such as `composer ci:check`, `composer test`, bare `php artisan test`, `npm run check`, `npm run types`, `npm run lint:check`, `npm run format:check`, or `npm run build`.
10. At completion, provide the manual checklist in commentary and make the final response exactly `TASK DONE`. Do not use that response for blocked or incomplete work.

## Status legend

| Status | Meaning |
|---|---|
| `Pending` | Not started or waiting for an earlier dependency. |
| `In progress` | The only task Codex may implement now. |
| `Completed` | All acceptance criteria and focused Codex verification passed; manual full-suite checks were listed for the user. |
| `Blocked` | Cannot proceed; the reason and required action are recorded. |

## Current task

| Field | Value |
|---|---|
| Active task | `SCR-01` |
| Status | `In progress` |
| Objective | Implement the versioned `niche-opportunity-v1` scoring schema and deterministic five-component engine with confidence, warnings, and persisted inputs. |
| Start condition | ANL-04 completed. |
| Completion condition | All SCR-01 acceptance criteria and relevant verification checks pass. |

## Task register

| Task | Status | Dependency | Completion / blocker evidence |
|---|---|---|---|
| FND-01 | Completed | — | Existing project backlog marks it complete. |
| FND-02 | Completed | FND-01 | Existing project backlog marks it complete. |
| FND-03 | Completed | FND-02 | Existing project backlog marks it complete. |
| FND-04 | Completed | FND-03 | 2026-08-07 — Added brand tokens, authenticated shell navigation/header, reusable data UI states, and fixture showcase. Passed focused feature tests, browser smoke QA, `composer test`, `vendor/bin/pint --test`, `npm run types`, `npm run lint`, and `npm run build`. |
| FND-05 | Completed | FND-04 | 2026-08-07 — Added one non-mutating aggregate verification command, enforced explicit HTTP fakes, disabled SSR dependency in tests, and documented the suite. Passed focused HTTP safety tests and `composer ci:check`. |
| AUTH-01 | Completed | FND-05 | 2026-08-07 — Added persisted timezone/default-market preferences, validated domain updates, explicit self-authorization, sensitive endpoint throttles, loopback-only registration, and backend coverage. Passed: focused auth/settings tests; `composer test`; `composer ci:check`. |
| AUTH-02 | Completed | AUTH-01 | 2026-08-07 — Added a responsive branded auth experience, complete loading/error/success feedback, account preference controls, security and appearance cards, and destructive confirmation UI. Passed: focused auth/settings tests; 1440px and 768px browser QA; `composer ci:check`. |
| AUTH-03 | Completed | AUTH-02 | 2026-08-07 — Expanded authentication coverage for success/failure flows, guest protection, session invalidation, cross-user isolation, preference persistence/clearing, password behavior, and all configured sensitive-action throttles. Passed: focused auth/settings tests; `composer ci:check`. |
| SET-01 | Completed | AUTH-03 | 2026-08-07 — Added canonical market persistence and idempotent seeding, enabled-market settings integration and validation, immutable frozen market parameters, exact YouTube request mapping, and focused coverage. Passed: focused market/settings/provider tests; `composer test`; `composer ci:check`. |
| SET-02 | Completed | SET-01 | 2026-08-07 — Added a typed Laravel YouTube adapter, safe configuration and errors, durable per-attempt quota accounting, Pacific-day project summaries, bounded transient retries, search normalization/pagination, ID batching, and safe shared Inertia data. Passed: focused provider/ledger/helper tests; `composer test`; `composer ci:check`. |
| SET-03 | Completed | SET-02 | 2026-08-07 — Added persisted default result depth, safe integration status and connectivity checks, quota meters/disclaimer, and a responsive authenticated-header quota widget with polling and estimate states. Passed: focused settings/YouTube tests; PHPStan; ESLint; 1440px and 768px browser QA; `composer ci:check`. |
| SET-04 | Completed | SET-03 | 2026-08-07 — Expanded fake-HTTP coverage for exact market mapping, token pagination, missing/invalid keys, quota exhaustion and boundaries, malformed/partial payloads, and secret-safe settings responses. Passed: focused provider/settings tests (37 tests, 181 assertions); focused Pint; `composer ci:check` (98 passed, 4 skipped, 521 assertions). |
| FIX-01 | Completed | SET-03 | 2026-08-07 — Increased the quota trigger's contained width, added safe shrink/overflow behavior, and constrained the header controls. Passed: focused Prettier; focused ESLint; browser QA at 1280px and 768px with readable values, no horizontal overflow, and no console warnings/errors. Manual: [x] `npm run check` (user-confirmed). |
| SRCH-01 | Completed | SET-04 | 2026-08-07 — Added user-owned projects, normalized queries, immutable frozen run parameters, UUID route keys, ownership policies, lifecycle transitions, safe failures, and research-linked quota integrity. Passed: `php artisan test tests/Feature/Research/ResearchSchemaAuthorizationTest.php` (8 tests, 60 assertions); focused YouTube quota/provider tests (13 tests, 72 assertions); focused PHPStan; focused Pint. Manual: [x] `composer test` (user-confirmed). |
| SRCH-02 | Completed | SRCH-01 | 2026-08-08 — Added durable search page/result staging, frozen-filter pagination, database-queued resumable collection, monotonic progress, duplicate-delivery guards, bounded retries, safe failures and partial warnings, immutable retry attempts, and enrichment handoff. Passed: focused orchestration tests (6 tests, 62 assertions); affected research/provider tests (18 tests, 122 assertions); focused PHPStan; focused Pint. Manual: [ ] `composer test`. |
| SRCH-03 | Completed | SRCH-02 | 2026-08-08 — Added the validated market/filter search form, estimated call cost, owner-authorized live run pages, real polling/progress and result previews, partial/error/quota guidance, and retry. Passed: focused research tests (19 tests, 223 assertions); focused PHPStan; focused Pint; focused ESLint; focused Prettier; browser QA at 1440px and 768px. Manual: [ ] `composer test`; [ ] `npm run check`; [ ] create a low-depth search and verify progress, partial/error guidance, and retry. |
| SRCH-04 | Completed | SRCH-03 | 2026-08-08 — Expanded search acceptance coverage for validation boundaries, frozen inputs, the full lifecycle graph, invalid queue/retry states, transient resume, duplicate results/tokens, ownership, and quota failure after partial persistence. Passed: focused research tests (26 tests, 375 assertions); focused Pint; PHP syntax checks. Manual: [ ] `composer test`. |
| ANL-01 | Completed | SRCH-04 | 2026-08-08 — Added provider-deduplicated channels/videos, ranked run membership, immutable per-run channel/video snapshots, nullable metrics, cascade/restrict rules, and lookup indexes. Passed: `php artisan test tests/Feature/Catalog/CatalogSnapshotSchemaTest.php` (4 tests, 44 assertions); focused Pint; focused PHPStan. Manual: [ ] `composer test`. |
| ANL-02 | Completed | ANL-01 | 2026-08-08 — Added typed 50-ID video/channel enrichment, exact provider request parts, nullable/hidden metric normalization, atomic resumable batch persistence, derived age/rate/ratio metrics, safe failures, and scoring handoff. Passed: 30 focused tests (202 assertions); focused Pint; focused PHPStan. Manual: [ ] `composer test`. |
| ANL-03 | Completed | ANL-02 | 2026-08-08 — Added run-scoped snapshot aggregates, coverage and freshness, accessible video/channel charts with table alternatives, searchable paginated tables, exact-value detail drawers, nullable-metric warnings, and responsive loading/empty/partial/error states. Passed: analysis interface test (1 test, 44 assertions); affected research interface tests (5 tests, 101 assertions); focused PHPStan; focused Pint; focused ESLint; focused Prettier; scoped diff check. Manual: [ ] `composer test`; [ ] `npm run check`; [ ] enriched-run UI flow at 1440px and 768px. |
| ANL-04 | Completed | ANL-03 | 2026-08-08 — Expanded focused analysis coverage for the 50-ID provider boundary, missing/hidden values, catalog and retry deduplication, cross-run immutable refreshes, zero/future-date derived-metric boundaries, owner isolation, and a constant one-query analysis read model. Passed: 18 focused tests (201 assertions); focused Pint; PHP syntax checks. Manual: [ ] `composer test`. |
| SCR-01 | In progress | ANL-04 | Next unblocked task; do not implement until the next user instruction. |
| SCR-02 | Pending | SCR-01 | — |
| SCR-03 | Pending | SCR-02 | — |
| DASH-01 | Pending | SCR-03 | — |
| DASH-02 | Pending | DASH-01 | — |
| DASH-03 | Pending | DASH-02 | — |
| DISC-01 | Pending | DASH-03 | — |
| DISC-02 | Pending | DISC-01 | — |
| DISC-03 | Pending | DISC-02 | — |
| LIB-01 | Pending | DISC-03 | — |
| LIB-02 | Pending | LIB-01 | — |
| LIB-03 | Pending | LIB-02 | — |
| HIST-01 | Pending | LIB-03 | — |
| HIST-02 | Pending | HIST-01 | — |
| HIST-03 | Pending | HIST-02 | — |
| EXP-01 | Pending | HIST-03 | — |
| EXP-02 | Pending | EXP-01 | — |
| EXP-03 | Pending | EXP-02 | — |
| RET-01 | Pending | EXP-03 | Requires resolution of O-001 in `10_DECISIONS.md`. |
| RET-02 | Pending | RET-01 | — |
| RET-03 | Pending | RET-02 | — |
| REL-01 | Pending | RET-03 | — |
| REL-02 | Pending | REL-01 | — |
| REL-03 | Pending | REL-02 | — |
| REL-04 | Pending | REL-03 | — |

## Completion history

| Date | Task | Result | Verification evidence |
|---|---|---|---|
| 2026-08-07 | FND-01 | Completed before live tracking was introduced. | See existing backlog/check-in history. |
| 2026-08-07 | FND-02 | Completed before live tracking was introduced. | See existing backlog/check-in history. |
| 2026-08-07 | FND-03 | Completed before live tracking was introduced. | See existing backlog/check-in history. |
| 2026-08-07 | FND-04 | Completed. | Authenticated component showcase and responsive shell delivered. Passed focused feature tests, browser smoke QA, `composer test`, `vendor/bin/pint --test`, `npm run types`, `npm run lint`, and `npm run build`. |
| 2026-08-07 | FND-05 | Completed. | Added aggregate PHP/frontend verification, explicit HTTP-fake enforcement, and local documentation. Passed focused HTTP safety tests and `composer ci:check`. |
| 2026-08-07 | AUTH-01 | Completed. | Added user preference persistence and validation, authorization policy enforcement, rate limiting for sensitive authentication/account endpoints, and loopback-only registration. Passed focused auth/settings tests, `composer test`, and `composer ci:check`. |
| 2026-08-07 | AUTH-02 | Completed. | Delivered responsive branded authentication pages and account settings for profile, timezone, default market, password, appearance, and account deletion with accessible interaction states. Passed focused auth/settings tests, 1440px and 768px browser QA, and `composer ci:check`. |
| 2026-08-07 | AUTH-03 | Completed. | Added comprehensive authentication tests for failures, protected routes, invalidated sessions, session-scoped ownership, preference persistence, password reset/confirmation, and every configured auth/account throttle. Passed focused auth/settings tests and `composer ci:check` with 64 passed, 4 skipped, and 325 assertions. |
| 2026-08-07 | SET-01 | Completed. | Added and seeded the three canonical markets, persisted enabled-market settings options, validated immutable market-to-YouTube request mappings, and rejected disabled selections. Passed 30 focused tests with 136 assertions, `composer test`, and `composer ci:check` with 76 passed, 4 skipped, and 373 assertions. |
| 2026-08-07 | SET-02 | Completed. | Added typed provider/configuration boundaries, normalized redacted errors, retry-aware quota attempt persistence, configurable bucket estimates with Pacific reset boundaries, pagination/batching helpers, and safe project-wide Inertia summary props. Passed focused provider/ledger/helper tests, `composer test`, and `composer ci:check` with 84 passed, 4 skipped, and 429 assertions. |
| 2026-08-07 | SET-03 | Completed. | Added persisted default result depth, safe server-side key presence and connectivity feedback, project-wide quota meters/disclaimer, and a responsive persistent quota widget with per-bucket estimates, last-call details, polling, reset time, and loading/stale/unavailable/exhausted states. Passed 26 focused tests with 201 assertions, PHPStan, ESLint, 1440px and 768px browser QA with no console errors or horizontal overflow, and `composer ci:check` with 90 passed, 4 skipped, and 484 assertions. |
| 2026-08-07 | SET-04 | Completed. | Added fake-HTTP coverage for all market request mappings, provider pagination, invalid/missing keys, quota exhaustion and exact allowance/reset boundaries, malformed/partial responses, and API-key secrecy in errors, storage, sessions, and Inertia output. Passed 37 focused tests with 181 assertions, focused Pint, and `composer ci:check` with 98 passed, 4 skipped, and 521 assertions. |
| 2026-08-07 | SRCH-01 | Completed. | Added the research schema, normalized user-owned queries, immutable frozen run inputs, UUID route binding, policy isolation, sequential attempts, guarded lifecycle transitions, safe terminal failures, and the quota-ledger run foreign key. Passed 21 focused tests with 132 assertions, focused PHPStan, and focused Pint. Manual checklist: [x] `composer test` (user-confirmed). |
| 2026-08-07 | FIX-01 | Completed. | Contained the authenticated-header quota widget while keeping both desktop bucket values readable and preserving the compact tablet state. Passed focused Prettier and ESLint; browser measurements and visual QA at 1280px and 768px confirmed no overflow or console errors. Manual checklist: [x] `npm run check` (user-confirmed). |

| 2026-08-08 | SRCH-02 | Completed. | Persisted resumable search pages and deduplicated candidates, queued frozen-filter pagination, recorded progress and quota context, handled bounded retries/partial data/safe failures, created immutable retry attempts, and dispatched the enrichment boundary. Passed 24 focused tests with 184 assertions, focused PHPStan, and focused Pint. Manual checklist: [ ] `composer test`. |
| 2026-08-08 | SRCH-03 | Completed. | Delivered a responsive search form and live run interface with frozen market/window/filter submission, local search-call estimates, persisted progress and candidate previews, polling quota updates, partial and empty states, safe actionable failures, and immutable retry navigation. Passed 19 focused research tests with 223 assertions, focused PHPStan, Pint, ESLint, Prettier, and browser QA at 1440px and 768px with no horizontal overflow or console warnings/errors. Manual checklist: [ ] `composer test`; [ ] `npm run check`; [ ] create a low-depth search and verify progress, partial/error guidance, and retry. |
| 2026-08-08 | SRCH-04 | Completed. | Expanded boundary and resilience coverage for submission validation, ownership, frozen collection inputs, every run-state transition, invalid queue/retry states, retry resumption, duplicate delivery safeguards, and quota failure after saved partial results. Passed 26 focused tests with 375 assertions, focused Pint, and PHP syntax checks. Manual checklist: [ ] `composer test`. |
| 2026-08-08 | ANL-01 | Completed. | Added globally deduplicated catalog entities, ranked per-run video membership, immutable nullable metric snapshots, safe run-artifact cascades, shared-entity restrictions, and required indexes. Passed 4 focused tests with 44 assertions, focused Pint, and focused PHPStan. Manual checklist: [ ] `composer test`. |
| 2026-08-08 | ANL-02 | Completed. | Extended the provider boundary with correctly shaped batched video/channel requests, normalized missing and hidden statistics, persisted canonical entities and immutable snapshots atomically, calculated stored age/views-per-day/views-to-subscriber metrics, resumed retries from saved batches, and handed enriched runs to scoring. Passed 30 focused tests with 202 assertions, focused Pint, and focused PHPStan. Manual checklist: [ ] `composer test`. |
| 2026-08-08 | ANL-03 | Completed. | Delivered owner-scoped snapshot aggregates, exact freshness and coverage, responsive aggregate cards, accessible video/channel bar summaries with complete table alternatives, searchable paginated tables, keyboard-operable detail drawers, and explicit loading, empty, partial-data, success, and recoverable-error presentation. Passed 6 focused feature tests with 145 assertions, focused PHPStan, Pint, ESLint, Prettier, and scoped diff check. Automated browser QA was unavailable because the shared Node kernel could not initialize. Manual checklist: [ ] `composer test`; [ ] `npm run check`; [ ] open an enriched run at 1440px and 768px and verify search, pagination, drawer focus, and no horizontal overflow. |
| 2026-08-08 | ANL-04 | Completed. | Hardened the analysis module with explicit regressions for maximum provider batch splitting, nullable and hidden statistics, global catalog reuse, retry idempotency, immutable later-run snapshots, derived-rate edge cases, authenticated owner isolation, and bounded read-model query count. Passed 18 focused tests with 201 assertions, focused Pint, and PHP syntax checks. Manual checklist: [ ] `composer test`. |

## Update template

When completing a task, replace its register row and add a history row using this format:

```text
| TASK-ID | Completed | PREVIOUS-ID | YYYY-MM-DD — summary. Passed: command 1; command 2. |
```

Then set the next unblocked task to `In progress` in both the **Current task** section and the task register.
