# Data Model

## 1. Principles

- Use numeric primary keys unless a public UUID is needed for URLs or queued references.
- Public YouTube entities are deduplicated by provider ID.
- Research runs and snapshots preserve historical values and are not overwritten by refreshes.
- User-owned aggregates always contain `user_id` and indexed ownership paths.
- Use JSON only for frozen provider parameters, explanations, or evolving metadata; important query/filter fields remain typed columns.
- Separate API observations, calculated metrics, inferred classifications, and estimates structurally.
- Pin every completed workflow to the exact immutable observations used in its output.

## 2. Core tables

### `users`

Standard Laravel user fields plus:

- `timezone` default `Europe/Chisinau`;
- `default_market_key` nullable;
- timestamps.

### `markets`

- `id`, `key` unique, `name`;
- `region_code` nullable;
- `relevance_language`;
- `is_enabled`, `sort_order`;
- timestamps.

Seed `global_en`, `ro_ro`, and `ru_ru`. Runs copy the effective market parameters so later edits do not rewrite history.

### `research_projects`

- `id`, `public_id` UUID, `user_id`;
- `name`, `description` nullable, `color` nullable;
- timestamps, optional `archived_at`.

Index `(user_id, updated_at)`.

### `research_queries`

- `id`, `public_id`, `user_id`, optional `research_project_id`;
- `query_text`, normalized `query_key`;
- `market_id`;
- defaults/filters such as order, published window, duration, category;
- timestamps.

Unique constraints should not prevent a user from intentionally saving similar queries; use indexes for lookup.

### `research_runs`

- `id`, `public_id`, `user_id`, `research_query_id`;
- `kind`: `search` or `discovery_validation`;
- `status`, `attempt_number`;
- frozen `query_text`, `market_key`, `region_code`, `relevance_language`;
- frozen `parameters` JSON;
- `requested_result_count`, `collected_result_count`, `enriched_result_count`;
- `progress_percent`;
- nullable `collection_warnings` JSON containing safe, user-facing partial-collection warnings;
- `started_at`, `search_completed_at`, `completed_at`, `failed_at`;
- safe `error_code`, `error_message`;
- timestamps.

Indexes: `(user_id, created_at)`, `(research_query_id, completed_at)`, `(status, created_at)`.

### Search collection staging

`research_run_search_pages` durably records each requested page number, request/next page tokens, normalized result count, approximate provider total, and safe warnings. `research_run_search_results` stores the first normalized occurrence of each provider video ID with channel ID, title, publish time, page, rank, and provider order. Both belong to a research run and cascade with it.

This staging layer is the retry boundary between search collection and catalog enrichment. A redelivered search job resumes from the last persisted page and does not duplicate saved candidates; later catalog work converts these candidates into the canonical video/channel and snapshot tables.

## 3. Shared collection context

### `collection_runs`

- `id`, `public_id`, `user_id`, provider, kind, status, attempt number;
- frozen request parts, cache policy, thresholds/configuration context, and safe metadata;
- requested/processed counts, progress, warnings, safe error fields;
- `started_at`, `completed_at`, `failed_at`, timestamps.

Kinds initially include `search_enrichment`, `video_analysis`, `channel_analysis`, and `watchlist_refresh`. Initiating workflow tables reference a collection run through explicit foreign keys. API usage events may also reference it.

Search page/token staging remains attached to `research_runs`. Collection runs own reusable provider observation work.

## 4. Catalog and immutable observations

### `channels`

- `id`, `provider`, `provider_channel_id`;
- latest non-metric identity fields: title, custom URL, thumbnail URL, country;
- timestamps.

Unique `(provider, provider_channel_id)`.

### `channel_snapshots`

- `id`, `channel_id`, `collection_run_id`;
- nullable legacy `research_run_id` during the staged compatibility period; shared non-Research capture does not require a fake Research aggregate;
- subscriber, view, video counts nullable;
- `subscriber_count_hidden` boolean;
- optional normalized metadata JSON;
- `collected_at`, timestamps.

Unique `(collection_run_id, channel_id)`; index `(channel_id, collected_at)`.

### `videos`

- `id`, `provider`, `provider_video_id`, `channel_id`;
- identity fields: latest title, thumbnail URL, published time, duration seconds, category;
- `is_short` nullable because classification may be uncertain;
- timestamps.

Unique `(provider, provider_video_id)`; index `(channel_id, published_at)`.

### `video_snapshots`

- `id`, `video_id`, `collection_run_id`;
- nullable legacy `research_run_id` during the staged compatibility period; the collection link is authoritative for new shared capture;
- view, like, comment counts nullable;
- `collected_at`, timestamps.

Unique `(collection_run_id, video_id)`; index `(video_id, collected_at)`. Calculated Analyzer values live in versioned result tables rather than API snapshot rows.

### `research_run_videos`

- `research_run_id`, `video_id`;
- result rank, page number, provider order;
- pinned `video_snapshot_id` and `channel_snapshot_id` after enrichment;
- matched query metadata if needed;
- timestamps.

Unique `(research_run_id, video_id)`; index `(research_run_id, result_rank)`.

During the staged migration, existing research-owned snapshot foreign keys remain compatible until all read models use the shared collection/source links. No historical value or timestamp is rewritten.

Research analysis, scoring, history, exports, and discovery read the pinned `video_snapshot_id` and `channel_snapshot_id` on each membership. A cached source can therefore remain owned by its original collection while a later workflow preserves that original observation time. Retention preserves a source run while another retained Research result pins one of its observations.

### `video_categories`

- provider, category ID, region key, display language, category name, assignable flag;
- fetched/expiry timestamps.

Unique by provider/category/region/language. Official category remains separate from inferred niche/topic.

## 5. Analyzer

### `analyzer_runs`

- `id`, `public_id`, `user_id`, target kind;
- nullable anchor `video_id`, required target/author `channel_id` after resolution;
- pinned nullable `channel_snapshot_id` for the exact author/standalone-channel observation used by either target kind;
- nullable `collection_run_id` when cached sources were sufficient;
- origin kind/reference, frozen recent-video limit and cache mode;
- calculation/threshold/channel-behavior versions, lifecycle/progress/warnings/safe error fields;
- started/completed/failed/calculated timestamps.

Indexes: `(user_id, created_at)`, `(user_id, video_id, completed_at)`, `(user_id, channel_id, completed_at)`, `(status, created_at)`.

### `analyzer_curations`

- owner, allow-listed subject type (`video` or `channel`), and canonical subject ID;
- private research status (`unreviewed`, `researching`, `promising`, or `ruled_out`) and optional note;
- unique `(user_id, subject_type, subject_id)` so repeated saves update one owner-scoped record.

Favorites and tags continue to use the Library aggregate. Analyzer access to a canonical video/channel is a valid ownership path for Library resolution; it does not automatically create a favorite, watch, or workspace item.

### `analyzer_run_videos`

- analyzer run, video, pinned video snapshot, optional pinned channel snapshot;
- role: `anchor` or `channel_recent_upload`;
- playlist/source position and deterministic cohort rank fields.

Unique `(analyzer_run_id, video_id, role)`.

### `video_analysis_metrics`

- analyzer run and anchor video;
- age, lifetime-average views/day, views/subscribers;
- like/comment rates and per-1000 values;
- public engagement proxy;
- channel median/average ratios, rank, percentile, breakout class;
- calculation version, input summary, warnings, calculated time.
- prior comparable video snapshot reference plus observed elapsed time, public-count deltas, observed recent views/day, and positive-denominator growth percentage.

### `channel_analysis_metrics`

- analyzer run and channel;
- channel age/size class and recent valid count;
- typed view/like/comment/duration baseline values;
- upload intervals/frequency/longest gap;
- breakout shares, momentum value/class, consistency score/class;
- fixed-block momentum inputs, MAD/median consistency coverage, duration/performance rank correlation and bucket evidence;
- prior comparable channel snapshot reference plus observed public-count deltas;
- duration/category distributions, calculation version, warnings, calculated time.

### `user_entity_observations`

- `user_id`, allow-listed subject type (`video` or `channel`), subject ID;
- first/last seen, last fetched, first/latest snapshot IDs, first observed public count.

Unique `(user_id, subject_type, subject_id)`. This table is user-private mutable summary state over immutable observations.

### `comment_collection_runs` and `public_comments`

- An owner-scoped comment collection run belongs to one completed video Analyzer attempt and freezes provider video ID, page size, maximum comments, and top-level-only reply scope.
- Persist queue/collection/terminal state, resumable next-page token, page/comment counts, reported page total, safe error, and collection/completion timestamps.
- Store only provider comment ID, plain top-level text, like count, YouTube-reported reply count, and provider publish/update timestamps. Do not store author identity or reply text.
- Unique `(comment_collection_run_id, provider_comment_id)` makes retry and duplicate job delivery idempotent.
- Comment collections are independent six-month retention targets and cascade their stored text; deleting an Analyzer attempt also removes its comment collections.

### `saved_comment_ideas`

- Stores an owner-scoped reversible save of one collected top-level comment with a public UUID, nullable source-comment reference, canonical video reference, provider comment ID, exact text at save time, and source publish time.
- Unique `(user_id, video_id, provider_comment_id)` makes repeated heart actions idempotent across retained comment collections.
- The nullable source-comment foreign key becomes null when raw comment retention removes the collection. The intentional saved text copy and canonical video link remain until the owner removes the idea; removing it never deletes or mutates the original comment collection.
- Index `(user_id, created_at)` supports a deterministic bounded Ideas list without provider work.

### `audience_signal_profiles`, `audience_signals`, and `audience_signal_evidence`

- Each immutable owner-scoped profile pins one stored comment collection plus inferred provenance, provider/algorithm version, detected language, sparse/unsafe/partial status, sample coverage, confidence, warnings, and calculation time.
- Ordered signals use the allow-listed kinds repeated question, topic, entity, suggestion, complaint, and confusion point. Labels retain occurrence/source-comment counts and confidence.
- The evidence pivot links every signal to the exact stored top-level comments that support it. Comment retention cascades profiles, signals, and evidence; inferred output never outlives its source text.
- Idempotency is enforced per `(comment_collection_run_id, provider, algorithm_version)`. A later collection or provider/version creates a new historical profile rather than mutating an existing result.

### `audience_signal_exclusions`

- Stores one normalized single word per owner with its display form, active state, exclusion time, and nullable restoration time.
- The unique `(user_id, normalized_word)` key makes repeated hide actions idempotent while preserving reversible preference history.
- Exclusions are presentation preferences: they never mutate immutable Audience Signal profiles or evidence and remain independent of comment/profile retention.
- Filtering requires exact equality with `audience_signals.label_key`; a multi-word label containing an excluded word remains visible.

### `transcript_documents` and `transcript_segments`

- An immutable owner-scoped transcript document belongs to one completed video Analyzer attempt and its exact anchor video. It stores the local provider/version, input format, declared language, original pasted text, normalized plain text, checksum, warnings, rights-confirmation time, and provided time.
- Ordered segments store nullable start/end milliseconds and normalized text. Bracket timestamps and SRT/VTT preserve navigation offsets; plain text is one untimed segment with an explicit limitation warning.
- Unique `(analyzer_run_id, checksum_sha256)` makes duplicate submission idempotent. Pasting changed content creates a new revision instead of mutating history.
- Transcript documents are independent six-month retention targets and cascade their segments. Owner-confirmed manual deletion removes only that revision; transcript absence or deletion does not alter Analyzer observations or calculated results.
- `transcript_deletion_audits` records non-text metadata for every owner-confirmed direct deletion (owner, Analyzer/document/video references, format/version/language, sizes, and deletion time) without retaining deleted transcript text. Retention cleanup uses the existing cleanup-run audit trail instead.

### `transcript_structure_profiles` and `transcript_structure_insights`

- One immutable owner-scoped profile pins one exact transcript document plus inferred provenance, provider/algorithm version, language, status, word/evidence counts, confidence, warnings, and calculation time. Unique `(transcript_document_id, provider, algorithm_version)` makes duplicate calculation idempotent; a new transcript revision or provider version creates new history.
- Typed insights use the allow-listed kinds `summary`, `topic`, `entity`, `hook`, `section`, `cta`, `question`, and `script_structure`. Each stores inferred label/detail/confidence plus exact normalized-text start/end character offsets and nullable source-video start/end milliseconds.
- Original transcript text remains only on the transcript document. The UI derives an evidence excerpt from the pinned offsets rather than copying source text into inferred rows. Transcript deletion or six-month retention cascades its profiles/insights without changing Analyzer observations, metrics, classifications, scores, or completion.

## 6. Scoring

### `opportunity_scores`

- `id`, `research_run_id`;
- `formula_version`;
- `overall_score` decimal;
- five decimal component columns;
- `confidence_score` decimal;
- `sample_size`;
- `input_summary` JSON;
- `explanations` JSON;
- `warnings` JSON;
- `calculated_at`, timestamps.

Unique `(research_run_id, formula_version)`. Component names and formula are defined in `04_SCORING_MODEL.md`.

## 7. Discovery

### `discovery_runs`

- `id`, `public_id`, `user_id`, optional project;
- market and frozen parameters;
- status/progress/error fields;
- `started_at`, `completed_at`, timestamps.

### `discovery_seeds`

- `id`, `discovery_run_id`, `seed_query`, `source`;
- optional linked `research_run_id`;
- timestamps.

### `niche_candidates`

- `id`, `public_id`, `discovery_run_id`;
- `phrase`, normalized phrase, cluster key;
- summary and evidence JSON;
- component/overall scores and formula version where calculated;
- `status`: new, saved, dismissed, validated;
- optional linked validation run;
- timestamps.

Index `(discovery_run_id, status, overall_score)`.

## 8. Library

### `favorites`

- `id`, `user_id`, optional project;
- typed target: niche candidate, video, channel, research query, or research run;
- target ID, note, timestamps.

Enforce one favorite per `(user_id, target_type, target_id)` and validate allowed target types rather than accepting arbitrary class names.

### `tags` and `taggables`

Tags belong to users. The pivot permits tagging selected library items. Enforce user ownership in application code.

## 9. Explore, Watchlist, and Topic Workspace

Explore is a read model over user-owned runs, snapshots, candidates, Analyzer results, watch state, and workspace links. It does not require an `explore_results` copy table in its first version.

### `watchlist_items`

- `id`, `public_id`, `user_id`, allow-listed target type/ID;
- optional project/workspace, status, active flag, refresh mode/interval;
- last/next refresh pointers, note, timestamps.

Unique `(user_id, target_type, target_id)`; useful indexes include owner/status/next-refresh.

### `watchlist_refresh_runs`

- watchlist item, owner, collection run, status/progress/warnings/errors;
- previous/current pinned snapshot references and lifecycle timestamps.

### `topic_workspaces`

- `id`, `public_id`, `user_id`, optional project;
- name, normalized key, description, status;
- primary market/language context, timestamps, optional archive time.

### `topic_workspace_items`

- workspace, explicit target type/ID, evidence role, note, sort position, timestamps.

Allowed targets: video, channel, research query/run, discovery candidate, Analyzer run, and watchlist item. Enforce owner resolution for every user-owned target.

### `topic_workspace_launches`

- workspace and owner;
- launch type (`search` or `discover`);
- exactly one linked immutable Research or Discovery run;
- timestamps for the workspace workflow history.

Launch lineage remains separate from typed evidence. This lets a workspace trace a Discovery run without widening the evidence allow-list or copying candidate/metric payloads; individual candidates may be linked later as evidence.

### Semantic tables (V1.1)

`semantic_topic_profiles` stores one immutable, owner-scoped profile per Analyzer attempt: status, inferred provenance, provider/version, detected language, niche/subniche labels and confidence, topic concentration, evidence summary, warnings, and calculation time. A repeated calculation of the same attempt reuses that row; a new Analyzer attempt creates a new historical version.

`semantic_classifications` stores ordered niche, subniche, topic, and content-pillar labels/keys with confidence and the exact frozen Analyzer cohort video IDs used as evidence. These inferred records remain structurally separate from the official YouTube category on video observations. Search, Discover, Explore, and Topic Workspace read the latest owner-authorized profile by reference and do not copy or relabel it as an API fact or opportunity score.

Later topic/title performance aggregates link to these versioned profiles and Analyzer inputs; they do not overwrite classifications or API category data.

`semantic_performance_profiles` stores one immutable, owner-scoped performance calculation per Analyzer attempt. It pins the source semantic profile when one exists, the topic/title-pattern/calculation versions, frozen minimum sample, cohort count, warnings, and calculation time. `semantic_performance_aggregates` stores typed topic or editorial-title-pattern rows with an explicit unclassified flag, minimum-sample state, sample/coverage counts, nullable median and average views, nullable median and average Lifetime Average Views/Day, nullable Breakout rate, and the exact provider video IDs used as evidence. Values remain null until the relevant metric has the frozen minimum sample; a new Analyzer attempt creates a new historical performance profile.

`semantic_performance_profiles` stores one immutable, owner-scoped performance calculation per Analyzer attempt. It pins the source semantic profile when one exists, the topic/title-pattern/calculation versions, frozen minimum sample, cohort count, warnings, and calculation time. `semantic_performance_aggregates` stores typed topic or editorial-title-pattern rows with an explicit unclassified flag, minimum-sample state, sample/coverage counts, nullable median and average views, nullable median and average Lifetime Average Views/Day, nullable Breakout rate, and the exact provider video IDs used as evidence. Values remain null until the relevant metric has the frozen minimum sample; a new Analyzer attempt creates a new historical performance profile.

## 10. Export, settings, and operations

### Thumbnail analysis tables (V1.2)

`thumbnail_analysis_profiles` stores immutable owner-scoped attempts linked to one Analyzer run: queued/processing/complete/partial/insufficient/failed state, inferred provenance, provider and feature/association versions, frozen minimum sample, cohort/availability/cache counts, confidence, warnings, and safe failure fields.

`thumbnail_analysis_items` stores one immutable result per profile and Analyzer video membership. It pins the video, role, source URL/hash/checksum metadata, fresh or reused cache provenance, dimensions, numeric visual features, inferred classes/cluster, confidence, and a safe unavailable code. It stores no image bytes. `source_item_id` traces exact bounded cache reuse and nulls safely if the source attempt is retained for less time.

`thumbnail_performance_aggregates` stores version-pinned cluster counts, metric-specific sample counts, nullable view/views-per-day/Breakout associations, minimum-sample state, and exact provider video evidence IDs. Thumbnail profiles, items, and aggregates cascade with the owning Analyzer run and are included explicitly in retention preview/audit counts.

Cross-channel comparison adds no persistence. Its read model resolves two or three completed owner-scoped `analyzer_runs` for distinct channels, their pinned channel snapshots/metrics, immutable semantic performance rows, and the latest useful immutable thumbnail attempt. Missing groups stay null, and no historical aggregate is copied or rewritten.

### `exports`

- `id`, `public_id`, `user_id`;
- format, selection JSON, status;
- disk/path, size, checksum;
- `started_at`, `completed_at`, `expires_at`, error fields;
- timestamps.

### `user_settings`

- `user_id` unique;
- default market, result depth, timezone;
- cleanup notification and optional scoring-display preferences;
- Analyzer recent-video depth and cache preference within bounded application limits;
- timestamps.

The YouTube API key should remain in `.env`, not this table, in the initial local release.

### `api_usage_events`

- `id`, `user_id` nullable, `research_run_id` nullable;
- provider, quota bucket, endpoint;
- request count/cost, outcome, safe error code;
- `occurred_at`, timestamps.

Index `(provider, quota_bucket, occurred_at)` and `(user_id, occurred_at)`. This is a local estimate, not an authoritative Google quota record.

### `cleanup_runs`

- `id`, `public_id`, `initiated_by_user_id` nullable;
- mode: scheduled, manual-selection, manual-retention;
- status, cutoff time, dry-run flag;
- eligible/deleted counts by entity JSON;
- `started_at`, `completed_at`, safe error fields;
- timestamps.

### `snapshot_deletion_items`

- cleanup run ID;
- target type and target public/reference ID;
- original collection time;
- deletion outcome and timestamp.

Do not store full deleted payloads in the audit table.

## 11. Retention behavior

- Default cutoff is `now - 6 months`, calculated in UTC.
- Eligibility covers completed/failed run artifacts, snapshots, run pivots, and expired export files according to explicit dependency order.
- Projects, queries, favorites, user settings, and users survive unless the user explicitly deletes those aggregates through a separate future feature.
- If a favorite references an expiring snapshot/run, the cleanup preview must warn and either preserve it or require explicit confirmation according to the chosen implementation policy. Record that policy in `10_DECISIONS.md` before coding cleanup.
- Analyzer and Watchlist observations use the same six-month eligibility rule. Analyzer cleanup audits the run, deletes only its unshared collection observations, preserves canonical entities and first-seen state, and retains sources pinned by another Research or Analyzer result. Preview linked Watchlist/Topic Workspace evidence and preserve explicitly protected evidence under D-016 semantics.

## 12. Expansion migration order

1. add collection runs/source links beside existing Research relations;
2. backfill historical Research collection contexts without changing values;
3. route Search capture through the shared boundary and migrate dependent read models;
4. add Analyzer runs, metrics, source-aware UI, and user observation state;
5. add Explore projections, Watchlist, and Topic Workspaces;
6. add semantic/comments/transcript/thumbnail tables only with their approved vertical slices;
7. remove obsolete Research-only snapshot constraints only after parity, rollback, retention, export, history, and score verification.

The complete target and compatibility strategy is defined in `12_UNIFIED_ANALYZER_MODEL.md`.
