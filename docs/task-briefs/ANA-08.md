# ANA-08 — Competitor publishing-strategy profile

## Goal and boundaries

Create an immutable Analyzer evidence profile for competitor cadence, timing, duration, format, themes, title structures, and channel-relative outliers from stored observations.

## Acceptance

- Profile stores cadence/regularity, observed day/time, duration distribution, format coverage, recurring themes/formats/title structures, and outliers against channel baseline.
- Every signal includes period, sample, coverage, confidence, source videos, formula/version, and unavailable reasons.
- Queue work is idempotent/retry-safe and uses provider contracts only when the brief's explicit collection preflight authorizes enrichment.
- UI separates observation from recommendation or causality and supports legacy/partial/error states.
- Ownership and immutable attempt/snapshot behavior match existing Analyzer profiles.

## Applicable decisions

- D-005–D-013, D-019–D-029, D-041–D-045, D-049–D-054.

## Initial inspection targets

- Analyzer channel/profile/semantic/performance read models, observations, jobs, tabs, ChartFrame and tests.

## Relevant contracts and UI

- New versioned strategy profile pinned to one Analyzer attempt and exact source observations.

## Focused verification

- Exact unit aggregation/outlier tests; feature queue/idempotency/ownership/partial tests; frontend strategy-profile tests.

## Exact reference links

- [RDSN-06 brief](RDSN-06.md)
- [Working rules](../00_WORKING_RULES.md)
