# EXP-04 — Selection-aware exports

## Goal and boundaries

Extend owner-scoped exports to support filtered rows, explicit selections, Shortlists, comparisons, and Topic Workspaces with optional technical details and a mandatory dataset confirmation. Preserve frozen historical evidence, owner boundaries, queued generation, download expiry, and formula/Unicode safety.

Do not export foreign data, imply live provider collection, mutate frozen source records, or bypass confirmation for a changing dataset.

## Acceptance

- Owners can select a supported stored dataset, confirm its exact scope, and request an export with optional technical details.
- Export manifests freeze owner-scoped filters, selected identifiers, and source versions before queued generation begins.
- Foreign, unavailable, expired, or incompatible sources are rejected without exposing private resource details.
- Download, queued, failed, expired, empty, partial-data, and Unicode/formula-safe output states are explicit.
- Focused backend and frontend tests cover authorization, confirmation, frozen manifests, bounded generation, and accessible controls.

## Applicable decisions

- D-005–D-008: Export reads, manifests, requests, and downloads remain authenticated and owner-safe.
- D-014–D-018: Exported observations and source manifests remain immutable historical evidence.
- D-038: Desktop accessibility, keyboard behavior, and exact values are required.
- D-041–D-042: Evidence quality, freshness, unavailable values, and partial datasets remain explicit and null-safe.

## Initial inspection targets

- Existing export models, requests, actions, jobs, serializers, download controllers, routes, pages, and focused tests.
- Existing Favorites, Shortlist, comparison, and Topic Workspace read-model boundaries used as export sources.
- Existing retention, file expiry, queue idempotency, and CSV/Unicode/formula-safety utilities.

## Focused verification

- Feature tests for owner isolation, mandatory confirmation, frozen source manifests, queued retry safety, safe output, and expiry/download states.
- Frontend tests for source selection, confirmation, empty, partial, queued, failed, expired, and keyboard-operable controls.
- Formatter, lint, type, PHPStan, and Pint checks only for changed files.

## Completion record

- Completed 2026-08-21. Added confirmed source selection for completed runs, Shortlists, comparisons, and Topic Workspaces; frozen owner-scoped source/run manifests and selected video-row filters before dispatch; optional technical columns; and explicit queued, failure, expiry, and accessible confirmation states.
- Focused checks passed: export feature tests (17 tests, 169 assertions), export frontend test, scoped ESLint/Prettier, Pint, PHPStan, and diff check.
- Manual verification required: `composer test`; `npm run check`; create a filtered Topic Workspace export and confirm its frozen scope before download.
