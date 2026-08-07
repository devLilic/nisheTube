# Product Requirements

## 1. Product summary

NisheTube helps authenticated local users research YouTube content opportunities. A user selects a market and query, collects public YouTube results, sees visual indicators, saves interesting niches, and compares timestamped snapshots over time.

The first release is a local desktop-browser workflow served by Laragon. It is not a hosted SaaS product.

## 2. Users and ownership

- The application supports registration, login, logout, password reset, and account settings.
- Multiple users may exist in the local database.
- Projects, searches, runs, favorites, exports, settings, and deletion actions belong to a user.
- A user must never view, modify, export, or delete another user's private research data.
- Public YouTube entities may be deduplicated globally, but user-specific collections and snapshots remain authorized by ownership.

## 3. Markets

| Market key | UI label | YouTube region | Relevance language | Notes |
|---|---|---:|---:|---|
| `global_en` | Global / English | none | `en` | Global sampling; results are not a complete global census. |
| `ro_ro` | Romania / Romanian | `RO` | `ro` | Romanian market sampling. |
| `ru_ru` | Russia / Russian | `RU` | `ru` | Russian-language market sampling. |

Every research run stores its market parameters so historical results remain reproducible even if defaults later change.

## 4. Functional modules

### 4.1 Authentication and users

- Register and sign in with email/password.
- Manage profile, password, timezone, and default market.
- Protect all application pages behind authentication, except auth routes.

### 4.2 Dashboard

- Show recent research runs, saved projects, favorites, quota status, cleanup status, and high-opportunity discoveries.
- Provide primary actions for new search and discovery run.
- Display meaningful empty and failure states.

### 4.3 Keyword search

- Accept a query, market, time window, result depth, and optional YouTube filters.
- Create a durable run immediately and collect data asynchronously.
- Show progress, partial completion, failure reason, retry action, and completion timestamp.
- Support paginated API collection without implying that approximate result totals are exact.

### 4.4 Video and channel analysis

- Enrich returned video IDs with public video statistics and content details.
- Enrich channel IDs with public channel statistics.
- Show tables/cards for views, age, views per day, engagement proxy where data exists, subscribers, channel size, publishing frequency, and views-to-subscriber ratio.
- Show source timestamp and identify missing/hidden statistics.

### 4.5 Opportunity scoring

- Produce an overall score from 0 to 100 and five explainable component scores.
- Store formula version, inputs, confidence, missing-data warnings, and computation timestamp.
- Let users inspect why a score is high or low.
- Never label the result as exact demand or guaranteed performance.

### 4.6 Discovery

- Run seed-query exploration per market.
- Detect breakout videos, recurring themes, underserved query clusters, and promising channel-size patterns.
- Generate candidate niche phrases using deterministic rules first.
- Allow future AI providers for clustering, query expansion, and summaries without coupling core collection to one vendor.
- Save, dismiss, or start a full search from a candidate.

### 4.7 History and comparisons

- List timestamped search/discovery runs.
- Compare two compatible runs for the same query/market.
- Show changes in score, components, median metrics, leading videos, and channel composition.
- Clearly warn when formula versions or collection parameters differ.

### 4.8 Projects and favorites

- Group queries and discoveries into named projects.
- Favorite niches, videos, channels, and runs.
- Add user notes and tags.
- Filter and sort the library.

### 4.9 Export

- Export user-selected research data to CSV and Excel-compatible `.xlsx`.
- Include source market, query, collection time, metric timestamps, score version, and warnings.
- Run large exports as queued jobs and expose download status in the UI.

### 4.10 Settings and API quota

- Configure the YouTube API key through local environment settings; never reveal its full value in the UI.
- Test API connectivity safely.
- Show locally recorded quota calls by bucket/day and remaining estimated allowance.
- Configure default market, search depth, timezone, and cleanup preferences.
- Distinguish locally estimated quota from authoritative Google Cloud quota.

### 4.11 Snapshot retention

- Mark snapshot/run records with collection and completion timestamps.
- Automatically delete eligible historical snapshot data older than six months.
- Provide a preview/dry run before scheduled or manual cleanup.
- Allow selective manual snapshot deletion with confirmation.
- Record deletion actor, scope, counts, and time.

## 5. Non-functional requirements

- Desktop-first responsive interface; usable at tablet width.
- Visual cards, indicators, charts, and data tables without hiding exact values.
- Accessible keyboard navigation, focus indicators, labels, and non-color-only statuses.
- Retry-safe external operations and actionable error messages.
- Secrets remain local and excluded from Git.
- Database queries remain user-scoped and indexed.
- Dates are stored in UTC and rendered in the user's configured timezone.
- Scores are deterministic for the same stored inputs and formula version.

## 6. Extensibility requirements

The design must allow later addition of:

- OAuth access to private YouTube account data;
- alternative research providers and data sources;
- AI clustering, summarization, and query expansion;
- additional markets and languages;
- new scoring versions and user-adjustable weights;
- Redis-backed queues/cache;
- scheduled unattended scans;
- additional export formats.

These extensions are not part of the initial implementation unless explicitly moved into the backlog.

## 7. Initial exclusions

- Public hosting, deployment, billing, subscriptions, teams, and organization roles.
- Publishing, editing, or deleting content on YouTube.
- Scraping YouTube pages outside supported APIs.
- Promising exact search volume, revenue, CPM, or ranking probability.
- Mandatory AI dependency for the core research workflow.

## 8. Product success criteria

The first usable release succeeds when an authenticated user can run a market-specific search, watch its status, inspect normalized video/channel metrics and an explainable five-part score, save findings, compare later snapshots, export data, and safely clean old snapshots from the UI.

