# DISC-06 — Multilingual glued-phrase normalization

## Goal and boundaries

Extend Discovery phrase normalization for glued hashtags and tokens while retaining originals, avoiding speculative splits, and preserving immutable versioned evidence.

## Acceptance

- Originals remain stored and visible; normalization handles Unicode, camelCase, digits, and explicit delimiters.
- RO/EN/RU lexical segmentation runs only above a frozen confidence threshold.
- Unsure phrases remain unsplit with an explicit reason.
- Aliases merge evidence without double-counting scores, videos, channels, or seeds.
- New normalization has a new version; historical candidates are not rewritten.

## Applicable decisions

- D-010, D-014–D-018, D-040–D-042, D-047.

## Initial inspection targets

- Discovery phrase normalizer, candidate evidence builder/schema, multilingual fixtures, candidate UI.

## Relevant contracts and UI

- Normalized output retains original, candidate segments, confidence, rule/version, and accepted/rejected state.

## Focused verification

- Exact unit fixtures for Romanian, English, Russian, emoji/Unicode, camelCase, digits, and false-positive guards.
- Exact Discovery feature/UI tests for alias deduplication and evidence disclosure.

## Exact reference links

- [Decision D-040](../10_DECISIONS.md#d-040--discovery-candidates-use-immutable-evidence-quality-versions-and-explicit-weak-signals)
- [Working rules](../00_WORKING_RULES.md)
