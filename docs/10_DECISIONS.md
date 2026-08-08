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

## D-011 — Persistent local YouTube quota estimate

**Status:** Accepted  
**Decision:** Show a compact `YouTube API Today` widget in every authenticated page. It displays a locally calculated remaining amount per configured quota bucket, last request details, and reset time.  
**Reason:** The user needs immediate visibility of the remaining daily budget during research. Google Cloud Console remains authoritative because quota can also be consumed outside NisheTube.

## D-012 — Live task-status register

**Status:** Accepted  
**Decision:** `docs/TASK_STATUS.md` is the live task-state register. It tracks each task as Pending, In progress, Completed, or Blocked, stores completion verification, and contains exactly one active task unless the project is blocked.  
**Reason:** Codex needs an auditable, durable handoff between chats and must only promote the next task after the prior one is correctly verified.

## D-016 — Preserve favorited runs during automatic retention cleanup

**Status:** Accepted  
**Decision:** Favorited research runs are exempt from automatic and manual-retention cleanup until unfavorited. Selective manual deletion may include a favorited run only after the owner receives an explicit favorite-impact warning and confirms that exact deletion scope.  
**Reason:** Favorites represent deliberate user curation. Automatic retention must not silently remove their historical evidence, while an explicit owner-directed deletion path still allows storage to be reclaimed intentionally.

## D-013 — Loopback-only registration

**Status:** Accepted  
**Decision:** Keep registration available for multiple local users, but permit the registration screen and submission only from loopback addresses.  
**Reason:** NisheTube requires multiple local accounts while avoiding unintended account creation if the Laragon virtual host is exposed to the LAN. Revisit this boundary if trusted-proxy or intentional LAN access is introduced.

## D-014 — Codex runs focused checks only

**Status:** Accepted  
**Decision:** Codex may run only tests and checks explicitly scoped to the active task's exact test files, filters, or changed source files. Full PHP, React/frontend, build, and aggregate verification commands are manual-only. At completion, Codex gives the user a checklist of at most three relevant manual checks in commentary and returns exactly `TASK DONE` as the final response.  
**Reason:** Keep automated task work fast and bounded while leaving broad project verification under direct user control.

## D-015 — Durable search staging before enrichment

**Status:** Accepted  
**Decision:** Persist normalized search pages, page tokens, and deduplicated video candidates per research run before dispatching enrichment. Treat these records as retry staging rather than canonical catalog snapshots.  
**Reason:** Database-backed staging lets a redelivered search job resume pagination without duplicating saved entities and gives the later catalog enrichment job a durable handoff instead of relying on a large, fragile queue payload.
