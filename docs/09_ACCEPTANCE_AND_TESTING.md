# Acceptance and Testing

## 1. Definition of done

A task group is done only when:

- acceptance behavior is implemented with real persistence or explicit fakes in tests;
- authenticated ownership and unauthorized access are tested;
- the UI exists and handles loading, empty, success, partial-data, and error states;
- validation and destructive confirmations are present;
- Codex-run tests and checks are strictly scoped to the current task and pass;
- the user receives a short checklist for any full PHP, React/frontend, or cross-stack verification that must be run manually;
- no secret appears in code, logs, fixtures, rendered pages, or committed files;
- related documentation and backlog checkboxes are current.

Codex must never run full or aggregate project suites. Commands such as `composer ci:check`, `composer test`, bare `php artisan test`, `npm run check`, `npm run types`, `npm run lint:check`, `npm run format:check`, and `npm run build` are manual-only. The absence of a Codex-run full suite does not prevent task completion when the focused checks and acceptance criteria pass.

At completion, Codex provides the manual checklist in commentary and its final response is exactly `TASK DONE`. A blocked or incomplete task must not use that completion response.

## 2. Test layers

### PHP unit tests

- scoring formulas, percentiles, confidence and versioning;
- provider normalization and error mapping;
- value objects, time windows, retention eligibility;
- deterministic discovery utilities;
- export cell sanitization.

### Laravel feature tests

- authentication and settings;
- user ownership/policies for every aggregate;
- Inertia responses and form validation;
- queue dispatch and run transitions;
- API fakes, quota ledger, retries and failures;
- cleanup previews/execution and audit;
- export download authorization.

### Frontend tests

Use targeted component tests for interactive/high-risk behavior such as score explanations, comparison deltas, cleanup confirmation, table filtering, and progress/error transitions. Do not duplicate Laravel tests for static markup.

### End-to-end smoke flow

Automate when practical; otherwise maintain a reproducible manual checklist for the complete local workflow.

## 3. Provider testing rules

- Automated tests never call the live YouTube API.
- Use Laravel HTTP fakes and sanitized fixtures.
- Test multiple pages, batch splitting, missing fields, non-200 responses, timeouts, malformed payloads, and partial completion.
- Assert quota usage is recorded for attempts according to current configuration.
- Assert the API key never appears in exceptions or UI props.

## 4. Authorization matrix

For each user-owned resource, test:

- owner can list/view/create/update/delete as intended;
- another authenticated user receives not-found or forbidden without resource details;
- guest is redirected to login;
- queued actions still enforce owner and current target state;
- downloads and manual cleanup cannot accept foreign IDs.

Resources include projects, queries, runs, discovery runs/candidates, favorites, tags, exports, settings, and cleanup actions.

## 5. Data integrity tests

- A completed refresh creates a new run/snapshot and does not overwrite the previous one.
- Duplicate job delivery does not duplicate run entities or quota ledger entries incorrectly.
- Missing provider counts remain null.
- All run parameters, market mapping, formula version, and timestamps remain frozen.
- Cascade rules delete only documented dependents.
- Retention preserves users, settings, projects, saved queries, and unrelated favorites.

## 6. Scoring acceptance

- Inputs and weights match `04_SCORING_MODEL.md` for `niche-opportunity-v1`.
- Same stored inputs produce the same stored score.
- Overall score remains within 0–100.
- All component scores and confidence remain within 0–100.
- An extreme single video cannot dominate a robust aggregate unexpectedly.
- Missing values lower confidence and create warnings.
- UI displays confidence, timestamp, formula version, and explanations with the score.

## 7. Visual acceptance

Every page is reviewed at approximately 1440px and 768px widths:

- no clipped actions or unreadable tables;
- loading skeletons do not cause severe layout shift;
- long English/Romanian/Russian titles are handled;
- numeric formatting retains exact-value access;
- focus order is logical;
- status uses text/icon in addition to color;
- charts have a textual or tabular alternative;
- destructive actions clearly name their scope.

## 8. Release smoke checklist

1. Start Laragon services and development processes.
2. Register two users and verify isolation.
3. Set default markets and timezone.
4. Test YouTube connectivity without exposing the key.
5. Run one search in each of the three markets.
6. Observe queue/progress and inspect completed video/channel data.
7. Inspect score, components, confidence, warnings, and collection time.
8. Save results to a project/favorites.
9. Run a later comparable snapshot and compare it.
10. Run discovery and validate a candidate.
11. Export CSV and XLSX with Unicode data.
12. Preview retention, manually remove a selected eligible snapshot, and inspect audit history.
13. Manually run the full verification commands; Codex must not run them.

## 9. Performance targets for the local MVP

These are engineering targets, not external service guarantees:

- normal authenticated pages should avoid visible blocking on external API calls;
- research submission should persist/queue promptly and navigate to progress;
- tables must paginate rather than render unbounded data;
- common dashboard and history queries must avoid N+1 loading;
- batch external requests within API limits;
- scoring should run from stored data and complete quickly for configured sample sizes.
