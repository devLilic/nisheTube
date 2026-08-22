# DOC-02 — Liquid Glass redesign programme alignment

## Goal and boundaries

Translate the accepted Liquid Glass, bilingual-interface, asynchronous Analyzer, research-correctness, and advanced-researcher plan into the live Codex documentation system. This task changes documentation only: it creates the ordered task programme and decision records, supersedes conflicting locked directions, and activates the first design task. It does not change application code, schemas, routes, or user data.

## Acceptance

- `TASK_INDEX.md` contains the complete Phase 23–27 sequence with exactly one active task.
- Every new task has a complete brief with dependencies, acceptance, contracts/UI, focused verification, and exact references.
- Decisions D-044–D-054 are indexed and recorded with rationale; D-005 and D-038 clearly point to their accepted superseding decisions.
- `AGENTS.md`, `00_WORKING_RULES.md`, and the backlog consistently describe bilingual RO/EN UI, light-only Liquid Glass, responsive acceptance, and the mobile full-sheet menu.
- Historical task status and completed task evidence remain unchanged.

## Applicable decisions

- D-012: the task index remains the live compact status register.
- D-014: Codex runs only focused checks.
- D-044–D-054: the new product, evidence, localization, asynchronous UI, and visual-system constraints introduced by this documentation change.

## Initial inspection targets

- `docs/00_WORKING_RULES.md`, `docs/TASK_INDEX.md`, and this brief.
- Existing decision format and D-005/D-038 conflicts in `docs/10_DECISIONS.md`.
- Existing Phase 21–22 backlog and completed task history.

## Relevant contracts and UI

- Documentation is the only output of DOC-02.
- The implementation order must keep one `In progress` task and preserve the mandatory vertical-slice rule.
- The redesign branch is `codex/liquid-glass-redesign`, created from commit `14c61d0` on the previous `redesign` branch.
- New task briefs may link the reusable Liquid Glass reference once RDSN-01 creates it.

## Focused verification

- Inspect the task index for exactly one `In progress` row and valid dependency order.
- Search for conflicting English-only, desktop-only, or dark/system requirements in live working documentation.
- Check that every new task row links to an existing complete brief and every D-044–D-054 decision is present in both decision documents.

## Exact reference links

- [Working rules](../00_WORKING_RULES.md)
- [Task index](../TASK_INDEX.md)
- [Decision index](../DECISION_INDEX.md)
- [Decision log](../10_DECISIONS.md)
- [Backlog](../08_BACKLOG.md)
