# Elite Business Hub — Phase 1 Platform Audit

**Date:** 2026-08-06  
**Scope:** Internal platform for **one company** (Elite Business Hub) — 1-month usable launch, 6-month internal proving, then Phase 2 (~50 companies), then Phase 3 enterprise SaaS.  
**Codebase:** `main` @ post-#22/#24/#25 (pgsql green, sqlite→pgsql script, workspace role pivot).  
**Method:** Code + docs inspection (routes, Livewire, models, policies, layout builder, budget/costing, nav, CRM/finance workflow). Not a generic SaaS lecture.

---

## 1. Executive Summary

Elite Business Hub is already a **deep, working event operations system** — not a prototype. The event hub (19 modules), CRM→Proposal→Win→Event, contracts/invoices/payments, budget with fee/margin, venue layout canvas + load-in PDFs, transport live/dispatch, and registration/check-in are real and tested.

It is **not yet a polished “premium command center” for every internal role**, and it is **not ready for multi-company SaaS**. For Phase 1 (your company only), that is fine — if you **stop overbuilding**, fix a small set of **critical workflow and auth gaps**, and launch on a disciplined commercial path.

**Honest launch call:**  
**Ready for internal launch in ~4 weeks** as a Professional Internal System — *if* Weeks 1–4 below are executed.  
**Not ready** for Client Viewer accounts, least-privilege Finance≠Designer, inventory/warehouse equipment, closed-loop layout→invoice, or 50-company isolation.

**Platform level:** **Professional Internal System** (leaning Early SaaS only in tenancy scaffolding).

---

## 2. Current Platform Level

| Level | Fit? |
|---|---|
| Prototype | No — 1000+ tests, full hub, PDFs, money flows |
| Internal MVP | Surpassed |
| **Professional Internal System** | **Yes — current home** |
| Early SaaS | Partial — tenants/workspaces exist; product still one-house |
| Enterprise SaaS | No |

**Why:** Depth of ops modules and commercial documents exceeds MVP. Premium UI consistency, role lenses, notifications, and closed commercial handoffs are still uneven. Multi-tenant product surfaces (onboarding, billing, company switcher) are intentionally absent.

---

## 3. Current Readiness Scores

| Dimension | Score /10 | Note |
|---|---|---|
| UI/UX | **6.5** | Strong navy/gold identity; chrome dense; ghosts in nav; not yet “executive command” for all roles |
| Module Structure | **7.5** | Hub registry is clear; portfolio nav duplicates; Projects thin; Quotations absent (OK) |
| Event Workflow | **7.0** | Strong after Event exists; pre-sales continuity and close-out weak |
| Dashboard | **6.0** | Useful signals; no role lenses; weak money signals on live advisor |
| Navigation | **6.0** | Rail + fixed panel + Library depth; Messages/Guests are ghosts |
| Auth System | **7.5** | Login + gates solid; **invite / set-password + forgot-password on main** (`2b32188`) |
| Roles & Permissions | **5.0** | 5 ranks only; manager is a god-bucket; event team labels cosmetic |
| Layout Builder | **7.0** | Real metre canvas + seating + PDF; not enterprise object library |
| Equipment Management | **5.0** | Requirements list + status — **not inventory** |
| Costing Engine | **7.0** | Cost→budget→fee/sell works; dual catalogues; weak invoice bridge |
| Internal Launch Readiness | **7.0** | Launchable with discipline + Week 1–4 fixes |
| Future SaaS Readiness | **4.5** | Tenancy columns/scopes present; product/ops for 50 cos not built |

---

## 4. Top Critical UI/UX Problems

| # | Problem | Why / Impact | Improvement | Priority | Where |
|---|---|---|---|---|---|
| 1 | Nav ghosts (Messages, Guests, Help, Assets stub) look clickable | Confuses new staff; looks unfinished | Hide or “Soon” badge; remove from primary map | **Critical** | `NavPanel`, `app-panel.blade.php` |
| 2 | Chrome density (rail 78 + panel 288 + hub tabs) | Coordinators lose the work surface on laptop | Collapse panel by default &lt;xl; hub primary strip only | **High** | `layouts/app.blade.php`, `hub.blade.php` |
| 3 | Dashboard is time-ordered, not role-operational | CEO/Finance/Ops see the same Signals | Role-weighted widgets (money for manager+, my tasks for coordinator) | **High** | `Dashboard`, `PortfolioAdvisor` |
| 4 | Dual catalogues: Equipment (`Requirement`) vs Price List (`ServiceItem`) | Ops and finance retype; training cost | One story in UI: “Ops list” vs “Rate card” labels + deep-link | **High** | Library Equipment, CatalogueSettings, PricingTab |
| 5 | Coming-soon rows share chrome with real links | False affordance | Grey + non-interactive until built | **Medium** | `app-panel` |
| 6 | Alert bell → `#live-alerts` not a first-class home section | Broken expectation | Wire bell to Signals panel or remove | **Medium** | `app-tools`, dashboard |

---

## 5. Top Critical Workflow Problems

| # | Problem | Impact | Fix for Phase 1 | Priority |
|---|---|---|---|---|
| 1 | Win without proposal / Event Studio bypasses CRM | Orphan events, no agreed figure | Process + soft gate: confirm before Mark Won; CRM “Draft proposal” CTA | **Critical** |
| 2 | Proposal lines → budget seed | ~~Retype scope after win~~ | **Done on main** (`b2212db` / `BudgetSync::syncProposal`) — verify PricingTab path in pilots | **Done** |
| 3 | Staff invite / password reset | ~~Cannot onboard coordinators safely~~ | **Done on main** (`2b32188`) — invite/set-password + forgot-password | **Done** |
| 4 | Approvals don’t gate Contract send | Process risk | Soft UI warn; hard gate can wait 6 months | **High** |
| 5 | No notifications for pending approvals | Missed decisions | Email or in-app ping for approvers | **High** |
| 6 | Brief is post-Event, not pre-Quote | Sales expects wrong order | Document: Brief = delivery scope lock after win | **Medium** (doc) |
| 7 | No formal post-event evaluate | Close-out weak | Use Reports + `completed`/`closed` + archive; evaluate later | **Medium** |

**Adopted Phase 1 commercial spine (ship this, not the paper 26-step order):**  
Client → Deal → Proposal → Accept/Win → Event → Brief (approve+generate) → Contract → Payments → Invoice → Ops modules (Agenda, Transport, Venue, … in parallel once the Event exists) → show-day tools → archive → Client 360.

Ops may start as soon as the Event shell exists; money documents (Contract → Invoice) should not wait until after logistics are finished — train **commercial lock-in first**, then deepen ops.

---

## 6. Top Critical Module Structure Problems

| Issue | Recommendation |
|---|---|
| Quotation object missing | **Delay** — Proposal is the quote for Phase 1 |
| Projects = thin list | Keep as folder; don’t rebuild as second Event | **Phase 2** |
| Layout under Venue, not top-level Design | Correct for Phase 1; add Library “Layouts” later if needed |
| Equipment not a hub tab | OK — Venue Equipment tab + `/requirements` catalogue |
| Exhibition floor ≠ BudgetSync | Document; sync booth fees in Month 3–4 |
| `config/modules.php` stale vs `NavPanel` | Delete or regenerate from `NavPanel` / `HUB_MODULES` |

### Module classification

**A — Must-have (1-month launch)**  
Dashboard/Signals, Events, Event Hub core (Overview, Brief, Contract, Tasks, Planning, Budget, Agenda, Venue+Layout, Transport, Attendees, Approvals, Risks), CRM (Clients+Deals), Proposals, Contracts register, Invoices, Payments, Team roster, Company settings, Equipment catalogue (requirements), Suppliers, Venues.  
**Also A (workflow continuity):** ~~invite/password~~ + ~~proposal→budget seed~~ **done on main**; remaining: CRM→proposal handoff, hide nav ghosts, Overview next-steps.

**B — Should-have (first 6 months)**  
Sponsors, Exhibition, Catering, Accommodation, Speakers, Documents, Reports polish, Finance P&L, Planning board cross-event, Arrivals/check-in polish, soft approval→contract, notifications, venue budget line-split.

**C — Phase 2 (~50 companies)**  
Company isolation UX, invites at scale, subscriptions/billing readiness, RFQ/Quotation objects, Assets inventory, Messages/Guests, Client portal (limited), Projects as real workspace, layout versions/approvals, equipment availability.

**D — Phase 3 enterprise SaaS**  
Marketplace, APIs, venue digital twin / 3D, AI layout, real-time collab, advanced analytics, multi-region infra.

---

## 7. Top Auth and Permission Risks

| Risk | Severity | Phase 1 action |
|---|---|---|
| Invite / password reset | **Done** (`2b32188`) | Keep training + email delivery verified in pilots |
| Viewer cannot open event hubs even on team | **Critical** if you want Client Viewer; else **document “no client logins”** | Prefer document for month 1 |
| `manager` = god (budget+contract+approvals+all events) | **High** | Map GM/Finance/Ops → manager; accept for Phase 1 |
| Event team roles (`finance_owner` etc.) cosmetic | **High** for later; OK now | Don’t promise module-scoped roles yet |
| Admin can assign `super_admin` | **High** | Restrict escalation to existing super_admin |
| Unbound Tenancy in console/jobs | **Medium** | Bind tenant in scheduled jobs |

**Phase 1 permission model (recommended):**  
**B — Role (5 ranks) + event assignment** for coordinators.  
Do **not** build 14 functional roles yet. Map titles → ranks in a one-page staff matrix.

| Title | Rank | Notes |
|---|---|---|
| Owner / Super Admin | `super_admin` | Platform |
| Admin / GM | `admin` / `manager` | Team vs ops god-bucket |
| Ops / Event / Project Manager / Finance / Sales lead | `manager` | Same gates — process discipline |
| Coordinator / Designer / Production / Logistics | `coordinator` + event team | Hub only on assigned events |
| Read-only internal | `viewer` | Portfolio lists only until view policy fixed |
| Client Viewer | — | **Delay** (portal or fixed viewer policy in Month 5) |

---

## 8. Top Layout Builder Problems

| Problem | Priority | Phase |
|---|---|---|
| Canvas furniture ≠ equipment BOQ / budget lines | **High** | Train Phase 1; soft generate in Month 4 |
| Tiny object library (tables/chairs/stage/screen/podium/booth) | **Medium** | Expand categories Month 4; full library Phase 2 |
| No layers / multi-floor / venue digital twin | — | **Phase 2–3** |
| Fixed 960×560 canvas | **Medium** | Accept for Phase 1; responsive later |
| PDF strong; client approval view weak | **Medium** | Export PDF is enough for Month 1 |

**Phase 1 must-have (keep):** metre canvas, seating generators, layout PDF, equipment tab + load-in PDF, link room money into budget via BudgetSync.

**Do not build now:** 3D, CAD import, clash detection, marketplace objects, real-time collab.

---

## 9. Top Equipment and Costing Problems

| Problem | Priority | Fix |
|---|---|---|
| No owned/rented/stock/availability | **Critical** if treated as warehouse; else **High** | Phase 1: treat as **requirements list only** |
| Venue equipment collapsed to one budget line | **High** | Split hire + per-requirement lines (small code) |
| `Requirement` vs `ServiceItem` dual catalogues | **High** | UX labeling + optional alias Month 2 |
| Budget ↛ auto invoice | **High** | Manual PricingTab; seed from budget in Month 3 |
| Exhibition outside BudgetSync | **Medium** | Month 4 |
| Costing math (cost → markup/fee → sell) | **Good** | Keep; train finance |

---

## 10. Recommended Phase 1 Module Structure

Keep **Event as the operating unit**. Portfolio = Command + Events + CRM + Finance + Library.  
Hub modules stay toggleable via `HUB_MODULES`.  
Hide: Messages, Guests, Assets, Help (until built).  
Rename mental model: Proposal = Quotation for clients.

---

## 11. Recommended Sidebar Structure (Phase 1)

```
Command
  Dashboard (role-weighted)
  Calendar (events path / month — existing views)

Business
  Clients
  CRM / Deals
  Proposals   ← (this is your Quote)
  Contracts
  Invoices
  Payments

Operations
  Events
  Tasks
  Planning board
  Suppliers
  Venues
  Equipment (requirements catalogue)

Design (under Venue for now — don’t add top-level Design yet)
  Room Layout + Equipment (inside event Venue)

Admin
  Team
  Company / Defaults / Taxonomies / Packages
```

Remove Library dump of “Sponsorships / AI / Reports” from competing with hub — keep Reports/AI on event; portfolio Reports one link.

---

## 12. Recommended User Roles and Permissions

See §7 matrix. Gates to keep: `write`, `manage-budget`, `manage-contract`, `manage-events`, `decide-approvals`, `manage-team`, `EventPolicy::view`.

**Access:** password set/invite **done**. Optional: `EventPolicy::view` allow for `viewer` **on team** if internal observers need hubs (not clients).

---

## 13. Recommended Event Workflow

**Official Phase 1 path:**  
1. Create Client  
2. Create Deal (enquiry → …)  
3. Draft Proposal from CRM CTA  
4. Send / Accept Proposal → auto Win → Event  
5. Approve Brief → generate categories/risks/sponsors  
6. Draft/Sign Contract → payment schedule  
7. Raise invoices from installments  
8. Staff event team; Plan/Tasks; Venue/Layout/Equipment; Budget; Transport; Registration  
9. Live: Dashboard + Transport Live + Arrivals  
10. Stage completed/closed → Archive → Client history  

Automations: proposal→budget seed **done** (`b2212db`). Still add in Week 2–3: CRM Draft proposal; soft win gate. Verify PricingTab seed in pilots.

---

## 14. Recommended Layout Builder Structure

**Phase 1:** Keep current builder; document “equipment tab = cost & load-in; canvas = visual brief.”  
**Month 4:** Expand object categories lightly; optional “generate chairs/tables → requirements.”  
**Phase 2:** Versions, comments, venue templates, inventory link.  
**Phase 3:** Digital twin, 3D, AI suggest.

---

## 15. Recommended Equipment System Structure

**Phase 1 entity:** `Requirement` (catalogue) + room `requirements` JSON (qty/days/rate/status).  
**Explicit non-goals:** warehouse SKU, availability calendar, barcode.  
**Month 3–4:** owned/rented flag + supplier on line; split budget lines.

---

## 16. Recommended Costing Engine Structure

Keep: `EventBudgetItem` cost → `sell_cents` / `markup_pct` / `management_fee_pct`.  
Keep: BudgetSync from modules.  
Done: proposal accept → draft budget lines (`b2212db`). Verify PricingTab.  
Add: optional “Create invoice draft from budget billable lines” (Month 3).  
Delay: full tax engine, multi-currency conversion (PortfolioFinance exists — don’t expand yet).

---

## 17. Recommended Database Structure (Phase 1)

**Keep:** events, deals, proposals, contracts, invoices, budget items, rooms+layout JSON, requirements, attendees, transport, tenants/workspaces (already).  

**Do not add now:** separate quotations table, inventory tables, layout_objects normalized table (JSON is OK for Phase 1), 14 role tables.

**Watch:** `company_profiles` soft singleton (`orderBy id`) — when Phase 2 multi-company, must become per-tenant required.  
**Indexes/FKs:** largely addressed in Stage 5; continue on new tables only.

**Critical risk:** Treating layout JSON as inventory source of truth — don’t.

---

## 18. One-Month Internal Launch Plan

### Week 1 — Inspect, cleanup, prioritize
- Goals: Kill nav ghosts; freeze Phase 1 module list; staff role matrix; pull `main`.  
- Deliverables: Hidden Messages/Guests/Assets; one-page role map; launch checklist signed.  
- Success: New hire finds Events/CRM/Finance without dead ends.  
- **Do not:** Redesign sidebar from scratch; build portals; expand layout library.

### Week 2 — Core workflow + dashboard
- Goals: CRM→Draft proposal CTA; soft win gate; money signals on advisor; verify proposal→budget seed in pilots.  
- Deliverables: Documented spine; Dashboard shows overdue invoices + my pending approvals.  
- Success: One real deal won end-to-end without retyping scope twice.  
- **Do not:** Separate Quote object; Brief-before-sales.

### Week 3 — Access polish + team
- Goals: Train invite/forgot-password (already on main); escalate guard; seed team.  
- Deliverables: All staff can log in without WhatsApp password sharing.  
- Success: Coordinator limited to assigned events; managers see all.  
- **Do not:** 14 functional roles; Client Viewer.

### Week 4 — Polish, QA, train
- Goals: UI consistency pass (buttons/empty states on top 10 screens); full suite green (sqlite+pgsql); train PMs/coordinators/finance.  
- Deliverables: Internal launch day; runbook PDF; known-issues list.  
- Success: Two live events managed only in-app for a week.  
- **Do not:** Feature creep from Phase 2 wishlists.

---

## 19. Six-Month Internal Testing Plan

| Month | Focus | Go / No-Go |
|---|---|---|
| 1 | Daily usage; bug burn-down; training | ≥3 events live in hub |
| 2 | Workflow friction; CRM→Proposal→Win; nav polish | Win path used for 100% new sales events |
| 3 | Finance: budget↔invoice, costing clarity, dual-catalogue merge UX | Finance closes month without Excel shadow books |
| 4 | Ops: layout→requirements assist; exhibition→budget; production lists | Layout PDFs used in vendor packs |
| 5 | Limited client view (read-only hub or portal slice) | One client pilot without data leak |
| 6 | Phase 2 prep: tenancy product UX, invites, company settings, billing sketch | Written Phase 2 PRD + schema review |

Metrics: events/week in hub, time-to-proposal, invoice lag, approval wait hours, % budgets locked, PDF exports/week, critical bugs open.

---

## 20. Phase 2 — ~50 Companies

Product: company accounts, workspace switcher, onboarding, subscription flags, support impersonation.  
Tech: enforce tenant everywhere (no unbound jobs), per-tenant `CompanyProfile`, rate limits, backup per DB.  
UI: company branding light; admin console.  
Auth: invites, SSO readiness (not required day 1), finer roles.  
Layout/Equipment: versions, inventory soft-availability, vendor cost compare.  
**Avoid:** marketplace, 3D, public self-serve signup without sales.

---

## 21. Phase 3 — Enterprise SaaS

APIs, marketplace, digital twin, AI assist, realtime collab, advanced BI, compliance packs, multi-region.  
Only after Phase 2 retention and support load are proven.

---

## 22. Page-by-Page Improvement Plan (condensed)

| Page | Launch priority | Before launch | After launch |
|---|---|---|---|
| Dashboard | Must | Role weight + money signals; fix bell | Lenses per role |
| Events list | Must | Clear empty state; hide clutter | Saved filters |
| Event hub | Must | Primary strip only; More for rest | Density prefs |
| Brief / Contract / Budget / Tasks / Venue | Must | Train + small UX polish | Seed from proposal |
| Layout Builder | Must | SOP: canvas vs equipment tab | Object categories |
| Equipment catalogue | Must | Label vs rate card | Owned/rented flags |
| CRM / Proposals | Must | Draft proposal CTA | Win gate |
| Invoices / Payments | Must | Link from Signals | Budget→invoice draft |
| Team / Users | Must | Invite + password reset **done** | Escalation rules |
| Projects | Delay UI investment | Leave thin | Phase 2 |
| Messages / Guests / Assets | Hide | — | Phase 2 |
| Reports | Should | Keep | Month 3 finance packs |
| Client portal | Delay | — | Month 5 |

---

## 23. What to Build Now (next 30 days)

1. Hide nav ghosts  
2. ~~Invite / set-password + forgot-password~~ **done** (`2b32188`) — train + verify email  
3. CRM → Draft proposal + soft win discipline  
4. ~~Proposal accept → seed budget~~ **done** (`b2212db`) — verify PricingTab in pilots  
5. Dashboard money + approval signals; role-weight basics; Overview next-steps  
6. Split venue budget line (hire vs equipment) — optional but high value  
7. Staff training pack + role matrix  
8. Keep CI green (sqlite + pgsql); **Test suite (pgsql)** already required on `main`  

---

## 24. What to Delay

Separate Quotations/RFQs; Client Viewer portal; 14 roles; inventory warehouse; layout digital twin/3D; Messages; Assets; Projects rebuild; multi-company billing; notification platform beyond approvals email; hard approval→contract gate.

---

## 25. What to Remove or Simplify

- Nav entries without routes  
- Stale `config/modules.php` (or sync)  
- Promise of “equipment inventory” in sales talk — say “requirements & load-in”  
- Dual mental model Quote vs Proposal — **one word: Proposal**  
- Event Studio as default sales path — CRM win path preferred  

---

## 26. Final Professional Recommendation

**Launch in one month as Elite Business Hub’s internal operating system**, not as a SaaS product. You already own the hard parts (hub depth, documents, money math, layout PDFs, transport). Access invite and proposal→budget seed are already on `main`.

**Critical risks if ignored:** win-without-proposal orphans; dual costing catalogues; pretending Layout Builder is inventory; nav ghosts.

**Strategy:** Fix continuity (CRM handoffs + Overview next-steps) → run real events for six months → then productize tenancy for 50 companies. Do not freeze development on glassmorphism or 3D floors.

**Not ready for internal launch:** Client logins, warehouse equipment, closed-loop layout→invoice without humans, multi-company.  
**Ready with conditions:** Remaining §23 items (ghosts, CRM handoffs, signals, training).

---

## 27. Prioritized 30-Day Execution Checklist

### P0 — Critical (Week 1–2)
- [ ] Hide Messages / Guests / Assets / Help from nav  
- [ ] Staff role matrix (title → rank) published  
- [x] Invite or admin set-password + forgot-password — **done** `2b32188`  
- [ ] CRM “Draft proposal” CTA  
- [ ] Soft gate / confirm on Mark Won without accepted proposal  
- [x] Proposal accept seeds budget — **done** `b2212db` (verify PricingTab)  

### P1 — High (Week 2–3)
- [ ] Dashboard: overdue invoices + pending approvals for me  
- [ ] Basic role weighting (manager vs coordinator home)  
- [ ] Label Equipment catalogue vs Price List  
- [ ] Restrict `super_admin` assignment  
- [ ] Train PMs + Finance on commercial spine  
- [ ] Optional: split venue BudgetSync lines  

### P2 — Medium (Week 3–4)
- [ ] Empty states on Events / CRM / Invoices  
- [ ] Fix alert bell target  
- [ ] Approval email ping (minimal)  
- [ ] Known-issues + launch runbook  
- [x] Full test suites green; pgsql required check — **done** on `main`  
- [ ] Two pilot events run only in-app  

### Explicitly out of 30 days
- [ ] Client portal / Viewer clients  
- [ ] Inventory / availability  
- [ ] Layout object mega-library / 3D  
- [ ] Quotation entity  
- [ ] Multi-company onboarding  

---

## Appendix — Evidence map (key files)

| Area | Files |
|---|---|
| Nav | `app/Support/NavPanel.php`, `resources/views/layouts/partials/app-*.blade.php` |
| Dashboard | `app/Livewire/Dashboard.php`, `PortfolioAdvisor`, (orphan) `CommandCenterService` |
| Auth | `LoginController`, `User::ROLES`, `AppServiceProvider` gates, `EventPolicy` |
| Hub | `Event::HUB_MODULES`, `app/Livewire/Hub/*` |
| Layout | `RoomLayoutBuilder`, `EventRoom`, `RoomLayoutPdfController` |
| Equipment | `Requirement`, room `requirements`, `RoomEquipmentPdfController` |
| Costing | `EventBudgetItem`, `BudgetSync`, `BudgetTab`, `PricingTab`, `ServiceItem` |
| Commercial | `CrmPipeline`, `DealPipeline`, `Proposal`, `ContractTab`, `InvoicesLedger` |
| Prior audits | `docs/09`, `docs/10`, `docs/07`, `docs/02`, `docs/platform.md` |

---

*End of audit. Companion canvas: interactive scores + checklist in Cursor.*
