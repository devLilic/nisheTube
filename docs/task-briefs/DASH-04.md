# DASH-04 — Dashboard Decision Cockpit

## Goal and boundaries

Deliver the smallest complete owner-scoped Dashboard decision cockpit using stored, immutable NisheTube evidence. It must make evidence freshness, confidence, partial data, and unavailable results explicit, and it must not trigger provider work merely by loading the Dashboard.

This task includes any required read models, authorization, visible Inertia UI, loading/empty/success/partial/error states, focused tests, and status evidence. It does not implement the following Shortlist persistence task.

## Acceptance

- Dashboard data is scoped to the authenticated owner and never exposes another user&apos;s Research, scores, or estimates.
- Decisions use frozen stored records and label estimates, observed activity, confidence, freshness, partial data, and legacy/unavailable values plainly.
- The cockpit has accessible loading, empty, success, partial-data, and error states with exact values where available.
- Loading the dashboard does not call YouTube or recalculate historical records.
- Focused feature and frontend tests cover owner isolation, read-only behavior, payload state, and visible UI states.

## Applicable decisions

- D-005–D-008: authenticated local users and owner-safe access.
- D-014–D-018: immutable Research records, provider boundaries, and versioned scoring.
- D-038: desktop acceptance with accessibility and exact-value requirements.
- D-041–D-042: frozen Research evidence/versioned score inputs and explicit unavailable history/signals.

## Initial inspection targets

- Existing Dashboard route and presentation: `routes/web.php`, `app/Http/Controllers/`, `app/Http/ViewModels/`, and `resources/js/pages/dashboard.tsx`.
- Research score/fit records: `app/Models/ResearchRun.php`, `app/Models/OpportunityScore.php`, `app/Models/ProfitabilityFitScore.php`, and `app/Domain/Scoring/`.
- Existing dashboard/frontend tests in `tests/Feature/` and `tests/Frontend/`.

## Focused verification

- Feature tests for owner isolation, immutable/read-only dashboard payloads, and empty/partial/complete states.
- Frontend tests for decision labels, exact values, warnings, and accessibility-visible states.
- Formatter/lint/type checks only against changed files.

## References

- [Decision index](../DECISION_INDEX.md)
- [Research scoring model](../04_SCORING_MODEL.md#6-versioning)
- [Research evidence model](../04_SCORING_MODEL.md#13-research-evidence-version-research-evidence-v1)
