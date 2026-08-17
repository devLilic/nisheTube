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

## Phase 19 — Decision foundation

Detailed scope and traceability for Phases 19–22 are defined in `13_DECISION_WORKFLOW_REDESIGN.md`.

- [x] **IA-01 — Task-oriented navigation and persistent research context**
      Group production navigation into Research, Library, and Tools; expose Discover themes, Validate a niche, Compare niches, and Shortlist as decision-oriented destinations; hide the UI showcase outside development; preserve the active market/project/workspace safely across pages. Acceptance: owner-scoped context, incompatible/archived fallbacks, active-page and keyboard behavior, zero provider calls, complete desktop states, focused frontend/backend tests, and documentation pass.

- [x] **BRAND-01 — NisheTube identity mark refresh (UI)**
      Replace the generic play-button mark with a distinctive code-native NisheTube logo that combines video and niche-research cues, remains recognizable at compact sidebar/auth sizes, works in light and dark themes, preserves accessible product naming, and introduces no raster or remote asset dependency. Acceptance: consistent shell/auth usage, scalable SVG semantics, desktop visual QA, focused frontend tests, and documentation pass.

- [x] **IA-02 — Global research bar, stored-data search, and compact quota**
      Add a working market selector, active project/workspace context, bounded owner-scoped search across themes/videos/channels/runs, Shortlist access, completed-run notifications, and a compact quota popover with correct request/unit vocabulary and configuration-backed costs. Acceptance: persistence, owner isolation, debounce/bounds, read state, quota states, accessible search/popover behavior, no provider calls from navigation/search, focused tests, and documentation pass.

- [x] **SRCH-05 — Discover-market and validate-idea intake**
      Split Search into Discover a market and Validate my idea; add market/language/format/period/channel-size discovery inputs, validation presets, collapsed advanced filters, exact preflight parameters/cost, observed-demand guidance, and safe single submission. Acceptance: frozen validated inputs, preserved values, idempotency, `Creating run`, no false post-create validation errors, complete states, quota/ownership coverage, responsive UI, focused tests, and documentation pass.

- [x] **RSLT-01 — Research decision summary and lifecycle clarity**
      Lead Research results with verdict, opportunity, confidence, field-level completeness, stability, freshness/sample context, deterministic interpretation, principal evidence, risks, and one next action; clarify active progress and terminal complete/partial/reduced-confidence/failed states. Acceptance: no contradictory labels or unsupported ETA precision, partial data is preserved, all states are accessible and owner-scoped, viewing causes no provider calls, focused tests, and documentation pass.

- [x] **RSLT-02 — Research evidence inspection and actions**
      Add compact expandable components, evidence sorts/quick filters, one primary decision CTA, secondary handoffs, and a progressive provenance drawer for provider/query/parameters/window/cache/formula/snapshot details. Acceptance: server-bounded queries, exact accessible values, authorized handoffs and return context, no duplicate provider work, long-title/table responsiveness, focused tests, and documentation pass.

- [x] **DISC-04 — Discovery candidate evidence quality and multilingual normalization**
      Version candidate scoring around multi-video/channel proof, semantic coherence, robust typical performance, relevance, stability, outlier resistance, and English/Romanian/Russian normalization; classify below-threshold output as Weak phrase signals. Acceptance: at least three videos/two channels for a valid candidate, no one-video perfect score, intelligible labels/suggested queries, immutable evidence/versioning, owner isolation, complete states, focused formula/authorization/UI tests, and documentation pass.

- [x] **DISC-05 — Compact Discover decision table**
      Replace oversized candidate cards with a paginated evidence table and accessible expanded detail; keep Validate primary and organization actions secondary; visibly separate weak phrases. Acceptance: deterministic sorting/bounds, exact evidence alternatives, no provider calls on inspection, complete states, owner isolation, desktop usability at approximately 1440px, focused tests, and documentation pass.

- [x] **SCR-04 — Relevance, format, outlier, and stability evidence**
      Persist versioned per-result relevance, strict/related/weak/off-topic classes, separate Shorts/long-form evidence, robust outlier-resistant statistics, and compatible-snapshot stability. Acceptance: immutable/null-safe inputs, minimum-sample and compatibility rules, no direct cross-format claims, full-versus-strict outputs, focused edge fixtures and accessible UI evidence, and documentation pass.

- [ ] **SCR-05 — Opportunity and confidence v2**
      Add `niche-opportunity-v2` and `confidence-v2` using improved competition, reachability, creator-viability, observed-activity/momentum, freshness-gap, evidence-independence, stability, relevance, format, and outlier signals without overwriting v1. Acceptance: frozen versioned inputs/configuration, strict/full sample views, explicit confidence reductions and inferred provenance, deterministic tests/bounds, historical compatibility warnings, complete UI states, and documentation pass.

- [ ] **PROF-01 — Estimated profitability fit**
      Add an explainable estimated profitability-fit model separate from Opportunity, covering advertiser/affiliate/sponsor fit, production/access/copyright/seasonality risk, repeatability, format suitability, and user assumptions. Acceptance: owner-scoped versioned inputs, editable assumptions without historical score mutation, explicit estimated/unknown provenance, no CPM/revenue guarantee, comparison support, complete UI/tests, and documentation pass.

## Phase 20 — Decision workspaces

- [ ] **DASH-04 — Decision Cockpit**
      Lead Dashboard with one recommended next action, three to five top opportunities, Shortlist status, and Watchlist alerts; move runs/errors/quota/system/cleanup below and remove coming-soon/actionless technical cards. Acceptance: deterministic priority, bounded N+1-safe owner queries, no provider work, complete states, responsive keyboard UI, focused tests, and documentation pass.

- [ ] **SHORT-01 — Shortlist and niche comparison**
      Add an owner-scoped Shortlist decision surface and compare two to five candidates across opportunity, confidence, stability, relevance, components, profitability fit, risks, validation, and next step, with explained Go/Validate further/Monitor/Avoid verdicts. Acceptance: authorized/idempotent persistence, immutable evidence links, compatibility warnings, bounded provider-free comparisons, complete accessible states, focused tests, and documentation pass.

- [ ] **XPLR-02 — Explore productivity and score clarity**
      Simplify primary/advanced filters, add quick and owner-saved presets, table/card views, entity-specific fields, correct Parent niche score labeling, preserved filters/scroll, and bulk-selection hooks. Acceptance: server pagination, owner-scoped saved state, deterministic return context, honest nulls, zero provider calls, accessible responsive UI, focused tests, and documentation pass.

- [ ] **ANA-06 — Analyzer decision hierarchy and topic-quality guardrails**
      Reorganize Analyzer into Summary, Content patterns, Channel, and Raw data; make Shortlist/Workspace actions persistent; translate jargon while retaining tooltips/raw values; enforce semantic confidence/frequency/synonym/topic-versus-title distinctions. Acceptance: no duplicated formulas/provider work, complete accessible states, visible provenance/versioning, multilingual/responsive behavior, focused tests, and documentation pass.

- [ ] **XCMP-02 — Peer-aware comparison of up to four channels**
      After updating the decision log, extend comparison to two through four channels with peer groups, subscriber/performance/cadence/Shorts/niche/growth/freshness metrics, comparability warnings, accessible winners, and evidence-based competitor labels. Acceptance: owner authorization, deterministic bounds, null/version/sample guards, no provider work or unsupported score claim, responsive exact tables, focused tests, and documentation pass.

- [ ] **HIST-04 — Searchable history and direct snapshot comparison**
      Add bounded history search/filters, readable parameters, direct two-run selection, expanded score/evidence/stability deltas, and confirmed Repeat with same parameters when no compatible snapshot exists. Acceptance: owner isolation, pair/version validation, null-safe deltas, no provider call before confirmation, duplicate-submit prevention, complete states, focused tests, and documentation pass.

- [ ] **TOPIC-02 — Topic Workspace decision canvas**
      Add hypothesis/audience/market/opportunity/evidence-for-and-against/competitor/outlier/counterexample/angle/monetization/risk/decision/next-step fields, six decision statuses, grouped metric summaries, bulk evidence, notes/tags, and workflow history. Acceptance: owner policies, cross-market confirmation, immutable references without copied mutable metrics, bounded history, complete states, focused tests, and documentation pass.

- [ ] **WATCH-02 — Watchlist alerts and monitoring controls**
      Add compact change/refresh/workspace context, versioned alerts for performance/growth/breakout/score/competitor/topic/staleness, bulk refresh, pause, filters, largest-change sort, and notification-center integration. Acceptance: owner isolation, observation-only deltas, quota-confirmed idempotent refreshes, honest local-worker scheduling, complete states, focused tests, and documentation pass.

- [ ] **LIB-04 — Project and idea decision context**
      Extend Projects with purpose/market/themes/Shortlist/workspaces/status/activity/decisions and Ideas with optional topic/candidate/video/workspace/format/audience/status context while preserving saved-comment source semantics. Acceptance: owner-scoped optional relations, incompatible/foreign rejection, retention safety, bounded queries, complete UI states, focused tests, and documentation pass.

## Phase 21 — Operational productivity and presentation

- [ ] **EXP-04 — Selection-aware exports**
      Export filtered rows, explicit selections, Shortlists, comparisons, and workspaces with optional technical details and mandatory dataset confirmation. Acceptance: frozen owner-scoped manifests, queued authorization, Unicode/formula safety, bounded generation, complete job/download/expiry states, focused tests, and documentation pass.

- [ ] **SET-05 — Consolidated preferences and formatting**
      Regroup Settings and add default period, peer group, Shorts handling, numeric formatting, and notification preferences while keeping the interface English-only and adding no UI-language selector. Acceptance: validated owner preferences, backward-compatible defaults, UTC/timezone and exact-number behavior, no secret exposure, complete states, focused tests, and documentation pass.

- [ ] **LAND-01 — NisheTube landing and authentication entry**
      Replace the default Laravel landing page with accurate NisheTube purpose/data/limits/privacy/local-install copy and Login/Register actions; redirect authenticated users to Dashboard. Acceptance: loopback registration rules, guest/auth redirects, no framework/deployment marketing or secret leakage, accessible responsive UI, focused tests, and documentation pass.

- [ ] **JOB-01 — Controlled background jobs and notifications**
      Add safe failure reasons, preserved partial data, explicitly estimated ETA, pre-start cancel, controlled retry, meaningful completion/failure notifications, and a worker-not-running indicator without accidental quota duplication. Acceptance: guarded idempotent state transitions, ownership rechecks, quota-ledger integrity, notification read state, complete UI/tests, and documentation pass.

- [ ] **PERF-02 — Stored-interface performance and navigation continuity**
      Measure and apply server pagination, justified virtualization, lazy thumbnails, debounce, safe filter caching, skeletons, partial reloads, scroll restoration, prefetch, and sticky tables to redesigned surfaces. Acceptance: documented per-surface budgets, bounded payload/memory/query counts, no N+1/cache/accessibility regressions, focused performance/frontend tests, and documentation pass.

- [ ] **BULK-01 — Cross-surface bulk actions**
      Add a bounded shared selection model and valid Workspace, Shortlist/Favorite, Dismiss, Export, Compare, and Watch actions with explicit confirmation for destructive or quota-consuming work. Acceptance: per-target owner authorization, bounds/idempotency, mixed/foreign/partial outcomes, quota preview, accessible keyboard selection, focused backend/frontend tests, and documentation pass.

- [ ] **A11Y-02 — Terminology, accessibility, density, and desktop hardening**
      Standardize English decision terminology, move jargon to progressive help, audit WCAG-oriented contrast/focus/keyboard/labels/status/chart/table/zoom behavior, and implement desktop density rules; tablet and mobile-specific work is excluded. Acceptance: 200% zoom, long EN/RO/RU evidence text, desktop QA at approximately 1440px, non-color/exact alternatives, focused tests, and documentation pass.

## Phase 22 — Decision workflow hardening

- [ ] **QA-02 — Decision-workflow acceptance and regression suite**
      Add the focused calculation, Discover, UI, and cross-stack cases in `13_DECISION_WORKFLOW_REDESIGN.md`, then verify Discover -> select -> validate -> compare -> decide -> monitor. Acceptance: theme-to-verdict takes at most five meaningful steps; first-view decision hierarchy is complete; weak candidates cannot overclaim; opportunity and profitability remain distinct; v1 history remains immutable; quota vocabulary/configuration and production navigation are correct; focused cross-stack/accessibility/documentation checks pass.

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
- **M11 — Decision foundation:** Phase 19.
- **M12 — Decision workspaces:** Phase 20.
- **M13 — Operational productivity:** Phase 21.
- **M14 — Decision workflow hardening:** Phase 22.
