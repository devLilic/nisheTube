# BULK-01 — Cross-surface bulk actions

## Goal and boundaries

Provide a bounded, shared selection model for eligible owner-scoped stored evidence across the established decision surfaces. Support only valid Workspace, Shortlist/Favorite, Dismiss, Export, Compare, and Watch actions. Require explicit confirmation before destructive or quota-consuming work. Do not add provider collection behavior, bypass existing ownership policies, mutate completed evidence, or create broad private-data caches.

## Acceptance

- A shared selection model has an explicit item limit, is keyboard accessible, and retains only compatible, owner-authorized targets.
- Each bulk action reauthorizes every submitted target, remains idempotent where repeated, and rejects invalid, mixed, or foreign selections without leaking private data.
- Destructive and quota-consuming actions show their confirmation and, when applicable, quota preview before dispatch.
- The UI explicitly communicates loading, success, empty selection, partial completion, invalid/mixed selection, and error outcomes.
- Focused backend and frontend coverage verifies bounds, authorization, idempotency, mixed/partial outcomes, keyboard selection, and documentation.

## Applicable decisions

- D-005–D-008: Authentication, authorization, and user-owned cached/navigation state remain private and owner-scoped.
- D-009–D-013: Provider boundaries, queue safety, quota accounting, retry behavior, and secret safety remain intact for quota-consuming actions.
- D-014–D-018: Completed runs and metric snapshots stay immutable; bulk actions organize or reference them rather than rewrite historical evidence.
- D-038: Desktop keyboard access, focus behavior, and exact visible values are required.
- D-041–D-042: Mixed, partial, unavailable, and estimated outcomes must remain explicit.

## Initial inspection targets

- Existing selection, favorite, workspace, shortlist, watchlist, export, comparison, and dismissal actions, policies, and request boundaries.
- Stored-data table/card interfaces that can expose a bounded shared selection affordance.
- Focused owner-authorization, idempotency, quota, bulk-outcome, and keyboard-accessibility tests.

## Relevant contracts and UI

- Existing domain actions and Form Requests remain the mutation boundary; controllers orchestrate only authorized use cases.
- Existing policies must be reapplied per selected target at action execution time, including queued follow-on work.
- Selection state must remain local/owner-private, bounded, recoverable after partial errors, and not silently carry across incompatible surfaces.
- Visible bulk controls must expose selection count and limits, keyboard-operable selection, confirmations, quota impact where relevant, and clear outcome summaries.

## Implemented vertical slice

### Discovery candidate bulk dismissal

- The Candidate niches and Weak phrase signals tables share one keyboard-accessible, owner-private selection model, capped at ten candidates within the current Discovery run.
- A confirmed bulk-dismiss action reauthorizes the run and every selected candidate, rejects foreign or mixed-run identifiers without exposing them, and is safe to repeat.
- Validated candidates are never reclassified; already-dismissed candidates remain unchanged. The exact partial outcome is reported in the owner-visible toast, while the table keeps loading, success, empty-selection, limit, and error states explicit.
- This slice does not add a bulk route for actions that are not valid for Discovery candidates. Workspace, Favorite/Shortlist, Export, Compare, and Watch bulk paths remain in scope for their compatible stored-data surfaces.

## Focused verification

- Exact feature/unit tests for every selected target type, owner/foreign/mixed validation, action bounds, retry/idempotency, and partial outcomes.
- Frontend tests for keyboard selection, retained/cleared selection rules, confirmation and quota-preview states, and explicit loading/success/empty/partial/error rendering.
- Formatter, lint, type, PHPStan, and Pint checks only for changed files.

## Exact reference links

- [Working rules](../00_WORKING_RULES.md)
- [Task index](../TASK_INDEX.md)
- [Backlog entry](../08_BACKLOG.md)
