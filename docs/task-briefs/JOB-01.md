# JOB-01 — Controlled background jobs and notifications

## Goal and boundaries

Make existing queued collection and enrichment work safer and clearer to local users: preserve partial data, expose safe failure reasons and explicitly estimated ETA, allow cancellation before work starts, provide controlled retry and meaningful completion/failure notifications, and identify when no worker is running. Do not add new providers, duplicate quota consumption, deployment work, or cross-user visibility.

## Acceptance

- Queued work has guarded idempotent transitions, owner rechecks, safe retry/cancellation behavior, and quota-ledger integrity.
- Users see loading, partial, success, failure, cancellation, and worker-unavailable states with accurate, non-secret wording.
- Completion/failure notifications are owner-scoped and have durable read state.
- The responsive UI supports keyboard operation and preserves existing immutable-run/snapshot rules.
- Focused backend and frontend tests cover transitions, authorization, notifications, safe copy, and worker feedback.

## Applicable decisions

- D-005–D-008: Authentication, user ownership, and private local access remain enforced.
- D-009–D-013: Provider boundaries, quota accounting, failure handling, and secret safety remain intact.
- D-014–D-018: Existing completed records remain immutable and reproducible.
- D-038: Desktop accessibility, keyboard behavior, and exact values are required.
- D-041–D-042: Partial data, estimates, and unavailable values remain explicit.

## Initial inspection targets

- Existing queued research, discovery, analyzer, export, and watchlist jobs plus their lifecycle models/actions.
- Existing completed-run notification domain/UI and queue configuration.
- Focused job lifecycle, ownership, quota, notification, and polling/frontend tests.

## Focused verification

- Exact feature/unit tests for each changed job lifecycle and notification flow.
- Frontend tests for all visible job states and safe estimated/partial-data wording.
- Formatter, lint, type, PHPStan, and Pint checks only for changed files.

## Exact reference links

- [Working rules](../00_WORKING_RULES.md)
- [Task index](../TASK_INDEX.md)
