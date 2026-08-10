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

- [x] **LIB-03 — Library tests**
      Cover ownership, duplicates, target validation, archived projects, filters, and cross-user leakage.

## Phase 9 — History and comparisons

- [x] **HIST-01 — History and comparison services (BE)**
      Build compatible-run selection, metric/component deltas, new/lost entities, parameter/formula warnings, and efficient queries.

- [x] **HIST-02 — History and comparison interface (UI)**
      Add timeline/table, pair selector, delta cards, component chart, video/channel changes, compatibility warnings, and insufficient-history state.

- [x] **HIST-03 — History tests**
      Cover compatible/incompatible runs, different formulas, missing values, permissions, and delta correctness.

## Phase 10 — Exports

- [x] **EXP-01 — Export jobs and writers (BE)**
      Add CSV/XLSX writers behind a contract, user-scoped selections, queued status, metadata columns, storage, expiry, and safe deletion.

- [x] **EXP-02 — Export interface (UI)**
      Add export builder, included-record summary, format/column options, jobs table, progress, download, expiry, failure, and deletion states.

- [x] **EXP-03 — Export tests**
      Verify authorization, escaping/formulas, Unicode titles, timestamps, score metadata, large job behavior, file cleanup, and downloads.

## Phase 11 — Retention and manual snapshot deletion

- [x] **RET-01 — Retention eligibility and audit schema (DB/BE)**  
      Implement six-month cutoff, dependency-safe eligibility, dry-run preview, cleanup command/job, selective manual deletion, audit records, and idempotency. Resolve favorite-preservation policy in the decision log first.

- [x] **RET-02 — Retention and deletion interface (UI)**  
      Add Settings retention summary, eligible counts/date range, dry-run preview, cleanup progress/outcome, snapshot selection, destructive confirmation, and audit history.

- [x] **RET-03 — Retention tests**  
      Use frozen time to test boundary dates, ownership, preserved non-snapshot data, favorites policy, retries, dry run, audit output, and file deletion.

## Phase 12 — Release hardening

- [x] **REL-01 — End-to-end local workflow**  
      Verify register -> configure -> search -> analyze -> score -> save -> repeat -> compare -> export -> cleanup.

- [x] **REL-02 — Performance and database review**  
      Inspect N+1 queries, indexes, pagination, memory use, queued batch sizes, and large-result UI rendering.

- [x] **REL-03 — Accessibility and visual QA (UI)**  
      Keyboard test, focus, contrast, chart alternatives, long Romanian/Russian titles, tablet layout, dark/light theme if enabled, and screenshot review.

- [x] **REL-04 — Documentation and recovery**  
      Update setup, create backup/restore instructions for local MySQL and exports, document quota/reset behavior, and add troubleshooting.

## Phase 13 — Unified Analyzer expansion

- [x] **DOC-01 — Unified analyzer/application model and delivery plan**
      Integrate the Video Analyzer and Author Channel Analyzer specification with Search, Explore, Discover, Watchlist, and Topic Workspace before implementation. Acceptance: the canonical product, architecture, data, scoring, UI, API, testing, decision, backlog, and live-status documents agree on one reusable collection model, explicit data provenance, scope boundaries, dependencies, and vertical-slice delivery order.

- [x] **COL-01 — Shared collection runs and historical source migration**
      Add owner-scoped collection runs, generic immutable observation/source links, migration/backfill of current Research snapshots, policies, and a visible provenance/source panel on existing Research results. Preserve current score/history/export/retention inputs exactly. Acceptance: historical values, timestamps, rank, ownership, and formula inputs are unchanged; migration is reversible; loading/partial/error states and focused schema/authorization/backfill/UI tests pass; documentation status is updated.

- [x] **COL-02 — Shared capture, freshness, and Search compatibility**
      Route Search enrichment through reusable collection/provider/persistence services, add bounded cache decisions and explicit fresh/cached observation context, retain resumable jobs/quota accounting, and switch dependent Research read models to pinned sources. Acceptance: Search behavior and immutable scoring remain compatible; cached sources expose original timestamps; Force Refresh-capable contracts exist without UI duplication; provider, retry, score, history, export, discovery, retention, and visible freshness-state focused tests pass.

## Phase 14 — Analyzer MVP

- [x] **ANA-01 — Analyzer intake and source-aware Video Profile**
      Ship `/analyzer` URL/ID validation, owner-scoped immutable Analyzer runs, queued anchor video/author channel collection, category resolution, user first-seen state, and the live Video/Channel Profile UI. Add `Open in Analyzer` to Search results using the canonical video. Acceptance: supported URL forms and malicious/ambiguous inputs, authorization, cached/fresh/force-refresh behavior, progress, inaccessible/not-found, quota, partial/missing metadata, API/calculated provenance, responsive UI, and focused tests/documentation are complete.

- [x] **ANA-02 — Recent author-channel cohort and baseline**
      Resolve the uploads playlist, collect a frozen bounded recent cohort (default 30), batch video enrichment, persist cohort roles/source positions, calculate median/average/range, upload-frequency, duration, and category profiles, and render sortable/filterable Channel Profile and Recent Videos sections. Acceptance: playlist pagination, 50-ID batching, retry idempotency, unavailable playlist, deleted cohort items, nullable fields, cache/quota states, ownership, exact values, responsive tables, and focused tests/documentation are complete.

- [x] **ANA-03 — Relative performance, rank, percentile, and outliers**
      Persist versioned anchor-versus-channel median/average ratios, deterministic rank/percentile, configurable breakout class/rate, and Strong/Breakout views in the Analyzer UI. Acceptance: anchor baseline exclusion, tie/threshold/zero/insufficient-data boundaries, age and Lifetime Average Views/Day context, missing subscribers/engagement, threshold version visibility, filters, all UI states, authorization, and focused formula/feature/frontend tests pass.

- [x] **ANA-04 — Channel momentum, consistency, and observed growth**
      Freeze and implement `channel-behavior-v1`, including recent-versus-previous block momentum, robust consistency with insufficient-data rules, duration/performance observed correlations, user-scoped first seen, multi-snapshot deltas, and Growth History. Acceptance: formulas/configuration/fixtures are deterministic; Lifetime Average and Observed Recent Views/Day are distinct; no pre-first-seen history is invented; refresh/retry/partial states, accessible chart table, authorization, retention impact, and focused tests/documentation are complete.

- [x] **ANA-05 — Analyzer curation and standalone channel entry**
      Add notes/tags/research status, Favorite compatibility, direct author-channel analysis using the shared aggregate, recent analyses, and reusable actions for Watchlist/Topic Workspace handoff without implementing those modules prematurely. Acceptance: video/channel routes use one metric service, user state is isolated, duplicate submissions are safe, empty/loading/error/success states and confirmations are complete, and focused backend/frontend tests plus documentation pass.

- [x] **FIX-03 — Show resolved Analyzer video and channel names (UI)**
      Resolve Analyzer display identity from owner-authorized runs and stored canonical catalog records without provider calls. Show the video title and author channel name on Video Profile headings and Recent analyses; show the channel name for channel analyses; use explicit pending/unavailable copy with the provider ID only as secondary context when no stored identity exists. Acceptance: owner scoping, legacy/source-reused runs, queued/unavailable fallbacks, long-title wrapping, and focused backend/frontend tests pass.

- [x] **FIX-04 — Handle uploads-playlist errors in YouTube settings (BE)**
      Keep the settings connectivity guidance exhaustive after adding the Analyzer uploads-playlist provider error. Acceptance: `PlaylistUnavailable` maps to safe actionable English copy and focused PHPStan/Pint checks pass.

- [x] **FIX-05 — Synchronize the library morph-map verification (TEST)**
      Update the documented morph-map assertion for the approved Topic Workspace evidence types. Acceptance: Analyzer runs and Watchlist items remain explicitly allow-listed and the focused library schema test passes.

## Phase 15 — Connected research surfaces

- [x] **XPLR-01 — Stored-evidence Explore**
      Build the owner-scoped Explore read model and interface across Research, Analyzer, Discovery, and library evidence with filters for market/source/category/topic/performance/breakout/channel size/score/date. Add explicit Analyze, Validate, Watch, and Workspace actions. Acceptance: ordinary browsing performs zero provider requests, queries are paginated/indexed, missing metrics are honest, cross-user filters do not leak, all UI states are present, and focused query/authorization/frontend tests plus documentation pass.

- [x] **WATCH-01 — Video and channel Watchlist**
      Add owner-scoped watchlist items and queued manual refresh runs for videos/channels, using shared collection and Analyzer calculations; expose statuses, project/workspace links, notes/tags, last observation, deltas, pause/resume, refresh/retry/remove, and complete UI states. Acceptance: Favorites remain independent, refresh jobs are idempotent and quota-aware, ownership is rechecked, snapshots/retention previews are correct, and focused schema/domain/feature/frontend tests plus documentation pass.

- [x] **TOPIC-01 — Topic Workspace evidence hub**
      Add owner-scoped topic workspaces and explicit allow-listed evidence items/roles for videos, channels, queries/runs, candidates, Analyzer runs, and watchlist items. Ship list/detail/create/edit/archive flows, notes, filters, and confirmed Search/Discover launches that link resulting runs back. Acceptance: no metric payload is copied, market/language context and cross-market warnings are visible, ownership/duplicates/archived states are enforced, all UI states exist, and focused backend/frontend tests plus documentation pass.

- [x] **INT-01 — Unified Search/Explore/Discover/Analyzer/Watchlist/Workspace handoffs**
      Persist origin/return context and complete canonical entity navigation, Analyzer evidence consumption in Discover, validation-search guards, and consistent save/watch/workspace actions across all surfaces. Acceptance: no surface duplicates formulas or calls Google directly, Analyzer evidence cannot masquerade as a validated opportunity score, back/return flows preserve filters safely, quota actions are explicit, cross-user lineage is private, and a focused cross-stack workflow test plus responsive UI verification pass.

## Phase 16 — Semantic and audience depth

- [x] **SEM-01 — Versioned detected niche/topic classification**
      Add inferred-classification persistence and a deterministic provider for niche, subniche, topics, content pillars, and concentration using stored metadata; render Topic Profile with evidence, confidence, language, provider/version, and empty/partial/error states. Acceptance: official YouTube category remains separate, inference is never labeled API fact, ownership/versioning/multilingual/missing-input behavior is tested, and Search/Discover/Explore/Workspace integration plus documentation are complete.

- [x] **SEM-02 — Topic and title-pattern performance**
      Group the recent cohort by versioned detected topics and editorial title patterns, persist typed aggregates, and ship accessible performance tables/charts with counts, medians, averages, and breakout rates. Acceptance: minimum sample and mixed/unclassified handling are explicit, observed association is not causation, recalculation creates a new version, filters/exports/handoffs are consistent, and focused deterministic/domain/frontend tests plus documentation pass.

- [x] **COMM-01 — Opt-in public comments**
      Add queued paginated public-comment collection behind a provider contract, minimal retention-aware storage, and an Analyzer Comments section with count/like/reply context and disabled/empty/partial/quota/error states. Acceptance: collection is explicit, reply completeness is honest, personal public text is minimized, jobs are idempotent/owner-scoped, no API key leaks, and focused provider/retention/authorization/frontend tests plus documentation pass.

- [x] **AUD-01 — Versioned Audience Signals**
      Derive evidence-linked repeated questions, topics, entities, suggestions, complaints, and confusion points from stored comments through a deterministic baseline and optional future provider contract. Acceptance: output is inferred rather than authoritative sentiment, source comments/confidence/version are traceable, sparse/multilingual/unsafe output states are handled, and focused tests/UI/documentation are complete.

- [x] **TRN-01 — Approved transcript provider and viewer**
      Implement the approved local user-provided transcript provider for completed video Analyzer attempts, accepting pasted plain, bracket-timestamped, SRT, and VTT text behind one contract. Add immutable owner-scoped revisions, rights confirmation, retention-aware storage, searchable timestamp-linked viewer, explicit not-provided/partial/validation states, and confirmed deletion. Acceptance: no scraping, URL/media retrieval, unsupported API-key claim, or YouTube quota use; authorization/compliance/retention behavior is documented and tested; transcript absence or deletion never affects the other Analyzer results.

- [x] **TRN-02 — Transcript structure analysis**
      After TRN-01, add versioned inferred summary, topics, entities, hook, sections, CTA, questions, and script structure with evidence offsets and complete insufficient/partial/error states. Acceptance: original text and inferred output remain separate, provider/version/confidence are visible, and focused multilingual/domain/frontend tests plus documentation pass.

## Phase 17 — Advanced Analyzer research

- [x] **THMB-01 — Thumbnail pattern analysis**
      Add an optional image-analysis provider, versioned inferred features/clusters, and observed thumbnail/performance association views. Acceptance: storage/authorization/cache/retention, inaccessible images, confidence/version, no-causation copy, and focused tests/UI/documentation are complete.

- [x] **XCMP-01 — Cross-channel topic comparison**
      Compare compatible owner-scoped channel analyses and topic/title/thumbnail cohorts with explicit market, time, sample, source, and model-version warnings. Acceptance: no unsupported recommendation or score claim, missing/incompatible data is guarded, queries are bounded, exact values have accessible alternatives, and focused backend/frontend tests plus documentation pass.

- [x] **REL-05 — Integrated research expansion hardening**
      Verify the complete Search -> Analyzer -> Explore -> Watchlist -> Topic Workspace -> Discover validation -> History/Export/Retention workflow, performance, accessibility, quota behavior, migration recovery, and user isolation. Acceptance: focused cross-stack smoke, provider-fake safety, migration/rollback rehearsal, long multilingual data, tablet/desktop QA, and updated setup/recovery documentation pass.

## Phase 18 — Information hierarchy refinement

- [x] **UX-01 — Dashboard and Analyzer information hierarchy (UI)**
      Surface the most useful Analyzer, Watchlist, Topic Workspace, semantic, audience, transcript, and thumbnail signals on the owner-scoped Dashboard; reorganize Analyzer results into keyboard-accessible logical tabs; reduce profile density by approximately ten percent; simplify nonessential page-header copy; add soft-accent treatment for decision-critical video/channel values; and provide concise hover/focus explanations for important metrics. Acceptance: loading/empty/partial/error/success states remain honest, no new provider calls are introduced, tooltips are keyboard accessible, long Romanian/Russian titles and 768px/1440px layouts remain usable, owner isolation and bounded Dashboard queries are preserved, and focused backend/frontend/browser tests plus documentation pass.

- [x] **UX-02 — Recent channel videos to Video Analyzer handoff (UI)**
      On completed channel Analyzer results, expose a clear stored-cohort view for videos published in the last 90 days and a per-video Analyze action that opens the canonical Video Analyzer intake for that video. Acceptance: the 90-day boundary is timezone-independent and honest about the bounded stored cohort, no provider request is made while filtering or navigating, owner-scoped source/return context is preserved, empty and partial states remain explicit, keyboard and responsive table behavior pass, and focused backend/frontend/browser tests plus documentation are complete.

- [x] **UX-03 — Analyzer history, comment pagination, and local-result handoffs (UI)**
      Split the Analyzer intake history into distinct recent-video and recent-channel groups below the primary form; paginate stored comments without fetching or rendering the full collection at once; and replace a recent channel-video Analyze action with View data when an owner-scoped completed local video analysis exists. Acceptance: empty and mixed history states are explicit, comment pages are bounded and keyboard accessible, local-result detection is owner-scoped and selects the canonical latest completed attempt deterministically, new analysis remains available when no reusable result exists, no navigation or pagination triggers provider work, responsive layouts pass, and focused backend/frontend/browser tests plus documentation are complete.

- [x] **UX-04 — Contextual analytics glossary and paginated Analyzer history (UI)**
      Add a dismissible right-side documentation panel only to pages that display analytical results, with a page-owned list of the visible terms, each term's meaning, use, provenance, and calculation; add stored video/channel thumbnails to recent Analyzer results; and paginate each history independently with at most 10 items per page. Acceptance: glossary triggers and sheet focus behavior are accessible, each page exposes only its own analytical terms and makes no unsupported formula claims, non-analytical pages have no glossary, missing thumbnails have explicit fallbacks, history queries are owner-scoped and deterministically bounded, video/channel pagination state is independent and triggers no provider work, 768px/1440px layouts pass, and focused backend/frontend/browser tests plus documentation are complete.

- [x] **UX-05 — Ordered three-channel comparison workspace (UI)**
      Replace the repeated flat attempt selectors with an ordered, searchable channel-first picker that groups recent immutable attempts under each channel, and compare two or three distinct channels on the same page. Acceptance: at most three distinct owner-scoped completed channels can be selected, each selected channel exposes an explicit attempt and observation/sample context, duplicate-channel and foreign attempts are rejected server-side, comparison compatibility and every exact evidence table support both two and three columns, candidate/attempt queries remain deterministically bounded and provider-free, empty/loading/validation states are accessible, 768px/1440px layouts remain usable, and focused backend/frontend/browser tests plus documentation pass.

- [x] **UX-06 — Reversible Audience Signal word exclusions (UI)**
      Allow an owner to hide an unhelpful single-word Audience Signal and restore it later from a managed exclusion list. Acceptance: exclusions are normalized, owner-scoped, auditable through timestamps, and apply only when the complete normalized signal label equals the excluded word; multi-word labels containing it remain visible; adding/removing exclusions never mutates immutable signal profiles or triggers provider/comment collection work; duplicate, invalid, foreign, and missing targets are guarded; the current profile exposes filtered counts plus a reversible management UI with complete empty/loading/error/success states; 768px/1440px layouts pass; and focused persistence/domain/authorization/frontend/browser tests plus documentation are complete.

- [x] **UX-07 — Saved comment ideas (UI)**
      Let an owner save an interesting collected top-level comment as an idea, remove it after an accidental save, and browse all saved ideas on a dedicated paginated page. Each item retains the exact stored comment and canonical source-video context and exposes a safe YouTube video link. Acceptance: saves are owner-scoped and idempotent; foreign comments and saved ideas are not exposed; removal does not mutate or delete immutable comment collections; Analyzer controls and the Ideas page cover loading, empty, success, partial-source, and error feedback; list queries are bounded and deterministic; no provider request is introduced; responsive keyboard-accessible UI and focused persistence/domain/authorization/frontend tests plus documentation pass.

## Suggested milestone releases

- **M1 — Searchable:** Phases 0–4.
- **M2 — Explainable:** Phases 5–6.
- **M3 — Discoverable:** Phases 7–9.
- **M4 — Manageable:** Phases 10–12.
- **M5 — Shared observations:** Phase 13.
- **M6 — Analyzer MVP:** Phase 14.
- **M7 — Connected research:** Phase 15.
- **M8 — Semantic depth:** Phase 16, subject to the Transcript gate.
- **M9 — Advanced patterns:** Phase 17.
- **M10 — Research UX refinement:** Phase 18.
