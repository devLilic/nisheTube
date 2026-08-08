# Data Model

## 1. Principles

- Use numeric primary keys unless a public UUID is needed for URLs or queued references.
- Public YouTube entities are deduplicated by provider ID.
- Research runs and snapshots preserve historical values and are not overwritten by refreshes.
- User-owned aggregates always contain `user_id` and indexed ownership paths.
- Use JSON only for frozen provider parameters, explanations, or evolving metadata; important query/filter fields remain typed columns.

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

## 3. Catalog and immutable metrics

### `channels`

- `id`, `provider`, `provider_channel_id`;
- latest non-metric identity fields: title, custom URL, thumbnail URL, country;
- timestamps.

Unique `(provider, provider_channel_id)`.

### `channel_snapshots`

- `id`, `channel_id`, `research_run_id`;
- subscriber, view, video counts nullable;
- `subscriber_count_hidden` boolean;
- optional normalized metadata JSON;
- `collected_at`, timestamps.

Unique `(research_run_id, channel_id)`; index `(channel_id, collected_at)`.

### `videos`

- `id`, `provider`, `provider_video_id`, `channel_id`;
- identity fields: latest title, thumbnail URL, published time, duration seconds, category;
- `is_short` nullable because classification may be uncertain;
- timestamps.

Unique `(provider, provider_video_id)`; index `(channel_id, published_at)`.

### `video_snapshots`

- `id`, `video_id`, `research_run_id`;
- view, like, comment counts nullable;
- derived `age_seconds`, `views_per_day`, `views_to_subscribers_ratio` nullable;
- `collected_at`, timestamps.

Unique `(research_run_id, video_id)`; index `(video_id, collected_at)`.

### `research_run_videos`

- `research_run_id`, `video_id`;
- result rank, page number, provider order;
- matched query metadata if needed;
- timestamps.

Unique `(research_run_id, video_id)`; index `(research_run_id, result_rank)`.

## 4. Scoring

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

## 5. Discovery

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

## 6. Library

### `favorites`

- `id`, `user_id`, optional project;
- typed target: niche candidate, video, channel, research query, or research run;
- target ID, note, timestamps.

Enforce one favorite per `(user_id, target_type, target_id)` and validate allowed target types rather than accepting arbitrary class names.

### `tags` and `taggables`

Tags belong to users. The pivot permits tagging selected library items. Enforce user ownership in application code.

## 7. Export, settings, and operations

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

## 8. Retention behavior

- Default cutoff is `now - 6 months`, calculated in UTC.
- Eligibility covers completed/failed run artifacts, snapshots, run pivots, and expired export files according to explicit dependency order.
- Projects, queries, favorites, user settings, and users survive unless the user explicitly deletes those aggregates through a separate future feature.
- If a favorite references an expiring snapshot/run, the cleanup preview must warn and either preserve it or require explicit confirmation according to the chosen implementation policy. Record that policy in `10_DECISIONS.md` before coding cleanup.

## 9. Migration order

1. users extension and markets;
2. projects, queries, runs;
3. channels/videos and snapshots;
4. scoring;
5. discovery;
6. library/tags;
7. exports, usage ledger, cleanup audit.
