# SCR-06 — Explainable Small Creator Fit

## Goal and boundaries

Add an immutable versioned decision aid describing observed niche accessibility for smaller channels. It must not claim a probability of creator success.

## Acceptance

- A user-visible frozen creator-size threshold, distribution, typical performance, recency, consistency, outlier dependence, sample, coverage, and confidence are persisted.
- Calculation uses stored eligible evidence only and remains reproducible/idempotent.
- Unavailable subscriber data reduces coverage/confidence instead of becoming zero.
- UI explains every component, formula/version, evidence window, limitations, and legacy/unavailable state.
- Ownership, snapshot immutability, scoring-job safety, and no-provider read behavior are tested.

## Applicable decisions

- D-005–D-018, D-041–D-046, D-049, D-054.

## Initial inspection targets

- Research evidence/scoring profiles, profitability fit, Dashboard/Research score UI, configuration and tests.

## Relevant contracts and UI

- New immutable `small-creator-fit-v1` profile; it does not modify released Opportunity versions.

## Focused verification

- Exact unit formula/boundary tests, feature persistence/idempotency/ownership tests, and frontend disclosure tests.

## Exact reference links

- [Decision D-046](../10_DECISIONS.md#d-046--small-creator-fit-is-evidence-derived-and-explainable)
- [Working rules](../00_WORKING_RULES.md)
