# YouTube API Integration

## 1. Integration scope

The initial release uses YouTube Data API v3 with a server-side API key for public data. Browser code must never call Google directly or receive the key.

Future authenticated YouTube account features must use a separate OAuth adapter and are outside the first release.

## 2. Primary endpoints

| Need                         | Endpoint                                           | Notes                                                                                 |
| ---------------------------- | -------------------------------------------------- | ------------------------------------------------------------------------------------- |
| Keyword and seed search      | `search.list`                                      | Request `type=video`; freeze query, market, filters, and page tokens in run metadata. |
| Video details/statistics     | `videos.list` or supported batch statistics method | Batch IDs and request only required parts.                                            |
| Channel statistics           | `channels.list`                                    | Batch channel IDs; subscriber counts may be hidden.                                   |
| Channel uploads              | `channels.list` + `playlistItems.list`             | Use uploads playlist when creator viability requires recent publishing samples.       |
| Regions/languages/categories | relevant `i18n*` and category list methods         | Cache slowly changing reference data.                                                 |
| Popular chart seed           | `videos.list(chart=mostPopular)`                   | Treat as a limited seed source, not general YouTube Trending.                         |

## 3. Current quota constraints

As documented by Google in August 2026:

- default projects receive 100 `search.list` calls per day in the Search Queries bucket;
- a `search.list` call costs one unit in that bucket;
- pagination consumes an additional call for every page;
- default projects receive 10,000 daily units combined for most other endpoints;
- invalid requests still consume at least one relevant quota unit;
- actual quota and changes are authoritative in Google Cloud Console.

The product must track a local estimate but label it as an estimate. Never hardcode the allowance in scoring or business logic; place defaults in configuration and allow later adjustment.

Official references:

- https://developers.google.com/youtube/v3/getting-started
- https://developers.google.com/youtube/v3/docs/search/list
- https://developers.google.com/youtube/v3/guides/implementation/pagination
- https://developers.google.com/youtube/v3/docs/videos/list
- https://developers.google.com/youtube/v3/revision_history

## 4. Important discovery limitation

Since July 21, 2025, `videos.list(chart=mostPopular)` reflects trending music, movies, and gaming content rather than the former broad Trending Now experience. General discovery must therefore use seed queries, API sampling, breakout detection, topic extraction, and validation searches. `mostPopular` may supplement these sources but cannot be the only discovery mechanism.

## 5. Market request mapping

### Global / English

- `relevanceLanguage=en`;
- omit `regionCode` to avoid falsely presenting one region as global;
- label results as a global English-oriented sample.

### Romania / Romanian

- `regionCode=RO`;
- `relevanceLanguage=ro`.

### Russia / Russian

- `regionCode=RU`;
- `relevanceLanguage=ru`.

The API language parameter influences relevance but does not guarantee that every result is written or spoken in that language. Store detected/returned metadata and communicate sampling limits.

## 6. Collection rules

- Request only necessary resource parts.
- Use the maximum sensible batch size for ID enrichment.
- Deduplicate video and channel IDs before enrichment.
- Preserve result rank and collection page.
- Use `nextPageToken`; never create pagination from approximate `totalResults`.
- Record API attempt before or atomically with the call outcome so failed calls are visible in the quota ledger.
- Add bounded retries with exponential backoff for transient failures; do not retry invalid keys or exhausted quotas endlessly.
- Make run jobs idempotent using run/state and unique database constraints.
- Save nullable metrics when YouTube hides or omits a statistic; never coerce missing values to zero.

## 7. Configuration

Expected local variables:

```text
YOUTUBE_API_KEY=
YOUTUBE_API_BASE_URL=https://www.googleapis.com/youtube/v3
YOUTUBE_SEARCH_DAILY_ALLOWANCE=100
YOUTUBE_GENERAL_DAILY_ALLOWANCE=10000
YOUTUBE_QUOTA_RESET_TIMEZONE=America/Los_Angeles
```

Only the key is secret. Add the variable names with blank/sample-safe values to `.env.example`; keep the real key only in `.env`.

## 8. Error mapping

Normalize provider responses into safe internal errors:

| Internal code             | UI action                                           |
| ------------------------- | --------------------------------------------------- |
| `youtube_key_missing`     | Open Settings instructions.                         |
| `youtube_key_invalid`     | Replace or restrict the key correctly.              |
| `youtube_api_disabled`    | Enable YouTube Data API v3 in Google Cloud.         |
| `youtube_quota_exhausted` | Show bucket and expected reset guidance.            |
| `youtube_rate_limited`    | Retry later.                                        |
| `youtube_request_invalid` | Review filters; do not auto-retry.                  |
| `youtube_unavailable`     | Retry with bounded backoff.                         |
| `youtube_partial_data`    | Complete run with warning when useful data remains. |

Do not store full provider messages if they may contain sensitive request details.

## 9. In-application quota widget and ledger

NisheTube cannot present a Google-authoritative remaining-quota value after each request. Google directs developers to the Google Cloud Console Quotas page for that value. Instead, NisheTube maintains a local estimate from every API attempt it makes and labels the interface accordingly.

For each request:

1. identify the configured quota bucket and cost from versioned provider configuration;
2. create an `api_usage_events` ledger entry with endpoint, bucket, estimated cost, user/run context, timestamp, and outcome;
3. recompute the current day summary using the quota reset boundary at midnight Pacific Time;
4. include the safe summary in the next Inertia response/status poll so the persistent header widget refreshes;
5. when the provider returns a quota-exceeded response, mark that bucket exhausted in the current estimate and show an actionable warning.

Initial buckets are `search.list` (100 calls/day by default) and the general API bucket (10,000 units/day by default). The implementation must support new granular buckets and user-configured approved limits without schema rewrites.

Every request, including an invalid one, is charged at least one relevant quota unit according to Google documentation. If other software consumes the same project quota, the local estimate may be optimistic; the UI must state that Google Cloud Console is authoritative.

### 9.1 Reset and operator behavior

- The ledger day starts and resets at midnight in `YOUTUBE_QUOTA_RESET_TIMEZONE`, which defaults to `America/Los_Angeles`. This is daylight-saving aware; the UTC and user-local display time can change seasonally.
- `YOUTUBE_SEARCH_DAILY_ALLOWANCE` and `YOUTUBE_GENERAL_DAILY_ALLOWANCE` are NisheTube guardrails for the two local buckets. They do not change the Google Cloud project quota.
- The local estimate includes every provider attempt recorded by this application, including failed or invalid requests. It does not include requests made by another application using the same key or Google Cloud project.
- A provider quota-exhausted response marks the relevant local bucket exhausted until the next configured ledger day, even when the arithmetic estimate still shows unused allowance.
- Changing an allowance or reset timezone requires `php artisan optimize:clear` before long-running workers are restarted with `php artisan queue:restart`.
- Do not edit or delete `api_usage_events` to make quota appear available. Use Google Cloud Console for the authoritative value and wait for the provider reset when it reports exhaustion.

Operational troubleshooting is collected in [Backup, restore, and troubleshooting](11_BACKUP_AND_RECOVERY.md#troubleshooting).

## 10. Test strategy

- Fake HTTP responses; automated tests must not consume real quota.
- Cover pagination, batching, missing statistics, quota ledger writes, transient retries, invalid key, quota exhaustion, malformed payloads, and partial enrichment.
- Keep sanitized JSON fixtures small and document their source shape.
- A manual connectivity test is the only routine path allowed to make a real API call during setup.
