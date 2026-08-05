# Approval Workflow Engine

## What exists today

Approvals are a first-class model (`EventApproval`), not a status field bolted onto
something else:

- **Types**: `budget`, `supplier`, `design`, `venue`, `agenda`, `client`, `payment`, `report`
- **Statuses**: `pending` → `approved` / `rejected` / `needs_revision`
- Each record carries a requester (`requested_by`), a decider (`decided_by`), a decision
  timestamp (`decided_at`), and free-text notes. Only status/decision changes are
  audit-logged — the model doesn't drown its history in edits to the request itself.
- Gated by `Gate::authorize('decide-approvals', ...)`, which requires **manager** rank or
  above (see [07-user-roles-and-permissions.md](07-user-roles-and-permissions.md)).
- **Multi-step chains** (`approval_steps`, model `ApprovalStep`): every approval is a
  sequence of one or more ordered steps — a step can be left unassigned (any manager, the
  original behavior) or named to a specific person. `EventApproval.status` is **derived**
  from its steps (`EventApproval::syncStatusFromSteps()`), the same "derive, don't
  duplicate" shape as `EventContract`'s signatories, not typed in by `decide()` directly.
  A rejection or revision request on any step ends the chain immediately — remaining
  steps are marked `skipped` rather than left dangling as still-pending. Approval requires
  every step to say yes, in order; a step assigned to someone specific is theirs alone to
  decide. `EventApproval::booted()` guarantees a bare `create()` still gets one default
  step, so nothing that predates chains (or skips the form) is left undecidable.
- **Conditional routing**: `CompanyProfile::approval_threshold_cents` (Settings → Defaults
  & Templates, blank means off) — a `budget` or `payment` approval (`EventApproval::
  AMOUNT_GATED_TYPES`) whose `amount_cents` exceeds the threshold gets one more step
  appended automatically, gated to **admin** rank via `ApprovalStep.min_role` rather than
  a named person. `ApprovalStep::decidableBy()` is the one place that checks all three
  shapes a step can take — named person, minimum role, or (both null) any manager — so
  `decide()` and the pending-queue display never disagree about who a step belongs to.

A second, parallel approval flow exists for **budget revisions** specifically:
`EventBudgetVersion` (`pending` / `approved` / `rejected` / `superseded`) — a budget change
goes through its own versioned approval rather than editing figures in place, so there's a
record of what was proposed and what was decided.

Contract signatures have a related but distinct mechanism: `EventContract` status
(`draft` → `sent` → `partially_signed` → `signed` / `void`) is **derived automatically**
from its `contract_signatories` records as they're signed — not a manual approval step, but
the same idea of a status that means something specific and is computed, not typed in.

## What this is not yet

- Multi-step chains, conditional routing (amount-based), and **delegate-to** landed.
  The current step's assignee (or any manager who can decide an open / role-gated step)
  can hand it to another eligible manager; admins can reassign a stuck named step.
  Hand-off never decides the step — it only renames who owns it. Targets must still clear
  the same floor as assign-at-create (manager; meet `min_role` if set; never the requester).
- **Escalation on timeout** is still not built — nothing auto-moves a step that's sat too
  long (and a push notification/reminder system is a separate deferred piece).
- There's no notification/reminder system tied to a pending approval sitting too long —
  the Health Engine's attention list surfaces pending approvals, but nothing pushes.
- Approvals aren't yet linked into the Commercial Command Center's future RFQ/Quotation
  flow (see [03-command-center-architecture.md](03-command-center-architecture.md)) —
  that would be a new approval `type`, not a new mechanism.

## Building on this in Cursor

Extending approvals (a new type, a new gate) should follow the existing `EventApproval`
shape rather than inventing a parallel status/workflow system — this platform has already
paid once for the "same fact, multiple definitions" mistake (see
[10-current-codebase-assessment.md](10-current-codebase-assessment.md)) and a second
approval mechanism would repeat it.
