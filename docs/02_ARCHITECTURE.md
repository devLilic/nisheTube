# Architecture

## 1. System context

NisheTube is a Laravel monolith with an Inertia/React frontend and MySQL storage. It runs locally in Laragon and communicates outbound with the YouTube Data API over HTTPS.

```text
Browser -> Laravel routes/controllers -> application actions -> domain services
                                             |              -> scoring engine
                                             |              -> provider contracts
                                             |                    -> YouTube Data API
                                             -> queued jobs -> MySQL
Inertia props <- resource/view models <- stored runs, snapshots, scores, quota ledger
```

## 2. Technology baseline

- PHP 8.3 and Laravel 13.x.
- Official Laravel React starter-kit conventions: Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui, Fortify-based authentication.
- MySQL 8.4.
- Database queue and cache initially.
- Laravel HTTP client for external calls.
- Laravel scheduler/commands for retention and optional recurring work.
- A chart library may be added in the foundation phase after one comparison screen validates its need.

## 3. Backend boundaries

Use bounded modules under `app/Domain` or an equivalent consistently chosen structure:

| Domain | Responsibility |
|---|---|
| `Research` | Queries, run lifecycle, filters, orchestration, progress. |
| `YouTube` | API client, DTO normalization, pagination, quota tracking, errors. |
| `Catalog` | Deduplicated videos/channels and their immutable metric snapshots. |
| `Scoring` | Versioned component calculations, confidence, explanations. |
| `Discovery` | Seed exploration, anomaly detection, clustering contracts, candidates. |
| `Library` | Projects, favorites, notes, tags. |
| `History` | Comparison queries and trend projections from stored snapshots. |
| `Exports` | Export selection, generation, storage, expiration. |
| `Settings` | User preferences, market definitions, safe integration status. |
| `Retention` | Eligibility preview, deletion, audit logging. |

Controllers validate/authorize and invoke application actions. They do not contain scoring formulas, raw provider response parsing, or multi-step orchestration.

## 4. Frontend boundaries

Follow official starter-kit conventions under `resources/js`:

```text
resources/js/
  components/       shared presentational components
  features/         domain-specific components, filters, view models
  hooks/            shared UI hooks
  layouts/          authenticated and auth layouts
  lib/              formatting and UI-only utilities
  pages/            route-level Inertia pages
  types/            shared and generated TypeScript types
```

Do not reproduce domain scoring calculations in TypeScript. The backend returns component scores, explanations, confidence, and display-ready raw metrics. Frontend-only calculations are limited to presentation formatting.

## 5. Provider contracts

Define interfaces before concrete providers:

- `VideoResearchProvider`: search, fetch videos, fetch channels, fetch categories/regions.
- `TopicExpansionProvider`: optional future query expansion.
- `ClusteringProvider`: deterministic implementation initially; AI adapter later.
- `ExportWriter`: CSV and XLSX implementations.
- `QuotaLedger`: record attempts/costs and summarize local estimates.

Provider responses are normalized into internal DTOs. Raw payloads may be retained temporarily for diagnostics only if scrubbed of secrets and governed by retention.

## 6. Research run lifecycle

Suggested states:

```text
draft -> queued -> searching -> enriching -> scoring -> completed
                    |              |           |
                    +--------------+-----------+-> failed
completed/failed -> retry creates a new attempt while preserving history
```

Persist state transitions, progress counts, timestamps, and a safe error code/message. A retry must not silently duplicate snapshots or quota ledger entries.

## 7. Collection flow

1. User submits a validated search form.
2. Application creates a user-owned query and run with frozen parameters.
3. A job calls `search.list`, recording quota attempt and page tokens.
4. Video IDs are deduplicated and enriched in batches through video endpoints.
5. Channel IDs are deduplicated and enriched in batches.
6. Immutable metric snapshots are attached to the run.
7. Scoring engine calculates versioned components and confidence.
8. Run becomes completed and the Inertia UI refreshes/polls its status.

Discovery reuses the same collection pipeline rather than inventing a second YouTube client.

## 8. Authentication and authorization

- Use Laravel session authentication and built-in password hashing.
- Use policies for every user-owned aggregate.
- Never trust a `user_id` received from the browser; derive it from the authenticated session.
- Route model binding must still invoke authorization.
- Export and deletion jobs re-check ownership when executing.

Roles are not needed initially. If administration is later introduced, add explicit policies rather than email-based checks.

## 9. Time and historical integrity

- Store all timestamps in UTC.
- Persist `collected_at`, `started_at`, `completed_at`, and `calculated_at` where relevant.
- Completed snapshots are immutable; a refresh creates a new snapshot/run.
- Store scoring formula version and frozen run parameters.
- Comparison services reject or warn on incompatible markets, filters, or formulas.

## 10. Queue and scheduler behavior

- Use the database queue so the project has no Redis requirement.
- The local development command should start the web server, Vite, and queue worker together.
- Retention must also be invokable by a manual Artisan command because the PC is not always running.
- On normal app use, the UI may show that cleanup is due and let the user run it. Do not assume a 24/7 scheduler.

## 11. Error handling and observability

- Map provider errors to stable internal codes such as `quota_exhausted`, `invalid_key`, `rate_limited`, `provider_unavailable`, and `invalid_request`.
- Store safe diagnostic context and correlation IDs; never store the API key.
- UI errors include a next action: retry, update settings, reduce depth, or wait.
- Log state changes and cleanup outcomes through Laravel logging and database audit records where required.

## 12. Caching

- Cache slowly changing reference data such as regions/categories.
- Reuse recent video/channel enrichment only when the requested freshness policy allows it.
- Cache must never replace a run's immutable record of which metrics were used.
- Cache keys include provider and relevant market/language dimensions.

