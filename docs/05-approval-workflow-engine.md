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

A second, parallel approval flow exists for **budget revisions** specifically:
`EventBudgetVersion` (`pending` / `approved` / `rejected` / `superseded`) — a budget change
goes through its own versioned approval rather than editing figures in place, so there's a
record of what was proposed and what was decided.

Contract signatures have a related but distinct mechanism: `EventContract` status
(`draft` → `sent` → `partially_signed` → `signed` / `void`) is **derived automatically**
from its `contract_signatories` records as they're signed — not a manual approval step, but
the same idea of a status that means something specific and is computed, not typed in.

## What this is not yet

- There's no generic, configurable **workflow engine** (multi-step chains, conditional
  routing, delegate-to, escalation on timeout). Every approval type today is a single
  pending → decided step.
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
