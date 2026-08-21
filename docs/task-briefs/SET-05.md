# SET-05 — Consolidated preferences and formatting

## Goal and boundaries

Consolidate the authenticated user’s local preferences and display-format controls into one clear, English-only settings experience. Preserve owner boundaries, UTC storage with user-timezone display, and existing defaults. Do not introduce deployment settings, external accounts, or provider collection behavior.

## Acceptance

- Authenticated users can view and update their supported local preferences through an accessible settings flow.
- Preference validation, ownership, saved values, loading, success, empty, and error states are explicit.
- Time, number, and market-related display choices remain consistent with stored UTC evidence and do not rewrite historical data.
- Focused feature and frontend tests cover validation, persistence, owner scope, keyboard-operable controls, and formatting fallbacks.

## Applicable decisions

- D-005–D-008: Settings and user-owned data remain authenticated and owner-safe.
- D-014–D-018: Preferences must not mutate immutable historical records.
- D-038: Desktop accessibility, keyboard behavior, and exact displayed values are required.
- D-041–D-042: Missing and partial values remain explicit and null-safe.

## Initial inspection targets

- Existing settings controllers, requests, actions, pages, shared authenticated-user props, and focused settings tests.
- Current timezone, default-market, result-depth, and display-format consumers.
- Existing form controls, validation patterns, and user preference persistence.

## Focused verification

- Feature tests for authenticated updates, validation, and owner-scoped persistence.
- Frontend tests for accessible preference controls and null-safe formatting states.
- Formatter, lint, type, PHPStan, and Pint checks only for changed files.

## Exact reference links

- [Working rules](../00_WORKING_RULES.md)
- [Task index](../TASK_INDEX.md)

## Completion record

- Completed 2026-08-21. Consolidated timezone, default-market, and result-depth controls on a dedicated Preferences settings page, with an accessible live formatting preview and explicit invalid-timezone, no-market, save, and UTC-storage states.
- Focused checks passed: settings feature tests (16 tests, 110 assertions), preferences frontend test (3 tests), scoped Pint, PHPStan, ESLint, Prettier, and diff check.
- Manual verification required: `composer test`; `npm run check`; save each preference and verify the formatting preview and a timestamp elsewhere in the workspace.
