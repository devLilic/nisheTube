# NisheTube Liquid Glass design contract

Status: accepted by the RDSN-01 designer review on 2026-08-21  
Applies to: every production route at desktop, tablet, and mobile widths  
Implementation owner: RDSN-02 and later redesign tasks  
Related decisions: D-049, D-051, D-052, D-053, D-054

## 1. Product character

NisheTube is an evidence workspace, not an entertainment feed. The interface may feel luminous, tactile, and recognizably Liquid Glass, but a researcher must always be able to distinguish an observed value, an inference, a missing value, and an action that consumes YouTube quota.

The visual voice is **calm precision**:

- cool white canvas with a restrained violet identity;
- glass for navigation, commands, context, and temporary layers;
- stable high-contrast surfaces for evidence, tables, forms, and long reading;
- exact values before decoration;
- one obvious primary action per decision region;
- Romanian copy by default, with layouts tested against longer labels.

The redesign preserves route and domain contracts. It changes hierarchy, composition, responsive behavior, and visual primitives.

## 2. Material hierarchy

Every visible surface must declare exactly one material. A child may contain ordinary transparent layout wrappers, but never another glass material.

| Material        | Purpose                           | Visual recipe                                                                      | Allowed examples                                                           | Forbidden examples                                              |
| --------------- | --------------------------------- | ---------------------------------------------------------------------------------- | -------------------------------------------------------------------------- | --------------------------------------------------------------- |
| `canvas`        | Ambient page ground               | cool near-white base, two static radial color fields, no blur                      | application background, public landing background                          | animated gradients, content text directly on a busy color field |
| `glass-command` | navigation and transient commands | 60–68% white, 24–30px blur, saturation 125%, 1px inner/outer edge, elevated shadow | desktop rail, command bar, mobile sheet, popover, floating action tray     | data tables, repeated result rows, form bodies                  |
| `glass-panel`   | contextual tools and inspectors   | 76–82% white, 20–24px blur, defined border, medium shadow                          | filter inspector, quota preview, provenance inspector, tab rail            | nested inside command glass, one panel per list row             |
| `glass-content` | expressive but readable summaries | 94–98% white, 12–18px blur or opaque fallback, quiet border, low shadow            | hero summary, metric group, decision summary, empty/error state            | dense table body if transparency reduces contrast               |
| `content-solid` | maximum-density evidence          | 98–100% white, no blur, visible row separators                                     | tables, long comments, raw JSON, settings forms, destructive confirmations | navigation or floating controls                                 |

Rules:

1. A maximum of three blurred surfaces may be visible at once: rail, command bar, and one panel or transient layer.
2. Opening a `GlassSheet`, dialog, or popover suppresses decorative blur on the obscured page where necessary.
3. No glass material may be nested inside another glass material. Use border, spacing, or a tonal `content-solid` region to subdivide it.
4. Repeated rows never receive individual blur or drop shadows.
5. Transparency is decorative context only; meaning may not depend on seeing the canvas through a surface.

### Opaque fallback

When `backdrop-filter` is unsupported, reduced transparency is requested, or contrast becomes uncertain:

- `glass-command` becomes `oklch(0.985 0.006 270 / 0.98)`;
- `glass-panel` and `glass-content` become fully opaque;
- a visible border and shadow preserve separation;
- text, icons, focus, and status colors do not change;
- no layout measurement depends on blur.

## 3. Foundation tokens

Token names are the implementation contract. RDSN-02 may tune values only within the ranges below after browser verification; page tasks may not introduce page-local alternatives.

### Color

| Token                | Target                         | Usage                                            |
| -------------------- | ------------------------------ | ------------------------------------------------ |
| `--lg-canvas`        | `oklch(0.975 0.010 255)`       | page ground                                      |
| `--lg-canvas-cool`   | `oklch(0.935 0.045 255 / .55)` | upper-left ambient field                         |
| `--lg-canvas-violet` | `oklch(0.900 0.075 285 / .48)` | upper-right identity field                       |
| `--lg-ink`           | `oklch(0.205 0.035 270)`       | primary text                                     |
| `--lg-ink-muted`     | `oklch(0.445 0.030 265)`       | secondary text; must remain 4.5:1 on its surface |
| `--lg-violet`        | `oklch(0.520 0.225 282)`       | primary action and selected state                |
| `--lg-violet-strong` | `oklch(0.430 0.215 282)`       | hover/pressed and text links                     |
| `--lg-focus`         | `oklch(0.610 0.220 275)`       | 2px focus ring plus 2px canvas offset            |
| `--lg-border-soft`   | `oklch(0.860 0.025 270 / .72)` | material edge                                    |
| `--lg-border-strong` | `oklch(0.745 0.045 270 / .86)` | dense regions and high-contrast fallback         |
| `--lg-success`       | `oklch(0.455 0.145 150)`       | completed/positive observation                   |
| `--lg-warning`       | `oklch(0.555 0.155 70)`        | partial/attention                                |
| `--lg-danger`        | `oklch(0.505 0.205 28)`        | failed/destructive                               |
| `--lg-info`          | `oklch(0.480 0.160 245)`       | queued/informational                             |

Status never relies on color alone. Every state combines icon, localized label, and—when not obvious—one-sentence guidance.

### Type

- Font stack: `ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`. Do not distribute SF Pro.
- Display: 32/38 at 1440, 28/34 at 1024, 26/32 at 390; weight 650–700; maximum 24 characters before natural wrap is expected.
- Page title: 24/30 desktop, 22/28 mobile; weight 650.
- Section title: 18/24; weight 650.
- Body: 15/22; dense evidence may use 14/20.
- Caption: 12/17; never below 12px.
- Metrics: 28/32 or 22/28; weight 680; `font-variant-numeric: tabular-nums`.
- IDs, quota arithmetic, dates in compact tables, and raw data use the system monospace stack at 12–13px.
- Long YouTube titles use normal wrapping and a minimum two-line allowance. Truncation requires a native/title or visible expansion control.

### Geometry and spacing

| Token group    | Values                                              |
| -------------- | --------------------------------------------------- |
| spacing        | 4, 8, 12, 16, 20, 24, 32, 40, 48px                  |
| control radius | 10px compact, 12px regular                          |
| content radius | 14px dense, 16px regular                            |
| glass radius   | 20px panel, 24px command/sheet                      |
| control height | 36px compact, 44px default, 52px hero search        |
| touch target   | minimum 44×44px even when icon glyph is smaller     |
| desktop rail   | 248px expanded, 72px collapsed, 16px viewport inset |
| page width     | maximum 1680px, 12-column grid, 24px gutters        |
| tablet gutters | 20px                                                |
| mobile gutters | 16px; 12px only inside dense tables                 |

Concentric rule: when a child control sits inside a rounded parent, its radius must be smaller by the visible inset. Pill shapes are reserved for status, compact segmented navigation, and one-line filters—not general cards.

### Elevation

- `shadow-command`: soft 0 16px 45px violet-black at 12%, plus 0 1px white inset.
- `shadow-panel`: 0 12px 32px neutral-violet at 10%.
- `shadow-content`: 0 6px 20px neutral-violet at 6%.
- Hover may change border, background, and translate by at most 1px. Rows never float.

## 4. Motion contract

Motion communicates state or spatial origin; it is never ambient.

| Interaction         | Duration  | Curve                    | Notes                                             |
| ------------------- | --------- | ------------------------ | ------------------------------------------------- |
| hover/focus color   | 120–160ms | ease-out                 | no scale on dense controls                        |
| capsule selection   | 180–220ms | cubic-bezier(.2,.8,.2,1) | background/indicator only                         |
| sheet enter/exit    | 240–300ms | same emphasized curve    | must preserve focus and scroll lock               |
| inspector expand    | 180–240ms | ease-out                 | opacity + small translation; no blur animation    |
| async result reveal | 160–200ms | ease-out                 | opacity only; reserve final height where possible |

`prefers-reduced-motion: reduce` removes translation, scale, shimmer, smooth scrolling, and animated progress interpolation. Spinners become a static status glyph plus changing text; indeterminate progress may use a non-moving striped fill. No blur value is animated in any mode.

## 5. Responsive shell

### 1440px desktop

- Expanded rail at left, inset 16px from viewport and vertically separated from the command bar.
- Content starts after the 248px rail plus a 24px gap.
- Sticky command bar contains global search, active market/project/workspace context, YouTube quota, notifications, and user menu.
- Page content uses 12 columns. Main evidence normally spans 8–9; contextual inspector spans 3–4.
- Rail groups: Discover, Validate, Analyze, Organize, Manage.

### 1024px tablet/compact desktop

- Rail defaults collapsed to 72px and reveals labels through accessible tooltips; it may expand without covering the active focus.
- Market/project/workspace merge into one Context capsule.
- Main content uses 8 columns. Inspector becomes a right-side `GlassSheet` unless it is essential to interpreting the current result.
- No horizontal page scroll. Wide tables use their own labeled scroll region with sticky first identifier column.

### 390px mobile

- No bottom dock.
- A 56px command bar contains menu, compact page title/context, search, and the highest-priority status or quota affordance.
- The menu opens the complete IA in a left `GlassSheet`, with focus trap, Escape/close button, background scroll lock, and focus restoration.
- Content is one column. Noncritical inspectors and filters open as bottom/side sheets.
- Sticky actions may occupy one bottom bar only; safe-area insets are mandatory.
- Tables become labeled card rows only when semantic column relationships remain explicit; otherwise they use a contained horizontal scroll with a visible cue.

## 6. Component contracts

### `AppCanvas`

Owns page background, safe-area insets, maximum width, and ambient gradients. It never carries interactive state and never wraps public/auth content in a second canvas.

### `GlassNavigationRail`

Owns the complete IA and collapse state. Selected items use a violet tonal fill, left/leading indicator, and `aria-current="page"`. Collapsed icon buttons keep 44px targets and visible tooltips. Badge counts cannot change the rail width.

### `GlassCommandBar`

Sticky shell command surface. Search is first in reading order after the mobile menu. Context and quota have text equivalents. It cannot contain another glass primitive.

### `GlassCapsule`

For status, context, or segmented selection. Variants: neutral, selected, info, warning, danger, success. It is one line by default; Romanian labels may wrap only in mobile filter summaries.

### `GlassPanel`

For filters, inspectors, and contextual actions. It provides heading, optional description, body, and footer slots. Nested cards use `content-solid`, never glass.

### `GlassPopover` and `GlassSheet`

Transient layers with labelled title, close affordance, focus trap where modal, Escape handling, focus restoration, and opaque fallback. The sheet has mobile edge/safe-area behavior and may host full navigation or filters.

### `ContentPanel`

Stable reading surface for forms, tables, and evidence. Variants: regular, dense, quiet, critical. Headers use border separation rather than a second card.

### `MetricTile`

Required slots: label, exact value, context/basis, optional delta, confidence, and unavailable reason. Approximate charts may never replace the exact value. Four tiles maximum in a primary summary row.

### `EvidenceRow`

One video/channel/candidate per row with thumbnail/avatar, multi-line title, source/status, two to four aligned metrics, observation timestamp, and compact actions. Long titles wrap; actions do not overlap metrics. Repeated rows share one parent surface.

### `DataTableFrame`

Provides caption, column visibility summary, sticky header option, contained overflow, row selection, loading placeholders, empty/error regions, and pagination. Numeric columns are right-aligned and tabular. `0`, `—`, `Unavailable`, and `Not collected` are distinct values.

### `InspectorPanel`

Explains formula, provenance, filters, limitations, or comparison context. It is sticky on desktop, a `GlassSheet` at 1024/390, and never blocks primary evidence.

### `ChartFrame`

Requires title, exact-value alternative/table, time range, source, sample size, and legend that does not rely on color. Tooltips are keyboard reachable. Empty and partial chart states remain inside the same reserved geometry.

### `AsyncSection`

Owns `idle | queued | processing | completed | failed`. It preserves section height when practical, disables duplicate launch, uses `aria-busy` and an `aria-live="polite"` status, exposes Retry on safe failure, and updates only its section. It does not navigate or scroll the document.

## 7. Data-state grammar

Every data region—not only every page—must support these states:

| State   | Visual treatment                                            | Required content                                   | Action                       |
| ------- | ----------------------------------------------------------- | -------------------------------------------------- | ---------------------------- |
| loading | reserved skeleton in final geometry; no layout jump         | localized progress context; quota note if relevant | Cancel only when supported   |
| empty   | quiet illustration/icon in `glass-content` or inline region | what is absent and why it matters                  | one next best action         |
| partial | amber edge/banner attached to affected region               | coverage, missing source, effect on interpretation | Retry/inspect when available |
| success | evidence first; restrained success signal                   | exact value, basis, timestamp, provenance          | continue/save/compare        |
| error   | stable inline error; never replace unrelated content        | safe title, cause category, recovery guidance      | Retry or settings link       |

Queued and processing are separate labels. Unavailable, not collected, zero, and insufficient sample are never collapsed into a dash without an accessible explanation.

## 8. Localization and evidence rules

### Romanian expansion fixture

Use this fixture in every component review:

- Title: `Analizează oportunitatea nișei înainte de a consuma cota YouTube`
- Filter: `Canale mici cu performanță peste valoarea mediană observată`
- Status: `Date parțiale — 7 dintre 25 de videoclipuri nu au clasificarea formatului`
- Action: `Deschide dovezile și limitările calculului`

Interactive labels must tolerate 35–45% expansion over the English source. Primary buttons may grow; they may not truncate. Toolbars wrap by priority, not by arbitrary DOM order.

### Long evidence fixture

Use both strings without changing source language:

- `Cum am construit un studio YouTube într-un apartament foarte mic: lumini, sunet, fundal și costurile reale după șase luni`
- `Полный разбор компактной камеры для путешествий: стабилизация, автономность, перегрев и качество звука в реальных условиях`

Evidence titles permit three lines in feature summaries and at least two in rows. The source text is never translated. Exact metrics, timestamps, market, sample size, and provenance remain visually adjacent.

## 9. Accessibility and input behavior

- Text contrast: 4.5:1 minimum; large display text 3:1; component boundaries/focus 3:1 against adjacent colors.
- Focus: 2px violet ring plus 2px offset; never clipped by overflow containers.
- Keyboard order follows visual reading order. Sticky regions do not duplicate focusable controls.
- Icon-only buttons have localized accessible names and 44px targets.
- Sheets/dialogs trap focus, close with Escape, restore triggering focus, and prevent background interaction.
- Charts expose a table or ordered exact-value list.
- `forced-colors` uses system colors, 1–2px borders, underlined links, and visible selected/current indicators.
- `prefers-contrast: more` strengthens borders and suppresses nonessential transparency.
- Skip navigation targets the focusable `main` element below the sticky command bar.

## 10. Performance budget

- At most three visible blurred layers and no blur on repeated rows.
- Ambient gradients are static and limited to two radial fields.
- No animated gradients, blur, noise, or background video.
- Lists use shared surfaces, thumbnail lazy loading, explicit image dimensions, and `content-visibility: auto` for off-screen groups where browser-safe.
- Sticky regions are limited to rail, command bar, one contextual panel/tab rail, and at most one mobile action bar.
- The design must not introduce cumulative layout shift: reserve thumbnail, chart, async-section, and notification geometry.
- A slow-device check must show responsive scrolling on Explore and Analyzer before visual acceptance.

## 11. Implementation prohibition list

RDSN-02 and subsequent page tasks must not:

- invent page-local colors, radii, shadows, blur values, or motion curves;
- copy CSS recipes directly into pages instead of using system primitives/tokens;
- put a `GlassPanel`, `GlassCapsule` with surface blur, or glass card inside another glass container;
- render each evidence item as an elevated floating card;
- use transparency to communicate disabled, missing, or low-confidence data;
- animate backdrop blur or ambient gradients;
- hide exact values behind hover-only interactions;
- translate YouTube source evidence;
- reduce mobile navigation to a subset of destinations;
- reintroduce dark/system behavior during this redesign phase.

## 12. Designer release checklist

A vertical slice is visually acceptable only when all answers are yes:

1. Does each surface declare one material and avoid nested glass?
2. Can a researcher identify source, sample, freshness, confidence, and missing data without opening a tooltip?
3. Do Romanian fixtures and long YouTube evidence fit without clipped actions?
4. Are exact values visible and tabular where comparison matters?
5. Do keyboard focus, reduced motion/transparency, forced colors, and opaque fallback preserve all meaning?
6. Is the layout correct at 1440, 1024, and 390px?
7. Are there at most three active blurred layers and no repeated-row blur?
8. Does loading reserve final geometry and do partial/error states remain local?
