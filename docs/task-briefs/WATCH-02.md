# WATCH-02 — Watchlist alerts, monitoring controls, and notification integration

## Goal and boundaries

Extend the existing owner-scoped Watchlist with clear monitoring controls, alert visibility, and notification integration based only on stored refresh observations. Preserve user ownership, archived workspace boundaries, immutable refresh history, and explicit unavailable/partial data states.

Do not imply real-time monitoring, provider work before a confirmed monitoring action, or causal recommendations. Do not expose another user's watchlist items, channels, alerts, or notification state.

## Acceptance

- Owners can inspect and control their own watchlist monitoring settings and understand the next safe action.
- Stored refresh outcomes and alerts are explicit about freshness, partial data, unavailable values, and notification state.
- Monitoring and notification actions remain owner-scoped, validated, retry-safe, and do not mutate historical observations.
- The interface covers loading, empty, populated, partial-data, error, and keyboard-operable control states.
- Focused backend and frontend tests cover ownership, validation, refresh/notification status, and accessible controls.

## Applicable decisions

- D-005–D-008: watchlist and notification reads/writes remain authenticated and owner-safe.
- D-014–D-018: refresh observations and snapshots remain immutable historical evidence.
- D-038: desktop accessibility, keyboard behavior, and exact values are required.
- D-041–D-042: evidence quality, freshness, and unavailable states remain explicit and null-safe.

## Initial inspection targets

- Existing Watchlist controllers, requests, policies, refresh actions/jobs, read models, routes, pages, and focused tests.
- Existing notification and completed-run navigation boundaries.
- Existing watchlist frontend controls, status components, and dialog patterns.

## Focused verification

- Feature tests for owner isolation, monitoring validation, refresh/notification state, and retry safety.
- Frontend tests for empty, populated, partial, error, and keyboard control states.
- Formatter, lint, type, PHPStan, and Pint checks only for changed files.
