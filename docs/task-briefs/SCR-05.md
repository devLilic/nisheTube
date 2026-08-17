# SCR-05 — Opportunity and Confidence v2

## Goal and boundaries

Deliver immutable `niche-opportunity-v2` and `confidence-v2` for new Research runs, with complete, plain-language explanations. Do not overwrite or reinterpret `niche-opportunity-v1`, existing confidence values, historical results, or `research-evidence-v1`.

This task is a complete vertical slice: persistence/versioning, domain calculation, owner-safe read surfaces, accessible Research UI, all data states, focused tests, and documentation/status evidence. No provider call is introduced by score inspection.

## Acceptance

- Freeze versioned configuration, calculation time, input summaries, and exact source snapshot pins with every v2 result; identical stored inputs calculate deterministically and remain within documented bounds.
- Show strict-sample and full-sample views without conflating them. State `Observed activity`, not momentum, when no snapshot history exists.
- Score explanations distinguish public/provider facts from inferred or estimated conclusions; comparisons across incompatible score versions show a warning.
- Opportunity covers demand, competition, reachability, creator viability, and freshness using the required robust/relevance/format/stability evidence. Confidence visibly explains reductions from sample size, completeness, relevance, stability, diversity, subscriber visibility, format classification, outlier dependence, independent evidence, and missing history.
- Include loading, empty, success, partial, unavailable-legacy, and error states; preserve owner isolation and immutable history.

## Required evidence rules

- Competition includes concentration/HHI, unique/repeated-channel share, large-channel share, sub-10K/sub-100K evidence, repeat winners, diversity, and result ownership.
- Reachability includes robust subscriber-normalized performance, small/mid-channel breakouts, repeat small-channel winners, performance excluding large channels, and visible-subscriber coverage.
- Creator viability includes repeat winners, cadence, format repeatability, robust performance, peer-channel count, access/budget-complexity signals, Shorts dependence, and content lifespan.
- Demand separates lifetime activity from observed snapshot change. Freshness separately describes demand freshness, content age, and underserved gap.
- Use `research-evidence-v1` as frozen input. Keep Shorts, long-form, unknown format, nulls, minimum samples, outliers, and incompatible snapshots explicit; do not manufacture values or compare formats directly.

## Existing contracts and inspection targets

- Current v1 path: `app/Domain/Scoring/Actions/CalculateOpportunityScore.php`, `BuildScoringInput.php`, `Services/NicheOpportunityV1.php`, and `Data/ScoringInput.php`.
- Existing frozen evidence: `ResearchEvidenceV1.php`, `ResearchEvidenceProfile`, `ResearchResultEvidence`, and `ResearchEvidenceVideoInput.php`.
- Existing result UI: `resources/js/features/research/scoring/opportunity-score-section.tsx`, `decision-summary.tsx`, and `evidence-inspection.tsx`.
- Extend the existing owner-scoped Research result flow; use Form Requests/policies where a new exposed action or view requires them.

## Focused verification

- Unit fixtures for deterministic output, bounds, monotonicity, missing history, missing subscriber visibility, insufficient/strict samples, mixed formats, viral outliers, and incompatible versions/snapshots.
- Feature tests for immutable persistence, owner isolation, explanation/provenance payloads, and complete/partial/legacy/error result views.
- Frontend tests for version/provenance labels, warnings, exact values, and all required UI states; run formatter/lint/type checks only against changed files.

## Applicable decisions and references

- D-014–D-018: immutable owner-scoped Research runs, provider boundaries, and versioned scoring — [decision log](../10_DECISIONS.md).
- D-038: desktop acceptance with accessibility/exact-value requirements — [decision log](../10_DECISIONS.md#d-038--interface-implementation-and-qa-are-desktop-only).
- D-041: `research-evidence-v1` is immutable, separate from v1 Opportunity, and supplies later scoring inputs — [decision log](../10_DECISIONS.md#d-041--research-evidence-quality-is-versioned-separately-from-opportunity-v1).
- D-042: v2 freezes its configuration and source pins, presents no-history demand as observed activity, and leaves unavailable access/budget signals explicit — [decision log](../10_DECISIONS.md).
- SCR-05 canonical requirement — [decision-workflow redesign](../13_DECISION_WORKFLOW_REDESIGN.md#scr-05--opportunity-and-confidence-v2).
- Scoring versioning and v1 compatibility — [scoring model](../04_SCORING_MODEL.md#6-versioning), [research evidence](../04_SCORING_MODEL.md#13-research-evidence-version-research-evidence-v1).
- Full regression guidance — [acceptance/testing](../09_ACCEPTANCE_AND_TESTING.md#6-scoring-acceptance).

If a reference changes a locked decision or reveals a material ambiguity, update this brief and `DECISION_INDEX.md` before implementation proceeds.

## Current verification blocker

Backend scoring coverage and scoped local ESLint pass, but the Inertia score-interface test cannot render because `public/build/manifest.json` is absent. Restore the local Vite manifest, then rerun the SCR-05 interface check before completing this task.
