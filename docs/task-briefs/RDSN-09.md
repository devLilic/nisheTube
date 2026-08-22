# RDSN-09 — Visual, responsive, accessibility, and performance QA

## Goal and boundaries

Harden the completed production redesign and add stable focused visual smoke coverage. Fix only regressions discovered within the redesigned interface contract.

## Acceptance

- Designer review passes every production page at 1440px, 1024px, and 390px with RO/EN, long evidence, all data states, and 200% zoom.
- Chrome, Edge, and Safari-compatible CSS/fallback behavior is documented; unsupported blur remains a complete opaque experience.
- Contrast, keyboard, focus, reduced motion/transparency, forced colors, screen-reader status, exact chart/table alternatives, and touch targets pass.
- No nested glass, more than three visible blur layers, animated gradients/blur, or per-row backdrop filters remain.
- Focused stable screenshot/smoke fixtures cover design system and critical routes; Explore/Analyzer LCP, INP, CLS, query/payload, and scroll budgets are recorded.

## Applicable decisions

- D-014, D-049–D-054.

## Initial inspection targets

- All redesigned production pages, shared primitives, CSS, focused accessibility/performance tests, browser QA fixtures.

## Relevant contracts and UI

- QA validates released behavior; it does not introduce a new visual variant or broad feature scope.

## Focused verification

- Exact visual smoke files and changed route/interface tests only.
- Scoped lint/format/types and targeted browser/performance measurements.

## Exact reference links

- [Decision D-054](../10_DECISIONS.md#d-054--responsive-accessible-and-bounded-glass-is-a-release-gate)
- [RDSN-01 brief](RDSN-01.md)
- [Liquid Glass release checklist](../reference/RDSN_LIQUID_GLASS_DESIGN_CONTRACT.md#12-designer-release-checklist)
- [Responsive prototypes](../reference/RDSN_RESPONSIVE_PROTOTYPES.md)
- [Complete page matrix](../reference/RDSN_PAGE_COMPONENT_MATRIX.md)
- [Working rules](../00_WORKING_RULES.md)
