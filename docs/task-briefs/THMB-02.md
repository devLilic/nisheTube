# THMB-02 — Evidence-backed packaging patterns

## Goal and boundaries

Extend stored thumbnail evidence into recurring channel/niche packaging patterns and outlier-versus-baseline comparisons without causal or copying claims.

## Acceptance

- Versioned patterns cover supported text density, dominant color, face presence, composition, and consistency signals with detector confidence.
- Each pattern exposes frequency, examples, sample/coverage, detector version, association limits, and unavailable reasons.
- Outliers compare only against a compatible stored baseline and never claim the thumbnail caused performance.
- Work uses the ANA-07 AsyncSection behavior, transient allow-listed retrieval, no secret leakage, and idempotent immutable persistence.
- UI explicitly discourages identical competitor copying and supports partial/error/legacy states.

## Applicable decisions

- D-009–D-013, D-019, D-034–D-035, D-043, D-050–D-054.

## Initial inspection targets

- Thumbnail job/profile/features, image retrieval boundary, Analyzer performance/baseline, async section and tests.

## Relevant contracts and UI

- New packaging-pattern version pins source thumbnail features and baseline evidence.

## Focused verification

- Exact unit clustering/guard tests, feature retrieval/idempotency/ownership tests, and frontend pattern/async tests.

## Exact reference links

- [ANA-07 brief](ANA-07.md)
- [Decision D-034](../10_DECISIONS.md#d-034--thumbnail-analysis-uses-transient-allow-listed-image-retrieval-and-local-versioned-features)
- [Working rules](../00_WORKING_RULES.md)
