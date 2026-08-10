# Acceptance and Testing

## 1. Definition of done

A task group is done only when:

- acceptance behavior is implemented with real persistence or explicit fakes in tests;
- authenticated ownership and unauthorized access are tested;
- the UI exists and handles loading, empty, success, partial-data, and error states;
- validation and destructive confirmations are present;
- Codex-run tests and checks are strictly scoped to the current task and pass;
- the user receives a short checklist for any full PHP, React/frontend, or cross-stack verification that must be run manually;
- no secret appears in code, logs, fixtures, rendered pages, or committed files;
- related documentation and backlog checkboxes are current.

Codex must never run full or aggregate project suites. Commands such as `composer ci:check`, `composer test`, bare `php artisan test`, `npm run check`, `npm run types`, `npm run lint:check`, `npm run format:check`, and `npm run build` are manual-only. The absence of a Codex-run full suite does not prevent task completion when the focused checks and acceptance criteria pass.

At completion, Codex provides the manual checklist in commentary and its final response is exactly `TASK DONE`. A blocked or incomplete task must not use that completion response.

## 2. Test layers

### PHP unit tests

- scoring formulas, percentiles, confidence and versioning;
- provider normalization and error mapping;
- value objects, time windows, retention eligibility;
- deterministic discovery utilities;
- export cell sanitization.

### Laravel feature tests

- authentication and settings;
- user ownership/policies for every aggregate;
- Inertia responses and form validation;
- queue dispatch and run transitions;
- API fakes, quota ledger, retries and failures;
- cleanup previews/execution and audit;
- export download authorization.

### Frontend tests

Use targeted component tests for interactive/high-risk behavior such as score explanations, comparison deltas, cleanup confirmation, table filtering, and progress/error transitions. Do not duplicate Laravel tests for static markup.

### End-to-end smoke flow

Automate when practical; otherwise maintain a reproducible manual checklist for the complete local workflow.

## 3. Provider testing rules

- Automated tests never call the live YouTube API.
- Use Laravel HTTP fakes and sanitized fixtures.
- Test multiple pages, batch splitting, missing fields, non-200 responses, timeouts, malformed payloads, and partial completion.
- Assert quota usage is recorded for attempts according to current configuration.
- Assert the API key never appears in exceptions or UI props.

## 4. Authorization matrix

For each user-owned resource, test:

- owner can list/view/create/update/delete as intended;
- another authenticated user receives not-found or forbidden without resource details;
- guest is redirected to login;
- queued actions still enforce owner and current target state;
- downloads and manual cleanup cannot accept foreign IDs.

Resources include projects, queries, research/collection/Analyzer runs, discovery runs/candidates, favorites, tags, Watchlist items/refreshes, Topic Workspaces/items, exports, settings, and cleanup actions.

## 5. Data integrity tests

- A completed refresh creates a new run/snapshot and does not overwrite the previous one.
- Duplicate job delivery does not duplicate run entities or quota ledger entries incorrectly.
- Missing provider counts remain null.
- All run parameters, market mapping, formula version, and timestamps remain frozen.
- Cascade rules delete only documented dependents.
- Retention preserves users, settings, projects, saved queries, and unrelated favorites.
- Historical Research results retain the same source values, timestamps, rank, score inputs, exports, and comparison behavior after migration to shared collection/source links.
- Analyzer Refresh creates a new immutable attempt; cached reuse pins the original snapshot/time and Force Refresh creates new observations where available.
- First-seen state is user-scoped and never invents observations before NisheTube captured them.
- Shared Search enrichment persists through the collection boundary, retries receive a new collection context, and quota attempts can link directly to that context without requiring a Research-only provider contract.
- Cached Research sources are owner/provider scoped, bounded by the frozen freshness window, expose their original timestamps, and remain readable by scoring, analysis, history, export, discovery, and retention through exact pinned IDs. Force Refresh bypasses cache reuse.

## 6. Scoring acceptance

- Inputs and weights match `04_SCORING_MODEL.md` for `niche-opportunity-v1`.
- Same stored inputs produce the same stored score.
- Overall score remains within 0–100.
- All component scores and confidence remain within 0–100.
- An extreme single video cannot dominate a robust aggregate unexpectedly.
- Missing values lower confidence and create warnings.
- UI displays confidence, timestamp, formula version, and explanations with the score.

## 7. Visual acceptance

Every page is reviewed at approximately 1440px and 768px widths:

- no clipped actions or unreadable tables;
- loading skeletons do not cause severe layout shift;
- long English/Romanian/Russian titles are handled;
- numeric formatting retains exact-value access;
- focus order is logical;
- status uses text/icon in addition to color;
- charts have a textual or tabular alternative;
- destructive actions clearly name their scope.

## 7.1 Analyzer and integration acceptance

- URL parsing covers supported YouTube watch, short-link, Shorts, embed, and raw-ID inputs and rejects ambiguous hosts/IDs without provider calls.
- Analyzer progress is persisted and retry-safe across anchor, channel, playlist, cohort, calculation, and save stages.
- Provider tests cover missing/private/unavailable anchors, absent uploads playlist, hidden subscribers, missing likes/comments/tags/category/country, partial cohort pages, batch boundaries, quota exhaustion, and duplicate job delivery.
- Formula tests cover zero/one-day age, zero denominators, anchor exclusion from baseline, deterministic ties, percentile boundaries, every breakout threshold, odd/even medians, momentum blocks, upload gaps, duration buckets, and insufficient consistency data.
- UI separates API/calculated/inferred/estimated provenance and never labels lifetime-average views/day as current velocity.
- Search, Explore, Discover, Watchlist, and Topic Workspace open the same canonical Analyzer result and never implement alternative metric formulas.
- Explore filtering produces zero provider attempts; quota-consuming actions are explicit.
- Watchlist/Favorites independence and Topic Workspace evidence-link ownership are covered for owner, other user, and guest.
- Growth History shows only observed snapshots and clearly marks first seen.

## 7.2 Post-MVP acceptance gates

- Semantic/topic/title output stores algorithm/provider version, confidence, evidence, language, and `inferred` provenance.
- Topic/title-pattern performance uses only pinned recent-cohort observations, preserves explicit unclassified and mixed-language states, nulls metric claims below the frozen minimum sample, exposes exact accessible values beside its chart, and is labeled observed association rather than causation.
- Comment collection is opt-in, paginated, retention-aware, and distinguishes disabled, empty, partial, unavailable, and failed states.
- Saved comment ideas are owner-scoped and idempotent, expose reversible heart controls plus a bounded Ideas list, preserve the exact selected text and canonical video link after raw-comment retention, never delete the immutable source when unliked, and perform no provider work.
- Audience Signals persist inferred provenance, provider/version, language, confidence, and exact source-comment evidence; tests cover all six signal kinds, owner isolation, idempotency/immutability, sparse and mixed-language samples, unsafe-output withholding, failure, retention cascades, and accessible evidence disclosure. Owner-managed word exclusions are normalized and reversible, filter only an exact single-word label while preserving containing phrases, expose no foreign preference, and cause no profile mutation or provider work.
- User-provided transcripts require a completed owner-scoped video Analyzer attempt, explicit rights confirmation, immutable/idempotent revisions, searchable timestamp-aware viewing, confirmed deletion, and six-month retention coverage. Plain and partial inputs remain honest, no fixture or UI implies API-key transcript access, and transcript absence or deletion never changes existing Analyzer observations, calculations, status, or completion.
- Transcript structure analysis pins one immutable transcript revision and stores inferred provenance, provider/version, language, confidence, complete/partial/insufficient/failed state, all eight required output kinds when supported, and exact source character offsets with nullable timestamps. Original text and inferred output remain structurally and visually separate; owner isolation, idempotency, multilingual inference, safe failure, deletion/retention cascade, and accessible evidence disclosure are focused-test requirements.
- Thumbnail analysis is an explicit owner-scoped queued action; viewing the Analyzer does not trigger image-analysis collection.
- Thumbnail profiles/items/aggregates are immutable and versioned; raw image bytes are never persisted, exact reuse is owner/video/URL/provider/version scoped and time-bounded, and Analyzer retention previews/audits their cascade counts.
- Tests cover approved HTTPS hosts, redirect/type/size rejection, inaccessible/missing images, guest/foreign denial, idempotency/retry history, owner-isolated cache reuse, minimum samples, exact cohort evidence, confidence/version UI, and accessible partial/insufficient/failed states.
- Thumbnail/performance output is labeled inferred observed association, never causal, and never changes opportunity scoring.
- Cross-channel comparison accepts two or three different completed owner-scoped channel analyses, exposes no foreign attempt in its bounded 24-channel picker with at most five recent attempts per channel, and performs no provider work. Channel/topic/title/thumbnail compatibility is version-specific across the complete selection; market, source, observation-time, sample-size, missing-group, and below-minimum differences remain explicit. Exact accessible tables are capped at 100 rows per evidence family and make no score, causal, or recommendation claim.

## 8. Release smoke checklist

1. Start Laragon services and development processes.
2. Register two users and verify isolation.
3. Set default markets and timezone.
4. Test YouTube connectivity without exposing the key.
5. Run one search in each of the three markets.
6. Observe queue/progress and inspect completed video/channel data.
7. Inspect score, components, confidence, warnings, and collection time.
8. Save results to a project/favorites.
9. Run a later comparable snapshot and compare it.
10. Run discovery and validate a candidate.
11. Export CSV and XLSX with Unicode data.
12. Preview retention, manually remove a selected eligible snapshot, and inspect audit history.
13. Manually run the full verification commands; Codex must not run them.
14. Analyze a Search result, add its video/channel to Watchlist and a Topic Workspace, refresh it, then confirm Explore reuses the same canonical entity and observed history.

## 9. Performance targets for the local MVP

These are engineering targets, not external service guarantees:

- normal authenticated pages should avoid visible blocking on external API calls;
- research submission should persist/queue promptly and navigate to progress;
- tables must paginate rather than render unbounded data;
- common dashboard and history queries must avoid N+1 loading;
- batch external requests within API limits;
- scoring should run from stored data and complete quickly for configured sample sizes.
- Analyzer cohort collection batches video IDs within provider limits and renders/paginates recent videos without unbounded payloads.
- Explore reads stored user-owned projections and performs no provider I/O during ordinary browsing/filtering.
