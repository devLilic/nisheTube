# LIB-04 — Project and idea decision context

## Goal and boundaries

Extend owner-scoped Projects with decision context (purpose, market, themes, Shortlist, workspaces, status, activity, and decisions) and extend Ideas with optional topic, candidate, video, workspace, format, audience, and status context. Preserve saved-comment source semantics, ownership boundaries, immutable source evidence, retention safety, and bounded reads.

Do not copy mutable metrics into Project or Idea context, expose foreign or incompatible references, or turn optional decision context into an implied recommendation.

## Acceptance

- Owners can add, inspect, and update their own Project and Idea decision context with clear, optional relationships.
- Foreign, archived, and incompatible references are rejected without revealing another user's data.
- Project and Idea views distinguish empty, populated, unavailable, partial-data, and validation/error states.
- Stored source semantics and retention boundaries remain unchanged; context adds references rather than mutating historical observations.
- Focused backend and frontend tests cover ownership, compatibility validation, bounded reads, and accessible controls.

## Applicable decisions

- D-005–D-008: Project, Idea, and linked context reads/writes remain authenticated and owner-safe.
- D-014–D-018: Snapshot and run evidence remains immutable and versioned at its source.
- D-038: Desktop accessibility, keyboard behavior, and exact values are required.
- D-041–D-042: Evidence quality, freshness, and unavailable states remain explicit and null-safe.

## Initial inspection targets

- Existing Project, saved-comment Idea, Shortlist, Topic Workspace, and candidate models, policies, requests, actions, read models, routes, pages, and focused tests.
- Existing Library and Ideas UI controls, relationship selectors, state components, and dialog patterns.
- Retention and evidence-link boundaries for referenced runs, snapshots, and saved comments.

## Focused verification

- Feature tests for owner isolation, optional context validation, incompatible/archived reference rejection, and retention-safe source links.
- Frontend tests for empty, populated, unavailable, partial-data, validation/error, and keyboard-operable controls.
- Formatter, lint, type, PHPStan, and Pint checks only for changed files.
