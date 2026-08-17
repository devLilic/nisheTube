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

## D-017 — Shared collection boundary for public observations

**Status:** Accepted

**Decision:** Introduce user-owned `collection_runs` and workflow-specific source links so Search, Analyzer, and Watchlist share provider DTOs, cache policy, persistence, quota tracking, and immutable video/channel observations. Search pagination/rank staging remains Research-specific.

**Reason:** Existing snapshots are tied directly to Research runs, which would force Analyzer and Watchlist either to fake a search or duplicate snapshot/API logic. A staged shared boundary preserves historical Search behavior while enabling all workflows to pin the exact observation inputs they used.

## D-018 — Explore, Favorites, and Watchlist have different semantics

**Status:** Accepted

**Decision:** Explore is a no-provider-I/O read surface over the authenticated user's stored evidence. Favorites are bookmarks. Watchlist is an explicit opt-in to repeated queued observation of videos, channels, or later topics. No state is converted automatically between them.

**Reason:** Separating browsing, curation, and monitoring prevents surprise quota usage and makes retention and user intent auditable.

## D-019 — Structural metric provenance

**Status:** Accepted

**Decision:** Separate API observations, deterministic calculated metrics, inferred semantic analysis, and future estimates in persistence and UI. Use typed tables/read models rather than a universal metric EAV schema.

**Reason:** Users must know what YouTube actually returned versus what NisheTube calculated or inferred, while typed fields preserve validation, indexing, comparison, and export safety.

## D-020 — Analyzer MVP and deferred semantic/media scope

**Status:** Accepted

**Decision:** Analyzer MVP includes video/channel public data, a configurable recent-upload cohort, relative performance, channel behavior, immutable snapshots, first-seen history, and complete UI states. Semantic classification, comments/Audience Signals, transcripts, and thumbnail analysis are later vertical slices. Transcript implementation is blocked until an accepted provider/compliance decision exists.

**Reason:** The supplied specification's explicit MVP list excludes those advanced inputs, and YouTube Data API does not provide arbitrary third-party transcript text through the server-side API-key flow. This resolves the scope ambiguity without weakening the target model.

## D-021 — Bounded shared observation freshness

**Status:** Accepted

**Decision:** Shared collection callers choose `fresh_only`, `allow_fresh_cache`, or `force_refresh`. Cache reuse is limited to completed observations for the same owner and provider, uses a frozen six-hour default window clamped between five minutes and seven days, and always pins the original snapshot/time. Search remains fresh-only by default; Force Refresh bypasses reuse without adding another provider or persistence path.

**Reason:** This preserves current Search collection and scoring behavior while making cache savings and refresh semantics reusable, explicit, owner-safe, and auditable for Analyzer and Watchlist.

## D-022 — Analyzer relative-performance tie and threshold semantics

**Status:** Accepted

**Decision:** Analyzer relative performance version `video-relative-performance-v1` excludes the anchor from its median/average baseline, requires at least three other videos with public views, and includes the anchor in its comparison population. Rank uses competition ranking (`1 + count strictly greater`), so equal view counts share a rank. Percentile uses empirical midrank (`below + 0.5 × equal`) divided by the comparison count. Raw-view classes are `<0.5x` Underperformer, `0.5x–<1.5x` Normal, `1.5x–<3x` Above Average, `3x–5x` Strong, and `>5x` Breakout. Channel Strong share is `>=3x` the cohort median and Breakout share is `>5x`.

**Reason:** The unified model required deterministic ties, percentile boundaries, anchor exclusion, and configurable thresholds but did not freeze their exact statistical semantics. Versioning these choices makes stored Analyzer evidence reproducible and prevents later threshold changes from silently rewriting history.

## D-023 — Analyzer channel-behavior and observed-growth semantics

**Status:** Accepted

**Decision:** `channel-behavior-v1` uses median Lifetime Average Views/Day across fixed 5+5 playlist-position blocks for momentum (`<0.8x` declining, `0.8x–1.2x` stable, `>1.2x` growing). Consistency requires five values and scores `clamp(100 × (1 - MAD / median), 0, 100)`, with a zero median treated as insufficient (`>=75` consistent, `50–<75` mixed, `<50` volatile). Duration/performance is a five-sample-minimum Spearman rank correlation labeled as observed association, not causation. Growth uses only a strictly earlier owner-scoped immutable snapshot; cached duplicate snapshots do not create points, rates use actual elapsed time, and no period before first seen is inferred. Analyzer observations are six-month retention targets, while sources pinned by another retained result and canonical entities are preserved.

**Reason:** The unified model required a named robust formula, exact fixtures, age-aware momentum, honest observed history, and retention behavior but intentionally left the first formula implementation to this task. These rules keep results deterministic, prevent cross-user or pre-observation inference, and preserve immutable source lineage.

## D-024 — Standalone channel analysis and curation reuse canonical aggregates

**Status:** Accepted

**Decision:** Video and standalone channel intake create the same `analyzer_runs` aggregate and invoke one Analyzer metric orchestration service. Each resolved run pins its exact channel snapshot directly; a channel target omits only anchor-video metrics. Owner-scoped notes/status use `analyzer_curations`, while Favorites and tags continue through the existing Library aggregate. Watchlist and Topic Workspace actions expose canonical handoff references but remain unavailable until their own slices.

**Reason:** A parallel channel analyzer or duplicate curation store would fragment formulas, ownership, retention, and future handoffs. Direct source pinning preserves immutable lineage for both target kinds, while separating private curation from immutable attempts lets repeated analyses share one user decision state without silently creating monitoring or workspace records.

## D-025 — Watchlist refreshes are Analyzer-backed immutable attempts

**Status:** Accepted

**Decision:** Each manual video/channel Watchlist refresh creates one owner-scoped `watchlist_refresh_run` linked to an immutable Analyzer attempt and a `watchlist_refresh` collection run. Duplicate submissions reuse the active refresh, terminal refreshes pin the exact previous/current video or channel snapshots and store nullable public-count deltas, and worker exhaustion schedules a safe finalization path. Watchlist items retain only mutable organization state; Favorites remain independent. Retention preserves Analyzer/collection sources while any Watchlist refresh history pins or directly references them.

**Reason:** Reusing the full Analyzer orchestration keeps provider calls, cache policy, quota accounting, calculations, retries, and snapshot provenance on one tested boundary. Separate Watchlist refresh records preserve monitoring history and user intent without inventing a parallel metric pipeline or turning bookmarks into quota-consuming work.

## D-026 — Topic Workspace evidence and workflow lineage are separate

**Status:** Accepted

**Decision:** Topic Workspace items store only allow-listed typed references and an evidence role/note. Confirmed Search and Discover actions create normal immutable workflow runs and record their origin in a separate `topic_workspace_launches` history. A Search result may also be linked as Research-run evidence; a Discovery run remains lineage rather than a new evidence type, while its individual candidates may be linked explicitly.

**Reason:** Discovery runs are useful workspace history but are not part of the approved evidence allow-list. Separate lineage preserves that contract, prevents copied metric/candidate payloads, and keeps quota-consuming launches explicit and auditable.

## D-027 — Cross-surface handoffs persist references, not metric claims

**Status:** Accepted

**Decision:** Analyzer attempts may persist a sanitized same-application return URL alongside an owner-authorized typed origin. Search, Explore, Discover, Watchlist, and Topic Workspace pass canonical public/provider references through shared actions; they do not copy metric payloads. Discover may cite completed owner-scoped Analyzer attempts as supporting provenance, but its deterministic evidence rank remains distinct from the opportunity score, which requires an explicit validation Search.

**Reason:** Persisted, allow-listed navigation restores filtered research context without enabling open redirects or leaking foreign lineage. Reference-only handoffs keep formulas and provider access behind their existing domain boundaries, while the validation guard prevents richer Analyzer evidence from being presented as demand validation.

## D-028 — Semantic profiles are versioned Analyzer evidence

**Status:** Accepted

**Decision:** `semantic-title-terms-v1` deterministically classifies the frozen, provider-ID-deduplicated Analyzer cohort from stored video titles only. Unicode tokenization, language-specific stop words for English, Romanian, and Russian, repeated bigrams/unigrams, stable tie ordering, and explicit sparse/mixed-language warnings produce niche, subniche, topics, content pillars, concentration, and confidence. One immutable owner-scoped semantic profile belongs to one Analyzer attempt; retrying that calculation is idempotent, while a new Analyzer attempt creates a new version. Official YouTube category data remains structurally and visually separate.

**Reason:** Anchoring inference to immutable Analyzer inputs makes results reproducible without new provider I/O, avoids cross-user evidence leakage, and lets future semantic providers evolve without rewriting history or presenting inferred labels as YouTube facts or validated demand.

## D-029 — Semantic performance uses overlapping evidence groups and metric-specific minimum samples

**Status:** Accepted

**Decision:** `semantic-performance-v1` calculates only from an Analyzer attempt's pinned recent-upload memberships. Topic groups reuse the exact `semantic-title-terms-v1` evidence IDs; `editorial-title-patterns-v1` deterministically detects multilingual how-to, question, numbered-list, comparison, guide/tutorial, review, and challenge structures. A video may belong to multiple groups. Every unmatched video remains in an explicit Unclassified group. Counts and evidence remain visible at every size, while each median, average, or Breakout rate remains null until that metric has at least the frozen two-input minimum. One immutable owner-scoped result belongs to one Analyzer attempt; a refresh creates another version and reference-only handoffs do not copy the aggregates.

**Reason:** Overlapping groups preserve the real evidence behind multi-topic/editorial titles, while metric-specific coverage prevents one public value from becoming a misleading performance claim. Pinning versions and evidence to the Analyzer attempt makes filter, queued-export, and workspace use consistent without changing the validation-search opportunity score or implying causation.

## D-030 — Comment evidence is opt-in, minimal, and independently retained

**Status:** Accepted

**Decision:** Public comments use a separate owner-scoped queued collection run attached to a completed video Analyzer attempt. The first slice stores only plain top-level text, provider comment ID, like count, YouTube-reported reply count, and provider timestamps; it stores neither author identity nor reply text. Page position and a frozen maximum make bounded samples explicit, provider IDs make retries idempotent, and comment collections are independent six-month cleanup targets even when shared-source rules preserve the parent Analyzer evidence.

**Reason:** Comment text adds quota cost and personal public text that should not be collected by merely viewing an Analyzer result or retained as long as an otherwise preserved metric snapshot. Top-level-only storage supplies the requested research context while minimizing personal data and avoiding a false claim that replies were completely retrieved.

## D-031 — Audience Signals are immutable safe inference over one comment collection

**Status:** Accepted

**Decision:** `audience-comment-terms-v1` runs inside the existing queued comment workflow after a completed or useful partial collection. It derives only repeated questions, topics, entities, suggestions, complaints, and confusion points from that pinned stored sample through an `AudienceSignalProvider` contract. Profiles are immutable and idempotent per collection/provider/version; relational evidence links pin every signal to its exact source comments. Labels exclude URLs, email addresses, phone-like identifiers, and explicitly unsafe phrases, while sparse, mixed-language, unsafe-withheld, failed, partial, and complete states remain explicit.

**Reason:** Local deterministic inference adds no YouTube quota and keeps the first implementation reproducible, while the provider/version boundary permits later adapters without rewriting history. Exact evidence and conservative safety filtering make every displayed pattern auditable without presenting a sampled inference as authoritative sentiment or exposing avoidable identifying content.

## D-032 — Transcripts are optional user-provided evidence

**Status:** Accepted

**Decision:** TRN-01 uses a local `user_provided_transcript-v1` provider. An authenticated owner may paste plain text or timestamped text copied for the exact completed video Analyzer attempt; `.srt` and `.vtt` structures are accepted through the same parser boundary. Timestamped input is preferred because it preserves navigation/evidence offsets, while plain input becomes one untimed segment. NisheTube performs no YouTube caption/audio retrieval, scraping, or third-party transcription. The user explicitly confirms the right to use the pasted material. Transcript absence, unavailability, parsing failure, or deletion never changes video/channel observations, metrics, classifications, score, or Analyzer completion.

**Reason:** Manual user-supplied text is the only approved path that supports arbitrary analyzed videos without unsupported YouTube API claims or platform scraping. Keeping transcript evidence in a separate owner-scoped, retention-aware aggregate makes it genuinely optional, auditable, replaceable, and ready for later approved providers without coupling core analysis to transcript availability.

## D-033 — Transcript structure is deterministic, revision-pinned inferred evidence

**Status:** Accepted

**Decision:** `transcript-structure-v1` runs only on an explicit action for one immutable owner-provided transcript revision. A `TranscriptStructureProvider` returns summary, topics, entities, hook, sections, calls to action, questions, and script structure with inferred provenance, language, confidence, and exact normalized-text character offsets plus nullable source timestamps. Profiles are immutable and idempotent per transcript/provider/version; another transcript revision or algorithm version creates separate history. Fewer than 30 readable words is insufficient, while short, sparsely segmented, or unknown/mixed-language evidence is partial. The deterministic baseline runs locally without YouTube/network calls and does not affect Analyzer completion, public metrics, semantic results, or opportunity scoring.

**Reason:** Pinning every inferred claim to exact retained evidence makes structure auditable and reproducible while preserving the original/inferred boundary. An explicit local action avoids hidden work and quota claims, and the provider/version boundary permits a future approved implementation without rewriting historical output.

## D-034 — Thumbnail analysis uses transient allow-listed image retrieval and local versioned features

**Status:** Accepted

**Decision:** THMB-01 runs only after an authenticated owner explicitly requests analysis for a completed Analyzer attempt. A dedicated fetcher accepts configured HTTPS YouTube thumbnail hosts, refuses redirects, enforces bounded supported image types/sizes, and passes bytes transiently to `gd_visual_features`; raw image bytes are never persisted. Immutable attempts pin per-membership inferred features/classes, safe unavailable states, provider/algorithm versions, confidence, and exact bounded owner-scoped cache lineage. Cluster associations use only pinned recent uploads and keep metric claims null below the frozen minimum.

**Reason:** A narrow retrieval boundary prevents thumbnail URLs from becoming general server-side fetches, while local deterministic extraction adds no Data API quota and makes results reproducible. Immutable provenance and exact cohort evidence support useful pattern research without presenting visual correlation as causation or feeding it into opportunity scoring.

## D-035 — Cross-channel comparison is a bounded read model, not a new score

**Status:** Accepted

**Decision:** XCMP-01 compares two or three different completed owner-scoped Analyzer attempts through their existing pinned channel metrics, semantic topic/title-pattern aggregates, and latest useful immutable thumbnail aggregates. It creates no comparison persistence and performs no provider request. The channel-first selection query returns at most 24 recent channels and at most five recent attempts for each channel; each evidence family returns at most 100 rows. Compatibility is evaluated across the complete selection independently by model/version, while market origin, observation time, source policy, cohort size, missing groups, and nullable metric coverage remain visible warnings and exact values.

**Reason:** Comparing immutable evidence by reference preserves ownership, retention, and historical integrity without copying results or inventing a cross-channel formula. A maximum of three columns remains readable while making multi-channel patterns easier to inspect; grouping attempts by channel prevents repeated analyses from obscuring the available choices. Independent compatibility guards keep partially useful evidence inspectable while preventing side-by-side differences from being presented as a like-for-like opportunity score, causal finding, or channel recommendation.

## D-036 — Audience Signal exclusions are reversible owner preferences

**Status:** Accepted

**Decision:** A user may hide a generated Audience Signal only when its complete normalized `label_key` is one word and exactly equals an active owner-scoped exclusion. Exclusions live outside immutable profiles and evidence, retain active/excluded/restored timestamps, apply across that owner's Audience Signal views, and can be restored without recalculation. A phrase such as `audio setup` remains visible when `audio` is excluded. Hide and restore actions perform no comment collection, provider request, or signal-profile mutation.

**Reason:** Manual curation immediately removes noisy unigram output without introducing AI or rewriting historical inference. Exact equality prevents a broad stop-word action from erasing meaningful phrases, while a durable reversible preference avoids accidental permanent loss and preserves the provenance of the original generated signals.

## D-037 — Saved comment ideas outlive raw comment retention

**Status:** Accepted

**Decision:** A heart action creates one owner-scoped `saved_comment_ideas` record per canonical video and provider comment ID. It intentionally copies the exact selected top-level text and source publish time, pins the canonical video, and keeps a nullable link to the retained `public_comments` row. Raw comment cleanup nulls that link but preserves the private idea and its video URL. Removing the heart deletes only the saved idea and never mutates the immutable comment collection.

**Reason:** A research idea is explicit durable user intent, while a raw public-comment collection remains a bounded six-month evidence sample. A nullable provenance link plus a minimal intentional copy prevents retention from unexpectedly erasing the user's curated list, avoids preserving an entire comment collection for one selection, and keeps save/remove actions local, idempotent, and quota-free.

## D-038 — Interface implementation and QA are desktop-only

**Status:** Accepted

**Decision:** Beginning with IA-01, NisheTube interface implementation and visual acceptance target desktop browsers at approximately 1440px, with supported desktop widths starting at 1280px. Tablet and mobile-specific layouts, breakpoint optimization, and dedicated visual QA are out of scope. Smaller widths may retain best-effort wrapping or scrolling but are not completion gates.

**Reason:** The product owner explicitly deprioritized tablet behavior so implementation and verification effort can remain focused on the local desktop research workflow. Keyboard access, zoom, long multilingual evidence, exact-value access, and non-color status requirements remain mandatory on desktop.

## D-039 — Intake submissions use owner-scoped durable idempotency tokens

**Status:** Accepted

**Decision:** Each new Research validation or stored-evidence Discovery submission may carry a server-issued UUID. The UUID is persisted on the created run and is unique with its owner; a repeated valid request from that owner resolves to the original run instead of creating or queueing another attempt. Legacy and internal creation paths may omit the token, and retries remain separate immutable attempts under their existing lifecycle rules.

**Reason:** Disabling a button prevents ordinary double clicks but cannot cover repeated HTTP delivery, slow redirects, or navigation retries. Durable owner-scoped uniqueness prevents duplicate queued work and quota exposure without making tokens global across local users or conflating an explicit failed-run retry with accidental resubmission.

## D-040 — Discovery candidates use immutable evidence-quality versions and explicit weak signals

**Status:** Accepted

**Decision:** New Discovery runs freeze `candidate-evidence-v2`, its thresholds, reference time, and `discovery-phrase-normalization-v1`. Candidate evidence combines frequency, unique-channel/seed coverage, semantic coherence, typical and top-video-removed performance, small-channel proof, freshness, robust stability, seed relevance, and phrase quality. A candidate requires at least three videos and two channels and must satisfy every frozen quality threshold; all other output persists as `weak_phrase_signal` with exact reasons. The evidence payload and score cannot be updated after insertion, while organization status and a validation-run link remain mutable. Legacy rows remain identified as legacy and are never rewritten.

**Reason:** Candidate generation must resist viral single-video artifacts and multilingual surface variation without hiding useful but insufficient observations. Freezing exact inputs, transformations, thresholds, and outputs makes the result reproducible and auditable, while a dedicated weak state prevents low-quality phrases from being presented as validated niches or Opportunity scores.

## D-041 — Research evidence quality is versioned separately from Opportunity v1

**Status:** Accepted

**Decision:** `research-evidence-v1` is calculated once during a new Research run's scoring stage and stores immutable per-result relevance/source rows plus complete/strict, format-specific, outlier-resistant, and compatible-snapshot aggregates. Compatibility freezes normalized query, run kind, market mapping, requested depth, collection parameters, and evidence version. Unknown format/language/support signals remain explicit; Shorts and long-form are never compared directly. Historical runs without this profile remain unchanged and visibly unavailable. `niche-opportunity-v1` continues to use its released inputs and behavior until SCR-05 introduces a separate formula version.

**Reason:** Persisting the evidence layer separately makes later scoring explainable and reproducible without silently changing released scores or backfilling claims into old snapshots. Exact source pins and conservative minimum/compatibility gates distinguish missing history from low stability and prevent viral or mixed-format samples from becoming unsupported decisions.

## D-042 — Opportunity and confidence v2 freeze evidence-derived inputs

**Status:** Accepted

**Decision:** New scoring-stage Research runs persist `niche-opportunity-v2` and `confidence-v2` without altering existing v1 scores. The result freezes the v2 configuration, calculation time, full/strict sample counts, `research-evidence-v1` profile identity, and each video/channel snapshot pin. v2 adds channel concentration, repeat ownership, channel-size proof, subscriber visibility, relevance, format, outlier, and stability evidence to the existing robust stored metrics. Without a compatible earlier snapshot, demand is labelled `Observed activity`, not momentum; unavailable access/budget complexity remains explicitly unavailable. The UI shows full and strict sample counts and every confidence reduction as a stored warning.

**Reason:** Reusing immutable evidence prevents provider work or hidden recalculation while giving later runs a more auditable decision aid. Separating unavailable signals from zero-valued claims avoids false precision, and preserving v1 allows historical results to remain interpretable.

## D-043 — Four-channel comparison extends the bounded read model

**Status:** Accepted

**Decision:** XCMP-02 may compare two through four distinct completed owner-scoped Analyzer channel attempts. The comparison remains storage-free and provider-free: it reads only pinned Analyzer metrics and related immutable semantic, thumbnail, and observation evidence. Every metric retains its source version, observation time, sample/coverage state, and nullability; incompatible periods, versions, formats, or samples remain explicit rather than normalized or recalculated. Four columns are permitted only in the desktop comparison layout with non-color strongest-value indicators and no derived opportunity score, channel ranking, causal finding, or recommendation.

**Reason:** The user accepted a four-channel peer group because direct side-by-side context is useful for local research. Retaining the XCMP-01 bounded read-model constraints preserves ownership, historical integrity, and honest comparability while preventing the wider layout from becoming a hidden scoring or recommendation system.
