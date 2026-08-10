# Unified Analyzer and Research Model

## 1. Purpose

This document integrates Video Analyzer and Author Channel Analyzer with the existing Search, Discover, Projects/Favorites, History, and scoring workflows, and defines the new Explore, Watchlist, and Topic Workspace surfaces before implementation begins.

The Analyzer is not a separate statistics viewer. It is the shared entity-analysis capability for every place where NisheTube exposes a YouTube video or channel.

The target model must answer five questions quickly:

1. What is this video or channel about?
2. How well is it performing on public, observable signals?
3. Is the performance unusual relative to the author's recent channel baseline?
4. Which topics, formats, and publishing patterns appear to work for the channel?
5. Which next research action should the user take in Search, Discover, Watchlist, or a Topic Workspace?

## 2. Product vocabulary and responsibilities

| Surface         | Primary intent                                                                   |                    May call YouTube automatically? | Durable output                                                           |
| --------------- | -------------------------------------------------------------------------------- | -------------------------------------------------: | ------------------------------------------------------------------------ |
| Search          | Validate an explicit query in one market                                         |                     Yes, after explicit submission | Research run, ranked results, snapshots, opportunity score               |
| Explore         | Browse the authenticated user's stored research graph                            |                                                 No | Filters and navigation only; explicit actions may start another workflow |
| Discover        | Generate evidence-backed candidate niches from stored samples and explicit seeds |  Only through an explicit validation/search action | Discovery run and niche candidates                                       |
| Analyzer        | Inspect one video or channel and its recent channel context                      |                      Yes, after Analyze or Refresh | Analyzer run, source links, calculated metrics, snapshots                |
| Watchlist       | Track selected videos, channels, or topics over time                             | Only through explicit or configured queued refresh | Watchlist item and refresh history                                       |
| Topic Workspace | Organize evidence and research around a topic                                    |                                    No on page load | User-owned workspace, evidence links, notes, linked runs                 |
| Favorites       | Bookmark items for curation                                                      |                                                 No | Favorite, project assignment, notes, and tags                            |

Explore is deliberately not a second discovery collector. It is a read surface over data the user already owns. Favorites and Watchlist are also distinct: a favorite is a bookmark, while a watchlist item opts into repeated observation.

## 3. Shared entity and collection model

### 3.1 Canonical public entities

`videos` and `channels` remain globally deduplicated by provider ID. They contain current identity metadata, not user decisions or historical metrics.

Add or normalize the public metadata needed by Analyzer when the provider supplies it:

- video description, tags, category ID, default language/audio language, caption flag, definition, licensed-content flag, region restrictions, made-for-kids state, topic categories, thumbnails, duration, and publish time;
- channel description, handle/custom URL, published time, country, default language, keywords, topic categories, uploads playlist ID, and thumbnails;
- `video_categories` as cached provider reference data keyed by provider, category ID, region, and display language.

Missing provider fields remain null. Identity metadata may be refreshed, but historical statistics are never overwritten.

### 3.2 Collection runs

Introduce `collection_runs` as the shared provider-observation envelope. It is user-owned and records:

- public ID, user ID, provider, and kind;
- kind values such as `search_enrichment`, `video_analysis`, `channel_analysis`, and `watchlist_refresh`;
- frozen request/cache parameters and requested parts;
- queued lifecycle, progress, safe warnings/errors, attempt number, and timestamps;
- optional links from the initiating Research, Analyzer, or Watchlist workflow;
- quota context through the existing API usage ledger.

Search staging remains owned by `research_runs`, because query pagination and result rank are Search-specific. Public metric capture moves behind the collection boundary so Search, Analyzer, and Watchlist use the same provider DTOs, persistence actions, cache policy, and snapshot services.

### 3.3 Immutable observations and source links

`video_snapshots` and `channel_snapshots` become immutable provider observations attached to a `collection_run`, rather than being intrinsically limited to a research run.

Workflow-specific source-link tables pin the exact observations used by a completed result:

- a Research result links its video/channel snapshot and preserves search rank/page;
- an Analyzer run links its anchor video, author channel, and recent-channel cohort snapshots;
- a Watchlist refresh links the new snapshots captured for the watched subject.

When cache is allowed, a workflow may link an existing sufficiently fresh snapshot. It must expose the original observation time and must not pretend that cached data was recollected. Force Refresh always creates a new collection run and new observations where the provider returns data.

This model preserves immutable historical inputs without duplicating API logic or requiring every observation to belong to Search.

### 3.4 Collection membership roles

The reusable membership model identifies why an entity is present:

- `search_result`;
- `analysis_anchor`;
- `author_channel`;
- `channel_recent_upload`;
- `discovery_evidence`;
- `watchlist_subject`.

Role, source position, playlist position, result rank, and source timestamps are typed where they affect behavior. Evolving provider diagnostics may use JSON, but core filtering fields must not be hidden in JSON.

## 4. Analyzer aggregate

### 4.1 Analyzer runs

`analyzer_runs` is the user-owned, immutable analysis attempt. It contains:

- public ID and user ID;
- target kind: `video` or `channel`;
- anchor video ID when the target is a video;
- author/target channel ID;
- collection run ID when fresh provider work occurred;
- frozen recent-video limit, cache mode, threshold configuration version, and calculation version;
- origin context such as manual URL, Search, Explore, Discover, Watchlist, or Topic Workspace;
- lifecycle, progress stages, safe warnings/errors, and timestamps.

Analyzer intake accepts YouTube video URLs/IDs and canonical channel URLs/IDs. Both target kinds create the same immutable Analyzer aggregate, pin an exact author/target channel snapshot, and reuse the shared cohort and channel-metric service; channel-only runs simply omit video-specific metrics.

Analyze and Refresh create new attempts. A completed run is immutable. The Analyzer landing page may redirect to a sufficiently fresh completed run only when the cache policy permits it and must offer Force Refresh.

### 4.2 Analyzer results

Persist calculated output separately from API observations:

- `video_analysis_metrics` for video age, lifetime average views/day, public engagement proxies, relative performance, rank, percentile, and breakout classification;
- `channel_analysis_metrics` for recent baseline, duration profile, upload frequency, momentum, breakout rate, consistency, and channel size;
- versioned warnings and input summaries for missing values or insufficient cohorts;
- later semantic classification tables for inferred niche/topic/title-pattern output.

The tables use typed columns for metrics that are filtered, sorted, compared, or exported. JSON is reserved for versioned explanations, distributions, and non-critical evolving metadata.

### 4.3 User observation state

Add user-owned entity observation state for videos and channels:

- `first_seen_at` and the first observed public count;
- `last_seen_at`;
- `last_fetched_at`;
- first and latest snapshot references.

This state is scoped by user so one local user's activity is not exposed to another. It is a mutable pointer/summary over immutable snapshots, never a replacement for them.

User curation is stored separately by owner and canonical video/channel subject. Private notes and research status remain available without changing immutable attempts. Favorites and tags reuse Library storage; disabled Watchlist and Topic Workspace actions expose only future handoff references and create no premature records.

## 5. Data provenance

Every exposed value belongs to exactly one provenance class:

| Provenance   | Meaning                                                                 | Examples                                              |
| ------------ | ----------------------------------------------------------------------- | ----------------------------------------------------- |
| `api`        | Public field returned by the provider                                   | views, likes, title, category ID, subscribers         |
| `calculated` | Deterministic value computed from stored observations                   | lifetime views/day, median ratio, upload frequency    |
| `inferred`   | Semantic classification produced by a versioned rule or model           | niche, subniche, topic, content pillar, title pattern |
| `estimated`  | Explicit approximation not directly measured or deterministically known | future projections only; not part of Analyzer MVP     |

Provenance is structural: API observations, calculated result tables, and inferred classification tables are separate. UI read models also emit provenance labels and source timestamps. Do not create a generic EAV table for all metrics.

The UI labels the groups `YouTube Data`, `Calculated Metrics`, and `Detected Analysis`. Any future estimate must be visually named `Estimate`.

## 6. Analyzer collection flow

1. Parse and normalize an allowed YouTube video URL or ID server-side.
2. Create an owner-scoped Analyzer run immediately with frozen parameters.
3. Queue collection and show persisted progress stages.
4. Fetch the anchor video using the shared video provider boundary.
5. Fetch the author channel, including its uploads playlist ID.
6. Read up to the configured recent-video limit from the uploads playlist, with provider pagination as needed.
7. Enrich recent video IDs in batches of up to 50.
8. Persist canonical identities and immutable observations atomically per resumable batch.
9. Pin the exact anchor, channel, and cohort sources to the Analyzer run.
10. Calculate versioned video/channel metrics from stored observations only.
11. Update user first-seen/last-fetched pointers.
12. Complete with warnings when useful partial data exists; otherwise store a retryable safe failure.

Default recent-video limit is 30. The limit, cache lifetime, channel-size thresholds, cohort sizes, and breakout thresholds live in versioned configuration and are frozen on each run.

## 7. Required calculated metrics

### 7.1 Video metrics

- `age_days = max(floor(observed_at - published_at), 0)`;
- `lifetime_average_views_per_day = views / max(age_days, 1)`;
- `views_to_subscribers_ratio = views / subscribers`, null when subscribers are missing or zero;
- `like_rate_percent = likes / views * 100` and `likes_per_1000_views`;
- `comment_rate_percent = comments / views * 100` and `comments_per_1000_views`;
- `public_engagement_proxy_percent = (likes + comments) / views * 100` when all required values exist.

The product must always use the exact label `Lifetime Average Views/Day`; it is not current velocity. The engagement calculation must be named `Public Engagement Proxy`; it is not an official YouTube engagement rate.

### 7.2 Recent channel baseline

From the valid recent cohort calculate count, median/average/min/max views, median likes/comments, median/average/min/max duration, median video age, average/median days between uploads, videos per week/month, and longest recent upload gap.

Median is the primary performance baseline. Average is supporting context. When the anchor appears in the recent cohort, the relative-performance baseline excludes the anchor to prevent self-influence, while rank and percentile include it.

### 7.3 Relative performance and outliers

- `video_vs_channel_median = anchor views / baseline median views`;
- `video_vs_channel_average = anchor views / baseline average views`;
- recent rank orders the cohort by views with deterministic tie handling;
- percentile reports the anchor's empirical position in the cohort.

Initial configurable raw-view classes are:

| Ratio to recent median | Class          |
| ---------------------: | -------------- |
|               `< 0.5x` | Underperformer |
|           `0.5x–<1.5x` | Normal         |
|             `1.5x–<3x` | Above Average  |
|                `3x–5x` | Strong         |
|                  `>5x` | Breakout       |

The UI also shows age and lifetime-average views/day beside this classification because raw accumulated views favor older uploads. Threshold version and cohort coverage are always visible.

### 7.4 Channel behavior

- breakout rate supports the configured `>5x` definition and a visible supporting `>=3x` share;
- recent momentum compares the configured recent block with the preceding block and shows the exact input metric and cohort sizes;
- duration distribution uses `<5`, `5–10`, `10–20`, `20–40`, and `40+` minute buckets;
- duration-versus-performance is labeled observed correlation, never causation;
- consistency uses a versioned robust dispersion implementation and must return `insufficient_data` rather than inventing a label for a small or zero-median cohort.

The first implementation task for consistency must freeze its exact formula and fixtures before releasing a named version.

### 7.5 Snapshot growth

With two or more comparable observations calculate gained views/likes/comments, elapsed-time-normalized observed recent views/day, and growth percentage where the earlier denominator is positive.

`Observed Recent Views/Day` is distinct from `Lifetime Average Views/Day`. History begins at NisheTube's `first_seen_at`; the application never invents the period between YouTube publication and first observation.

## 8. Integration contracts

### 8.1 Search

- Every stored result exposes `Open in Analyzer` using the canonical video identity.
- Search and Analyzer share provider DTOs, snapshot persistence, provenance, and calculation services.
- Analyzer does not mutate the completed Search run or its score.
- Analyzer may start a new prefilled Search only after user confirmation; it creates a normal immutable research run.

### 8.2 Explore

- Explore queries the authenticated user's stored Research results, Analyzer results, candidates, watchlist state, and workspace links.
- Initial filters include market, entity type, source workflow, topic/category, breakout class, score/confidence, channel size, observed date, and watch/workspace state where data exists.
- Explore page loads never call YouTube. Analyze, Refresh, Validate, and Watch actions are explicit and quota-aware.

### 8.3 Discover

- Discovery may consume completed, owner-scoped Analyzer signals as evidence, including channel-relative breakouts and later inferred topics.
- Analyzer evidence does not become a niche opportunity score by itself. A candidate still requires the normal validation Search to claim research-level evidence.
- Candidate evidence links open the same Analyzer route rather than rendering a separate statistics implementation.

### 8.4 Watchlist

- `watchlist_items` are user-owned and target an explicit allow-list of video, channel, or detected topic/workspace topic.
- Store status, optional project/workspace, refresh policy, active flag, last/next refresh pointers, notes, and timestamps.
- `watchlist_refresh_runs` are queued, idempotent, owner-rechecked, quota-aware, and link to collection runs/snapshots.
- MVP supports manual refresh. Unattended schedules are added only with explicit settings and local-computer availability guidance.
- Favorites do not automatically become watched, and Watchlist does not silently favorite an item.

### 8.5 Topic Workspace

- `topic_workspaces` belong to a user and optionally a Research Project; they freeze a primary market/language context but may label cross-market evidence explicitly.
- `topic_workspace_items` use an explicit allow-list for videos, channels, queries, research runs, discovery candidates, Analyzer runs, and watchlist items.
- Each item records a role such as example, outlier, competitor, inspiration, counterexample, or evidence.
- Workspaces link to canonical entities and immutable runs; they do not copy metric payloads.
- A workspace can launch prefilled Search or Discover flows and receive their resulting run links.

## 9. Semantic, comments, transcripts, and thumbnails

The Analyzer MVP is the scope listed in section 42 of the supplied specification: public video/channel data, the recent cohort, relative performance, channel behavior, snapshots, and complete UI states. Comments, semantic detection, transcripts, and thumbnails are post-MVP capabilities.

### 9.1 Semantic analysis (V1.1)

Persist versioned inferred classifications with label, normalized key, kind, confidence, evidence references, algorithm/provider version, language, and calculated time. General niche, subniche, topics, content pillars, niche concentration, topic performance, and title-pattern performance must remain visibly inferred.

Deterministic extraction is the default provider. Optional AI adapters implement the existing classification/expansion contracts and may not become mandatory for core Analyzer completion.

### 9.2 Comments and Audience Signals (V1.1)

Comment retrieval is a separate queued, paginated, opt-in collection because it adds quota and personal public text. Store only fields required for the research use case and apply retention. `comments_disabled`, unavailable, empty, partial, and failed are distinct states.

Audience Signals is a versioned inferred layer over stored comments: repeated questions, topics, entities, suggestions, complaints, and confusion points. It is not presented as generic or authoritative sentiment.

The deterministic `audience-comment-terms-v1` baseline requires repeated evidence, records exact source-comment links and confidence, excludes unsafe or identifying output, and exposes sparse, mixed-language/partial, unsafe, failed, and complete states. It runs locally from a pinned comment collection without additional YouTube requests; future adapters implement the same provider contract and create a new immutable version.

### 9.3 Transcripts (optional V1.1)

YouTube Data API caption listing/download requires authorized access and does not provide a general public transcript path for arbitrary third-party videos. D-032 therefore approves only the local `user_provided_transcript-v1` boundary: the authenticated owner may paste plain, bracket-timestamped, SRT, or VTT text for the exact completed video Analyzer attempt after confirming the right to use it. NisheTube does not retrieve captions/audio, scrape a page, or spend YouTube quota for this workflow.

The stored revision is `available` or `partial`; no document is the normal `not_provided` UI state. Text and ordered nullable time offsets remain separate from later inferred structure. Revisions are immutable, owner-scoped, searchable, independently deletable, and retention-aware. Missing, invalid, or deleted transcript evidence never changes the Analyzer's public observations, calculated metrics, semantic results, score boundaries, or completed status.

TRN-02 adds the local deterministic `transcript-structure-v1` provider over one exact stored revision. Its immutable inferred profile contains summary, topics, entities, hook, sections, calls to action, questions, and script structure. Every insight pins normalized-text character offsets and optional timestamps; original text is never copied into or replaced by inferred output. The action consumes no YouTube quota and preserves explicit not-analyzed, insufficient, partial, failed, and complete states.

### 9.4 Thumbnail analysis (V1.2)

THMB-01 adds an explicit queued local analysis over the thumbnail URLs already pinned through the completed Analyzer cohort. The `ThumbnailImageFetcher` boundary permits only configured HTTPS YouTube image hosts, disables redirects, bounds MIME/size, and passes bytes transiently to the versioned `gd_visual_features` provider; raw images are never stored.

Each immutable owner-scoped attempt records provider/feature/association versions, confidence, availability, cache provenance, and safe per-image errors. The deterministic baseline measures dimensions/aspect, brightness, saturation, contrast, edge density, dominant color family, inferred classes, and a stable cluster key. Exact recent owner/video/URL/provider/version results may be reused only inside the frozen cache window.

Cluster performance associations use only pinned recent-upload memberships, retain exact evidence IDs, and null metric claims below the frozen minimum sample. Missing/inaccessible/invalid images, partial coverage, insufficient clusters, failure, and retry are distinct visible states. Every output is labeled inferred observed association rather than causation, uses no YouTube Data API quota, and never affects Analyzer metrics or opportunity scoring.

## 10. UI information architecture

Primary navigation expands to:

1. Dashboard
2. Search
3. Explore
4. Discover
5. Analyzer
6. Watchlist
7. Topic Workspaces
8. Projects
9. Favorites
10. History
11. Exports
12. Settings

Conceptual routes:

- `GET /analyzer` — URL/ID intake and recent analyses;
- `POST /analyzer` — validate, create, and queue an immutable run;
- `GET /analyzer/{analyzerRun}` — live/result page;
- `POST /analyzer/{analyzerRun}/refresh` — normal or forced refresh as a new run;
- `GET /analyzer/compare` — bounded owner-scoped comparison of two completed channel attempts;
- `GET /explore` — stored knowledge explorer;
- resource routes for Watchlist and Topic Workspaces with policies.

Analyzer result order:

1. compact Video Profile and next actions;
2. Video Performance;
3. Author Channel Profile;
4. Recent Videos;
5. Outliers;
6. Topic Profile and Topic Performance when available;
7. Growth History;
8. Comments/Audience Signals when implemented;
9. Transcript status/content when implemented;
10. Thumbnail patterns when explicitly analyzed;
11. Notes, tags, status, Watchlist, and Topic Workspace actions.

Every provenance group shows observation/calculation time, missing-data warnings, and exact values. A score or classification never appears without its version/context.

Required states include initial, queued/loading with real stages, cached-result, refreshing, success, partial data, empty subsections, retryable error, quota exhausted, inaccessible/private/not-found video, and unauthorized/not-found without leakage.

## 11. Authorization, retention, and safety

- Analyzer runs, collection runs, source links, user observation state, Watchlist, Topic Workspaces, notes, and inferred results are user-owned and policy-protected.
- Canonical public video/channel identity rows may be shared, but another user's runs, first-seen state, notes, watches, workspaces, or inference history are never exposed.
- Queued jobs re-check owner and current aggregate state.
- Analyzer and Watchlist observations follow the six-month snapshot retention policy; favorited or explicitly preserved evidence follows D-016 semantics.
- A workspace/watchlist link affected by cleanup must be surfaced in preview. Cleanup removes eligible observations, not canonical entities or user organization by accident.
- No API keys, raw sensitive provider messages, or unapproved transcript extraction appear in storage, logs, fixtures, screenshots, or documentation.

NisheTube never claims third-party channel watch time, average view duration, retention, impressions, CTR, traffic sources, returning/unique viewers, exact subscriber gain per video, RPM, CPM, revenue, shares, or saves.

## 12. Migration strategy

The existing completed MVP is migrated without rewriting history in one destructive step:

1. create collection runs and generic source links alongside current Research relations;
2. backfill each historical research run into a collection context and link existing immutable snapshots;
3. make Search enrichment write through the shared capture service while preserving current read behavior;
4. switch scoring, analysis, history, export, discovery, and retention reads to pinned source links with focused compatibility tests;
5. add Analyzer on the shared boundary;
6. remove obsolete Research-only snapshot foreign keys only in a later explicit migration after parity and rollback checks.

No migration may discard or rewrite snapshot values, collected timestamps, formula versions, search rank, ownership, favorite preservation, or audit history.

## 13. Release slices

- **M5 — Shared observations:** collection boundary, provenance, safe migration, unchanged Search behavior.
- **M6 — Analyzer MVP:** intake, source-aware profiles, recent cohort, relative performance, behavior, snapshots, and complete UI.
- **M7 — Connected research:** Explore, Watchlist, Topic Workspace, and cross-surface handoffs.
- **M8 — Semantic depth:** topic/title analysis, comments/Audience Signals, and only approved transcript capability.
- **M9 — Advanced patterns:** thumbnails, cross-channel comparisons, and recommendation experiments.
- Cross-channel comparison remains an evidence inspection surface; recommendation experiments are still deferred and cannot reuse the comparison as a score.

Each backlog task is a smallest complete vertical slice: storage changes, domain logic, provider boundary when needed, authorization, visible UI/states, focused tests, and documentation status ship together.
