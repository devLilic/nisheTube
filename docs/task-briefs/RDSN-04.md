# RDSN-04 — Dashboard and Validate/Search redesign

## Goal and boundaries

Deliver the first production Liquid Glass vertical slice for the decision cockpit and Research intake without changing released score/provider behavior.

## Acceptance

- Dashboard prioritizes one next action, one opportunity, confidence, up to four metrics, and resumable active runs.
- Validate/Search keeps the query dominant, groups market/language/depth, moves advanced options to an inspector, and keeps quota preflight visible.
- Submit/progress/cancel/retry remain idempotent, quota-safe, inline, and stable without layout jumps.
- RO/EN and loading/empty/partial/success/error states meet the design contract at all target widths.
- Existing owner isolation, exact values, formulas, and stored-evidence wording remain intact.

## Applicable decisions

- D-005–D-018, D-039, D-041–D-042, D-049, D-051–D-054.

## Initial inspection targets

- Dashboard decision cockpit/loading, Research create/search form/preflight/progress, job controls, page headers and metrics.

## Relevant contracts and UI

- Uses AppCanvas, MetricTile, ContentPanel, InspectorPanel, and GlassCapsule only through shared primitives.

## Focused verification

- Existing exact Dashboard/Research feature tests plus focused interface/localization/responsive tests.
- Scoped lint, format, types, and designer review.

## Exact reference links

- [RDSN-03 brief](RDSN-03.md)
- [Dashboard and Validate prototypes](../reference/RDSN_RESPONSIVE_PROTOTYPES.md#dashboard)
- [Discover and Validate page matrix](../reference/RDSN_PAGE_COMPONENT_MATRIX.md#discover-and-validate)
- [Decision D-051](../10_DECISIONS.md#d-051--liquid-glass-is-a-tiered-functional-design-system)
- [Working rules](../00_WORKING_RULES.md)
