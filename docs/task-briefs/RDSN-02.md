# RDSN-02 — Liquid Glass tokens, primitives, and showcase

## Goal and boundaries

Implement the design contract as reusable tokens and primitives plus a local authorized showcase. Do not migrate production feature pages beyond shared primitives needed to prove the system.

## Acceptance

- Theme tokens implement the three material tiers, concentric geometry, semantic colors, system typography, shadows, and static ambient canvas.
- New primitives expose typed variants and complete loading/empty/partial/error examples.
- Backdrop-filter fallback, reduced motion/transparency, forced colors, 44px targets, focus, and contrast requirements work without dark mode.
- The local authorized design-system route covers all states and is unavailable outside the intended environment/authorization boundary.
- No nested glass or per-row blur is introduced.

## Applicable decisions

- D-049, D-051–D-054.

## Initial inspection targets

- `resources/css/app.css`, `components.json`, shared UI primitives, current design-system page/route/test, theme hook.

## Relevant contracts and UI

- AppCanvas, GlassPanel family, ContentPanel, MetricTile, EvidenceRow, DataTableFrame, InspectorPanel, ChartFrame, AsyncSection.
- Maximum three visible blurred layers; content surfaces retain exact-value contrast.

## Focused verification

- Exact design-system route and interface tests.
- Scoped ESLint, Prettier, TypeScript, CSS fallback, keyboard, contrast, and designer review checks.

## Exact reference links

- [RDSN-01 brief](RDSN-01.md)
- [Liquid Glass design contract](../reference/RDSN_LIQUID_GLASS_DESIGN_CONTRACT.md)
- [Responsive prototypes and state fixtures](../reference/RDSN_RESPONSIVE_PROTOTYPES.md)
- [Production page and component matrix](../reference/RDSN_PAGE_COMPONENT_MATRIX.md)
- [Decision D-051](../10_DECISIONS.md#d-051--liquid-glass-is-a-tiered-functional-design-system)
- [Working rules](../00_WORKING_RULES.md)
