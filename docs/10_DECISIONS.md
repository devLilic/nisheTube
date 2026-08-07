# Decision Log

## D-001 — Local Laravel monolith

**Status:** Accepted  
**Decision:** Run a Laravel monolith locally through Laragon. No public server or deployment is required.  
**Reason:** The application is a personal/local research tool and benefits from Laravel queues, scheduler, validation, authorization, and database integration without operational infrastructure.

## D-002 — Laravel React stack

**Status:** Accepted  
**Decision:** Laravel 13.x, Inertia 3, React 19, TypeScript, Tailwind 4, and shadcn/ui, using official Laravel starter-kit conventions.  
**Reason:** Compatible with installed PHP 8.3 and Node 22 and provides authentication plus a strong visual foundation.

## D-003 — MySQL and database queue

**Status:** Accepted  
**Decision:** MySQL 8.4 for persistence and database-backed queues/cache initially.  
**Reason:** Available in Laragon and avoids requiring Redis for a local on-demand tool while preserving a future configuration path.

## D-004 — Multi-user local authentication

**Status:** Accepted  
**Decision:** Support login, registration, and multiple local users with strict ownership.  
**Reason:** Explicit product requirement; local operation does not remove the need for data separation.

## D-005 — English UI and three markets

**Status:** Accepted  
**Decision:** UI copy is English. Research markets are Global/English, Romania/Romanian, and Russia/Russian.  
**Reason:** Explicit product requirement. Market parameters are frozen per run.

## D-006 — Six-month snapshot retention

**Status:** Accepted  
**Decision:** Snapshot/run artifacts become cleanup-eligible after six months, with automatic/manual cleanup paths and selective manual deletion.  
**Reason:** Keep local storage bounded while retaining useful history.

## D-007 — Explainable five-part score

**Status:** Accepted  
**Decision:** Opportunity uses demand momentum, competition opportunity, audience reachability, content freshness gap, and creator viability, plus a separate confidence score.  
**Reason:** Balances interest, competitive access, freshness, and repeatability without claiming unavailable search-volume or revenue data.

## D-008 — Versioned immutable research

**Status:** Accepted  
**Decision:** Completed snapshots and scores are immutable and timestamped; refresh creates a new run. Formula changes create a new score version.  
**Reason:** Enables trustworthy history and comparisons.

## D-009 — Extensible provider boundaries

**Status:** Accepted  
**Decision:** YouTube collection, clustering, query expansion, and export implementations sit behind contracts. Deterministic discovery is first; AI is optional later.  
**Reason:** Preserve future integration paths without making the MVP depend on an AI vendor.

## D-010 — Discovery is seed-driven

**Status:** Accepted  
**Decision:** General discovery uses seed queries, sampling, breakout detection, topic extraction, and validation searches. `mostPopular` is supplementary only.  
**Reason:** The current `mostPopular` chart is limited to trending music, movies, and gaming and no longer represents general Trending Now.

## Open decision O-001 — Favorites during retention cleanup

**Status:** Must resolve before `RET-01`  
Choose and document one behavior:

1. favorite snapshots/runs are exempt from automatic cleanup until unfavorited; or
2. favorites preserve only a lightweight reference/note while the historical snapshot may be deleted after explicit warning.

Recommended default: exempt favorited runs from automatic cleanup and require explicit manual deletion confirmation.

## Open decision O-002 — Registration policy

**Status:** May resolve during `AUTH-01`  
Because this is a local tool, decide whether registration stays open on the local network or becomes disabled after the first user. Recommended default: allow registration while bound to localhost only; revisit if Laragon exposes the site to the LAN.

