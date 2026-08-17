# UI and UX Specification

## 1. Experience direction

NisheTube uses an English, visual, desktop-first dashboard. The interface should feel analytical but approachable: cards for summaries, indicators for status and confidence, charts for change, and tables for exact inspection.

Use the official Laravel React starter kit's Tailwind/shadcn foundation. Prefer a restrained neutral canvas with one primary brand color and semantic status colors. Support light and dark mode if the starter kit provides it without delaying core modules.

## 2. Application shell

Authenticated layout:

- collapsible left sidebar;
- top bar with current page, global market selector where appropriate, persistent `YouTube API Today` quota widget, and user menu;
- debounced global search over at most 20 owner-visible stored themes, videos, channels, and runs; opening, typing, and navigating never calls YouTube;
- active market/project/workspace selectors, a Shortlist shortcut, and a bounded completed-run notification center with persistent owner read state;
- main content width optimized for data tables and charts;
- persistent toast/notification region;
- breadcrumb only on nested detail pages.

Primary navigation:

1. Dashboard
2. Search
3. Explore
4. Discover
5. Analyzer
6. Watchlist
7. Topic Workspaces
8. Projects
9. Favorites
10. Ideas
11. History
12. Exports
13. Settings

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
- `ProvenanceLabel`: `YouTube Data`, `Calculated Metrics`, `Detected Analysis`, or `Estimate`, with source/version context.
- `ObservationContext`: original observed time, cache/freshness state, first-seen boundary, and refresh action.
- `BreakoutBadge`: channel-relative class with threshold version and cohort coverage, never shown without age context.

### Persistent YouTube API Today widget

Keep this compact enough for the authenticated header and expandable for detail:

- show one chip per configured bucket, initially `Search: 72 / 100 left` and `General: 9,846 / 10,000 units left`;
- label Search as NisheTube-recorded requests and General API as Google-estimated units; bucket measures, limits, and endpoint costs come from provider configuration rather than UI constants;
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

- The decision intake is split across `Discover a market` and `Validate my idea`.
- Discover freezes the selected market/language, content format, publication period, and target channel-size lens before generating themes from owner-scoped stored samples; constrained format/channel-size rows with missing required evidence are excluded honestly.
- Validate keeps the query primary and offers nine server-defined starting presets: Fast scan, Balanced, Deep validation, Trend check, Emerging trend, Evergreen check, Small-channel opportunity, Long-form documentary, and Shorts opportunity.
- Market and period remain visible; order, duration, category, custom dates, format, and channel-size lens are collapsed under Advanced filters.
- An exact preflight lists the values that will be frozen, including configuration-backed Search request cost. Channel size is explicitly an analysis lens rather than a YouTube API filter.
- The CTA is disabled while creating, reads `Creating run`, and an owner-scoped UUID submission token makes a repeated valid submission resolve to the same queued run.
- Copy states that NisheTube measures observed returned-video demand rather than YouTube search volume.

### 4.4 Research run detail

Header: query, market, collected time, status, retry/export/save actions.

The first result card is a deterministic stored-data decision summary. It presents one non-contradictory lifecycle outcome (`Complete data`, `Partial data`, `Reduced confidence`, or failure with an explicit saved-partial distinction), verdict, opportunity score, adjacent confidence, field-level available/total denominators, sample size, observation freshness, an honest not-yet-measured stability state, up to five principal evidence items, bounded risks, and one recommended next action. It does not call YouTube or use an AI interpretation provider.

The five stored opportunity components are compact keyboard-expandable disclosures. Video evidence inspection is server-filtered and capped at 10 rows per page, with exact versioned relevance classes/scores/signals, existing owner-scoped Analyzer Breakout context when available, channel-size/format/completeness quick filters, and safe Analyzer return URLs. A separate Evidence quality section shows complete-versus-strict robust statistics, independent Shorts and long-form evidence, full and strict top-one-through-three outlier removals, and compatible-snapshot stability; it never declares a cross-format winner. Legacy runs retain provider-order relevance and an explicit not-calculated state. The page keeps one primary Shortlist action, places Discover/Compare/Repeat/Workspace/Export handoffs in a keyboard menu, and progressively discloses provider, frozen request, endpoint, window, cache, formula, and snapshot provenance in a right-side drawer.

While a run is active, the same summary presents the persisted stage, percentage, collected/requested count, warning count, and an explicitly unavailable ETA when stored timing evidence cannot support a defensible estimate. Terminal pages collapse persisted progress into Collection details so a generic completion label cannot compete with partial-data or reduced-confidence outcomes. Failed runs keep saved candidates and metrics visible and state whether partial results were preserved.

Sections:

1. score overview with five component bars and confidence;
2. explanation cards and warnings;
3. key aggregate metric cards;
4. video performance scatter/bar chart;
5. channel reach/competition chart;
6. detailed videos table;
7. detailed channels table;
8. collection metadata and API usage.

During processing, show real progress and partial-data context rather than fake final metrics.

### 4.5 Discover

- Seed entry, market, depth/budget, and optional category.
- Progress timeline for seeds and validations.
- Candidate niches and Weak phrase signals render in separate compact decision-table sections with independently bounded 10-row server pagination and deterministic sorting. Columns show theme, evidence score, confidence, supporting videos, unique channels, small-channel proof, typical performance, stability, status, and one primary Validate action.
- Candidate evidence explicitly distinguishes `Candidate niche` from `Weak phrase signal`; weak signals show every unmet frozen threshold instead of disappearing or being presented as validated candidates.
- A keyboard-expandable row shows the stored description, included phrases, channel/video IDs, exact medians and outlier-free performance, channel-size evidence, sources, risks, suggested validation query, and version context. Save, Dismiss, Favorite, Workspace, and Analyzer controls remain together under Secondary actions. Legacy candidates remain inspectable without being relabeled as V2 evidence. The evidence score is labeled as distinct from Opportunity score.
- Filters for status, score, confidence, and market.
- Evidence links for videos/channels open the canonical Analyzer rather than a duplicate statistics panel.

### 4.6 Explore

- User-scoped filter/search surface over stored videos, channels, research results, Analyzer runs, candidates, watched items, and workspace evidence.
- Result cards/rows show source workflow, official category, available opportunity score/confidence, relative-performance class, observation time, and organization state without conflating missing fields.
- Loading/filtering does not call YouTube. Explicit Analyze, Refresh, Validate, Watch, and Add to Workspace actions show quota/cache consequences.

### 4.7 Analyzer

Landing page:

- Explicit video/channel target selection; supported video URL/ID and canonical `/channel/` URL/raw channel-ID intake; Analyze action; recent analyses; and initial/validation states.

Live/result page:

1. compact Video Profile with thumbnail, identity, official category, detected classification when available, origin, and actions;
2. Video Performance cards for public counts and calculated ratios;
3. Author Channel Profile and recent baseline;
4. sortable/filterable Recent Videos table;
5. Strong/Breakout outliers;
6. Topic Profile/Performance when implemented;
7. observed Growth History with first-seen boundary;
8. opt-in Comments and later Audience Signals/Transcript status;
9. notes, tags, research status, Watchlist, and Topic Workspace actions.

Notes and research status are saved per owner and canonical video/channel. Favorites and tags reuse Library controls with confirmed favorite removal and ruled-out status changes. Watchlist and Topic Workspace controls expose reusable canonical handoff references but remain visibly unavailable until their own vertical slices.

Persisted progress labels are `Fetching video`, `Fetching channel`, `Loading recent videos`, `Calculating metrics`, and `Saving analysis`. Normal Refresh respects cache; Force Refresh explains added provider calls and creates a new attempt.

The Video Profile and Channel Profile must let the user answer the five decision questions in `12_UNIFIED_ANALYZER_MODEL.md` before requiring detailed-table inspection.

Channel Behavior shows the exact recent/preceding Lifetime Average Views/Day block medians and sizes, momentum class, robust consistency score/sample, and duration/performance Spearman coefficient with exact duration-bucket alternatives. Growth History keeps `Observed Recent Views/Day` visibly distinct from `Lifetime Average Views/Day`, includes an accessible exact-value table beside its visual trend, marks first seen and the retention cutoff, deduplicates cached snapshots, and uses an explicit one-snapshot empty state.

Topic Profile labels niche, subniche, topics, content pillars, language, concentration, and confidence as inferred from the frozen recent-video titles. It shows provider/version, evidence count, calculation time, mixed-language or sparse-input warnings, and distinct loading, insufficient, partial, failed, and success states. The official YouTube category stays in the Video Profile and is never presented as the inferred niche; Search, Discover, Explore, and Topic Workspace use the same inferred badge and preserve the validation-search boundary.

Topic and title-pattern performance follows Topic Profile. A keyboard-operable grouping filter switches between detected topics and editorial title patterns. The section shows a compact median-views bar comparison plus an exact accessible table with video count, metric sample counts, median/average views, median/average Lifetime Average Views/Day, and Breakout rate numerator/denominator. Below-minimum and Unclassified groups remain visible, mixed-language/partial states retain their warnings, and the section always says the evidence is an observed association rather than causation. Version, minimum sample, frozen cohort size, and calculation time remain visible. CSV/XLSX actions queue an owner-scoped export of the same immutable profile and exact rows; Analyzer/Workspace handoffs keep referencing the Analyzer attempt rather than copying these values.

Topic and title-pattern performance follows Topic Profile. A keyboard-operable grouping filter switches between detected topics and editorial title patterns. The section shows a compact median-views bar comparison plus an exact accessible table with video count, metric sample counts, median/average views, median/average Lifetime Average Views/Day, and Breakout rate numerator/denominator. Below-minimum and Unclassified groups remain visible, mixed-language/partial states retain their warnings, and the section always says the evidence is an observed association rather than causation. Version, minimum sample, frozen cohort size, and calculation time remain visible; Analyzer/Workspace handoffs keep referencing the immutable Analyzer attempt rather than copying these values.

Comments follows Growth History for completed video analyses. Loading a profile never collects comments. The explicit action explains added quota and renders off, queued/collecting, empty, complete, partial, comments-disabled, unavailable, quota-exhausted, and failed states. Results show top-level text with like and reported reply counts, collection time, and retention eligibility; copy states that reply text and author identity are not stored and reported reply counts do not imply reply completeness.

Every stored comment exposes a keyboard-accessible heart control that saves it to the owner's Ideas list or removes an accidental save. The dedicated Ideas page paginates saved messages, shows the exact text and source-video title/thumbnail, opens the canonical YouTube video safely in a new tab, and keeps an explicit partial-source notice when raw comment retention has removed the original collection. These actions are local and never collect provider data.

Audience Signals follows Comments when a completed or partial stored sample has been analyzed. It labels the complete section as inferred rather than authoritative sentiment, shows sample coverage, detected language, confidence, provider/version, and calculation time, and groups repeated questions, topics, entities, suggestions, complaints, and confusion points. Every signal expands to exact source-comment evidence. An owner may hide an unhelpful single-word signal and restore it from the visible exclusion manager; copy states that only exact one-word labels are affected and longer phrases remain visible. Sparse, mixed-language/partial, unsafe-withheld, failed, fully-hidden, and successful states remain distinct and non-color-only.

Transcript follows the completed video analysis as an optional, owner-managed evidence section. Its default state says `No transcript provided` and explicitly confirms that video/channel analysis remains complete. The paste form prefers `[m:ss]` timestamped text but accepts plain text, SRT, and VTT, requires a language and rights confirmation, and reports validation/partial parsing without changing other results. The viewer supports exact-text search, keyboard-accessible timestamp links to the source video, provider/version and retention context, immutable replacement revisions, no-match state, and a confirmation dialog before deleting the current revision.

Transcript Structure follows the original transcript viewer in a separate card. An explicit quota-free action runs versioned inferred analysis for the current immutable revision. The card exposes summary, topics, entities, hook, sections, calls to action, questions, and script structure with `Detected Analysis`, provider/version, language, confidence, and calculation time. Every item expands to the exact original evidence excerpt, character offsets, and a timestamp link when available. Not-analyzed, analyzing, insufficient, partial, failed, and complete states remain distinct; inferred output never replaces or edits the original transcript.

### 4.8 Watchlist

- Tabs/filters for videos, channels, and later topics; statuses, project/workspace, last observation, next/manual refresh, and change summary.
- Add/remove, status, notes/tags, pause/resume, and refresh actions are owner-scoped and show progress, partial data, quota exhaustion, and safe retry.
- Distinguish watched state from favorite state in copy and controls.

### 4.9 Topic Workspaces

- Workspace list and detail with market/language context, description, evidence-role filters, notes, and linked Search/Discover/Analyzer/Watchlist history.
- Add existing evidence or create a new workspace from Analyzer without copying metric values.
- Provide prefilled `Search this topic` and `Discover related candidates` actions with confirmation before collection.

### 4.10 Projects and favorites

- Project card grid plus list toggle.
- Project detail contains saved queries, candidates, videos/channels, notes, and recent runs.
- Favorites support target-type tabs, tags, search, sorting, and bulk export.

### 4.11 History and comparison

- Timeline/table with timestamp, parameters, score, and status.
- Comparison picker permits only useful pairs or displays compatibility warnings.
- Comparison page shows metric deltas, component radar/bar chart, new/lost videos, channel mix changes, and formula warnings.

### 4.12 Exports

- Export creation summarizes included records and selected columns.
- Export jobs list format, state, size, created time, expiry, download, and safe delete.

### 4.13 Settings

Sections:

- Profile and defaults;
- YouTube integration status and masked key presence;
- quota ledger and daily usage;
- data retention preview and cleanup actions;
- scoring model information (read-only for v1).
- Analyzer recent-video depth/cache preference and read-only threshold/version information.

### 4.14 Thumbnail patterns

- The completed Analyzer page initially shows thumbnail analysis as off; viewing or polling the page never starts the analysis job.
- The explicit action shows queued/processing progress, then complete, partial, insufficient, or failed/retry states.
- Available items show the thumbnail and exact inferred measurements/classes. Missing, inaccessible, invalid, or disallowed images use a non-image placeholder and safe error code without guessing whether a remote image was private, removed, or restricted.
- Cluster associations expose exact sample counts, nullable values below the frozen minimum, evidence video IDs, confidence, provider/version, cache provenance, and calculation time in an accessible table.
- Copy always says inferred and observed association, not causation, and states that thumbnail output does not alter opportunity scoring.

### 4.15 Cross-channel comparison

- Analyzer exposes an alphabetically ordered, searchable picker over at most 24 recent owner-scoped channels, groups up to five newest immutable attempts per channel, and requires two or three different channels.
- The result shows each channel's frozen observation time, market context or explicit unknown state, cache/source policy, requested/valid cohort sizes, and channel/topic/title/thumbnail model versions before metrics.
- Separate exact tables cover channel behavior, detected topic cohorts, editorial title-pattern cohorts, and inferred thumbnail clusters. Every metric shows its own sample count; missing groups and below-minimum values are not zero.
- Compatibility warnings identify market, observation-time, sample-size, source-policy, missing-data, and model-version differences. Incompatible values remain inspectable but are labeled as not like-for-like.
- Copy explicitly says the page is not an opportunity score, causal finding, or channel recommendation. Loading, insufficient-selection, empty evidence, partial/incompatible, and success states remain keyboard accessible at desktop width.

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
- Cached-result and refreshing states with the original observation timestamp.
- Inaccessible/private/deleted provider entity without guessing which state applies when the provider does not distinguish it.
- Unauthorized/not-found handling without data leakage.

## 7. Accessibility and formatting

- Keyboard-operable navigation, menus, dialogs, tabs, and data grids.
- Visible focus rings and properly associated labels.
- Charts have text summaries or accessible tables.
- Status never relies on color alone.
- Use locale-aware number formatting and compact display only when the exact value remains accessible.
- Display absolute timestamp plus relative age where useful.
- Ensure long English, Romanian, and Russian video titles wrap or truncate with accessible full text.
- Do not use provenance or breakout color alone; include text and accessible explanation.

## 8. Responsive scope

- Supported target: desktop at 1280px and wider, with acceptance QA at approximately 1440px.
- Tablet and mobile-specific layout optimization and breakpoint QA are out of scope.
- Smaller widths may retain best-effort wrapping or scrolling, but they are not implementation or acceptance targets.

## 9. Planned decision-workflow redesign

The task-oriented information hierarchy and progressive-disclosure target for Phases 19–22 are specified in `13_DECISION_WORKFLOW_REDESIGN.md`. Its backlog tasks supersede the relevant current layouts only as each task is completed; they are not descriptions of current behavior.

The planned redesign remains English-only and targets desktop at approximately 1440px. Tablet and mobile-specific implementation are explicitly excluded.
