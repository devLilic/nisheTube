# RDSN-07 — Library and workspace redesign

## Goal and boundaries

Unify Shortlist, Favorites, Projects, Topics, Watchlist, Ideas, History, and Exports under a responsive Collection + Inspector pattern.

## Acceptance

- Every surface uses shared collection rows/cards, inspector, filters, pagination, empty/loading/partial/error states, and clear primary actions.
- Bulk actions, destructive confirmations, export manifests, monitoring refreshes, and saved-comment retention semantics remain authorized and idempotent.
- Filters, selection, page, and expanded context survive partial requests and mobile-sheet interactions.
- RO/EN, long evidence, exact values, keyboard access, and target widths pass.
- No immutable source evidence is copied or mutated except where existing decisions explicitly allow durable user intent.

## Applicable decisions

- D-005–D-018, D-025–D-027, D-037, D-041–D-042, D-049, D-051–D-054.

## Initial inspection targets

- Library, shortlist, topics, watchlist, ideas, history, exports pages/features/types and their focused tests.

## Relevant contracts and UI

- Shared Collection + Inspector composition; route and domain behavior remain stable.

## Focused verification

- Exact owner/bulk/export/retention/monitoring feature tests and focused interface/responsive/localization tests.
- Scoped query-budget, lint, format, types, and designer review.

## Exact reference links

- [RDSN-06 brief](RDSN-06.md)
- [Organize and Manage page matrix](../reference/RDSN_PAGE_COMPONENT_MATRIX.md#organize)
- [Component contracts](../reference/RDSN_LIQUID_GLASS_DESIGN_CONTRACT.md#6-component-contracts)
- [Working rules](../00_WORKING_RULES.md)
