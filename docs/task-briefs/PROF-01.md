# PROF-01 — Estimated profitability-fit decision aid

## Goal and boundaries

Deliver the smallest complete owner-scoped decision aid that estimates profitability fit for a Research result. It must be clearly presented as an estimate, never as measured revenue or a guarantee. Preserve immutable historical Research, evidence, and opportunity-score records; do not introduce provider calls from an inspection view.

This task includes any required persistence/versioning, domain calculation, authorization, visible Inertia UI, complete data states, focused tests, and task-status evidence. It does not implement the subsequent Dashboard Decision Cockpit.

## Acceptance

- Inputs, formula/configuration version, calculation time, explanations, and any source pins are frozen with each result; historical estimates are never rewritten.
- Results are owner-scoped and authorized, with no cross-user information exposure.
- The UI distinguishes public/provider facts, stored observations, estimates, and unavailable inputs in plain English.
- The UI includes loading, empty, success, partial-data, legacy/unavailable, and error states, and does not trigger provider work on read.
- Focused backend and frontend tests cover persistence, determinism/bounds, ownership, provenance, and every visible state.

## Applicable decisions

- D-005–D-008: authenticated local users and owner-safe access.
- D-014–D-018: immutable Research records, provider boundaries, and versioned scoring.
- D-038: desktop acceptance with accessibility and exact-value requirements.
- D-041–D-042: frozen Research evidence/versioned score inputs and explicit unavailable history/signals.

## Initial inspection targets

- Existing score/evidence inputs: `app/Domain/Scoring/`, `app/Models/ResearchRun.php`, and `app/Models/OpportunityScore.php`.
- Owner-scoped Research result flow: `app/Http/Controllers/Research/ResearchRunController.php`, `app/Http/ViewModels/ResearchRunViewModel.php`, and `resources/js/pages/research/show.tsx`.
- Existing score presentation: `resources/js/features/research/scoring/opportunity-score-section.tsx`.

## Focused verification

- Unit tests for deterministic estimates, documented bounds, unavailable inputs, and version compatibility.
- Feature tests for immutable persistence, owner isolation, and result payload states.
- Frontend tests for estimate/provenance labels, exact values, warnings, and all UI states; formatter/lint/type checks only for changed files.

## References

- [Decision index](../DECISION_INDEX.md)
- [Research scoring model](../04_SCORING_MODEL.md#6-versioning)
- [Research evidence model](../04_SCORING_MODEL.md#13-research-evidence-version-research-evidence-v1)
