# NisheTube Codex Instructions

## Mission

Build NisheTube as a local, multi-user YouTube research application for discovering and evaluating content niches in three markets: Global/English, Romania/Romanian, and Russia/Russian.

## Read before changing code

Read these documents in order:

1. `docs/01_PRODUCT_REQUIREMENTS.md`
2. `docs/02_ARCHITECTURE.md`
3. `docs/03_DATA_MODEL.md`
4. `docs/04_SCORING_MODEL.md`
5. `docs/05_UI_UX.md`
6. `docs/06_YOUTUBE_API.md`
7. `docs/08_BACKLOG.md`
8. `docs/09_ACCEPTANCE_AND_TESTING.md`

If implementation requires changing a locked decision, update `docs/10_DECISIONS.md` in the same change and explain the reason.

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

1. Select the next unblocked task group from `docs/08_BACKLOG.md`.
2. State the task IDs being implemented.
3. Inspect existing code and tests before editing.
4. Implement the smallest complete vertical slice.
5. Run focused tests, then the full relevant verification suite.
6. Update checkboxes only after acceptance criteria pass.
7. Record material architectural decisions in `docs/10_DECISIONS.md`.

Do not begin a later phase when an earlier dependency is incomplete, except for isolated design-system work that does not create throwaway behavior.

## Code conventions

- PHP: Laravel conventions, strict validation through Form Requests, policies for authorization, service/action classes for use cases, typed DTOs or value objects at external boundaries.
- TypeScript: strict types; avoid `any`; keep server-page props and shared types explicit.
- React: pages compose feature components; no API calls from components when an Inertia action is appropriate.
- Database: foreign keys, useful composite indexes, UTC timestamps, and explicit cascade/restrict behavior.
- Tests: Feature tests for user flows and authorization; Unit tests for scoring/math and provider normalization; frontend tests for calculation-heavy components when needed.
- Copy: all labels, validation messages, empty states, and user-facing errors are in English.

## Verification baseline

Run the commands available after the React starter kit is adopted:

```text
composer test
vendor/bin/pint --test
npm run types
npm run lint
npm run build
```

If scripts differ in the installed starter kit, align this list and `docs/07_LOCAL_SETUP.md` with `composer.json` and `package.json`.

## Safety and data rules

- Preserve user data and unrelated local changes.
- Never delete snapshots except through the documented manual deletion flow or the six-month retention job.
- Manual deletion must require confirmation, enforce ownership, and create an audit record.
- Retention must target only snapshot/run data older than six months; never delete users, projects, settings, or favorites unintentionally.
- External API failures must preserve the run record and expose a retryable status in the UI.

