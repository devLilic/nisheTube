# Decision Workflow Redesign Plan

## 1. Purpose

This document converts the requested NisheTube UI/UX, calculation, and workflow changes into implementation-ready vertical slices. The target user journey is:

```text
Discover -> select -> validate -> compare -> decide -> monitor
```

Every analytical result page must progressively disclose information in this order:

1. verdict;
2. confidence;
3. data completeness;
4. sample stability;
5. principal evidence;
6. risks;
7. recommended next action;
8. technical details, provenance, and formulas.

The tasks below are planned work. They do not describe already implemented behavior until their matching backlog items are completed.

## 2. Delivery rules

- Follow the vertical-slice and verification rules in `AGENTS.md`, `08_BACKLOG.md`, and `09_ACCEPTANCE_AND_TESTING.md`.
- Preserve immutable runs, snapshots, observations, and historical score versions.
- Every new owner-visible aggregate, filter, search result, comparison, selection, notification, and bulk action must be owner-scoped.
- Browsing, filtering, sorting, comparing, and navigating stored data must not cause provider work.
- Any quota-consuming action must show its estimated cost, require an intentional action, be retry-safe, and prevent duplicate submission.
- Do not describe observed demand as YouTube search volume or opportunity as guaranteed profitability.
- Keep exact values, inputs, provenance, and formulas accessible through drawers, tooltips, tables, or raw-data views rather than leading with them.
- Every page and component must cover loading, empty, success, partial-data, and recoverable error states, plus quota and inaccessible-provider states where applicable.
- Server-side pagination and deterministic bounds are mandatory for growing lists.

## 3. Scope constraints and decision gate

- The application interface remains English-only. English, Romanian, and Russian apply to researched content, normalization, and long-text handling, not to interface localization.
- Tablet and mobile-specific versions and layout optimization are out of scope. Required visual verification is desktop-only at approximately 1440px.
- **Four-channel comparison remains a decision gate:** the current comparison contract supports two or three channels. `XCMP-02` must approve and document a four-channel bound in `10_DECISIONS.md` before widening storage-free comparison queries and layouts.

## 4. Planned vertical slices

### IA-01 — Task-oriented navigation and persistent research context

Replace the long flat navigation with `Research`, `Library`, and `Tools` groups. Use the product labels Dashboard, Discover themes, Validate a niche, Compare niches, History, Shortlist, Topic Workspaces, Watchlist, Projects, Ideas, Analyzer, Exports, and Settings. Remove the UI showcase from production navigation and keep design-system routes development-only. Make the active page unambiguous and persist the selected market, project, and workspace as owner preferences or safe session context across internal navigation.

Acceptance includes authorization of persisted context, invalid/archived/incompatible-context fallbacks, no provider calls, keyboard navigation, skip navigation, long-label behavior, and desktop verification at approximately 1440px.

### IA-02 — Global research bar, stored-data search, and compact quota

Build the authenticated top bar around a working market selector, active project/workspace context, owner-scoped global search across themes, videos, channels, and runs, a Shortlist shortcut, completed-run notifications, and a compact quota summary with a detailed popover. Quota copy must distinguish NisheTube-recorded calls, Google-estimated units, Search, and General API; it must never call units or requests “tokens.” Costs and limits come from versioned provider configuration, not UI constants.

Acceptance includes bounded/debounced search, owner isolation, persistent selection, stale/unavailable/exhausted quota states, completed-run notification read state, accessible popover/search interactions, and no provider work from search or navigation.

### SRCH-05 — Discover-market and validate-idea intake

Split intake into `Discover a market` and `Validate my idea`. Discovery accepts market, language, content format, period, and target channel size before generating initial themes. Validation accepts a term, market, period, result-depth preset, and shows the estimated cost and exact frozen parameters before `Start validation`. Add Fast scan, Balanced, Deep validation, Trend check, Emerging trend, Evergreen check, Small-channel opportunity, Long-form documentary, and Shorts opportunity presets. Keep order, duration, category, custom dates, format, and channel-size limits in Advanced filters.

Acceptance includes server validation through Form Requests, preserved values after errors, a disabled single-submit CTA, an honest `Creating run` state, no false validation error after creation, duplicate-submit idempotency, explicit observed-demand copy, quota guardrails, owner-scoped run creation, and complete responsive states.

### RSLT-01 — Research decision summary and lifecycle clarity

Reorder the Research result above the fold around verdict, score, confidence, field-level completeness, sample stability, observation time, freshness, sample size, deterministic interpretation, up to five principal evidence items, risks, and one recommended next action. Use explicit terminal labels for complete data, partial data, reduced confidence, and failure with preserved partial results. Show active stage, percentage, collected count, warnings, and an explicitly estimated ETA while work is active; collapse progress into Collection details after completion.

Acceptance includes deterministic non-AI verdict/interpretation rules, no contradictory statuses, no unsupported ETA precision, field-level coverage denominators, retained partial data, loading/active/empty/partial/error/success states, owner isolation, and no new provider calls while viewing.

### RSLT-02 — Research evidence inspection and actions

Make the five opportunity components compact and expandable. Add sortable video evidence for relevance, views/day, engagement, channel size, reach ratio, publish date, and breakout class; add quick filters for strictly relevant, channel-size bands, breakouts, long-form, Shorts, and complete metrics. Keep one primary action (`Add to shortlist` or `Validate further`), place secondary actions in a menu, and expose Discover, Compare, Repeat snapshot, Analyzer, Workspace, and Export handoffs. Move provider/query/parameters/endpoints/window/cache/formula/snapshot details into an accessible provenance drawer.

Acceptance includes server-bounded sort/filter queries, exact accessible values, safe return context, action authorization, no duplicated provider work, long multilingual titles, keyboard menus/drawers, and complete desktop states at approximately 1440px.

### DISC-04 — Discovery candidate evidence quality and multilingual normalization

Introduce a new immutable candidate-evidence formula version using frequency, unique channels, seed coverage, semantic coherence, typical median performance, outlier-resistant performance, small-channel proof, freshness, stability, relevance, and phrase quality. A validated candidate requires at least three videos, two channels, a coherent phrase, seed relevance, typical performance, calculated confidence, and no total dependence on one outlier. Normalize inflections, transliterations, synonyms, stop words, and mixed-language noise for English, Romanian, and Russian while retaining original phrases as evidence. Route below-threshold results to `Weak phrase signals` and never allow one video to earn a perfect candidate score.

Acceptance includes versioned immutable inputs/output, deterministic intelligible labels and suggested validation queries, language/original-evidence provenance, owner scoping, explicit insufficient states, and focused fixtures for single videos, incomplete phrases, synonyms, outliers, multilingual input, and minimum-channel boundaries.

### DISC-05 — Compact Discover decision table

Replace oversized candidate cards with a compact table showing theme, evidence score, confidence, videos, unique channels, small-channel proof, typical performance, stability, status, and primary Validate action. An expandable row shows description, included phrases, channels, videos, medians, outlier-free performance, channel-size distribution, sources, risks, and suggested query. Put Save, Dismiss, Favorite, Workspace, and Analyzer in the secondary menu and separate weak phrase signals from candidate niches.

Acceptance includes server pagination, deterministic sorting, accessible expandable rows and exact-value alternatives, no repeated actions, no provider calls on inspection, owner isolation, complete states, and desktop QA at approximately 1440px without excessive page scrolling.

### SCR-04 — Relevance, format, outlier, and stability evidence

Persist versioned per-result query relevance using title, semantic, category, topic, negative-term, language, and format signals; classify results as strictly relevant, related, weakly related, or off-topic. Persist separate Shorts/long-form coverage and compatible metrics. Add robust medians, percentiles, trimmed means, values before/after the top one to three outliers, and outlier dependency. Compare compatible snapshots for result/channel overlap, order stability, metric variance, and median variation, producing High/Medium/Low stability.

Acceptance includes immutable inputs, null-safe and minimum-sample behavior, complete-versus-strict sample outputs, no direct Shorts/long-form comparison, historical compatibility checks, deterministic tests for weak relevance, mixed formats, viral outliers, duplicate titles, missing fields, and snapshot instability, plus accessible UI evidence and warnings.

### SCR-05 — Opportunity and confidence v2

Create `niche-opportunity-v2` and `confidence-v2` without overwriting v1. Competition must include concentration/HHI, unique and repeated-channel share, large-channel share, sub-10K/sub-100K evidence, repeat winners, diversity, and result ownership. Reachability must include robust subscriber-normalized performance, small/mid-channel breakouts, repeat small-channel winners, performance without large channels, and visible-subscriber coverage. Creator viability must include repeat winners, cadence, format repeatability, robust performance, peer-channel count, access/budget complexity signals, Shorts dependence, and content lifespan. Demand must separate lifetime activity from observed snapshot change; without history it is `Observed activity`, not momentum. Freshness must separate demand freshness, content age, and underserved gap. Confidence must explain reductions from sample size, completeness, relevance, stability, diversity, subscriber visibility, format classification, outlier dependence, independent evidence, and missing history.

Acceptance includes versioned configuration, input summaries, calculation time, source snapshot pins, deterministic bounds/monotonicity, historical comparison warnings across versions, explicit inferred/estimated provenance, strict-sample and full-sample score views, and focused formula/UI tests covering all specified edge cases.

### PROF-01 — Estimated profitability fit

Add a decision aid separate from Opportunity score for advertiser fit, affiliate potential, sponsor potential, production cost, repeatability, copyright risk, location/access needs, seasonality, long-form monetization suitability, and Shorts dependence. Every input not supplied by a public provider must be explicitly user-entered, inferred, or estimated with a source and confidence. Profitability fit must never claim CPM, revenue, or guaranteed profit.

Acceptance includes owner-scoped/versioned inputs, explainable null/unknown handling, editable assumptions without mutating historical opportunity scores, comparison support, complete UI states, and tests that prohibit opportunity/profitability conflation.

### DASH-04 — Decision Cockpit

Turn Dashboard into an owner-scoped decision cockpit led by one recommended next action, three to five top opportunities, Shortlist status counts, and Watchlist alerts. Each opportunity includes market, opportunity, confidence, stability, completeness, status, principal risk, and next action. Move active runs, recent research, errors, quota, system status, and cleanup below the decision area; remove coming-soon cards and actionless technical detail from the top.

Acceptance includes deterministic recommendation priority, bounded/N+1-safe queries, honest empty/partial/error states, no provider work, owner isolation, and keyboard/responsive QA.

### SHORT-01 — Shortlist and niche comparison

Promote Favorites-backed niche decisions into a dedicated Shortlist aggregate/surface with candidate, opportunity, confidence, stability, profitability fit, risk, validation status, decision, and next step. Support comparison of two to five compatible candidates and explain `Go`, `Validate further`, `Monitor`, or `Avoid` using evidence and risks rather than a single threshold. Favorites may remain an internal bookmark mechanism but must not masquerade as a decision state.

Acceptance includes owner-scoped persistence and policies, idempotent add/remove, compatibility warnings, immutable evidence references, bounded comparison queries, complete states, accessible two-to-five-column alternatives, no provider work, and focused authorization/domain/frontend tests.

### XPLR-02 — Explore productivity and score clarity

Show search, entity type, market, and sort as primary filters; move detailed filters into an Advanced drawer; add high-confidence, complete-data, small-channel-breakout, recent, favorited, curated, needs-validation, and validated presets. Provide table/card views with entity-specific columns. Remove a parent run score from entity presentation or label it `Parent niche score`. Preserve filters and scroll on Analyzer return, support saved filter presets, and prepare selection hooks for bulk actions.

Acceptance includes owner-scoped saved filters, server pagination, bounded queries, deterministic return state, zero provider calls from filters/views, exact nullable metrics, accessible drawer/table/cards, and responsive tests.

### ANA-06 — Analyzer decision hierarchy and topic-quality guardrails

Organize Analyzer into Summary, Content patterns, Channel, and Raw data. Replace `Save & organize` as a primary tab with persistent Shortlist/Workspace actions. Translate technical metrics into plain-language conclusions while preserving exact formulas in tooltips/raw data. Enforce minimum semantic confidence and frequency, remove meaningless term pairs, group synonyms, and distinguish topic, title fragment, topic confidence, profile confidence, and coverage.

Acceptance includes stored-data-only reorganization, no duplicated calculations, complete tab/pattern states, accessible keyboard/focus behavior, provenance/version access, long multilingual titles, and desktop tests at approximately 1440px.

### XCMP-02 — Peer-aware comparison of up to four channels

After recording the decision gate, extend channel comparison to two through four distinct channels. Add subscribers, median views, median views/day, engagement, repeat breakouts, cadence, Shorts share, niche concentration, observed snapshot growth, and freshness. Let users select a peer group, warn on incompatible periods/versions/samples, separate channel-size bands, highlight the strongest comparable value per metric, and provide evidence-based labels such as reachable competitor, dominant incumbent, unstable performer, or useful inspiration.

Acceptance includes strict owner authorization, deterministic four-channel bounds, nullable values and comparability guards, no provider calls, accessible non-color winner indicators, exact tables at responsive widths, and no unsupported opportunity score or recommendation claim.

### HIST-04 — Searchable history and direct snapshot comparison

Add owner-scoped search and filters for market, status, score, confidence, date, project, and workspace. Render parameters in readable form. Allow direct checkbox selection of two compatible runs and compare score, confidence, median velocity, competition, reachability, sample overlap, added/removed videos, new breakout channels, and stability. When no compatible prior snapshot exists, offer `Repeat with same parameters` with exact frozen-parameter confirmation.

Acceptance includes deterministic pagination, pair validation, version/parameter warnings, null-safe deltas, no provider call until confirmed repeat, duplicate-submit protection, and complete states.

### TOPIC-02 — Topic Workspace decision canvas

Extend Topic Workspace with hypothesis, target audience, selected market, opportunity summary, evidence for/against, competitors, outliers, counterexamples, content angles, monetization hypotheses, risks, decision status, and next validation step. Use Exploring, Validating, Promising, Rejected, Monitoring, and Approved statuses. Group evidence by role, show compact immutable metric summaries, support bulk evidence addition, notes, tags, and compact workflow history. Never default to a market-incompatible workspace; cross-market additions require confirmation.

Acceptance includes owner-scoped persistence/policies, no copied mutable metrics, explicit compatibility warnings, auditable workflow changes, server-bounded evidence/history, complete states, and focused backend/frontend tests.

### WATCH-02 — Watchlist alerts and monitoring controls

Show entity, type, market, last snapshot, latest change, alert status, next refresh, and linked workspace in a compact table. Add owner-configured alerts for views/day increases, subscriber growth, new breakouts, score changes, new competitors, topic shifts, and stale data. Support bulk refresh with quota confirmation, pause monitoring, alert filters, largest-change sort, and integration with the notification center.

Acceptance includes versioned alert thresholds, observation-only comparisons, owner isolation, idempotent quota-aware refresh dispatch, no invented schedule execution while the local PC/worker is unavailable, complete notification/monitor states, and focused tests.

### LIB-04 — Project and idea decision context

Extend Projects with purpose, market, themes, Shortlist, workspaces, status, recent activity, and decisions. Extend Ideas so a saved item may optionally reference a topic, candidate, evidence video, workspace, format, audience, and Draft/Researching/Ready/Produced/Rejected status while preserving its existing exact saved-comment source semantics.

Acceptance includes owner-scoped optional relations, foreign/incompatible target rejection, bounded list/detail queries, retention-safe source behavior, complete edit/filter states, and focused schema/domain/UI tests.

### EXP-04 — Selection-aware exports

Allow exports of filtered rows, explicit selections, a Shortlist, a niche/channel comparison, and a Topic Workspace, with technical data included or excluded. Always show dataset scope and require confirmation before queueing. The job list shows type, status, frozen filters, creation time, size, expiry, and download.

Acceptance includes immutable owner-scoped selection manifests, no hidden expansion of filters after confirmation, safe Unicode/formula handling, bounded/queued generation, authorization at create/run/download, complete states, and focused tests.

### SET-05 — Consolidated preferences and formatting

Group Settings into Profile, Default market, Default search depth, YouTube integration, Quota, Notifications, Data retention, Formatting, and Advanced. Add default period, peer group, Shorts handling, numeric format, and notification preferences. The interface remains English-only and no UI-language selector is introduced.

Acceptance includes validated owner preferences, backward-compatible defaults, UTC storage/user-timezone display, locale-aware numbers with exact access, no secret exposure, complete states, and focused tests.

### LAND-01 — NisheTube landing and authentication entry

Replace the default Laravel landing experience. Redirect authenticated users to Dashboard. Show unauthenticated local users what NisheTube does, which public/owner-provided data it uses, what it cannot guarantee, local-installation/privacy context, and Login/Register actions. Remove Laravel, Laracasts, and deployment marketing references.

Acceptance includes loopback registration rules, guest/authenticated redirects, accessible responsive content, no secret/config leakage, English copy consistency, and focused route/frontend tests.

### JOB-01 — Controlled background jobs and notifications

Expose safe failure reasons, preserved partial data, estimated ETA, cancel-before-start, controlled retry, completed-run notification, and a worker-not-running indicator across queued workflows. Prevent accidental parallel retry and quota duplication. Use the global notification center for meaningful completions/failures without producing a toast for every minor action.

Acceptance includes state-machine guards, idempotent cancel/retry, ownership rechecks in queued work, quota ledger integrity, safe error copy, notification read state, worker-health limitations, and complete UI/tests.

### PERF-02 — Stored-interface performance and navigation continuity

Apply server pagination, virtualization only where pagination is insufficient, lazy thumbnails, debounced search, cached owner-filter metadata, skeletons, focused partial reloads, scroll restoration, safe prefetch, and sticky tables to the redesigned surfaces. Measure before/after query count, payload size, render cost, and interaction responsiveness rather than adding speculative optimization.

Acceptance includes explicit performance budgets per changed surface, no N+1 regressions, bounded memory/payloads, invalidation-safe cached filters, preserved accessibility, and focused performance/frontend tests.

### BULK-01 — Cross-surface bulk actions

Add a shared bounded selection model for videos, channels, candidates, and other eligible stored evidence. Support Workspace, Shortlist/Favorite, Dismiss, Export, Compare, and Watch actions where semantically valid. Require explicit confirmation for destructive or quota-consuming actions and show partial per-item outcomes without losing the selection unexpectedly.

Acceptance includes owner re-authorization per target, maximum selection bounds, idempotency, mixed/invalid/foreign target handling, quota preview, server-side transaction/job behavior, accessible keyboard selection, and focused tests.

### A11Y-02 — Terminology, accessibility, density, and desktop hardening

Standardize English user-facing terms for score, confidence, completeness, stability, observed performance, opportunity, and validation. Keep jargon in tooltips/help/raw data. Audit contrast, focus, keyboard flow, skip navigation, labels, non-color status, chart alternatives, tables, target sizes, zoom, and desktop density. Desktop uses a compact sidebar and wide tables. Tablet and mobile-specific layout work is excluded.

Acceptance includes documented English terminology, WCAG-oriented audits, 200% zoom, long English/Romanian/Russian evidence text, desktop checks at approximately 1440px, and focused accessibility/frontend tests.

### QA-02 — Decision-workflow acceptance and regression suite

Add focused calculation, Discover, UI workflow, and cross-stack acceptance coverage after the preceding slices. Fixtures must cover viral outliers, channel dominance, distinct channels with high concentration, missing subscribers, mixed formats, instability, weak relevance, one and multiple small-channel breakouts, duplicate titles, multilingual data, and insufficient samples. Discover tests must reject one-video perfect scores and incoherent phrases. UI tests must cover preserved filters/scroll, single CTAs, non-contradictory statuses, explained incomplete data, keyboard operation, duplicate-submit prevention, honest loading, and responsive breakpoints.

Acceptance requires a user to move from theme to verdict in at most five meaningful steps; the first result viewport exposes verdict, confidence, completeness, stability, risk, and next action; candidates cannot overclaim weak evidence; results compare directly; opportunity and profitability remain distinct; v2 formulas preserve v1 history; quota labels/costs match configuration; production navigation hides the UI showcase; and the focused cross-stack suite plus documentation pass.

## 5. Requirement-to-task traceability

| Requested sections                                            | Backlog task(s)                           |
| ------------------------------------------------------------- | ----------------------------------------- |
| 1. Main navigation                                            | IA-01                                     |
| 2. Global top bar                                             | IA-01, IA-02, JOB-01                      |
| 3. Dashboard cockpit                                          | DASH-04                                   |
| 4. Discover/Validate intake                                   | SRCH-05                                   |
| 5. Search result                                              | RSLT-01, RSLT-02                          |
| 6. Discover redesign                                          | DISC-04, DISC-05                          |
| 7. Explore                                                    | XPLR-02, BULK-01, PERF-02                 |
| 8. Analyzer                                                   | ANA-06                                    |
| 9. Channel Compare                                            | XCMP-02                                   |
| 10. History/snapshots                                         | HIST-04                                   |
| 11. Topic Workspaces                                          | TOPIC-02                                  |
| 12. Watchlist                                                 | WATCH-02, JOB-01                          |
| 13. Projects/Shortlist/Favorites/Ideas                        | SHORT-01, LIB-04                          |
| 14. Exports                                                   | EXP-04                                    |
| 15. Settings                                                  | SET-05                                    |
| 16. Landing/auth                                              | LAND-01                                   |
| 17–20. Stability/relevance/formats/outliers                   | SCR-04                                    |
| 21–26. Opportunity components/confidence                      | SCR-05                                    |
| 27–28. Candidate scoring/normalization                        | DISC-04                                   |
| 29. Profitability                                             | PROF-01                                   |
| 30. Score versioning                                          | SCR-04, SCR-05, PROF-01                   |
| 31. Shortlist comparison                                      | SHORT-01                                  |
| 32. Provenance                                                | RSLT-02, ANA-06, EXP-04                   |
| 33. Background jobs                                           | JOB-01                                    |
| 34. Interface performance                                     | PERF-02                                   |
| 35. Bulk actions                                              | BULK-01                                   |
| 36. Notifications                                             | IA-02, JOB-01, WATCH-02                   |
| 37–39. English terminology/accessibility/desktop presentation | A11Y-02, SET-05                           |
| 40–43. Tests/general acceptance                               | QA-02 plus focused coverage in every task |

## 6. Recommended implementation order

Implement the matching backlog phases in order. Within a phase, keep the dependency order recorded in `08_BACKLOG.md` and `TASK_STATUS.md`. Do not start formula v2 until the evidence-quality inputs in `SCR-04` are frozen, and do not complete `QA-02` until all preceding redesign slices are complete.
