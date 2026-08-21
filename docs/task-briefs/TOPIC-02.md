# TOPIC-02 — Topic Workspace decision canvas and evidence workflow

## Goal and boundaries

Extend the existing owner-scoped Topic Workspace with a practical decision canvas that keeps its linked immutable research evidence, notes, and next actions readable and usable. Preserve existing workspace ownership, market boundaries, archived-state behavior, and immutable source evidence.

Do not alter research runs, snapshots, or evidence profiles from the workspace. Do not expose another user's projects, workspaces, or evidence. Do not introduce provider calls from workspace UI interactions.

## Acceptance

- The owner can view a clear workspace decision canvas with its project, market, linked evidence, notes, and actionable next steps.
- Evidence additions/removals and workspace notes remain owner-scoped, validated, and auditable through existing domain boundaries.
- The interface provides loading, empty, populated, partial-data, and error states with keyboard-operable controls and exact stored values.
- Existing archived and market-compatibility guards remain explicit; unavailable evidence is not implied or fabricated.
- Focused backend and frontend tests cover ownership, evidence validation, empty/partial states, and keyboard-accessible workflow controls.

## Applicable decisions

- D-005–D-008: workspace reads and writes remain authenticated and owner-safe.
- D-014–D-018: linked research runs and snapshots remain immutable historical evidence.
- D-038: desktop accessibility, keyboard behavior, and exact values are required.
- D-041–D-042: evidence quality and unavailable states remain explicit and null-safe.

## Initial inspection targets

- Existing Topic Workspace controllers, requests, policies, actions, read models, routes, pages, and focused feature tests.
- Existing workspace launch, evidence, project, and research-run ownership boundaries.
- Existing Topic Workspace frontend components and shared dialog/state components.

## Focused verification

- Feature tests for owner isolation, workspace/evidence validation, archived-state guards, and action routing.
- Frontend tests for empty, populated, partial, error, and keyboard workflows.
- Formatter, lint, type, PHPStan, and Pint checks only for changed files.

## Completion record

- Completed 2026-08-21.
- Added a stored-evidence decision canvas that presents the workspace note, exact evidence coverage by role, explicit unavailable/cross-market warnings, and a safe next workflow action without mutating source evidence or dispatching provider work.
- Focused checks passed: `php artisan test tests/Feature/Topics/TopicWorkspaceWorkflowTest.php`; `node --test tests/Frontend/topic-workspace-interface.test.mjs`; scoped Pint, Prettier, ESLint, PHPStan, and diff check.
- Manual verification required: `composer test`; `npm run check`; link evidence, review the decision canvas warning/action, then archive and restore the workspace.
