# SHORT-02 — Format-separated niche comparison

## Goal and boundaries

Add an honest format-aware comparison view over stored Research evidence. Do not classify unknown videos or create a new provider collection path.

## Acceptance

- Confirmed Shorts, confirmed long-form, unknown, and totals are separate.
- Unknown is excluded by default from direct format conclusions but remains inspectable.
- Every comparison exposes sample, coverage, formula/version, and insufficient-data reasons.
- Frozen minimum-sample gates prevent unsupported verdicts.
- Ownership, immutable snapshot pins, and legacy compatibility match the existing Shortlist comparison.

## Applicable decisions

- D-005–D-008, D-014–D-018, D-041–D-045.

## Initial inspection targets

- Shortlist comparison read model/page, Research evidence format aggregates, score disclosures, tests.

## Relevant contracts and UI

- Comparison is a read model with explicit availability; no hidden score or provider work.

## Focused verification

- Exact calculation/unit cases for mixed, unknown, insufficient, and complete samples.
- Exact owner/legacy feature tests and frontend comparison-state tests.

## Exact reference links

- [Decision D-045](../10_DECISIONS.md#d-045--format-comparisons-preserve-unknown-coverage)
- [FMT-01 brief](FMT-01.md)
- [Working rules](../00_WORKING_RULES.md)
