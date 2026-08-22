# FMT-01 — Conservative video-format evidence

## Goal and boundaries

Replace permanent/implicit format uncertainty with a versioned tri-state evidence contract. Do not infer Shorts from duration alone or rewrite historical snapshots.

## Acceptance

- New observations store `short`, `long_form`, or `unknown` plus method/evidence/version.
- Provider normalization and downstream DTOs preserve unknown honestly.
- Historical records remain readable and visibly legacy/unavailable.
- Format coverage is exposed to scoring/read models; unknown is never converted to zero or long-form.
- Collection remains behind provider contracts and queued/idempotent boundaries.

## Applicable decisions

- D-009–D-018, D-041–D-045.

## Initial inspection targets

- YouTube video normalizer/provider DTO, observation schema/models, research evidence builders, Analyzer/Explore types and format UI.

## Relevant contracts and UI

- `VideoFormat = 'short' | 'long_form' | 'unknown'`.
- Immutable evidence includes detection version, method, and nullable source detail.

## Focused verification

- Exact unit tests for normalization and classification boundaries.
- Exact feature tests for immutable storage, legacy reads, owner scope, and visible coverage.

## Exact reference links

- [Decision D-044](../10_DECISIONS.md#d-044--video-format-classification-is-conservative-and-tri-state)
- [Working rules](../00_WORKING_RULES.md)
