# Decision Index

Use this index to select only decisions relevant to the active task. The complete rationale and history remain in [the decision log](10_DECISIONS.md).

| Domain | Decision IDs | Working constraint |
| --- | --- | --- |
| Platform and persistence | D-001–D-004 | Local Laravel monolith, MySQL, queues, UTC storage, and historical records are durable. |
| Authentication and ownership | D-005–D-008 | Session-authenticated local users; owner-safe routes, reads, and mutations. |
| Provider and quota | D-009–D-013 | Provider boundary, quota accounting, safe failure handling, and no secret exposure. |
| Research runs and scoring | D-014–D-018, D-041–D-042 | Runs/snapshots and scoring versions are immutable; score inputs are stored and reproducible. |
| Analysis and evidence | D-019–D-039, D-043 | Analyzer, shared observations, semantic/comment/transcript/thumbnail evidence, handoffs, and four-channel comparison remain owner-scoped and versioned. |
| Discovery | D-040 | Candidate evidence is separate from Opportunity and weak signals stay explicit. |
| Interface scope | D-038 | Desktop is the visual acceptance target; accessibility and exact values remain required. |

## Current task mapping

| Task | Applicable decisions | Notes |
| --- | --- | --- |
| SCR-05 | D-014–D-018, D-038, D-041–D-042 | See [SCR-05 brief](task-briefs/SCR-05.md). |
| PROF-01 | D-005–D-008, D-014–D-018, D-038, D-041–D-042 | Profitability-fit must remain owner-scoped, versioned, and explicit about estimated inputs. |
| DASH-04 | D-005–D-008, D-014–D-018, D-038, D-041–D-042 | Dashboard decisions must use owner-scoped frozen evidence with accessible exact values. |
| SHORT-01 | D-005–D-008, D-014–D-018, D-038, D-041–D-042 | Shortlisted evidence and comparisons must remain owner-scoped and version-compatible. |
| XPLR-02 | D-005–D-008, D-038, D-041–D-042 | Explore filters and saved views must remain owner-scoped and explicit about stored evidence. |
| ANA-06 | D-005–D-008, D-019, D-022–D-024, D-027–D-029, D-038, D-041–D-042 | Analyzer hierarchy and semantic-quality views must use owner-scoped immutable stored evidence with explicit provenance and coverage. |
| XCMP-02 | D-005–D-008, D-019, D-022–D-024, D-027–D-029, D-035, D-038, D-043 | Four-channel peer comparison remains an owner-scoped bounded stored-evidence read model without a score or recommendation. |

When a task activates, add only its applicable IDs and one-line reason here. Add a new full decision to `10_DECISIONS.md` only when a locked decision changes or a material new architectural decision is accepted.
