# RDSN-01 — Liquid Glass design contract and responsive prototypes

## Goal and boundaries

Create the reusable visual and interaction contract for the total redesign. Produce inventory, component mapping, tokens, responsive prototypes, and state guidance; do not change production UI or business behavior.

## Acceptance

- A linked reference specifies `glass-command`, `glass-panel`, `glass-content`, geometry, color, type, motion, fallback, and performance limits.
- Dashboard, Validate/Search, Research result, Explore, and Analyzer prototypes cover 1440px, 1024px, and 390px plus loading, empty, partial, success, and error states.
- The component migration matrix covers every production page and forbids nested glass or page-local visual inventions.
- Romanian copy expansion, long YouTube evidence, exact metrics, keyboard focus, reduced motion/transparency, and forced-colors are demonstrated.
- The designer agent records a review outcome and RDSN-02 receives exact implementation references.

## Applicable decisions

- D-049 and D-051–D-054.

## Initial inspection targets

- `resources/css/app.css`, shared `components/ui`, application/auth layouts, representative Dashboard, Research, Explore, Discovery, Analyzer, Library, Settings, and landing pages.
- Existing `/design-system` page and focused frontend interface tests.

## Relevant contracts and UI

- Preserve existing route and domain contracts.
- Specify AppCanvas, GlassNavigationRail, GlassCommandBar, GlassCapsule, GlassPanel, GlassPopover, GlassSheet, ContentPanel, MetricTile, EvidenceRow, DataTableFrame, InspectorPanel, ChartFrame, and AsyncSection.
- Use a system font stack and retain the violet NisheTube identity.

## Focused verification

- Designer review at 1440px, 1024px, and 390px.
- Documentation link and component/page inventory checks.
- Contrast, material-layer, responsive, and state-matrix review without broad project tests.

## Exact reference links

- [Working rules](../00_WORKING_RULES.md)
- [Decision log, D-049 and D-051–D-054](../10_DECISIONS.md#d-049--romanian-and-english-ui-are-separate-from-research-language)
- [Backlog](../08_BACKLOG.md)
- [Liquid Glass design contract](../reference/RDSN_LIQUID_GLASS_DESIGN_CONTRACT.md)
- [Responsive page prototypes](../reference/RDSN_RESPONSIVE_PROTOTYPES.md)
- [Production page and component migration matrix](../reference/RDSN_PAGE_COMPONENT_MATRIX.md)

## Designer review outcome

**Reviewed:** 2026-08-21  
**Reviewer:** RDSN-01 UI/product designer agent  
**Outcome:** Accepted for implementation by RDSN-02.

The contract covers all production routes and the internal design-system route, fixes the three material levels and their opaque fallbacks, and defines implementation behavior at 1440px, 1024px, and 390px. The five priority page prototypes demonstrate loading, empty, partial, success, and error states; Romanian expansion, long Romanian/Russian YouTube evidence, exact-value presentation, focus, reduced motion/transparency, forced colors, and bounded blur are explicit release gates.

RDSN-02 must use the three linked RDSN references as exact implementation sources. Page-local color, radius, blur, shadow, motion, or glass recipes are rejected by this review.
