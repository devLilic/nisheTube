# SHORT-01 — Shortlist persistence and niche comparison

## Goal and boundaries

Deliver the smallest complete owner-scoped shortlist for saved Research decisions and a two-to-five item comparison. Comparisons must use frozen stored evidence, preserve score-version compatibility, and never trigger provider work or rewrite historical records.

The task includes required storage, domain logic, authorization, visible Inertia UI, all data states, focused tests, and task-status evidence. It does not implement the following Explore productivity task.

## Acceptance

- Users can save and remove only their own shortlist items with durable, idempotent persistence.
- A comparison supports two through five owner-owned items and clearly marks incompatible score versions, missing data, estimates, and legacy values.
- The UI provides loading, empty, selection-limit, success, partial-data, incompatible, and error states with accessible exact values.
- Reads and comparisons use stored snapshots only and perform no provider calls or historical recalculation.
- Focused backend and frontend tests cover ownership, persistence, bounds, compatibility, and UI states.

## Applicable decisions

- D-005–D-008: authenticated local users and owner-safe access.
- D-014–D-018: immutable Research records, provider boundaries, and versioned scoring.
- D-038: desktop acceptance with accessibility and exact-value requirements.
- D-041–D-042: frozen Research evidence/versioned score inputs and explicit unavailable history/signals.

## Initial inspection targets

- Existing Research actions and Library persistence: `app/Domain/Research/`, `app/Domain/Library/`, `app/Models/ResearchRun.php`, and `resources/js/features/research/research-actions.tsx`.
- Existing comparison/history presentation: `app/Domain/History/`, `app/Http/ViewModels/`, and `resources/js/pages/`.
- Relevant focused tests in `tests/Feature/` and `tests/Frontend/`.

## Focused verification

- Feature tests for owner isolation, idempotency, selection bounds, and version compatibility.
- Frontend tests for all selection and comparison states, exact values, and warnings.
- Formatter/lint/type checks only against changed files.

## References

- [Decision index](../DECISION_INDEX.md)
- [Research scoring model](../04_SCORING_MODEL.md#6-versioning)
- [Research evidence model](../04_SCORING_MODEL.md#13-research-evidence-version-research-evidence-v1)
