# RDSN-01 responsive page prototypes

These low-fidelity prototypes are implementation contracts, not decorative mockups. Each block fixes hierarchy, responsive transformation, material choice, and state placement. The shared shell and token details are defined in [the Liquid Glass design contract](RDSN_LIQUID_GLASS_DESIGN_CONTRACT.md).

Legend: `GC` = glass-command, `GP` = glass-panel, `GX` = glass-content, `CS` = content-solid, `→ sheet` = moves into GlassSheet at that width.

## Shared viewport frames

### 1440px

```text
┌──────────────────────────────────────────────────────────────────────────────┐
│ 16px  ┌─ GC navigation 248 ─┐  24px  ┌─ GC command bar ──────────────────┐ │
│       │ complete grouped IA │        │ Search | Context | Quota | User   │ │
│       │                      │        └────────────────────────────────────┘ │
│       │                      │        ┌─ 12-column content, max 1680 ─────┐ │
│       │                      │        │ main 8–9 cols │ inspector 3–4      │ │
│       └──────────────────────┘        └────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────────────────┘
```

### 1024px

```text
┌──────────────────────────────────────────────────────────────────────┐
│ ┌GC rail 72┐  ┌─ GC command: Search | Context | Quota ────────────┐ │
│ │ icons     │  └───────────────────────────────────────────────────┘ │
│ │ + tooltip │  ┌─ 8-column content ───────────────────────────────┐ │
│ │           │  │ main full width; nonessential inspector → sheet │ │
│ └───────────┘  └───────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────────┘
```

### 390px

```text
┌──────────────────────────────────────┐
│ GC command 56: ☰  Page/Context  ⌕  Q│
├──────────────────────────────────────┤
│ one-column content, 16px gutters     │
│ filters / provenance / IA → sheet    │
│ optional one sticky action bar       │
└──────────────────────────────────────┘
```

## Dashboard

### 1440px prototype

```text
Command bar: global search | România · Română | quota 8/10 | notifications

GX Next best action (8 cols)                 GP Research context (4 cols)
┌────────────────────────────────────────┐   ┌──────────────────────────────┐
│ Continuă validarea „studio mic...” 72% │   │ Market / project / freshness │
│ [Continuă cercetarea] [Vezi dovezile]  │   │ Last sync / incomplete runs  │
└────────────────────────────────────────┘   └──────────────────────────────┘

Metric group, one GX surface: [Opportunity] [Observed demand] [Competition] [Confidence]

CS Trend + exact table (8 cols)              CS Recent activity (4 cols)
```

### 1024px transformation

- Hero spans eight columns; Research context is a compact row below it, not a side card.
- Four MetricTiles form a 2×2 grid.
- Trend and activity stack; trend keeps exact-value table toggle.
- Context controls live in the command-bar Context capsule.

### 390px transformation

- Next action is first; primary button full-width, secondary action text-link below.
- Metrics become a horizontally snap-free 2×2 grid; labels may wrap to two lines.
- Trend defaults to the exact compact list; chart is an optional disclosure.
- Recent activity uses EvidenceRows with one visible action.

### State placement

| State   | Dashboard behavior                                                                    |
| ------- | ------------------------------------------------------------------------------------- |
| loading | hero, four metric slots, and two activity rows reserve final height                   |
| empty   | hero says `Începe prima validare de nișă`; secondary regions explain what will appear |
| partial | banner is attached to the affected metric/trend group and names coverage              |
| success | next action, four metrics, trend, and recent activity visible                         |
| error   | failed widget remains local; other dashboard evidence stays usable                    |

## Validate / Search

### 1440px prototype

```text
Page title: Validează o nișă

GX Search composer (8 cols)                  GP Quota & method (4 cols, sticky)
┌────────────────────────────────────────┐   ┌──────────────────────────────┐
│ Termen de căutare [52px input         ]│   │ Search tokens 1 / regular 20 │
│ Market [RO] Language [ro] Depth [Std]  │   │ What will be collected       │
│ Advanced filters ▸                     │   │ [Rulează cercetarea]          │
└────────────────────────────────────────┘   └──────────────────────────────┘

CS Recent/reusable query evidence (8 cols)
```

### 1024px transformation

- Search composer spans eight columns.
- Quota preview becomes a sticky horizontal `glass-panel` below the form fields.
- Advanced filters open in a right GlassSheet.
- Primary action remains visible without covering validation errors.

### 390px transformation

- 52px search input and full-width action.
- Market, research language, and depth are three explicit stacked controls; they are never inferred from UI locale.
- Advanced filters open a bottom GlassSheet with Apply and Reset.
- Quota preview is an inline bordered region immediately above the action.

### State placement

| State   | Validate behavior                                                                           |
| ------- | ------------------------------------------------------------------------------------------- |
| loading | submit area becomes inline progress; form values remain visible and locked only as required |
| empty   | no recent queries is a quiet inline hint, never the page hero                               |
| partial | run progress identifies completed/failed stages and retained evidence                       |
| success | navigates once to the created run; subsequent progress updates remain within run page       |
| error   | validation beside fields; provider/quota error in quota panel with recovery action          |

Romanian stress label: `Analizează oportunitatea nișei înainte de a consuma cota YouTube` may wrap above the action and must not squeeze quota arithmetic.

## Research result

### 1440px prototype

```text
GC contextual header: query | RO · ro | completed Aug 21 | Save | Compare
GC capsule tab rail: [Verdict] [Evidence] [Videos] [Channels] [Provenance]

GX Verdict & confidence (8 cols)              GP Inspector (4 cols, sticky)
┌─────────────────────────────────────────┐   ┌─────────────────────────────┐
│ Opportunity 78/100 · moderate evidence │   │ Formula version / sample    │
│ What supports it / what limits it       │   │ freshness / limitations     │
└─────────────────────────────────────────┘   └─────────────────────────────┘

CS evidence table/list (8 cols)                Inspector continues
```

### 1024px transformation

- Contextual header wraps actions into an overflow menu after the primary Save action.
- Tab rail scrolls within itself with visible edge cue; selected tab stays in view.
- Inspector moves to a GlassSheet opened by `Formula și proveniență`.
- Evidence spans full width.

### 390px transformation

- Query may occupy two lines; source title evidence may occupy three.
- Context metadata becomes capsules under the title.
- Tabs use horizontally scrollable compact capsules with keyboard controls.
- Verdict shows exact score, confidence, sample, and one-sentence interpretation before charts.
- Evidence uses compact rows; metrics appear in a definition list, not a four-column mini-grid.

### State placement

| State   | Research result behavior                                                                                   |
| ------- | ---------------------------------------------------------------------------------------------------------- |
| loading | contextual header remains; progress stage and retained evidence slots reserve geometry                     |
| empty   | completed run with no returned evidence states `Nu au fost observate rezultate eligibile`, not zero demand |
| partial | top summary and each affected tab display coverage and missing source                                      |
| success | verdict, exact evidence, freshness, and provenance are mutually traceable                                  |
| error   | run record and completed stages remain visible; retry is scoped to eligible stage                          |

Long evidence fixture must render unaltered: `Cum am construit un studio YouTube într-un apartament foarte mic: lumini, sunet, fundal și costurile reale după șase luni`.

## Explore

### 1440px prototype

```text
Page header + result count + Save preset

GP Filter inspector (3 cols, sticky)   CS Result collection (9 cols)
┌───────────────────────────────┐      ┌─────────────────────────────────────┐
│ entity/source/market/topic    │      │ EvidenceRow: thumb + title + metrics│
│ performance/score/confidence  │      ├─────────────────────────────────────┤
│ observed range/organization   │      │ EvidenceRow                         │
│ [Apply] [Reset]               │      ├─────────────────────────────────────┤
└───────────────────────────────┘      │ EvidenceRow                         │
                                       └─────────────────────────────────────┘
```

### 1024px transformation

- Filter inspector moves to a right GlassSheet.
- A compact filter-summary row shows applied filters and result count.
- Results use one shared ContentPanel; metrics remain aligned in columns.
- Saved presets open in a GlassPopover from the summary row.

### 390px transformation

- `Filtre (4)` opens a full-height GlassSheet; Reset and Apply remain in its footer.
- Sort is a separate compact control beside result count.
- EvidenceRow becomes: title (two to three lines), source/status line, 2×2 definition list, timestamp, one primary action plus overflow.
- No individual glass card or shadow per result.

### State placement

| State   | Explore behavior                                                                                 |
| ------- | ------------------------------------------------------------------------------------------------ |
| loading | filter summary remains interactive; six result-row skeletons reserve thumbnail and metric widths |
| empty   | applied filters stay visible; empty region offers Reset filters or Start research                |
| partial | per-row badges and collection banner distinguish unavailable fields from zero                    |
| success | exact score, confidence, performance, subscribers, timestamp, and source are aligned             |
| error   | results remain when stale/retained; filter error is local and retryable                          |

Numeric example: `0` = observed zero, `—` only as compact visual with `Unavailable` accessible name, `Not collected` = collection never requested, `Insufficient sample` = formula withheld.

## Analyzer

### 1440px prototype

```text
GC contextual header: channel/video | fresh/cached | Compare | Save | Refresh
GC capsule tab rail: [Summary] [Content] [Audience] [Channel] [Raw Data]

GX Decision summary (8 cols)                GP Inspector (4 cols, sticky)
CS Baselines and outliers (8 cols)          formula / provenance / exclusions
CS Comments — AsyncSection (8 cols)
CS Thumbnail patterns — AsyncSection (8 cols)
CS Raw evidence within selected tab
```

Only rail + command bar + tab rail/inspector may blur. When Inspector is visible, the tab rail uses an opaque high-contrast surface if it would become a fourth blur.

### 1024px transformation

- Analyzer tabs stay sticky below the command bar.
- Inspector moves to a GlassSheet.
- Compare/Refresh actions enter an overflow menu; Save remains visible.
- Comments and Thumbnail patterns keep their own reserved geometry and polling state.

### 390px transformation

- Header shows entity title, status, and one primary action; remaining actions are in overflow.
- Tabs scroll within their rail.
- Each AsyncSection has heading, quota note where relevant, status text, progress/skeleton, and Retry.
- Starting Comments or Thumbnail patterns never changes the active tab, scroll, focus, expanded rows, or page of comments.
- The sticky curation bar may exist only when the page has no other bottom sheet/action layer open.

### AsyncSection state prototypes

```text
idle       Comments              10 search / 186 regular available  [Collect]
queued     ◷ Comments queued      You can continue reviewing other evidence
processing [reserved skeleton]   Processing 18 of 50 comments…
completed  ✓ 50 comments         Updated 14:32 · View patterns
failed     ! Could not collect    Existing analysis is unchanged     [Retry]
```

Thumbnail patterns uses the same grammar but explicitly states `Nu consumă cota YouTube`.

### State placement

| State   | Analyzer behavior                                                                   |
| ------- | ----------------------------------------------------------------------------------- |
| loading | entity/header context remains; main profile skeleton matches final tab geometry     |
| empty   | no reusable profile explains what input or collection is required                   |
| partial | warnings attach to affected profile section and quantify missing evidence           |
| success | summary, baseline, evidence, and inspector provenance are connected                 |
| error   | failed enrichment remains inside its AsyncSection; unrelated analysis stays visible |

## Cross-page data-state fixture board

The internal design-system route must present each component in the following matrix during RDSN-02; fixture values are safe, invented, and visibly labelled as fixtures.

| Component           | loading               | empty                      | partial                            | success                  | error                        |
| ------------------- | --------------------- | -------------------------- | ---------------------------------- | ------------------------ | ---------------------------- |
| MetricTile group    | four fixed skeletons  | unavailable reason         | coverage caption                   | exact values             | one tile local failure       |
| Evidence collection | six row skeletons     | next action                | row + collection coverage          | mixed RO/EN/RU evidence  | retained rows + retry banner |
| DataTableFrame      | header + row skeleton | caption + next action      | unavailable cells explained        | exact aligned values     | local error row              |
| ChartFrame          | fixed plot geometry   | no observations            | dashed missing interval + coverage | plot + exact table       | retained table + chart error |
| InspectorPanel      | labelled shell        | not applicable explanation | limitations emphasized             | formula/source/freshness | safe recovery guidance       |
| AsyncSection        | reserved block        | idle is its empty state    | completed subset                   | terminal result          | inline Retry                 |

## Designer handoff anchors

RDSN-02 must implement and demonstrate:

1. Material recipes from `RDSN_LIQUID_GLASS_DESIGN_CONTRACT.md#2-material-hierarchy`.
2. Tokens from `#3-foundation-tokens` without page-local variants.
3. Every component contract from `#6-component-contracts` on `/design-system`.
4. All five cross-page states from `#7-data-state-grammar`.
5. The three shared viewport frames and five page prototypes in this document.
6. Romanian and long-evidence fixtures at 1440, 1024, and 390px.
7. Accessibility and performance release gates from sections 9–12 of the design contract.
