# RDSN-05 — Research Results, Explore, and Discovery redesign

## Goal and boundaries

Migrate the core evidence-discovery surfaces to the new responsive system while preserving immutable evidence, query budgets, filters, selection, and exact-value access.

## Acceptance

- Research Results provides sticky context and Verdict, Evidence, Videos, Channels, and Provenance views.
- Explore uses compact EvidenceRows, a desktop inspector, mobile filter Glass Sheet, and stable numeric columns.
- Discovery visually separates seeds, candidates, weak signals, evidence, and shortlist decisions.
- Filters, selection, pagination, bulk actions, and return state survive partial requests.
- RO/EN and all data states work at 1440px, 1024px, 390px, 200% zoom, and long multilingual evidence.

## Applicable decisions

- D-005–D-018, D-039–D-049, D-051–D-054.

## Initial inspection targets

- Research show/analysis/evidence/provenance, Explore page/read model, Discovery pages/candidate lists/bulk selection and focused tests.

## Relevant contracts and UI

- Uses EvidenceRow, DataTableFrame, InspectorPanel, ChartFrame, GlassSheet, and explicit unavailable-state contracts.

## Focused verification

- Existing exact Research/Explore/Discovery feature tests and focused frontend interaction/accessibility/localization tests.
- Scoped lint, format, types, query-budget checks, and designer review.

## Exact reference links

- [RDSN-04 brief](RDSN-04.md)
- [Research Result and Explore prototypes](../reference/RDSN_RESPONSIVE_PROTOTYPES.md#research-result)
- [Discover and Validate page matrix](../reference/RDSN_PAGE_COMPONENT_MATRIX.md#discover-and-validate)
- [XPLR-03 brief](XPLR-03.md)
- [DISC-06 brief](DISC-06.md)
- [Working rules](../00_WORKING_RULES.md)
