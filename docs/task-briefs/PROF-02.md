# PROF-02 — User-entered commercial scenarios

## Goal and boundaries

Add owner-private reproducible sensitivity scenarios that clearly separate stored product evidence from financial assumptions entered by the user.

## Acceptance

- RPM, conversion, offer price/value, cost, currency, and period have no product-provided numeric defaults.
- Validation defines safe ranges and requires explicit assumptions before calculation.
- Outputs are ranges/sensitivity views, not factual revenue/profit claims, and preserve formula/version plus the entered assumptions.
- Scenarios are owner-scoped, editable through new versions or explicit records, and never rewrite immutable Research evidence.
- UI clearly labels observed, calculated, assumed, unavailable, and warning states in RO/EN.

## Applicable decisions

- D-005–D-008, D-014–D-018, D-041–D-042, D-048–D-049, D-051–D-054.

## Initial inspection targets

- Existing profitability-fit domain/UI, Research/Project relations, settings/formatters, policies and tests.

## Relevant contracts and UI

- Versioned scenario input/output DTO; no hidden currency or financial fallback.

## Focused verification

- Exact unit formula/sensitivity tests, feature validation/ownership/versioning tests, and frontend assumption/disclosure tests.

## Exact reference links

- [Decision D-048](../10_DECISIONS.md#d-048--commercial-scenarios-contain-no-hidden-financial-defaults)
- [Working rules](../00_WORKING_RULES.md)
