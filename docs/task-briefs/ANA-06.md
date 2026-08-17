# ANA-06 — Analyzer decision hierarchy and topic-quality guardrails

## Goal and boundaries

Reorganize the existing owner-scoped Analyzer result into Summary, Content patterns, Channel, and Raw data decision views. Make Shortlist and Workspace actions persistently reachable, translate technical metrics into plain-language conclusions while retaining exact values, formulas, provenance, and versions, and harden semantic presentation with confidence/frequency thresholds, synonym grouping, and distinct topic/title-confidence/coverage semantics.

Use only stored Analyzer evidence and existing immutable records. Do not add provider calls, duplicate or recalculate historical metrics, change scoring, mutate frozen semantic profiles, or begin XCMP-02 work.

## Acceptance

- Analyzer results expose accessible Summary, Content patterns, Channel, and Raw data views with loading, empty, partial, legacy, and error states.
- Shortlist and Workspace handoffs remain persistently available where the owner has access, without becoming a primary Analyzer tab.
- Plain-language conclusions preserve exact metric values, formulas/tooltips, provenance, algorithm versions, and explicit unavailable states.
- Semantic presentation enforces stored confidence/frequency quality guards, groups supported synonyms, removes meaningless term pairs, and distinguishes topic, title fragment, topic confidence, profile confidence, and coverage.
- All reads remain owner-scoped, bounded, provider-free, and do not duplicate calculations or rewrite immutable Analyzer/semantic records.
- Focused feature and frontend tests cover authorization, no-provider reads, visible tab/data states, semantic-quality labels, long multilingual titles, and keyboard/focus behavior at the desktop acceptance target.

## Applicable decisions

- D-005–D-008: Analyzer reads and handoffs remain authenticated and owner-safe.
- D-019, D-022–D-024: calculated metrics, Analyzer provenance, stored inputs, and curation boundaries remain explicit and versioned.
- D-027–D-029: handoffs are reference-only; semantic profiles and semantic performance are immutable stored evidence with coverage safeguards.
- D-038: desktop accessibility, keyboard/focus behavior, and exact values are required.
- D-041–D-042: frozen evidence, unavailable history, and versioned confidence remain explicit.

## Initial inspection targets

- `resources/js/pages/analyzer/show.tsx`, `resources/js/features/analyzer/`, and `resources/js/types/analyzer.ts`.
- `app/Http/ViewModels/AnalyzerRunViewModel.php`, `app/Domain/Analyzer/`, and `app/Domain/Semantic/` read models/services.
- Existing Analyzer feature and frontend interface tests.

## Focused verification

- Feature tests for owner isolation, stored-data-only Analyzer reads, and persistent Shortlist/Workspace handoffs.
- Frontend tests for tabs, plain-language/provenance labels, semantic-quality states, multilingual titles, and keyboard/focus behavior.
- Formatter, lint, type, PHPStan, and Pint checks only for changed files.

## References

- [Decision workflow redesign — ANA-06](../13_DECISION_WORKFLOW_REDESIGN.md#ana-06--analyzer-decision-hierarchy-and-topic-quality-guardrails)
- [Backlog — ANA-06](../08_BACKLOG.md#phase-19--decision-workflow-redesign)
- [Decision index](../DECISION_INDEX.md)
