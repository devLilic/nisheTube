# XCMP-02 — Peer-aware comparison of up to four channels

## Goal and boundaries

Extend the existing owner-scoped channel comparison from two or three channels to a deterministic selection of two through four distinct completed Analyzer channel attempts. Present subscribers, median views, median views/day, public engagement, repeat breakouts, cadence, Shorts share, niche concentration, observed snapshot growth, and freshness with explicit nullable and comparability states.

The accepted four-channel decision permits a storage-free read model only. Do not call providers, persist comparisons, recalculate frozen Analyzer or semantic evidence, create an opportunity score, or frame the output as a recommendation.

## Acceptance

- Owners can select two to four distinct completed channel analyses, with deterministic bounds and authorization at every read.
- Comparison exposes exact stored metrics, channel-size bands, peer selection, freshness, and comparable/non-comparable warnings for periods, versions, samples, formats, and nullable evidence.
- Strongest comparable values have accessible non-color indicators; evidence-based labels remain conservative: reachable competitor, dominant incumbent, unstable performer, or useful inspiration.
- The UI has loading, empty, partial, incompatible, legacy, and error states, remains keyboard operable, and supports long English, Romanian, and Russian labels at the desktop acceptance target.
- Reads remain bounded, owner-scoped, provider-free, and storage-free; no opportunity score, causal claim, or recommendation is added.

## Applicable decisions

- D-005–D-008: all selections and reads remain authenticated and owner-safe.
- D-019, D-022–D-024: Analyzer metrics, provenance, inputs, and versions remain explicit and immutable.
- D-027–D-029: comparison and semantic/thumbnail evidence are referenced read-only rather than copied or recalculated.
- D-035: cross-channel comparison stays a bounded read model without a new score.
- D-038: desktop accessibility, keyboard behavior, non-color indicators, and exact values are required.
- D-043: comparison is explicitly approved for two through four distinct channels under the bounded storage-free contract.

## Initial inspection targets

- `resources/js/pages/analyzer/compare.tsx`, `resources/js/features/analyzer/cross-channel-comparison.tsx`, and `resources/js/types/analyzer-comparison.ts`.
- `app/Domain/Analyzer/ReadModels/BuildCrossChannelComparison.php` and `ListComparableChannelAnalyses.php`.
- Existing cross-channel Analyzer feature and frontend interface tests.

## Focused verification

- Feature tests for owner isolation, two-to-four distinct-channel bounds, stored-data-only reads, and nullable/comparability guards.
- Frontend tests for peer selection, exact table values, non-color winners, multilingual labels, and keyboard/focus behavior.
- Formatter, lint, type, PHPStan, and Pint checks only for changed files.

## References

- [Decision workflow redesign — XCMP-02](../13_DECISION_WORKFLOW_REDESIGN.md#xcmp-02--peer-aware-comparison-of-up-to-four-channels)
- [Backlog — XCMP-02](../08_BACKLOG.md#phase-19--decision-workflow-redesign)
- [D-035](../10_DECISIONS.md#d-035--cross-channel-comparison-is-a-bounded-read-model-not-a-new-score)
- [D-043](../10_DECISIONS.md#d-043--four-channel-comparison-extends-the-bounded-read-model)

## Delivery record

Completed 2026-08-16. The storage-free comparison reader now supports two through four distinct owner-scoped completed channel attempts. It exposes exact stored channel values, snapshot size/freshness/growth, Shorts coverage, niche concentration, compatibility warnings, conservative evidence labels, and explicit unavailable engagement evidence without provider calls, persistence, scores, causal claims, or recommendations.

Focused checks passed: Pint on changed PHP files; `php artisan test tests/Feature/Analyzer/CrossChannelComparisonTest.php`; `node --test tests/Frontend/cross-channel-comparison.test.mjs`; and local ESLint on changed frontend files.

Manual verification required: `composer test`; `npm run check`; select four channels and inspect keyboard selection, nullable labels, and compatibility warnings.
