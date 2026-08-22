# RDSN-01 page and component migration matrix

This is the complete production-page inventory at the RDSN-01 audit point. All routes retain their domain contracts. `resources/js/pages/design-system.tsx` is an internal verification route and is listed separately.

No page may invent local glass recipes. “Surface” below means the outer semantic region; children use plain layout wrappers or `content-solid`, never a second glass material.

## Shared shell and component migration

| Current implementation                                | Replacement / retained role                                                  | Migration rule                                                                                                    |
| ----------------------------------------------------- | ---------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `AppShell`, `AppSidebarLayout`, `AppHeaderLayout`     | `AppCanvas` + one responsive shell                                           | One authenticated shell; header-only variants must share the same command/navigation primitives.                  |
| `AppSidebar` + shadcn `Sidebar`                       | `GlassNavigationRail` / mobile `GlassSheet`                                  | Full grouped IA at every width; 248/72px desktop rail; no bottom dock.                                            |
| `AppSidebarHeader`, `AppHeader`                       | `GlassCommandBar`                                                            | Search, Context, quota, notification, user menu; remove starter-kit repository/documentation affordances.         |
| `PageContainer`                                       | AppCanvas content grid                                                       | Max 1680px, 12/8/1-column responsive contract.                                                                    |
| `PageHeader`, `Heading`, `Breadcrumbs`                | contextual header pattern                                                    | Title, evidence context, actions by priority; breadcrumbs become secondary location, not a separate chrome strip. |
| `Card` used as generic section                        | `ContentPanel`, `GlassPanel`, or semantic row                                | Select by purpose; no universal card styling and no nested glass.                                                 |
| `MetricCard`                                          | `MetricTile`                                                                 | Exact value + basis + confidence/unavailable reason; four maximum in primary row.                                 |
| `data-state`, `PartialDataBanner`                     | shared region-state grammar                                                  | Loading/empty/partial/success/error local to affected region.                                                     |
| `Table` wrappers                                      | `DataTableFrame`                                                             | Caption, exact numeric alignment, contained overflow, local state and pagination.                                 |
| repeated favorite/explore/analyzer cards              | `EvidenceRow` in one `ContentPanel`                                          | No shadow/blur per row; long evidence wraps.                                                                      |
| `analytics-glossary`, `metric-hint`, provenance cards | `InspectorPanel`                                                             | Sticky desktop; GlassSheet at 1024/390; keyboard accessible.                                                      |
| chart cards                                           | `ChartFrame`                                                                 | Exact-value alternative, range, source, sample, non-color legend.                                                 |
| comments/thumbnail progress cards                     | `AsyncSection`                                                               | Local state and polling; no page navigation/reload.                                                               |
| shadcn `Dialog`, `Popover`, `Sheet`, dropdown         | `GlassPopover` / `GlassSheet` visual layer on retained accessible primitives | Exactly one glass surface, opaque fallback, focus restoration.                                                    |
| `appearance-tabs`                                     | removed from production UI                                                   | Light-only; stored historical preference is ignored, not deleted.                                                 |

## Discover and Validate

| Production page             | Research job                  | Primary composition                                           | Responsive behavior                                     | Required system primitives                                                      |
| --------------------------- | ----------------------------- | ------------------------------------------------------------- | ------------------------------------------------------- | ------------------------------------------------------------------------------- |
| `pages/dashboard.tsx`       | resume and prioritize work    | next-action hero, four metrics, trend, recent activity        | 12-col split → stacked 8-col → one-column               | GlassCommandBar, GlassPanel, ContentPanel, MetricTile, ChartFrame, EvidenceRow  |
| `pages/discovery/index.tsx` | generate candidate themes     | seed/context form, method/quota inspector, recent run context | side inspector → sheet; form stacks on mobile           | ContentPanel, GlassPanel/Sheet, Async progress region                           |
| `pages/discovery/show.tsx`  | evaluate candidates           | run status, candidate collection, shortlist/validate actions  | evidence rows collapse metrics to definition lists      | EvidenceRow, DataTableFrame/ContentPanel, InspectorPanel, state grammar         |
| `pages/explore/index.tsx`   | inspect all stored evidence   | filter inspector + dense result collection                    | inspector → sheet; rows reflow without individual cards | GlassPanel/Sheet, EvidenceRow, ContentPanel, Pagination, state grammar          |
| `pages/research/create.tsx` | validate a niche              | dominant query form + sticky quota/method preview             | quota panel becomes row then inline block               | ContentPanel, GlassPanel, GlassSheet, Async progress                            |
| `pages/research/show.tsx`   | interpret a run               | contextual header, tab rail, verdict, evidence, provenance    | inspector → sheet; tab rail scrolls                     | GlassCapsule, ContentPanel, MetricTile, EvidenceRow, ChartFrame, InspectorPanel |
| `pages/history/index.tsx`   | revisit completed/active runs | compact run collection + filters/actions                      | table → contained scroll/labelled rows                  | DataTableFrame, EvidenceRow, state grammar                                      |
| `pages/history/compare.tsx` | compare immutable snapshots   | paired context, deltas, exact before/after evidence           | synchronized columns → stacked paired metric rows       | ContentPanel, MetricTile, ChartFrame, InspectorPanel                            |

## Analyze

| Production page              | Research job                  | Primary composition                                                 | Responsive behavior                                         | Required system primitives                                                       |
| ---------------------------- | ----------------------------- | ------------------------------------------------------------------- | ----------------------------------------------------------- | -------------------------------------------------------------------------------- |
| `pages/analyzer/index.tsx`   | launch/open analysis          | intake form, recent videos/channels                                 | two collections stack; form controls stack                  | ContentPanel, EvidenceRow, GlassPanel, state grammar                             |
| `pages/analyzer/show.tsx`    | inspect video/channel profile | contextual header, sticky tabs, summary/evidence, local enrichments | inspector → sheet; tabs scroll; local async state           | GlassCapsule, ContentPanel, MetricTile, ChartFrame, InspectorPanel, AsyncSection |
| `pages/analyzer/compare.tsx` | compare up to four channels   | selector, synchronized baselines/deltas, evidence                   | 4-col → 2×2 → paired vertical comparisons; no page overflow | DataTableFrame, MetricTile, ChartFrame, InspectorPanel                           |

Feature-level mappings within Analyzer:

| Current feature                                                  | Target pattern                                                                  |
| ---------------------------------------------------------------- | ------------------------------------------------------------------------------- |
| `analyzer-decision-summary`, `analyzer-profile`                  | one `glass-content` summary followed by `content-solid` evidence sections       |
| `analyzer-profile-tabs`                                          | sticky `GlassCapsule` tab rail; selected tab has text + shape + `aria-selected` |
| `analyzer-comments`, `thumbnail-analysis`                        | independent `AsyncSection` regions                                              |
| `analyzer-channel-cohort`, `topic-performance`, `growth-history` | `ChartFrame` + exact data alternative                                           |
| `audience-signals`, `topic-profile`, `transcript-structure`      | `EvidenceRow`/ContentPanel collection + InspectorPanel limitations              |
| `analyzer-raw-data`                                              | dense `content-solid` with monospace data and copy controls                     |
| `analyzer-curation`                                              | one sticky action tray; must yield while a sheet/dialog is open                 |
| `cross-channel-comparison`                                       | synchronized comparison grid with common baseline and sample coverage           |

## Organize

| Production page                     | Research job               | Primary composition                                  | Responsive behavior                                          | Required system primitives                            |
| ----------------------------------- | -------------------------- | ---------------------------------------------------- | ------------------------------------------------------------ | ----------------------------------------------------- |
| `pages/shortlist/index.tsx`         | decide what to validate    | shortlist collection + inspect/validate actions      | filters/actions → sheet/overflow; rows stack metrics         | EvidenceRow, ContentPanel, InspectorPanel             |
| `pages/library/favorites/index.tsx` | retrieve saved evidence    | collection, tag/filter context, curation actions     | shared rows; actions collapse by priority                    | EvidenceRow, GlassPanel/Sheet, state grammar          |
| `pages/library/projects/index.tsx`  | organize projects          | project collection + create/edit/archive             | grid → list; no floating glass card per project              | ContentPanel collection, GlassPopover/Dialog          |
| `pages/library/projects/show.tsx`   | operate one project        | project context, queries, favorites, discovery runs  | sections stack; contextual tools → sheet                     | GlassPanel, ContentPanel, EvidenceRow, InspectorPanel |
| `pages/topics/index.tsx`            | browse topic workspaces    | workspace collection + lifecycle filters             | grid/list → single collection                                | ContentPanel, EvidenceRow, state grammar              |
| `pages/topics/show.tsx`             | synthesize topic evidence  | workspace header, evidence, launch actions           | inspector/actions → sheets; evidence stays first             | ContentPanel, EvidenceRow, InspectorPanel             |
| `pages/watchlist/index.tsx`         | monitor entities           | target collection, refresh state, observation deltas | dense table → contained rows; one sticky refresh summary max | DataTableFrame, AsyncSection, MetricTile              |
| `pages/ideas/index.tsx`             | curate saved comment ideas | idea collection, status/edit actions, source trace   | source/detail → sheet; collection stays stable               | EvidenceRow, ContentPanel, InspectorPanel             |

## Manage

| Production page                  | Research job                  | Primary composition                                           | Responsive behavior                                     | Required system primitives                                  |
| -------------------------------- | ----------------------------- | ------------------------------------------------------------- | ------------------------------------------------------- | ----------------------------------------------------------- |
| `pages/exports/index.tsx`        | create/download/retry exports | export composer + job collection                              | form and jobs stack; progress local                     | ContentPanel, AsyncSection, DataTableFrame                  |
| `pages/settings/profile.tsx`     | identity                      | settings navigation + stable form                             | left nav → navigation stack                             | ContentPanel, settings navigation, state grammar            |
| `pages/settings/preferences.tsx` | research/UI defaults          | categorized form + explanations                               | left nav → stack; descriptions remain adjacent          | ContentPanel, GlassPanel only for contextual help           |
| `pages/settings/security.tsx`    | password/security             | regular and critical zones                                    | one-column; destructive separation retained             | ContentPanel regular/critical, Dialog                       |
| `pages/settings/youtube.tsx`     | API key/test/quota            | secure form, integration status, quota evidence               | stack; secret input never shown in screenshots/fixtures | ContentPanel, MetricTile, AsyncSection, Alert               |
| `pages/settings/appearance.tsx`  | legacy appearance route       | explain light-only state or redirect according to APP-01      | no theme selector at any width                          | ContentPanel quiet                                          |
| `pages/settings/retention.tsx`   | preview/execute cleanup       | policy explanation, preview, destructive confirmation/history | stack; confirmation remains explicit                    | ContentPanel critical, DataTableFrame, Dialog, AsyncSection |

## Public and authentication

| Production page                   | Primary composition                                                              | Responsive behavior                                   | Required system primitives                                                 |
| --------------------------------- | -------------------------------------------------------------------------------- | ----------------------------------------------------- | -------------------------------------------------------------------------- |
| `pages/welcome.tsx`               | minimal glass header, evidence-led hero, realistic product preview, trust points | preview stacks below hero; no decorative empty panels | AppCanvas, GlassCommandBar-lite, glass-content hero, content-solid preview |
| `pages/auth/login.tsx`            | focused auth form + locale switch                                                | single expressive panel with safe mobile gutters      | AppCanvas, one glass-content auth panel, ContentPanel form body            |
| `pages/auth/register.tsx`         | account form + password requirements                                             | requirements remain visible; fields one-column        | same auth contract                                                         |
| `pages/auth/forgot-password.tsx`  | recovery form/status                                                             | one column; success replaces only form body           | same auth contract + state grammar                                         |
| `pages/auth/reset-password.tsx`   | reset form                                                                       | one column                                            | same auth contract                                                         |
| `pages/auth/confirm-password.tsx` | protected-action confirmation                                                    | concise one-column critical context                   | same auth contract                                                         |
| `pages/auth/verify-email.tsx`     | verification status/actions                                                      | one column; resend status local                       | same auth contract + state grammar                                         |

Auth layout migration:

- `auth-simple-layout`, `auth-card-layout`, and `auth-split-layout` converge on one responsive auth shell.
- No nested glass panel inside a glass split pane.
- Locale selection is reachable before the first form control.
- Background art is static, nonsemantic, and suppressed in forced colors/reduced transparency.

## Internal verification route

| Page                      | Required RDSN-02 content                                                                                                                                                                                                                                |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `pages/design-system.tsx` | all 14 named primitives; material comparison and opaque fallback; typography/geometry/tokens; interaction focus; Romanian expansion; long RO/RU evidence; loading/empty/partial/success/error; 1440/1024/390 frames; forced-colors/reduced-motion notes |

The route remains local-environment and authenticated/verified only. All displayed evidence is clearly marked fixture data and contains no secrets or real user content.

## Information architecture contract

| Group    | Destinations                                             |
| -------- | -------------------------------------------------------- |
| Discover | Dashboard, Discovery, Explore                            |
| Validate | New research, History, Compare niches                    |
| Analyze  | Analyzer, Compare channels/videos                        |
| Organize | Shortlist, Favorites, Projects, Topics, Watchlist, Ideas |
| Manage   | Exports, Settings                                        |

Known corrections are mandatory during shell migration:

- Compare niches must not link to `/analyzer/compare`.
- Shortlist must link to `/shortlist`, not `/favorites`.
- starter-kit Repository/Documentation actions and stale “coming soon” text are removed from production chrome.

## Coverage review

- Production page files audited: 33.
- Internal verification page audited: 1.
- Shared component families audited: shell/navigation, page hierarchy, form/dialog primitives, metric/status, tables, evidence collections, charts, asynchronous sections, settings, and auth.
- All production pages map to a page archetype and named redesign primitives.
- No page is authorized to add a fourth material level or a page-local glass recipe.
