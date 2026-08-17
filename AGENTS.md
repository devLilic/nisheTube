# NisheTube Codex Instructions

## Mission

Build NisheTube as a local, multi-user YouTube research application for discovering and evaluating content niches in three markets: Global/English, Romania/Romanian, and Russia/Russian.

## Read before changing code

Read only these working documents, in order:

1. `docs/00_WORKING_RULES.md`
2. `docs/TASK_INDEX.md`
3. the brief linked for the single `In progress` task in `docs/task-briefs/`

The task brief is the operational source of truth for scope, acceptance, relevant decisions, code touchpoints, and focused checks. Read a document in `docs/reference/` only when the brief links to an exact section or cannot resolve a material ambiguity. Do not reload historical status, the full backlog, or the full decision log by default.

If implementation requires changing a locked decision, update `docs/DECISION_INDEX.md`, the active task brief, and the full entry in `docs/10_DECISIONS.md` in the same change, with the reason.

## Locked technical direction

- Laravel 13.x on PHP 8.3.
- Inertia 3, React 19, TypeScript, Tailwind CSS 4, and shadcn/ui.
- MySQL 8.4 through Laragon.
- Laravel session authentication with registration and multiple local users.
- Database-backed queues for the first version; Redis may be added behind queue/cache configuration later.
- English-only application interface for the first version.
- Store timestamps in UTC and display them using the authenticated user's timezone.
- Run locally from `C:\laragon\www\NisheTube`; no deployment work is in scope.

## Required implementation shape

- Organize business logic by domain, not in controllers or React pages.
- Keep YouTube calls behind provider contracts. Controllers and scoring code must not call Google directly.
- Treat completed research runs and metric snapshots as immutable historical records.
- Every user-owned query must be scoped by `user_id` and protected by policies or equivalent authorization.
- Use queued jobs for external API collection, enrichment, discovery, exports, and retention cleanup.
- Make jobs idempotent and safe to retry.
- Do not put API keys or passwords in source control, logs, exceptions, fixtures, screenshots, or documentation.
- Do not claim to measure YouTube search volume. The product measures observed demand from returned videos and channels.

## Mandatory vertical-slice rule

Every functional module must ship all of the following in the same milestone:

1. database/storage changes, when required;
2. backend domain logic and validation;
3. authorization and user ownership;
4. Inertia route/page or visible reusable UI components;
5. loading, empty, success, partial-data, and error states;
6. automated tests for critical behavior;
7. a short documentation or backlog status update.

A backend-only module is not done. A UI using hard-coded production data is not done.

## Development workflow

1. Read `docs/00_WORKING_RULES.md`, `docs/TASK_INDEX.md`, and the active task brief first. They are the live working context.
2. Work only on the single task marked `In progress`.
3. If no task is `In progress`, select the next unblocked `Pending` task from `docs/TASK_INDEX.md`, create its complete brief, mark it `In progress` in `docs/TASK_INDEX.md`, and state its ID before editing code.
4. Inspect existing code and tests before editing.
5. Implement the smallest complete vertical slice.
6. Run only tests and checks strictly scoped to the current task and the files changed for it. Codex must never run a full or aggregate PHP, React, frontend, or project verification suite.
7. Only when every acceptance criterion and Codex-scoped check passes: mark the task `Completed` in `docs/TASK_INDEX.md`; append the date, changed scope, focused checks, and manual checklist to the task's archival entry in `docs/TASK_STATUS.md`.
8. Create the next unblocked task's complete brief before marking it `In progress` in `docs/TASK_INDEX.md`. Do not implement it in the same turn unless the user explicitly asks.
9. If verification fails or a dependency is missing, keep the current task `In progress`, record the blocker in its brief and archival entry, and do not promote another task.
10. Record material architectural decisions in `docs/DECISION_INDEX.md` and `docs/10_DECISIONS.md`.

Do not begin a later phase when an earlier dependency is incomplete, except for isolated design-system work that does not create throwaway behavior.

## Code conventions

- PHP: Laravel conventions, strict validation through Form Requests, policies for authorization, service/action classes for use cases, typed DTOs or value objects at external boundaries.
- TypeScript: strict types; avoid `any`; keep server-page props and shared types explicit.
- React: pages compose feature components; no API calls from components when an Inertia action is appropriate.
- Database: foreign keys, useful composite indexes, UTC timestamps, and explicit cascade/restrict behavior.
- Tests: Feature tests for user flows and authorization; Unit tests for scoring/math and provider normalization; frontend tests for calculation-heavy components when needed.
- Copy: all labels, validation messages, empty states, and user-facing errors are in English.

## Verification boundary

Codex may run only narrowly targeted commands that name the exact test files, filters, or changed source files relevant to the active task. Examples:

```text
php artisan test tests/Feature/Research/SpecificTest.php
vendor/bin/pint --test app/Domain/Research tests/Feature/Research/SpecificTest.php
npx eslint resources/js/features/research/specific-component.tsx
npx prettier --check resources/js/features/research/specific-component.tsx
```

Codex must not run aggregate or full-project commands, including `composer ci:check`, `composer test`, bare `php artisan test`, `npm run check`, `npm run types`, `npm run lint:check`, `npm run format:check`, or `npm run build`. These checks are manual-only and belong to the user.

Before completing a task, Codex must tell the user that manual verification is required and provide a short checklist of no more than three relevant checks:

- PHP/backend changes: `composer test`;
- React/frontend changes: `npm run check`;
- cross-stack changes: both commands, plus one concise task-specific manual UI flow when needed.

When the task is complete, provide the checklist in commentary and make the final response exactly `TASK DONE`, with no other text, Markdown, or punctuation. Do not output `TASK DONE` when the task is blocked or incomplete.

## Safety and data rules

- Preserve user data and unrelated local changes.
- Never delete snapshots except through the documented manual deletion flow or the six-month retention job.
- Manual deletion must require confirmation, enforce ownership, and create an audit record.
- Retention must target only snapshot/run data older than six months; never delete users, projects, settings, or favorites unintentionally.
- External API failures must preserve the run record and expose a retryable status in the UI.
