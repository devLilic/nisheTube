# XPLR-03 — Explorer metrics from eligible Research snapshots

## Goal and boundaries

Make Explorer metrics source-complete and provenance-rich by reading every eligible completed Research snapshot instead of Analyzer-only metric subqueries.

## Acceptance

- Owner-scoped completed Research snapshots are the canonical eligible source regardless of entry screen.
- Zero, unavailable, and not collected remain distinct in domain and UI types.
- Each metric exposes run, collected time, formula/version, sample size, coverage, and freshness.
- Reads perform no provider work and remain bounded/paginated.
- Existing presets, filters, return state, and legacy snapshots remain compatible.

## Applicable decisions

- D-005–D-008, D-014–D-018, D-041–D-045, D-054.

## Initial inspection targets

- Explore read model/controller/types, Analyzer metric subqueries, Research snapshots, presets, interface tests.

## Relevant contracts and UI

- Metric availability is an explicit discriminated state rather than a nullable value rendered as zero.
- Provenance opens in the redesigned InspectorPanel.

## Focused verification

- Exact feature tests for source eligibility, ownership, query bounds, zero/unavailable, legacy and provenance.
- Exact frontend tests for metric states and preserved Explore navigation.

## Exact reference links

- [Working rules](../00_WORKING_RULES.md)
- [FMT-01 brief](FMT-01.md)
- [RDSN-05 brief](RDSN-05.md)
