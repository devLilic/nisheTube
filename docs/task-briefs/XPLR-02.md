# XPLR-02 — Explore productivity

## Goal and boundaries

Deliver the smallest owner-scoped Explore productivity slice: filters, view state, score labels, preserved return state, and saved presets over stored local evidence. Do not introduce provider collection, alter historical records, or implement the following Analyzer work.

## Acceptance

- Explore filters and saved presets are validated, owner-scoped, durable where required, and preserve return context.
- Score labels, missing/legacy values, and stored-evidence provenance remain explicit.
- The UI covers loading, empty, success, partial, invalid-filter, and error states accessibly.
- Reads do not call providers or recalculate history.
- Focused feature and frontend tests cover ownership, validation, persisted state, and visible states.

## Applicable decisions

- D-005–D-008: authenticated local users and owner-safe access.
- D-038: desktop acceptance with accessibility and exact-value requirements.
- D-041–D-042: frozen evidence and unavailable history/signals stay explicit.

## Initial inspection targets

- `app/Domain/Explore/`, `app/Http/Controllers/Explore/`, `app/Http/Requests/Explore/`, and `resources/js/pages/explore/`.
- Existing Explore feature/frontend tests.

## Focused verification

- Feature tests for owner isolation, filter validation, return state, and saved presets.
- Frontend tests for labels and all visible data states; formatter/lint/type checks only for changed files.

## References

- [Decision index](../DECISION_INDEX.md)
- [Research evidence model](../04_SCORING_MODEL.md#13-research-evidence-version-research-evidence-v1)

## Completion record — 2026-08-16

- Added owner-scoped durable Explore filter presets, explicit score provenance labels, and Analyzer return links that retain the active Explore filters and page.
- Focused checks passed: `php artisan test tests/Feature/Explore/ExploreIndexTest.php`, `node --test tests/Frontend/explore-interface.test.mjs`, `vendor/bin/pint --test` for changed Explore PHP files, and changed-file ESLint/Prettier checks using local binaries.
- Manual verification required: run `composer test`, run `npm run check`, then save/apply a preset and verify an Analyzer return preserves the Explore context.
