# UI and UX Specification

## 1. Experience direction

NisheTube uses an English, visual, desktop-first dashboard. The interface should feel analytical but approachable: cards for summaries, indicators for status and confidence, charts for change, and tables for exact inspection.

Use the official Laravel React starter kit's Tailwind/shadcn foundation. Prefer a restrained neutral canvas with one primary brand color and semantic status colors. Support light and dark mode if the starter kit provides it without delaying core modules.

## 2. Application shell

Authenticated layout:

- collapsible left sidebar;
- top bar with current page, global market selector where appropriate, persistent `YouTube API Today` quota widget, and user menu;
- main content width optimized for data tables and charts;
- persistent toast/notification region;
- breadcrumb only on nested detail pages.

Primary navigation:

1. Dashboard
2. Search
3. Discover
4. Projects
5. Favorites
6. History
7. Exports
8. Settings

## 3. Global components

- `MarketBadge`: Global/English, Romania/Romanian, Russia/Russian.
- `ScoreGauge`: opportunity score, qualitative label, formula version tooltip.
- `ConfidenceBadge`: always adjacent to a score.
- `MetricCard`: value, unit, comparison delta, source timestamp, tooltip.
- `RunStatus`: queued/searching/enriching/scoring/completed/failed.
- `QuotaMeter`: local estimate, reset context, authoritative-source disclaimer.
- `DataFreshness`: collected-at and age.
- `EmptyState`, `ErrorState`, `PartialDataBanner`, `LoadingSkeleton`.
- sortable/filterable `DataTable` with column visibility and pagination.
- confirmation dialog requiring clear target/count for destructive cleanup.

### Persistent YouTube API Today widget

Keep this compact enough for the authenticated header and expandable for detail:

- show one chip per configured bucket, initially `Search: 72 / 100 left` and `General: 9,846 / 10,000 units left`;
- tooltip/expanded panel shows `NisheTube estimate`, last endpoint, request cost, timestamp, reset at midnight Pacific Time, and a Google Cloud Console authoritative-value note;
- refresh the server-supplied summary after every provider request; while a queued run is active, update through the existing status polling cycle;
- include loading, stale, unavailable, and quota-exhausted states without exposing the API key;
- when multiple local users share one configured key/project, show the project-wide remaining estimate and only show user-specific call history in detailed settings.

## 4. Pages by module

### 4.1 Authentication

- Login and registration use a focused card or split layout.
- Include password visibility control, validation, loading state, and clear local-app wording.
- Account settings cover profile, password, timezone, and default market.

### 4.2 Dashboard

Top row cards:

- research runs this month;
- best recent opportunity score with confidence;
- saved niches/favorites count;
- YouTube search quota estimate.

Main content:

- recent runs table with status and market;
- high-opportunity candidates;
- small score-trend chart;
- cleanup-due notice;
- prominent `New search` and `Start discovery` actions.

### 4.3 Search creation

- Query input is primary.
- Market uses three visual options with code/label.
- Advanced filters are collapsed by default.
- Show estimated number of search calls before submission based on requested depth.
- Submission creates a run and navigates to its live detail page.

### 4.4 Research run detail

Header: query, market, collected time, status, retry/export/save actions.

Sections:

1. score overview with five component bars and confidence;
2. explanation cards and warnings;
3. key aggregate metric cards;
4. video performance scatter/bar chart;
5. channel reach/competition chart;
6. detailed videos table;
7. detailed channels table;
8. collection metadata and API usage.

During processing, show real progress and partial-data banners rather than fake final metrics.

### 4.5 Discover

- Seed entry, market, depth/budget, and optional category.
- Progress timeline for seeds and validations.
- Candidate cards show phrase, score, confidence, evidence chips, and save/dismiss/validate actions.
- Filters for status, score, confidence, and market.

### 4.6 Projects and favorites

- Project card grid plus list toggle.
- Project detail contains saved queries, candidates, videos/channels, notes, and recent runs.
- Favorites support target-type tabs, tags, search, sorting, and bulk export.

### 4.7 History and comparison

- Timeline/table with timestamp, parameters, score, and status.
- Comparison picker permits only useful pairs or displays compatibility warnings.
- Comparison page shows metric deltas, component radar/bar chart, new/lost videos, channel mix changes, and formula warnings.

### 4.8 Exports

- Export creation summarizes included records and selected columns.
- Export jobs list format, state, size, created time, expiry, download, and safe delete.

### 4.9 Settings

Sections:

- Profile and defaults;
- YouTube integration status and masked key presence;
- quota ledger and daily usage;
- data retention preview and cleanup actions;
- scoring model information (read-only for v1).

## 5. Score labels

Default labels, subject to user testing:

- 80–100: Strong opportunity
- 65–79: Promising
- 50–64: Mixed
- 35–49: Competitive / uncertain
- 0–34: Weak observed opportunity

Never display a score without confidence and the collection timestamp.

## 6. Required states for every data page

- Initial loading skeleton.
- Empty state with a useful primary action.
- Active processing state with progress.
- Completed state.
- Partial-data warning.
- Recoverable error with retry.
- Quota-exhausted state with reset guidance.
- Unauthorized/not-found handling without data leakage.

## 7. Accessibility and formatting

- Keyboard-operable navigation, menus, dialogs, tabs, and data grids.
- Visible focus rings and properly associated labels.
- Charts have text summaries or accessible tables.
- Status never relies on color alone.
- Use locale-aware number formatting and compact display only when the exact value remains accessible.
- Display absolute timestamp plus relative age where useful.
- Ensure long English, Romanian, and Russian video titles wrap or truncate with accessible full text.

## 8. Responsive scope

- Primary target: 1280px and wider.
- Usable tablet target: 768px and wider.
- On smaller screens, cards stack and tables may scroll horizontally.
- Full mobile optimization is desirable but not a release blocker for this local desktop tool.
