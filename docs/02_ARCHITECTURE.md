# Architecture

## 1. System context

NisheTube is a Laravel monolith with an Inertia/React frontend and MySQL storage. It runs locally in Laragon and communicates outbound with the YouTube Data API over HTTPS.

```text
Browser -> Laravel routes/controllers -> application actions -> domain services
                                             |              -> scoring engine
                                             |              -> provider contracts
                                             |                    -> YouTube Data API
                                             -> queued jobs -> MySQL
Inertia props <- resource/view models <- stored runs, snapshots, scores, quota ledger
```

Search, Analyzer, and Watchlist initiate different user workflows but share one collection boundary for public video/channel observations. Explore and Topic Workspace read and link stored evidence; they do not introduce a parallel YouTube client.

## 2. Technology baseline

- PHP 8.3 and Laravel 13.x.
- Official Laravel React starter-kit conventions: Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui, Fortify-based authentication.
- MySQL 8.4.
- Database queue and cache initially.
- Laravel HTTP client for external calls.
- Laravel scheduler/commands for retention and optional recurring work.
- A chart library may be added in the foundation phase after one comparison screen validates its need.

## 3. Backend boundaries

Use bounded modules under `app/Domain` or an equivalent consistently chosen structure:

| Domain       | Responsibility                                                                                 |
| ------------ | ---------------------------------------------------------------------------------------------- |
| `Research`   | Queries, run lifecycle, filters, orchestration, progress.                                      |
| `Collection` | Shared collection runs, snapshot/source persistence, freshness, and workflow handoff.          |
| `YouTube`    | API client, DTO normalization, pagination, quota tracking, errors.                             |
| `Catalog`    | Deduplicated videos/channels and their immutable metric snapshots.                             |
| `Analyzer`   | URL/ID intake, anchor/cohort analysis, relative performance, and versioned calculated results. |
| `Comments`   | Opt-in comment collection workflows and owner-scoped saved comment ideas.                      |
| `Scoring`    | Versioned component calculations, confidence, explanations.                                    |
| `Discovery`  | Seed exploration, anomaly detection, clustering contracts, candidates.                         |
| `Explore`    | User-scoped stored-evidence projections and filters with no implicit provider calls.           |
| `Watchlist`  | Tracked subjects, refresh policy, queued refresh history, and observation deltas.              |
| `Topics`     | Topic workspaces, typed evidence links, and cross-workflow handoffs.                           |
| `Library`    | Projects, favorites, notes, tags.                                                              |
| `History`    | Comparison queries and trend projections from stored snapshots.                                |
| `Exports`    | Export selection, generation, storage, expiration.                                             |
| `Settings`   | User preferences, market definitions, safe integration status.                                 |
| `Retention`  | Eligibility preview, deletion, audit logging.                                                  |

Controllers validate/authorize and invoke application actions. They do not contain scoring formulas, raw provider response parsing, or multi-step orchestration.

## 4. Frontend boundaries

Follow official starter-kit conventions under `resources/js`:

```text
resources/js/
  components/       shared presentational components
  features/         domain-specific components, filters, view models
  hooks/            shared UI hooks
  layouts/          authenticated and auth layouts
  lib/              formatting and UI-only utilities
  pages/            route-level Inertia pages
  types/            shared and generated TypeScript types
```

Do not reproduce domain scoring calculations in TypeScript. The backend returns component scores, explanations, confidence, and display-ready raw metrics. Frontend-only calculations are limited to presentation formatting.

## 5. Provider contracts

Define interfaces before concrete providers:

- `VideoResearchProvider`: search, fetch videos, fetch channels, fetch categories/regions.
- `ChannelUploadsProvider`: resolve/list upload playlist items through the same YouTube adapter; no controller-level playlist calls.
- `TopicExpansionProvider`: optional future query expansion.
- `ClusteringProvider`: deterministic implementation initially; AI adapter later.
- `CommentProvider`: optional, paginated public-comment collection introduced only in its own vertical slice.
- `TranscriptProvider`: optional parsing boundary. The approved `user_provided_transcript-v1` implementation normalizes owner-pasted plain, timestamped, SRT, or VTT text locally and performs no network retrieval.
- `TranscriptStructureProvider`: versioned inferred analysis over one immutable stored transcript revision. The deterministic `transcript-structure-v1` baseline performs no network or YouTube request and returns evidence-linked summary, topic, entity, hook, section, CTA, question, and script-structure results.
- `ExportWriter`: CSV and XLSX implementations.
- `QuotaLedger`: record attempts/costs and summarize local estimates.

Provider responses are normalized into internal DTOs. Raw payloads may be retained temporarily for diagnostics only if scrubbed of secrets and governed by retention.

## 6. Research run lifecycle

Suggested states:

```text
draft -> queued -> searching -> enriching -> scoring -> completed
                    |              |           |
                    +--------------+-----------+-> failed
completed/failed -> retry creates a new attempt while preserving history
```

Persist state transitions, progress counts, timestamps, and a safe error code/message. A retry must not silently duplicate snapshots or quota ledger entries.

## 7. Collection flow

1. User submits a validated search form.
2. Application creates a user-owned query and run with frozen parameters.
3. A job calls `search.list`, recording quota attempt and page tokens.
4. Video IDs are deduplicated and enriched in batches through video endpoints.
5. Channel IDs are deduplicated and enriched in batches.
6. Immutable metric snapshots are attached to the run.
7. Scoring engine calculates versioned components and confidence.
8. Run becomes completed and the Inertia UI refreshes/polls its status.

Discovery reuses the same collection pipeline rather than inventing a second YouTube client.

Analyzer and Watchlist also reuse that boundary. A `collection_run` represents fresh provider observation work, while each completed workflow pins the exact immutable snapshot IDs used for its calculations. Cached reuse preserves the original observation time; Force Refresh creates a new collection run and observations.

## 7.1 Analyzer flow

1. Validate/normalize a supported YouTube URL or ID and create an owner-scoped Analyzer run.
2. Queue anchor video, author channel, uploads-playlist, and recent-cohort collection with persisted stages.
3. Enrich at most 50 video IDs per provider call and persist resumable immutable observations.
4. Pin anchor/channel/cohort snapshot sources to the Analyzer run.
5. Calculate versioned metrics only from those stored inputs.
6. Complete with explicit partial warnings or a safe retryable failure.

Search-specific pagination/rank staging remains in Research. The shared collection layer handles canonical identity, observations, cache decisions, and quota recording.

## 8. Authentication and authorization

- Use Laravel session authentication and built-in password hashing.
- Use policies for every user-owned aggregate.
- Never trust a `user_id` received from the browser; derive it from the authenticated session.
- Route model binding must still invoke authorization.
- Export and deletion jobs re-check ownership when executing.

Roles are not needed initially. If administration is later introduced, add explicit policies rather than email-based checks.

## 9. Time and historical integrity

- Store all timestamps in UTC.
- Persist `collected_at`, `started_at`, `completed_at`, and `calculated_at` where relevant.
- Completed snapshots are immutable; a refresh creates a new snapshot/run.
- First-seen/last-fetched summaries are user-scoped pointers over immutable observations and never imply pre-observation history.
- Store scoring formula version and frozen run parameters.
- Comparison services reject or warn on incompatible markets, filters, or formulas.

## 10. Queue and scheduler behavior

- Use the database queue so the project has no Redis requirement.
- The local development command should start the web server, Vite, and queue worker together.
- Retention must also be invokable by a manual Artisan command because the PC is not always running.
- On normal app use, the UI may show that cleanup is due and let the user run it. Do not assume a 24/7 scheduler.

## 11. Error handling and observability

- Map provider errors to stable internal codes such as `quota_exhausted`, `invalid_key`, `rate_limited`, `provider_unavailable`, and `invalid_request`.
- Store safe diagnostic context and correlation IDs; never store the API key.
- UI errors include a next action: retry, update settings, reduce depth, or wait.
- Log state changes and cleanup outcomes through Laravel logging and database audit records where required.

## 12. Caching

- Cache slowly changing reference data such as regions/categories.
- Reuse recent video/channel enrichment only when the requested freshness policy allows it.
- Cache must never replace a run's immutable record of which snapshot IDs and observation timestamps were used.
- Cache keys include provider and relevant market/language dimensions.
- Shared observation policy has three explicit modes: `fresh_only`, `allow_fresh_cache`, and `force_refresh`. Reuse is owner/provider scoped, accepts only completed source collections, and uses a frozen bounded freshness window.
- Search remains `fresh_only` by default for compatibility. Shared callers may opt into cache reuse; Force Refresh always bypasses reuse while retaining the same provider and persistence boundary.
- Dependent Research projections join the pinned snapshot IDs on `research_run_videos`, never a latest snapshot or an implicit `research_run_id` match.

## 13. Cross-surface rules

- Search/Discover/Explore links open the canonical Analyzer workflow; they do not calculate video metrics themselves.
- Explore performs no provider request on page load or filter changes.
- Favorites are bookmarks; Watchlist is explicit repeated observation.
- Topic Workspace stores typed references to immutable evidence instead of copied metrics.
- Analyzer signals may support Discovery evidence but do not become a niche opportunity score without validation Search.
- Cross-channel comparison is a bounded owner-scoped read model over two or three completed Analyzer attempts for distinct channels. It creates no copied aggregate or comparison table, performs no provider request, caps the picker at 24 recent channels with five attempts per channel and each evidence family at 100 stored rows, and reports compatibility across the complete selection independently for channel, topic, title-pattern, and thumbnail versions.

See `12_UNIFIED_ANALYZER_MODEL.md` for the target relationships and staged migration.

## 14. Optional thumbnail-analysis boundary

Thumbnail analysis is an explicit owner-authorized queued action on a completed Analyzer attempt. `ThumbnailImageFetcher` accepts only configured HTTPS YouTube image hosts, disables redirects, and bounds MIME type and byte size. Bytes exist only in the worker process and are never persisted. `ThumbnailAnalysisProvider` receives that transient payload; the baseline `gd_visual_features` adapter deterministically emits versioned visual measurements, classes, a cluster key, and confidence.

Immutable profiles and items pin the Analyzer membership, canonical video, stored URL hash, provider/version, result state, and safe unavailable code. Exact owner/video/URL/provider/version matches may reuse a recent feature result within the frozen cache window. Performance aggregates use only the same Analyzer attempt's pinned `channel_recent_upload` cohort, retain exact evidence IDs, and keep metric values null below their per-metric minimum. The output is inferred observed association, does not claim causation, and never changes Analyzer metrics or opportunity scoring.

## 14. Optional thumbnail-analysis boundary

Thumbnail analysis is an explicit owner-authorized queued action on a completed Analyzer attempt. `ThumbnailImageFetcher` accepts only configured HTTPS YouTube image hosts, disables redirects, and bounds MIME type and byte size. Bytes exist only in the worker process and are never persisted. `ThumbnailAnalysisProvider` receives that transient payload; the baseline `gd_visual_features` adapter deterministically emits versioned visual measurements, classes, a cluster key, and confidence.

Immutable profiles and items pin the Analyzer membership, canonical video, stored URL hash, provider/version, result state, and safe unavailable code. Exact owner/video/URL/provider/version matches may reuse a recent feature result within the frozen cache window. Performance aggregates use only the same Analyzer attempt's pinned `channel_recent_upload` cohort, retain exact evidence IDs, and keep metric values null below their per-metric minimum. The output is inferred observed association, does not claim causation, and never changes Analyzer metrics or opportunity scoring.
