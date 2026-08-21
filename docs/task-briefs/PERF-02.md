# PERF-02 — Stored-interface performance and navigation continuity

## Goal and boundaries

Measure and improve the performance of owner-scoped stored-data interfaces while preserving navigation continuity. Apply bounded pagination, justified virtualization, lazy thumbnail handling, debounced interactions, safe filter caching, skeletons, partial reloads, scroll restoration, prefetching, or sticky tables only where existing evidence shows they are needed. Do not change provider collection behavior, bypass authorization, or introduce broad caching of private data.

## Acceptance

- Each changed surface has documented performance budgets and bounded payload, memory, and query behavior.
- Pagination, caching, deferred loading, and navigation preserve owner scope, filters, and accessibility.
- No N+1 queries, private-cache leakage, or keyboard/visual regression is introduced.
- Loading, empty, partial, success, and error states remain explicit on affected screens.
- Focused backend and frontend performance regressions plus documentation pass.

## Implemented performance budget

### Projects index

- **Payload and rendered memory:** a page contains at most 24 owner-scoped project cards; each card carries only its presentation fields and three database-derived counts. No thumbnails are loaded on this surface.
- **Database behavior:** pagination uses one count query and one owner-scoped, aggregate-count select query; the card count must not create per-project relationship queries. The complete Inertia request is budgeted at ten queries or fewer, including framework and shared-prop reads.
- **Navigation behavior:** filter and pagination visits preserve the active filters and scroll position, replace filter-history entries, and reload only `projects`, `pagination`, and `filters`. Text search waits 300 ms after typing stops before requesting data.
- **Accessible states:** the existing empty state remains visible; in-place partial data remains visible during updates; a live status announces loading, success totals, and update failures.

## Applicable decisions

- D-005–D-008: Reads, cached state, and navigation remain owner-scoped and private.
- D-014–D-018: Stored historical evidence remains immutable and reproducible.
- D-038: Desktop accessibility, keyboard behavior, and exact values are required.
- D-041–D-042: Partial, estimated, and unavailable values remain explicit.

## Initial inspection targets

- Existing paginated/stored-data read models and their page/view-model payloads.
- Shared table, thumbnail, filtering, polling, and navigation components.
- Focused query-count, pagination, accessibility, and frontend interface tests.

## Focused verification

- Exact performance/query-count and feature tests for each changed surface.
- Frontend tests for retained filters, loading states, keyboard access, and bounded rendering.
- Formatter, lint, type, PHPStan, and Pint checks only for changed files.

## Exact reference links

- [Working rules](../00_WORKING_RULES.md)
- [Task index](../TASK_INDEX.md)
