# Implementation Backlog

## How to use this backlog

- Execute phases in order unless a task explicitly has no dependency.
- A module is complete only when its DB, backend, UI, states, authorization, and tests are complete.
- Check a box after the acceptance criteria and focused Codex checks in this file and `09_ACCEPTANCE_AND_TESTING.md` pass. Full PHP/React suites are manual-only and must be listed briefly for the user.
- Every functional group below includes an explicit visual task.
- `TASK_STATUS.md` is the live status register. At completion, mark the task complete there with focused verification evidence and a short manual-check list, then mark exactly one next unblocked task as in progress.

## Phase 0 — Foundation

- [x] **FND-01 — Adopt the Laravel 13 React starter-kit foundation**  
  Integrate official Laravel conventions for Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui, and built-in Fortify authentication into this existing repository. Preserve documentation and local configuration. Acceptance: starter page compiles, auth routes exist, types/lint/build scripts are defined.

- [x] **FND-02 — Configure local MySQL and environment**  
  Configure `nishetube`, database queue/cache/session tables, `.env.example` variables, and safe Git ignores. Acceptance: clean migrations work without a real API key.

- [x] **FND-03 — Establish module structure and shared contracts**  
  Add domain/application/provider boundaries described in Architecture without empty speculative classes. Start with contracts required by the first search slice.

- [x] **FND-04 — Create the UI design system and application shell (UI)**  
  Add brand tokens, sidebar/top bar, navigation, page container, cards, badges, tables, skeletons, empty/error/partial states, responsive behavior, and a placeholder-safe `YouTube API Today` header area. Acceptance: a component showcase or Storybook-equivalent page demonstrates states using fixtures.

- [x] **FND-05 — Establish test and CI-like local scripts**
  Configure PHP tests, formatting, TypeScript checks, lint, build, and HTTP fakes. Acceptance: documented verification suite passes locally.

## Phase 1 — Authentication and preferences

- [x] **AUTH-01 — Authentication backend and storage**  
  Registration, login, logout, password reset, profile/password settings, timezone, default market, validation, rate limiting, and policies.

- [x] **AUTH-02 — Authentication and account interface (UI)**  
  Implement polished English auth pages and account settings with loading, validation, success, and failure states.

- [x] **AUTH-03 — Authentication tests**  
  Cover successful/failed flows, ownership boundaries, disabled sessions, timezone/default-market persistence, and rate limiting.

## Phase 2 — Markets, settings, and YouTube connection

- [x] **SET-01 — Market storage and seeding (DB/BE)**  
  Add and seed `global_en`, `ro_ro`, `ru_ru`; validate frozen request mapping.

- [x] **SET-02 — YouTube provider and quota ledger (BE)**  
  Add provider DTOs, Laravel HTTP client adapter, safe configuration, quota-attempt ledger, normalized errors, pagination and batch helpers. Record every request, recompute quota-bucket estimates after each request, support current configurable buckets, and publish a safe project-wide quota summary for the UI.

- [x] **SET-03 — Integration and market settings interface (UI)**  
  Add settings sections for masked key presence, connectivity test, quota meters/disclaimer, default market, timezone, and default depth. Implement the persistent authenticated-header `YouTube API Today` widget with per-bucket remaining estimates, last call details, reset time, polling updates, and loading/stale/exhausted states.

- [x] **SET-04 — Provider/settings tests**  
  Fake all API responses; cover market mapping, pagination, invalid/missing key, quota exhaustion, partial payloads, and no key leakage.

- [x] **FIX-01 — Contain the YouTube API Today header widget (UI)**  
  Keep the market and quota controls within the authenticated header at tablet and narrow desktop widths. Acceptance: quota values remain readable without crossing the page boundary at the reported width and at 768px.

## Phase 3 — Search and run lifecycle

- [x] **SRCH-01 — Research schema and authorization (DB/BE)**  
  Add projects, queries, runs, statuses, frozen parameters, policies, and run state transitions.

- [x] **SRCH-02 — Search orchestration jobs (BE)**  
  Queue search, pagination, enrichment handoff, progress, idempotency, retry, and safe failures.

- [x] **SRCH-03 — Search creation and live run interface (UI)**  
  Add query/market/filter form, estimated search-call cost, run progress page, partial states, error guidance, and retry action.

- [x] **SRCH-04 — Search tests**  
  Cover validation, ownership, frozen parameters, state machine, job retries, duplicated delivery, and quota failure.

## Phase 4 — Video and channel analysis

- [x] **ANL-01 — Catalog and immutable snapshot schema (DB)**  
  Add videos, channels, run pivots, video snapshots, channel snapshots, constraints, and indexes.

- [x] **ANL-02 — Enrichment and derived metrics (BE)**  
  Batch enrichment, normalize nullable/hidden metrics, compute age and display metrics from stored data, and preserve collection timestamps.

- [x] **ANL-03 — Research analysis interface (UI)**  
  Add aggregate metric cards, video/channel charts, searchable tables, detail drawers, freshness labels, exact values, partial-data warnings, and responsive behavior.

- [x] **ANL-04 — Analysis tests**  
  Cover batch limits, missing statistics, deduplication, immutable refreshes, derived metrics, authorization, and query performance.

## Phase 5 — Opportunity scoring

- [x] **SCR-01 — Versioned scoring schema and engine (DB/BE)**
  Implement `niche-opportunity-v1`, five components, robust normalization, confidence, warnings, deterministic persistence, and configuration.

- [x] **SCR-02 — Score explanation interface (UI)**
  Add overall gauge, confidence badge, five component bars/cards, explanation/warning panels, formula information, and insufficient-data state.

- [x] **SCR-03 — Scoring tests**  
  Implement all fixtures listed in the scoring document, including outliers, hidden subscribers, monotonicity, confidence, and formula versioning.

## Phase 6 — Dashboard

- [x] **DASH-01 — Dashboard read models (BE)**  
  Build efficient user-scoped queries for recent runs, counts, top opportunities, trend points, quota and cleanup status.

- [x] **DASH-02 — Visual dashboard (UI)**  
  Implement summary cards, recent runs, candidate highlights, trend chart, quota indicator, cleanup notice, and primary actions with all empty/error/loading states.

- [x] **DASH-03 — Dashboard tests**  
  Cover user scoping, empty account, partial/failed runs, and aggregate correctness.

- [x] **FIX-02 — Expose YouTube links and video previews in research results (UI)**  
  Show the stored YouTube thumbnail for each video directly in the detailed results table and detail drawer, with an accessible unavailable-image fallback. Make video titles/thumbnails and channel names direct outbound links to their canonical YouTube video or channel pages, opening in a new tab with safe external-link attributes and without requiring the detail drawer. Preserve the existing searchable pagination, long-title handling, partial/empty/error states, and responsive table behavior. Acceptance: completed and partial runs expose keyboard-accessible row-level video/channel links and video previews at 1440px and 768px; missing thumbnails do not break layout; focused interface tests cover canonical URLs, link safety, preview/fallback rendering, and owner-scoped props; no additional provider request or quota usage is introduced.

## Phase 7 — Discovery

- [x] **DISC-01 — Discovery schema and deterministic engine (DB/BE)**  
  Add discovery runs/seeds/candidates, seed sampling, breakout detection, topic phrase extraction, candidate evidence, validation linkage, and provider-extension contracts.

- [x] **DISC-02 — Discovery workflow interface (UI)**  
  Add seed form, market/budget controls, progress timeline, candidate cards, evidence, filters, save/dismiss/validate actions, and no-results/quota states.

- [x] **DISC-03 — Discovery tests**  
  Cover deterministic clustering/extraction, duplicate candidates, validation runs, user ownership, retries, and limited quota.

## Phase 8 — Projects and favorites

- [x] **LIB-01 — Projects, favorites, notes, and tags (DB/BE)**  
  Implement CRUD, target allow-list, authorization, tagging, notes, archive behavior, and useful filters.

- [x] **LIB-02 — Projects and favorites interface (UI)**  
  Add project grid/list, detail tabs, favorite actions across analysis/discovery, notes/tags, filters, sorting, empty states, and confirmations.

- [ ] **LIB-03 — Library tests**  
  Cover ownership, duplicates, target validation, archived projects, filters, and cross-user leakage.

## Phase 9 — History and comparisons

- [ ] **HIST-01 — History and comparison services (BE)**  
  Build compatible-run selection, metric/component deltas, new/lost entities, parameter/formula warnings, and efficient queries.

- [ ] **HIST-02 — History and comparison interface (UI)**  
  Add timeline/table, pair selector, delta cards, component chart, video/channel changes, compatibility warnings, and insufficient-history state.

- [ ] **HIST-03 — History tests**  
  Cover compatible/incompatible runs, different formulas, missing values, permissions, and delta correctness.

## Phase 10 — Exports

- [ ] **EXP-01 — Export jobs and writers (BE)**  
  Add CSV/XLSX writers behind a contract, user-scoped selections, queued status, metadata columns, storage, expiry, and safe deletion.

- [ ] **EXP-02 — Export interface (UI)**  
  Add export builder, included-record summary, format/column options, jobs table, progress, download, expiry, failure, and deletion states.

- [ ] **EXP-03 — Export tests**  
  Verify authorization, escaping/formulas, Unicode titles, timestamps, score metadata, large job behavior, file cleanup, and downloads.

## Phase 11 — Retention and manual snapshot deletion

- [ ] **RET-01 — Retention eligibility and audit schema (DB/BE)**  
  Implement six-month cutoff, dependency-safe eligibility, dry-run preview, cleanup command/job, selective manual deletion, audit records, and idempotency. Resolve favorite-preservation policy in the decision log first.

- [ ] **RET-02 — Retention and deletion interface (UI)**  
  Add Settings retention summary, eligible counts/date range, dry-run preview, cleanup progress/outcome, snapshot selection, destructive confirmation, and audit history.

- [ ] **RET-03 — Retention tests**  
  Use frozen time to test boundary dates, ownership, preserved non-snapshot data, favorites policy, retries, dry run, audit output, and file deletion.

## Phase 12 — Release hardening

- [ ] **REL-01 — End-to-end local workflow**  
  Verify register -> configure -> search -> analyze -> score -> save -> repeat -> compare -> export -> cleanup.

- [ ] **REL-02 — Performance and database review**  
  Inspect N+1 queries, indexes, pagination, memory use, queued batch sizes, and large-result UI rendering.

- [ ] **REL-03 — Accessibility and visual QA (UI)**  
  Keyboard test, focus, contrast, chart alternatives, long Romanian/Russian titles, tablet layout, dark/light theme if enabled, and screenshot review.

- [ ] **REL-04 — Documentation and recovery**  
  Update setup, create backup/restore instructions for local MySQL and exports, document quota/reset behavior, and add troubleshooting.

## Suggested milestone releases

- **M1 — Searchable:** Phases 0–4.
- **M2 — Explainable:** Phases 5–6.
- **M3 — Discoverable:** Phases 7–9.
- **M4 — Manageable:** Phases 10–12.
