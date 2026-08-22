# NisheTube Working Rules

## Task context

Before implementation, read this file, `TASK_INDEX.md`, and the linked brief for the sole `In progress` task. The brief is the working source of truth. Consult `reference/` only through a brief link or for a material unresolved ambiguity.

## Fixed technical direction

- Laravel 13 / PHP 8.3, Inertia 3, React 19, TypeScript, Tailwind CSS 4, shadcn/ui, and MySQL 8.4 through Laragon.
- Local multi-user application using Laravel session authentication; UI copy supports Romanian and English, with Romanian as the default and English as fallback.
- The active interface direction is light-only Liquid Glass with desktop, tablet, and mobile acceptance; dark/system controls are temporarily unavailable.
- Store timestamps in UTC and display them in the authenticated user's timezone.
- Use database-backed queues in v1; Redis and deployment work are out of scope.

## Non-negotiable engineering rules

- Keep domain logic out of controllers and React pages. Use typed boundaries, Form Requests, policies, domain actions/services, and provider contracts.
- Controllers and scoring code never call Google directly. External collection, enrichment, discovery, exports, and retention use retry-safe, idempotent queued jobs.
- User-owned reads and writes are scoped by `user_id` and authorized. Never expose ownership or resource details to another user.
- Completed research runs and metric snapshots are immutable historical records. New versions or refreshes create new records; never rewrite released results.
- Never put secrets in source control, logs, fixtures, exceptions, screenshots, or documentation. Do not claim YouTube search volume; describe observed demand from returned videos/channels.

## Vertical slice and verification

- A functional task includes persistence when needed, backend validation/domain logic, authorization, visible Inertia UI, loading/empty/success/partial/error states, critical automated tests, and the required documentation/status update.
- Run only commands scoped to the active task and changed files. Never run aggregate suites or broad commands such as bare `php artisan test`, `composer test`, `npm run check`, `npm run build`, or repository-wide lint/type commands.
- Before completing a task, provide no more than three manual checks: `composer test` for backend, `npm run check` for frontend, plus one concise task-specific UI flow when cross-stack behavior changed.

## Documentation maintenance

- `TASK_INDEX.md` contains only current state, dependencies, and task links. The active brief contains all operational detail.
- On completion, record detailed verification/history in `TASK_STATUS.md`; keep the index to one compact row per task.
- Before activating a task, create `task-briefs/<TASK-ID>.md`. It must include scope, acceptance, applicable decisions, relevant contracts/UI, focused tests, and exact reference links.
- A changed locked decision requires synchronized updates to the active brief, `DECISION_INDEX.md`, and `10_DECISIONS.md`.
