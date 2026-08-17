# Product Requirements

## 1. Product summary

NisheTube helps authenticated local users research YouTube content opportunities. A user can validate a market/query, analyze a specific video and its author channel, explore stored evidence, discover candidate niches, watch selected entities over time, organize topic workspaces, and compare timestamped observations.

The first release is a local desktop-browser workflow served by Laragon. It is not a hosted SaaS product.

## 2. Users and ownership

- The application supports registration, login, logout, password reset, and account settings.
- Multiple users may exist in the local database.
- Projects, searches, runs, favorites, exports, settings, and deletion actions belong to a user.
- A user must never view, modify, export, or delete another user's private research data.
- Public YouTube entities may be deduplicated globally, but user-specific collections and snapshots remain authorized by ownership.

## 3. Markets

| Market key  | UI label           | YouTube region | Relevance language | Notes                                                      |
| ----------- | ------------------ | -------------: | -----------------: | ---------------------------------------------------------- |
| `global_en` | Global / English   |           none |               `en` | Global sampling; results are not a complete global census. |
| `ro_ro`     | Romania / Romanian |           `RO` |               `ro` | Romanian market sampling.                                  |
| `ru_ru`     | Russia / Russian   |           `RU` |               `ru` | Russian-language market sampling.                          |

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
- Accept a YouTube video URL/ID and create a durable queued Analyzer run that is also reusable from Search, Explore, Discover, Watchlist, and Topic Workspace.
- Resolve the author channel and collect a configurable recent-upload cohort, initially 30 videos, through the shared provider and snapshot boundary.
- Show tables/cards for views, age, `Lifetime Average Views/Day`, `Public Engagement Proxy`, subscribers, channel size, publishing frequency, views-to-subscriber ratio, channel-relative rank/percentile, and breakout class where data exists.
- Calculate a robust recent-channel baseline, upload behavior, momentum, duration profile, breakout rate, consistency state, and observed snapshot growth from stored inputs.
- Distinguish `YouTube Data`, `Calculated Metrics`, `Detected Analysis`, and any future `Estimate` in storage and UI.
- Store first-seen/last-fetched state per user without inventing history before NisheTube observed the entity.
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
- Reuse owner-scoped completed Analyzer breakouts and detected topics as evidence, while still requiring a normal validation Search before treating a candidate as validated.

### 4.7 Explore

- Provide a user-scoped browsing surface over stored Search results, Analyzer results, discoveries, watched items, and topic-workspace evidence.
- Filter stored entities by source, market, official category, observed performance, breakout class, channel size, score/confidence, date, and organization state when those values exist.
- Never make provider calls merely by loading or filtering Explore; Analyze, Refresh, Validate, and Watch are explicit quota-aware actions.

### 4.8 History and comparisons

- List timestamped search/discovery runs.
- Compare two compatible runs for the same query/market.
- Show changes in score, components, median metrics, leading videos, and channel composition.
- Clearly warn when formula versions or collection parameters differ.
- Compare two or three owner-scoped completed channel Analyzer attempts through their pinned channel, topic, editorial-title-pattern, and thumbnail-cluster cohorts. Show market/source context, observation times, exact sample counts, and model versions; never turn the comparison into an opportunity score or channel recommendation.

### 4.9 Projects and favorites

- Group queries and discoveries into named projects.
- Favorite niches, videos, channels, and runs.
- Add user notes and tags.
- Filter and sort the library.

Favorites remain bookmarks and do not opt an entity into periodic collection.

Collected top-level comments may be saved separately as private comment ideas. Each saved idea preserves the exact selected text and canonical source-video link, remains available if the raw comment collection later expires, and can be removed without modifying the immutable collection.

### 4.10 Watchlist

- Track user-selected videos, channels, and later detected/workspace topics independently from Favorites.
- Store a user status, notes/tags, optional project/workspace, refresh policy, and last/next refresh context.
- Create queued, retry-safe, owner-rechecked refresh runs that reuse the Analyzer collection and snapshot services.
- Support manual refresh in the first slice; unattended schedules require explicit configuration and local-computer availability guidance.

### 4.11 Topic Workspace

- Create user-owned workspaces for a topic in a primary market/language context.
- Link videos, channels, queries, research runs, discovery candidates, Analyzer runs, and watchlist items as typed evidence without copying metric payloads.
- Record evidence roles such as example, outlier, competitor, inspiration, counterexample, or evidence.
- Launch prefilled Search/Discover workflows and link their immutable results back to the workspace.

### 4.12 Export

- Export user-selected research data to CSV and Excel-compatible `.xlsx`.
- Include source market, query, collection time, metric timestamps, score version, and warnings.
- Run large exports as queued jobs and expose download status in the UI.

### 4.13 Settings and API quota

- Configure the YouTube API key through local environment settings; never reveal its full value in the UI.
- Test API connectivity safely.
- Show locally recorded quota calls by bucket/day and remaining estimated allowance.
- Configure default market, search depth, timezone, and cleanup preferences.
- Configure Analyzer recent-video depth and cache policy within safe bounded limits; formula and threshold versions remain application-controlled for the first release.
- Distinguish locally estimated quota from authoritative Google Cloud quota.
- Display a compact persistent `YouTube API Today` widget in the authenticated application header. It shows estimated remaining allowance for each configured quota bucket, the latest endpoint/cost/time, and the daily reset time.
- Refresh the widget after every provider request. During queued research, refresh from the locally recorded quota ledger while the run-progress page polls for status.
- Label the value `NisheTube estimate`: it tracks all requests made by this application but can differ from Google Cloud Console if another app/key/project consumer uses quota.

### 4.14 Snapshot retention

- Mark snapshot/run records with collection and completion timestamps.
- Automatically delete eligible historical snapshot data older than six months.
- Provide a preview/dry run before scheduled or manual cleanup.
- Allow selective manual snapshot deletion with confirmation.
- Record deletion actor, scope, counts, and time.
- Preview the impact on Analyzer history, Watchlist history, and Topic Workspace evidence before deleting shared observations.

## 5. Non-functional requirements

- Desktop-only application interface; tablet and mobile layout optimization are out of scope.
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
- direct channel-URL intake and cross-channel comparison;
- semantic niche/topic/title-pattern classification;
- public-comment collection and Audience Signals with reversible owner-managed exact-word exclusions;
- an optional user-provided transcript for a completed video analysis, with explicit rights confirmation and no provider retrieval;
- thumbnail computer-vision analysis.

These extensions are not part of the initial implementation unless explicitly moved into the backlog.

## 7. Initial exclusions

- Public hosting, deployment, billing, subscriptions, teams, and organization roles.
- Publishing, editing, or deleting content on YouTube.
- Scraping YouTube pages outside supported APIs.
- Promising exact search volume, revenue, CPM, or ranking probability.
- Mandatory AI dependency for the core research workflow.
- Scraping YouTube pages or assuming arbitrary public transcripts are available through YouTube Data API.
- Making transcript availability a prerequisite for public video/channel analysis, metrics, scoring, or Analyzer completion.
- Presenting third-party watch time, retention, impressions, CTR, traffic sources, returning/unique viewers, RPM, CPM, revenue, shares, saves, or exact subscriber gain per video as available public facts.

## 8. Product success criteria

The expanded application succeeds when an authenticated user can move one canonical video/channel between Search, Explore, Discover, Analyzer, Watchlist, and Topic Workspace without duplicated metric logic; understand public, calculated, and inferred evidence; compare only observed history; and preserve the existing score, export, authorization, quota, and retention guarantees.

The complete cross-module target model is defined in `12_UNIFIED_ANALYZER_MODEL.md`.

## 9. Planned decision-workflow redesign

Phases 19–22 plan a post-M10 redesign around `Discover -> select -> validate -> compare -> decide -> monitor`. The implementation-ready tasks, dependencies, acceptance criteria, and source-request traceability are defined in `13_DECISION_WORKFLOW_REDESIGN.md` and registered in `08_BACKLOG.md`/`TASK_STATUS.md`.

This planned work keeps the interface English-only, continues to analyze English/Romanian/Russian content, and requires desktop behavior at approximately 1440px. Tablet and mobile-specific implementation are not in scope. Until an individual task is completed, the currently implemented requirements above remain authoritative for application behavior.
