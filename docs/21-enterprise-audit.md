# Elite Business Hub — Full Enterprise Audit (A–Z)

**Date:** 8 August 2026
**Scope:** Phase 1 — internal Elite Business Hub use. Phase 2 (SaaS) reviewed as roadmap only.
**Method:** every claim below was checked against the running application, the database, or the
source. Nothing here is inferred from the plan documents. Where a claim rests on reading code
rather than clicking a screen, it says so.
**Prior audits:** [`docs/18`](18-phase1-platform-audit.md) (Cursor, 6 Aug) ·
[`docs/20`](20-implementation-inspection.md) (Claude, 8 Aug). §L compares.

---

## A. Executive Summary

### Condition

This is a **deep, genuinely built platform with a thin operating layer on top of it.**
59 models, 58 Livewire components, 120 migrations, 100 routes, 23 PDF document
generators, 1,043 passing tests across sqlite *and* Postgres. That is not a
prototype. Individual modules — Contracts, Agenda, Transport, Budget — are at or
above the standard a mid-market commercial product ships at.

What is missing is not depth. It is **the connective tissue that turns modules into
an operating system**: notifications, role meaning, navigation coherence, and a
handful of joins between modules where work silently falls through.

### Main strengths

1. **Event lifecycle is real and enforced.** Ten stages with a transition graph
   (`Event::TRANSITIONS`) that actually rejects illegal moves — not a status
   dropdown pretending to be a workflow.
2. **Document generation is a genuine competitive asset.** 23 PDF controllers.
   Bilingual AR/EN typed contracts with e-signature and per-type clause libraries.
   Four agenda documents, five transport documents. This is the hardest part of an
   events platform to build and it is done.
3. **Authorization is mechanically enforced.** `AuthorizationGuardTest` reflects
   over every public Livewire method and fails the build if one writes to the
   database without `Gate::authorize`. Stronger than most commercial codebases.
4. **Audit trail is real** — 16 models carry `Auditable`, including User role
   changes, contracts, invoices and approvals.
5. **Money model is sophisticated.** Per-line cost *and* charge, markup vs quoted
   price, billable flags, live FX with a JOD peg fallback (`CurrencyService`).

### Main weaknesses

1. **There is no notification system.** One notification class exists
   (`TeamInvite`, built yesterday) and **no `notifications` table at all**. No
   approval ping, no assignment alert, no deadline warning, no supplier-issue
   escalation. Everything depends on someone opening the right screen.
2. **Roles are decoration below the top rank.** Eight per-event functional roles
   (Finance Owner, Operations Lead, Design Owner…) exist as labels and are
   **never read for any authorization decision**. A Design Owner and a Finance
   Owner have identical powers.
3. **Navigation is the single worst surface in the product** — and below 1280px
   it is functionally broken (Settings and Sign-out become unreachable). §D.
4. **Suppliers cannot be operated.** The tab renders status but has no write path
   at all — the only module in the hub a coordinator cannot change.
5. **Reporting is a stub** relative to what a management team needs.

### Main risks

| Risk | Severity |
|---|---|
| Sub-1280px users cannot reach Settings or sign out | **Functional bug, ship-blocking** |
| Winning a deal by drag produces an event with no itemised budget | **Money divergence** |
| No notifications → approvals and deadlines depend on memory | **Adoption killer** |
| Role labels imply permissions that do not exist | **False sense of control** |
| Suppliers read-only → procurement stays in WhatsApp/Excel | **Defeats the goal** |

### Overall score: **64 / 100**

Launchable internally with discipline. Not yet an operating system.

---

## B. Phase 1 Context Review

**Is it suitable for Elite Business Hub internal use today?** Yes, with the four
must-fix items in §M. The event-delivery half is genuinely strong. The
management-and-coordination half is where the gaps are.

**Prioritise now:** notifications, role meaning, navigation rebuild, Suppliers
write path, CRM→budget continuity, role-weighted dashboard.

**Delay to Phase 2:** subscription billing, tenant onboarding, white-label,
usage limits, SaaS analytics, tenant admin panels.

**Do not overbuild now:** The multi-tenancy spine is **already merged** (tenants,
workspaces, `tenant_id` on 62 tables, global scoping, per-workspace roles). That
was the right amount of preparation. **Stop there.** Do not build a workspace
switcher, tenant onboarding, or plan management for a single-company Phase 1 —
they add UI surface with no user.

---

## C. Module-by-Module Audit

Priority key: **P0** = blocks real use · **P1** = material friction · **P2** = polish.

### C1. Command Center / Dashboard — **58 / 100**

**Purpose:** the company's situational awareness in one screen.
**Strength:** genuinely rich. `CommandCenterService` computes in-the-book, live-now,
open tasks, overdue count, at-risk events, risks, approvals. Event Radar rail,
week-ahead chart, today strip. Not decorative — tiles deep-link.
**Weakness:** **no role weighting whatsoever.** `Dashboard.php` contains zero
`role` / `isAtLeast` branching, verified by grep. The CEO and an on-site
coordinator see byte-identical pages. A CEO does not need "0 movements today";
a coordinator does not need portfolio margin.
**Missing:** revenue/profit-margin headline, pending-payments total, proposal
pipeline value, per-role "my day" lens.
**Fix:** one `Dashboard` component, role-selected widget set. P0.

### C2. Events — **80 / 100**

**Strength:** the strongest module. Hub with 23 tabs, enforced 10-stage lifecycle,
health scoring with explainable component breakdown, module-readiness doors
(added 7 Aug), attention counts per tab, cover/logo theming, PDF packs.
**Weakness:** event *creation* has no guidance — a new event lands with every
module enabled and no sense of what to do first. Close-out is a stage
(`completed → closed`) with no wizard behind it: no report snapshot, no lessons
learned, no client-history write-back.
**Missing:** close-out checklist; "what do I do first" on a fresh event.
**Fix:** close-out wizard. P1.

### C3. Clients / CRM — **62 / 100**

**Strength:** proper schema — Clients, Contacts, Deals, DealActivities. Pipeline
board with drag between lanes. Client 360 view. Deal → Event linkage preserved.
**Weakness (verified):**
- Dragging a deal to **Won** calls `DealPipeline::win()` immediately. Dragging to
  **Lost** opens a reason prompt. **Winning — the consequential one — asks nothing.**
- Because `BudgetSync::syncProposal()` has exactly **one** caller
  (`Proposal::accept()`), a deal won by drag produces an event with
  `budget_cents` set and **zero itemised budget lines**. Two win paths, two
  different financial outcomes.
- The CRM board contains **zero references to proposals** (verified: 0 occurrences
  of "proposal" in `crm-pipeline.blade.php`). A salesperson cannot start an offer
  from where they work.
**Missing:** communication log against a client (only deal activities exist),
follow-up reminders (no notification system).
**Fix:** soft win gate + Draft-proposal CTA. **P0 — highest value in the audit.**

### C4. Proposals — **76 / 100**

**Strength:** well-modelled. Numbered (`EBH-PRO-2026-014`) with collision retry,
optional lines quoted-but-not-counted, separate fee and tax, derived expiry
(a draft past its date has not "lapsed" — correct), accept → wins deal → opens
event → seeds budget. PDF. Win-rate stat that ignores undecided offers.
**Weakness:** no version control — editing a sent proposal mutates it silently,
with no record of what the client actually received. No internal approval step
before sending (anyone with `coordinator` can send a priced offer).
**Missing:** version snapshots, send-approval gate, client-view tracking.
**Fix:** snapshot on send. P1.

### C5. Contracts — **82 / 100**

**Strength:** best-in-class for this product category. Five typed bilingual
documents, per-type clause template libraries, many contracts per event, party
morph (client/supplier/venue), signatories with status auto-sync, the Deck
(document-wall + pipeline views), appendices with `{{appendix:slug}}` reference
tokens pulling live data from Budget/Agenda/Venue/Brief, WYSIWYG paper → Chrome PDF.
**Weakness:** no version history on the contract body. Contract → invoice bridge
is weak (payments exist; generating an invoice *from* a contract milestone is
manual).
**Fix:** contract version snapshots at signature. P1.

### C6. Planning / Timeline — **60 / 100**

**Strength:** Plan Studio with Gantt, deliberately keeps its own colourful visual
identity (an owner decision on record).
**Weakness:** planning is disconnected from Tasks — two systems that both hold
"work to do". No dependencies between items, no critical path, no department
handover points. Brief → generate → Planning exists but the generated WBS is
being replaced (there is an approved, unstarted plan to rebuild Planning as a
Budget-style category/item/sub-item tree).
**Missing:** dependencies, milestone approvals, handover markers.
**Fix:** finish the approved Planning rebuild. P1.

### C7. Operations (Venue / Layout / Equipment / Logistics) — **74 / 100**

**Strength:** real metre-accurate layout canvas with seating, room equipment
status with one canonical source, room/venue PDFs, requirements catalogue,
event-wide requirements aggregated into budget.
**Weakness:** **Equipment is a requirements list, not inventory.** No stock
levels, no availability calendar, no double-booking detection across events.
`docs/19` explicitly rules warehouse inventory out of scope — correct for Phase 1,
but the team must not be told "equipment is managed" when what exists is
"equipment is listed and costed".
**Missing:** production schedule as a first-class object, permits, emergency/
incident log, crew scheduling.
**Fix:** name the limitation in training. Add incident log. P2.

### C8. Transport — **84 / 100**

**Strength:** the most operationally complete module. Seven statuses, drivers +
vehicles catalogues, guest pool, readiness scoring, dispatch board with time axis
and clash detection and drag-to-reassign, Live ops with two-tap show-day actions
(**On the way** / **Issue** / +15m·+30m·+60m), five PDFs.
**Weakness:** none material for Phase 1. Inspected on real data 7 Aug — already
meets the quality bar `docs/19` schedules a UX pass for. **That pass should be struck.**

### C9. Agenda / Speakers — **83 / 100**

**Strength:** day tabs with readiness rings, room timeline drawn to scale,
Timeline ⇄ Programme toggle, conflict detection for double-booked **rooms and
speakers**, teaching empty states, four PDFs, CSV import, Run of Show.
**Weakness:** none material. **Also inspected 8 Aug — its UX pass should be struck too.**

### C10. Budget / Finance — **70 / 100**

**Strength:** per-line cost *and* charge; three pricing modes (quoted price,
markup %, house fee); billable flag; `actual_cents` nullable so "not costed yet"
≠ "costs nothing"; budget versions with approval; module sync (Stay, Transport,
Speakers, Venue, Catering, Requirements) with manual-line preservation; live FX
with JOD peg.
**Weakness:**
- **No company-level default tax rate.** `CompanyProfile` has no tax field
  (verified). Jordan VAT (16%) must be retyped on every proposal and invoice.
- Tax exists on Proposal and Invoice but **not** on budget lines — cost planning
  is tax-blind.
- Invoice-from-budget does not exist (no `fromBudget` path on `InvoiceEditor`).
**Fix:** company default tax rate. P1. Invoice-from-budget. P1.

### C11. Suppliers — **38 / 100 — lowest score in the audit**

**Strength:** good catalogue, categories, ratings, per-event assignment with an
8-state pipeline, supplier order PDF, budget linkage.
**Weakness (verified):** `resources/views/events/hub/suppliers.blade.php` has
**no Livewire component, no embedded component, and zero `wire:click` /
`wire:model` / `wire:submit`.** It is the **only** module tab in the hub that is
purely presentational. A coordinator cannot change a status, raise an issue, add
a note, or attach/detach a supplier from inside an event. The pivot is written
only by seeders.
Compounding it: **24 of 54** supplier links carry status `confirmed`, which is not
in the 8-state vocabulary — it renders a green "Confirmed" badge beside a red 10%
readiness bar, because the readiness map falls through to `?? 10`.
**Missing:** quotations, cost comparison, purchase orders, payment status per
supplier, performance history.
**Fix:** write path + status validation. **P0.**

### C12. Tasks / Collaboration — **55 / 100**

**Strength:** tasks with assignee, status, area, due date, event linkage;
portfolio task list; team workload on event overview.
**Weakness:** **no comments, no attachments, no notifications, no @mentions.**
This is the module whose entire purpose is to replace WhatsApp, and it currently
cannot carry a conversation. A task assigned to someone tells them nothing until
they happen to log in and look.
**Fix:** notifications first, then comments. **P0** (see §C15).

### C13. Documents / Files — **64 / 100**

**Strength:** per-event folders, module-scoped document components, upload,
audit-logged, folder colours by module.
**Weakness:** **no version control** (verified — no version field on
`EventDocument`). Replacing a signed PDF destroys the previous one with no trace.
No cross-event search. Permissions are event-level only, not document-level.
**Fix:** document versioning. P1.

### C14. Reports / Analytics — **40 / 100**

**Strength:** a Reports page exists with event/portfolio figures.
**Weakness:** thin (172-line component) and **zero export paths** (verified: 0
occurrences of export/download/pdf in `ReportsOverview`). Management cannot take
a report to a board meeting. No post-event report, no proposal-conversion report,
no supplier performance, no team performance, no profitability ranking.
**Fix:** see §I. P1.

### C15. Notifications — **10 / 100 — the biggest structural hole**

**Verified:** `app/Notifications/` contains exactly one class (`TeamInvite`).
There is **no `notifications` migration** and **no notification model**. Laravel's
notification table was never published.

Consequence across every module: approvals sit unseen; overdue tasks surface only
on a dashboard nobody is required to open; a supplier flagged `issue` alerts no
one; a VIP transfer with no driver is visible only to whoever opens Transport
Live. **This is the single highest-leverage build in the platform.** Every other
module gets better the moment it exists.
**Fix:** `php artisan notifications:table`, an in-app bell backed by real records,
and notifications on: approval requested/decided, task assigned, task overdue,
supplier issue, proposal accepted/declined, event stage change. **P0.**

### C16. Users / Roles / Permissions — **46 / 100**

**Verified structure:** 5 global ranks (`super_admin, admin, manager,
coordinator, viewer`), 6 gates (`write, decide-approvals, manage-budget,
manage-contract, manage-events, manage-team`), 1 policy (`EventPolicy`), and 8
per-event team roles.

**The core problem:** those 8 per-event roles —
`project_manager, operations_lead, registration_lead, supplier_coordinator,
finance_owner, design_owner, production_owner, client_rm` — are **never read for
authorization anywhere** (verified by grep across `app/`). They are display
labels. A Design Owner can edit the budget exactly as well as the Finance Owner,
because both are `coordinator`.

**Second problem:** `manager` is a god-bucket. One rank grants approvals, budget,
contracts *and* events. Your Sales Manager and your Finance Manager must both be
`manager`, so your Sales Manager can approve budgets.

Cursor's `docs/18` reached the same two conclusions independently
("manager is a god-bucket", "event team labels cosmetic"). Two independent audits
agreeing raises confidence.

**Fix:** make event team roles grant capability (§G). **P0.**

### C17. Settings / Company Configuration — **72 / 100**

**Strength:** genuinely good coverage — company profile, defaults, price list,
registration templates, sponsor packages, statuses & colours, types/taxonomy,
transport catalogues. Editable vocabularies with key-vs-label safety.
**Weakness:** **no tax settings** (verified), no departments, no notification
preferences, no approval-workflow configuration, no proposal/contract/invoice
template management from Settings (templates are in code).
**Fix:** tax rate. P1. Departments. P2.

---

## D. Sidebar / Navigation Architecture Audit — **41 / 100**

Audited from first principles on the running app at two breakpoints.

### D1. Current problems (all verified)

1. **Two navigation surfaces on the same edge, always.** `app-rail.blade.php`
   (78px, icon-only) *plus* `app-panel.blade.php` (288px, labelled). 366px of
   permanent chrome. Enterprise products ship one rail that expands.
2. **They use two different taxonomies of the same product.**
   Rail: `Command Center · Events · Tasks · CRM · Finance · Library`.
   Panel: `Events · Planning · Communication · Business · Finance`.
   The rail calls it *CRM*; the panel calls it *Business*. Two mental models,
   side by side, permanently.
3. **FUNCTIONAL BUG — below 1280px, Settings and Sign-out are unreachable.**
   Measured at 1180px viewport: `settingsLinksVisible: 0`, `logoutFormsVisible: 0`.
   The panel is `hidden … xl:flex`; both live only in its dock; **there is no
   hamburger or toggle**. A 13" laptop with a non-maximised window lands here.
   Planning board, Proposals, Contracts, Invoices and Payments also vanish — the
   panel is their only door.
4. **Nav ghosts are still live.** `NavPanel::PANEL` contains **Messages**
   (`messages.index`) and **Guests** (`guests.index`); **neither route exists**.
   They render as rows *deliberately drawn identically* to working ones
   (`"drawn the same on purpose"` — the code's own comment), differing only by a
   `title="Coming soon"` tooltip. Two of eleven rows go nowhere and look real.
   *(Correction: `docs/20` recorded this P0 as done. That was wrong — it checked
   `command-spine.blade.php`, which is dead code. `docs/20` §7 is amended by this
   document.)*
5. **Dead code describing a third, conflicting nav.**
   `resources/views/components/command-spine.blade.php` +
   `app/View/Components/CommandSpine.php` — an orphaned pair, rendered nowhere,
   describing 8 different items (Projects, Sponsors, Reports; **no CRM, no
   Library**). It is what caused error (4) above.
6. **Order does not match how the business works.** `NavPanel::AREAS` is
   `workspace → events → tasks → crm → finance → library`. The company's own
   workflow spine begins `Client → Deal → Proposal → Event`. CRM sits fourth
   while being where every job starts.
7. **"Library" is a filing-cabinet word for operational data** — it holds
   Suppliers, Venues, Team, Reports, Requirements. Nobody thinks "I'll check the
   Library" when a supplier has a problem.
8. **Icon-only rail with hover-only labels** is a mobile pattern on a desktop
   product. Fragile too — labels are painted *outside* the rail, so any
   `overflow-hidden` silently swallows them (has broken twice; there is now a
   regression test).

### D2. Top navigation mistakes, ranked

| # | Mistake | Severity |
|---|---|---|
| 1 | Settings/Sign-out unreachable < 1280px | **Critical** |
| 2 | Two parallel navs, two taxonomies | **Critical** |
| 3 | Dead rows indistinguishable from live ones | High |
| 4 | Dead `CommandSpine` pair as a false source of truth | High |
| 5 | Order contradicts the sales→delivery spine | Medium |
| 6 | "Library" mis-names operational data | Medium |

### D3. Recommended architecture — one rail, expand/collapse, grouped by lifecycle

Single navigation surface. Icon+label when expanded, icon-only when collapsed,
**with a persistent toggle at every breakpoint** and a slide-over drawer below
`lg`. Grouped by the order work actually happens.

```
┌────────────────────────────────┐
│  ◆ ELITE BUSINESS HUB      «   │  ← logo + collapse toggle (ALWAYS present)
├────────────────────────────────┤
│  👤 Emran Ahmed                 │
│     Super Admin            ⌄   │
├────────────────────────────────┤
│  ⌘K  Search…                   │
├────────────────────────────────┤
│  🏠  Command Center            │  ← daily home
├────────────────────────────────┤
│  WIN THE WORK                  │
│  🤝  Clients                    │
│  📈  Pipeline                   │
│  📄  Proposals                  │
│  ✍️  Contracts                  │
├────────────────────────────────┤
│  DELIVER THE WORK              │
│  📅  Events              [16]   │
│  ✅  Tasks               [37]   │
│  🗓  Planning                   │
├────────────────────────────────┤
│  RUN THE OPERATION             │
│  🚚  Suppliers                  │
│  🏛  Venues                     │
│  📦  Equipment                  │
├────────────────────────────────┤
│  THE MONEY                     │
│  💰  Finance                    │
│  🧾  Invoices                   │
│  💳  Payments                   │
├────────────────────────────────┤
│  KNOW WHAT HAPPENED            │
│  📊  Reports                    │
│  🗂  Documents                  │
├────────────────────────────────┤
│  👥  Team                       │
│  ⚙️  Settings                   │
│  ↪  Sign out                   │
└────────────────────────────────┘
```

### D4. Group rationale

| Group | Purpose | Who | Frequency | Priority |
|---|---|---|---|---|
| **Command Center** | "What needs me today" | Everyone | Every login | P0 |
| **Win the work** | Lead → signed contract | Sales, GM, CEO | Daily (sales) | P0 |
| **Deliver the work** | Signed → delivered | PM, coordinators | Constant | P0 |
| **Run the operation** | Who and what supplies it | Ops, procurement | Daily in build-up | P1 |
| **The money** | Cost, charge, collect | Finance, GM, CEO | Weekly | P1 |
| **Know what happened** | Evidence and learning | CEO, GM, PM | Monthly | P2 |
| **Team / Settings** | Administration | Admin, CEO | Rare | P2 |

Every current destination survives. Nothing is buried; **Suppliers, Venues and
Equipment are promoted out of "Library"** into a named operational group.

### D5. Renames, merges, removals

| Action | Item | Reason |
|---|---|---|
| **Remove** | Messages, Guests | Routes do not exist |
| **Remove** | `command-spine.blade.php` + `CommandSpine.php` | Dead, conflicting |
| **Rename** | Library → *(dissolved)* | Filing word for live data |
| **Rename** | CRM → **Clients** + **Pipeline** | "CRM" is jargon; split by job |
| **Promote** | Suppliers, Venues | Were two clicks deep |
| **Promote** | Proposals, Contracts | Were panel-only, invisible <1280px |
| **Merge** | Rail + Panel → one rail | Removes duplicate taxonomy |

### D6. Role-based visibility

| Group | CEO/GM | PM | Ops | Finance | Sales | On-site | Viewer |
|---|---|---|---|---|---|---|---|
| Command Center | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Win the work | ✅ | read | — | read | ✅ | — | — |
| Deliver the work | ✅ | ✅ | ✅ | read | read | own events | read |
| Run the operation | ✅ | ✅ | ✅ | read | — | read | — |
| The money | ✅ | read | — | ✅ | — | — | — |
| Reports | ✅ | ✅ | read | ✅ | read | — | — |
| Team / Settings | ✅ | — | — | — | — | — | — |

### D7. Navigation scores

| Dimension | Score |
|---|---|
| Sidebar Structure | 38 |
| Information Architecture | 42 |
| User Flow | 45 |
| Discoverability | 40 |
| Navigation Simplicity | 35 |
| Enterprise Readiness | 40 |
| Scalability | 55 |
| Role-Based Navigation | 25 |
| Mobile Navigation | 30 |
| **Overall Navigation** | **41** |

### D8. Quick wins (one PR, ~half a day)

1. Add a nav toggle at all breakpoints + drawer below `lg` — **fixes the lockout**
2. Hide Messages and Guests
3. Delete the `CommandSpine` pair
4. Add a Settings/Sign-out fallback into the rail dock

### D9. Long-term

5. Merge rail + panel into one expand/collapse rail
6. Regroup to §D3 and reorder to the spine
7. Role-based visibility
8. Persist collapsed state per user

---

## E. Full Workflow Audit (28 steps)

| # | Step | Supported | Gap |
|---|---|---|---|
| 1–4 | Lead → client → deal → brief | ✅ | Brief is event-level, not deal-level |
| 5 | Proposal created | ✅ | **No CTA from the CRM board** |
| 6 | Internal approval | ❌ | **Anyone `coordinator`+ can send a priced offer** |
| 7 | Sent to client | ⚠️ | Status only; no send tracking, no email |
| 8 | Status tracked | ✅ | Derived expiry is correct |
| 9 | Accepted | ✅ | Wins deal, opens event, seeds budget |
| 10–11 | Contract generated / signed | ✅ | Strong. No version history |
| 12 | Payment terms | ✅ | `ensurePayments()` from schedule |
| 13 | Event activated | ⚠️ | **Drag-to-Won bypasses budget seed** |
| 14 | Team assigned | ⚠️ | Roles are labels only |
| 15 | Planning timeline | ⚠️ | Disconnected from Tasks |
| 16 | Budget created | ✅ | Strong |
| 17 | Suppliers assigned | ❌ | **Read-only in the hub** |
| 18 | Equipment / layout | ✅ | Requirements + canvas |
| 19 | Tasks distributed | ⚠️ | **No notification on assignment** |
| 20 | Client approvals | ⚠️ | Approvals exist; **no ping** |
| 21 | Operations confirmed | ✅ | Readiness scoring |
| 22 | Run sheet | ✅ | Run of Show + Transport Live |
| 23 | Executed | ✅ | Two-tap show-day actions |
| 24 | Supplier payments | ⚠️ | Budget-level, not supplier-level |
| 25 | Client invoice | ✅ | **No invoice-from-budget** |
| 26 | Post-event report | ❌ | **Does not exist** |
| 27 | Archived | ⚠️ | Stage exists; no wizard |
| 28 | Lessons learned | ❌ | **Does not exist** |

**Score: 68 / 100.** Strong from contract to show-day. Weak at both ends —
pre-sales continuity and post-event closure.

**Broken handoffs:** (1) CRM→Budget on the drag path. (2) Proposal→send with no
approval. (3) Task assignment→the assignee. (4) Event→archive→client history.

**Approvals needed at:** proposal send, budget baseline (exists), supplier
commitment above threshold, contract send, going live with an empty agenda.

**Notifications needed at:** steps 6, 7, 9, 19, 20, 24, 26 — **none exist today.**

---

## F. UI / UX Audit

### UI — **78 / 100**

**Genuinely premium.** Navy/gold/Playfair is coherent and distinctive; it does not
look like a Bootstrap admin panel. Shared components are real (`<x-confirm>`,
`<x-alert>`, `<x-date>`, `Money`, status badges, donuts, `op-card`). Density work
has been done. PDFs are company-stationery quality.

**Weak points:** the dark rail+panel against a light body is a strong idea
executed twice; decorative "gold dust and orbit arcs" in the nav is styling
effort spent on the product's least functional surface; status colour is
occasionally contradicted by adjacent data (green "Confirmed" beside a red 10% bar).

### UX — **62 / 100**

**Where users get lost:** the left nav (§D) — and below 1280px they are genuinely
stuck. **Where users stop:** anywhere that needs a response from another person —
because nothing tells that person. **Where users make mistakes:** dragging a deal
to Won (no confirmation, silent budget divergence); clicking Messages/Guests.
**Repeated entry:** tax on every document; supplier status nowhere.

**Guidance missing:** a fresh event opens with 23 tabs and no first step.

---

## G. Roles & Permissions Audit

**Current:** 5 ranks · 6 gates · 1 policy · 8 cosmetic event roles.

**Risks:** `manager` is a god-bucket (budget + contracts + approvals + events in
one rank) — your Sales Manager can approve budgets. Event team labels imply
control that does not exist. No per-document permissions. `viewer` can see full
financials on any event they can open.

### Recommended Phase 1 structure — keep 5 ranks, make event roles *mean* something

Do **not** build 13 global roles. Keep ranks as a **ceiling**, and let the
per-event team role grant capability **within that event**:

| Rank | Ceiling |
|---|---|
| `super_admin` | Everything incl. settings, users, all money |
| `admin` | Users, catalogues, settings — not company P&L |
| `manager` | Approvals, budgets, contracts, all events |
| `coordinator` | Their events, per team-role capability |
| `viewer` | Read-only, money hidden |

| Event team role | Should grant, inside that event |
|---|---|
| Project Manager | Everything except contract signature |
| Operations Lead | Venue, Equipment, Transport, Suppliers |
| Finance Owner | Budget, Pricing, Invoices |
| Supplier Coordinator | Suppliers, purchase orders |
| Design Owner | Brief, Documents, Layout |
| Production Owner | Agenda, Run of Show, Venue |
| Registration Lead | Attendees, Registration, Arrivals |
| Client RM | Client comms, approvals, documents |

**Also split** `manage-budget` from `manage-contract` from `decide-approvals` so
one rank stops granting all three. **Add** a money-visibility gate so `viewer`
and non-finance coordinators see operations without margins.

**Score: 46 / 100.** P0.

---

## H. Dashboard / Command Center Audit

Build **one** dashboard, role-selected widgets.

| Role | Must see |
|---|---|
| **CEO / GM** | Revenue MTD/YTD, margin %, pipeline value, cash in/out, overdue invoices, at-risk events, approvals awaiting *them*, headcount load |
| **Event Director / PM** | My events by stage, module readiness, my overdue tasks, approvals I raised, risks, next 7 days |
| **Operations** | Today's movements, supplier issues, equipment gaps, venue readiness, crew, tomorrow's build |
| **Finance** | Unpaid invoices by age, supplier payments due, budgets awaiting approval, event profitability ranking, cash forecast |
| **Sales** | My deals by stage, proposals out + expiring, win rate, follow-ups due |
| **On-site** | Today only — my movements, my sessions, arrivals, who to call |
| **Viewer** | Read-only schedule, no money |

**Missing KPIs today:** revenue, margin, cash position, pipeline value, invoice
ageing, proposal conversion, supplier performance, per-person utilisation.

**Score: 58 / 100.** P0.

---

## I. Reporting & Analytics Audit — **40 / 100**

**Have:** portfolio + event figures on screen. **No exports at all.**

**Phase 1 must-have (in order):**
1. **Post-event report** — budget vs actual, attendance, supplier performance, issues. *Closes the loop; does not exist.*
2. **Event profitability** — ranked, quoted vs cost vs actual.
3. **Invoice ageing** — 0/30/60/90.
4. **Proposal conversion** — by month, by client, by value.
5. **Supplier performance** — on-time, issue count, spend.
6. **Team workload** — open tasks per person across events.

Every one must export to PDF (stationery already exists) and XLSX (`xlsx` trait
already exists). **P1.**

---

## J. Enterprise Quality Audit — **66 / 100**

| Dimension | Verdict |
|---|---|
| **Scalability** | Good. Tenancy spine merged; FK indexes audited (78 added); N+1 work done |
| **Maintainability** | Strong. 1,043 tests, mechanical auth guard, single-source registries |
| **Security** | Strong. Policy + gates + `AuthorizationGuardTest` + security headers + soft deletes. **Gap:** money visibility not gated by role |
| **Data structure** | Strong. Nullable `actual_cents`, cents everywhere, morph parties, taxonomy keys locked vs labels |
| **Audit logs** | ✅ 16 models |
| **Notifications** | ❌ **Absent** |
| **Approvals** | ⚠️ Exist; nobody is told |
| **Document control** | ⚠️ No versioning |
| **Version control** | ❌ Not on proposals, contracts or documents |
| **Workflow automation** | ⚠️ Budget sync + lifecycle only |
| **Integration readiness** | ⚠️ No API, no webhooks, no calendar sync |
| **Error handling** | Good. Sentry wired |
| **CI/CD** | ⚠️ CI strong (3 required checks); **no CD** |

**Verdict: enterprise-grade engineering discipline, not yet an enterprise
operating system.** The craft is there; the connective layer is not.

---

## K. Audit Scores

| # | Category | Score | Main weakness | Priority |
|---|---|---|---|---|
| 1 | UI Design | **78** | Two navs; decoration on the weakest surface | P1 |
| 2 | UX Experience | **62** | Nav lockout; nothing tells anyone anything | P0 |
| 3 | Sidebar / Navigation | **41** | Two navs, ghosts, <1280px lockout | **P0** |
| 4 | Information Architecture | **48** | Two taxonomies for one product | P0 |
| 5 | Workflow Logic | **68** | Broken at both ends | P0 |
| 6 | Module Completeness | **74** | Suppliers read-only | P0 |
| 7 | Dashboard Quality | **58** | No role weighting | P0 |
| 8 | Event Lifecycle Coverage | **82** | Close-out missing | P1 |
| 9 | Internal Ops Readiness | **72** | Equipment ≠ inventory | P1 |
| 10 | Finance & Budgeting | **70** | No tax default; no invoice-from-budget | P1 |
| 11 | Document & Contract | **76** | No versioning | P1 |
| 12 | Team Collaboration | **45** | No notifications, no comments | **P0** |
| 13 | Role & Permission | **46** | 8 roles that grant nothing | **P0** |
| 14 | Reporting & Analytics | **40** | No exports, no post-event report | P1 |
| 15 | Enterprise Quality | **66** | Notifications, versioning absent | P0 |
| 16 | Phase 1 Readiness | **70** | Launchable with the 4 must-fixes | P0 |
| 17 | Future SaaS Readiness | **74** | Spine merged; product not built (correct) | P2 |
| | **OVERALL** | **64** | | |

---

## L. Comparison With Previous Audits

Previous audit data **is** available: `docs/18` (Cursor, 6 Aug, scored /10) and
`docs/20` (Claude, 8 Aug). Cursor's converted to /100:

| Dimension | docs/18 (6 Aug) | This audit (8 Aug) | Δ |
|---|---|---|---|
| UI/UX | 65 | 70 (UI 78 / UX 62) | **+5** |
| Module structure | 75 | 74 | −1 |
| Event workflow | 70 | 68 | −2 |
| Dashboard | 60 | 58 | −2 |
| Navigation | 60 | **41** | **−19** |
| Auth system | 75 | 85 | **+10** |
| Roles & permissions | 50 | 46 | −4 |
| Costing engine | 70 | 70 | 0 |
| Internal launch readiness | 70 | 70 | 0 |
| Future SaaS readiness | 45 | 74 | **+29** |

**Improved:** Auth (+10) — invite/set-password + forgot-password merged 7 Aug.
SaaS readiness (+29) — the five-slice tenancy retrofit merged. UI (+5) — module
readiness doors and the daily-ops tab strip.

**Declined:** Navigation (−19). Not a regression in code — a **more severe
finding**. `docs/18` correctly saw "ghosts in nav"; this audit additionally found
the sub-1280px functional lockout and the dead `CommandSpine` pair by testing the
running app at two breakpoints.

**Still weak in both:** Roles (50 → 46; both audits independently concluded
"manager is a god-bucket" and "event team labels are cosmetic"), Dashboard role
lenses, Reporting.

**Correction to `docs/20`:** it recorded P0 "Hide nav ghosts" as ✅ *already done*.
**That was wrong** — it checked `command-spine.blade.php`, which is dead code.
The live `NavPanel::PANEL` still contains Messages and Guests. `docs/20` §3 and §7
are superseded by §D of this document.

---

## M. Final Roadmap

### Phase 1 — Must-have (before real client events)

1. **Notification system** — table, in-app bell, approval/assignment/overdue/issue. *Unblocks every other module.*
2. **Navigation quick wins** — toggle at all breakpoints, hide ghosts, delete dead pair. *Fixes a functional lockout.*
3. **Suppliers write path** + status validation.
4. **Soft win gate** + Draft-proposal CTA on the CRM board.

### Phase 1 — Should-have

5. Role-weighted dashboard · 6. Event team roles grant capability · 7. Split `manage-budget`/`manage-contract`/`decide-approvals` · 8. Company default tax rate · 9. Post-event report + exports · 10. Nav rebuild to §D3.

### Phase 1 — Nice-to-have

11. Document versioning · 12. Proposal/contract snapshots · 13. Close-out wizard · 14. Invoice-from-budget · 15. Task comments.

### Ignore for now

Warehouse inventory · 3D/CAD · client portal · 13 global roles · public API · calendar sync.

### Phase 2 — SaaS (prepare, do not build)

**Already right:** tenancy spine merged, `tenant_id` on 62 tables, global scoping,
per-workspace roles, `ResolveTenant` on the web group.
**Do not build now:** workspace switcher, tenant onboarding, billing, white-label,
usage limits, tenant admin.
**Prepare cheaply:** keep `CompanyProfile` per-tenant-ready; keep taxonomies
per-tenant; keep `BelongsToTenant` on every new model (the guard test enforces it).

---

## N. Final Action Plan

### Top 10 urgent fixes
1. Nav toggle at all breakpoints (**lockout**) · 2. Notification system · 3. Suppliers write path · 4. Soft win gate · 5. Hide Messages/Guests · 6. Delete `CommandSpine` pair · 7. Fix 24 bad supplier statuses + validate · 8. Draft-proposal CTA · 9. Split the manager god-bucket · 10. Money-visibility gate for `viewer`.

### Top 10 UI/UX
1. One nav rail · 2. Role-weighted dashboard · 3. First-run guidance on new events · 4. Fix status/readiness contradiction · 5. Sub-`xl` drawer · 6. Reduce nav decoration · 7. Consistent empty states on remaining modules · 8. Sticky save on long forms · 9. Mobile pass on Arrivals/Transport Live · 10. Loading states on slow tabs.

### Top 10 navigation
Per §D8/§D9 — toggle, ghosts, dead code, Settings fallback, merge rail+panel, regroup to lifecycle, rename CRM→Clients+Pipeline, dissolve Library, role visibility, persist collapse.

### Top 10 workflow
1. Notify on approval · 2. Notify on assignment · 3. Win gate · 4. Proposal send approval · 5. Post-event report · 6. Close-out wizard · 7. Lessons learned · 8. Invoice-from-budget · 9. Supplier payment tracking · 10. Planning↔Tasks unification.

### Top 10 missing features
Notifications · post-event report · report exports · document versioning · proposal versioning · task comments · supplier quotations/POs · company tax rate · departments · incident log.

### Best development order

```
Week 1  Nav quick wins (lockout, ghosts, dead code)   ← half a day, unblocks daily use
Week 1  Notification system                            ← highest leverage in the platform
Week 2  Suppliers write path + status validation
Week 2  Soft win gate + Draft-proposal CTA
Week 3  Role-weighted dashboard
Week 3  Event team roles grant capability + gate split
Week 4  Post-event report + exports
Week 5  Nav rebuild (one rail, regrouped)
Week 6  Close-out wizard + lessons learned
```

Do notifications before anything cosmetic. Every module in this audit scores
higher the moment the platform can tell someone that something needs them.

---

*Related: [18-phase1-platform-audit.md](18-phase1-platform-audit.md) ·
[19-internal-improvement-plan.md](19-internal-improvement-plan.md) ·
[20-implementation-inspection.md](20-implementation-inspection.md)*
