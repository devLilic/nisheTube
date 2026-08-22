# RDSN-06 — Analyzer and Compare redesign

## Goal and boundaries

Migrate Analyzer and bounded comparison surfaces to the Liquid Glass information hierarchy after section-scoped enrichment is available.

## Acceptance

- Analyzer uses sticky glass tabs for Summary, Content, Audience, Channel, and Raw Data.
- Formula/provenance/coverage move to an accessible inspector while exact values remain visible.
- Comments and Thumbnail patterns use AsyncSection without full reload or scroll movement.
- Compare uses synchronized columns, common baseline, non-color delta/strongest indicators, and explicit incompatibility/sample states.
- Two-to-four channel comparison, curation, handoffs, exports, and immutable evidence semantics remain unchanged.

## Applicable decisions

- D-005–D-008, D-019–D-035, D-043, D-049–D-054.

## Initial inspection targets

- Analyzer show/profile tabs/feature sections, compare page/read model, curation, handoffs, exports, responsive tables and tests.

## Relevant contracts and UI

- No new score, causal claim, ranking, or provider read is introduced by presentation changes.

## Focused verification

- Existing exact Analyzer/comparison feature tests plus focused tabs, async, responsive, localization, and accessibility frontend tests.
- Scoped lint/format/types and designer review.

## Exact reference links

- [ANA-07 brief](ANA-07.md)
- [Analyzer responsive prototype](../reference/RDSN_RESPONSIVE_PROTOTYPES.md#analyzer)
- [Analyzer migration matrix](../reference/RDSN_PAGE_COMPONENT_MATRIX.md#analyze)
- [Decision D-043](../10_DECISIONS.md#d-043--four-channel-comparison-extends-the-bounded-read-model)
- [Working rules](../00_WORKING_RULES.md)
