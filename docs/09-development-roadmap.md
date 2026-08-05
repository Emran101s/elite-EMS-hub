# Development Roadmap

## Priority order (business direction)

1. Company Command Center — **built**
2. Event Command Center — **built**
3. Event life cycle — **built** (`Event::STAGES`)
4. Approval workflow engine — **built**, single-step only (see [05-approval-workflow-engine.md](05-approval-workflow-engine.md))
5. Supplier portal — **not built**
6. Client portal — **not built**
7. Contracts, budget, invoices, payments — **built**
8. Documents, reports, notifications — **documents and reports built; no notification system yet**
9. Templates and knowledge base — **event templates (save-as/start-from) built; no knowledge base**
10. Multi-tenant SaaS — **not built, not designed for yet — deliberately deferred**

## What's already built (by theme, not exhaustive)

- Full event hub: Brief, Contract, Planning, Tasks, Budget, Agenda, Speakers, Suppliers,
  Venue (with a drag-and-drop room layout builder), Transport (the deepest module —
  7-status movements, driver/vehicle catalogues, guest pool, Live Operations, Dispatch
  Board, 6 PDF exports), Accommodation, Food & Beverage, Exhibition, Sponsors, Attendees,
  Documents, Risks, Approvals, Reports.
- CRM: contacts, deals, activities, pipeline board, client 360 record.
- Typed multi-party contracts (client/vendor/speaker/sponsorship/letter) with bilingual
  clause blocks, e-signature tracking, a document deck/pipeline view.
- Public registration → attendee, badge printing, QR check-in.
- Appendices system: reusable `{{appendix:slug}}` tokens pulling live data into documents.
- Taxonomy system: editable statuses/categories/sub-categories across the platform,
  key-vs-label separated so relabeling never breaks stored data.
- Health Engine: explainable 0–100 score with an attention list.
- ~17 PDF documents rendered by headless Chrome from the app's own markup.

## Recently completed: platform hardening (this handover's immediate predecessor)

**Stage 1 — authorization.** A mechanical guard (`tests/Unit/AuthorizationGuardTest.php`)
now fails the build if any mutating Livewire method lacks an authorization gate. Found and
fixed dozens of real gaps, not just theoretical ones — see
[07-user-roles-and-permissions.md](07-user-roles-and-permissions.md).

**Stage 2 — one definition per fact.** Money formatting, date formatting and three
cross-file status-colour maps were each defined once instead of scattered, after two real
bugs (a currency mismatch, a drifted status colour) traced back to duplication. Full detail
in [10-current-codebase-assessment.md](10-current-codebase-assessment.md).

## Recently completed: Stage 3 — shared UX chrome

**Stage 3 — loading/error states + confirm dialog.** One confirm host
(`<x-confirm-host>` + `window.ebhConfirm`) replaces browser `wire:confirm` on the
high-traffic surfaces (Events index, Speakers, Venue, Sponsors, F&B, Exhibition,
Settings archive). Session flashes use `<x-alert>`; button busy labels use
`<x-busy>`. Remaining `wire:confirm` call sites migrate onto `<x-confirm>` as those
screens are touched.

## Recently completed: Stage 4 — health score + invoices

**Stage 4 — performance + collection signal.** `EventHealthService::RELATIONS` now
eager-loads `invoices.lines.payment` (lines + payment are what money math needs).
Overdue outstanding invoices pull the budget component down and surface in the
advisor attention list. Dashboard `dayAt()` N+1 was already fixed earlier.

## Recently completed: Stage 5 — security / robustness

**Stage 5.** Security headers middleware (CSP deferred — PDF/fonts need their own
allowlist). Soft deletes on invoices and budget items. Missing FK indexes added.
`EventPolicy` for create/update/archive/duplicate/delete. Badge check-in now uses a
separate `checkin_token` and a signed attendee code so rotating a leaked registration
link does not invalidate printed badges, and a guessed badge number alone cannot open
the door; check-in scans are rate-limited.

## Deferred, not started (explicitly sequenced for later)

- **CSP allowlist** — deliberate follow-up so headless Chrome PDFs and Google Fonts keep working.
- **Supplier Portal, Client Portal, RFQ/Quotation objects, notifications, knowledge base,
  multi-tenant SaaS** — priority items 5, 6, 9, 10 above, and the RFQ/Quotation gap noted
  in [03-command-center-architecture.md](03-command-center-architecture.md).

## Rule for whoever (or whatever AI) picks this up next

Don't start multi-tenancy, a portal, or a notification system opportunistically while doing
something else — each is a large, deliberate decision (see
[01-product-vision.md](01-product-vision.md)). The honest backlog above is the safer place
to pick up incremental work.
