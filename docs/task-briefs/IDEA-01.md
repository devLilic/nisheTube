# IDEA-01 — Stored-evidence idea generator

## Goal and boundaries

Generate owner-private content ideas from existing evidence only. No provider request, hidden search, quota use, or unsupported demand claim is permitted.

## Acceptance

- Inputs are bounded owner-scoped snapshots, outliers, phrases, titles, packaging patterns, and evidenced gaps.
- Every idea contains angle, format, title variants, hook, audience/intent, source references, confidence, saturation/sample risks, and generator version.
- Saves are idempotent, authorized, project-linkable, and retain durable provenance without mutating source evidence.
- Missing/incompatible evidence yields explicit unavailable/insufficient results rather than generic invented ideas.
- UI supports generate/loading/empty/partial/error/save states and consumes zero YouTube quota.

## Applicable decisions

- D-005–D-008, D-014–D-018, D-027, D-037, D-041–D-047, D-049–D-054.

## Initial inspection targets

- Ideas models/pages, saved comment ideas, Projects, Research/Discovery/Analyzer evidence links, queue conventions and tests.

## Relevant contracts and UI

- Generator input manifest and output are versioned; source references remain owner-authorized at read time.

## Focused verification

- Exact unit generation/insufficient-evidence tests, feature ownership/idempotency/no-quota tests, and frontend provenance/save tests.

## Exact reference links

- [Decision D-047](../10_DECISIONS.md#d-047--idea-generation-is-stored-evidence-only)
- [RDSN-07 brief](RDSN-07.md)
- [Working rules](../00_WORKING_RULES.md)
