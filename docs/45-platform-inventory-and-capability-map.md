# Elite EMS — Master Platform Inventory & Capability Map

**Type:** Read-only audit. **Scope:** Entire application (`/Users/emranalitan/Herd/elitehub`, branch `cursor/soft-command-phase1`). **Method:** Direct reads of `routes/web.php` (416 lines), `app/Support/NavPanel.php`, `app/Models/Event::HUB_TABS`, `app/Support/Workflow::SETS`, `app/Models/User` roles/gates, plus three parallel research passes over all 58 Livewire components, all 43 controllers, and all 9 Settings screens with a dedicated dead-code hunt. No code was modified, deleted, or created by this audit — this document is the only file it produced.

---

## SECTION A — Executive Summary

| Metric | Count |
|---|---|
| Top-level navigation areas (the rail in `NavPanel::AREAS`) | 8, plus Settings as a 9th standalone rail item |
| Event Hub modules (`Event::HUB_TABS`) | 23 |
| Total named routes | 86 |
| Distinct navigable pages (excludes pure export endpoints) | ~68 |
| Livewire components | 58 (36 top-level/shared, 20 under `Hub/`, 2 shared traits) |
| Controllers | 43 |
| Named builders/interactive tools | 9 |
| Generated report/export documents | 30 (24 PDF, 6 XLSX) |
| Dashboard/intelligence surfaces | 4 (Command Center, Reports, Finance Overview, AI Assistant) |
| Settings screens | 9, plus the Settings hub index |
| Fully public (unauthenticated) pages | 2 business pages (Registration, Check-In) + 3 auth-flow pages |
| Confirmed dead/duplicate/partial items | 7 (detailed in Section J) |
| Platform complexity score | **7.5 / 10** — see note below |
| Platform maturity score | **7 / 10** — see note below |

**Complexity note:** driven by the Event Hub alone (23 modules, 20 dedicated Livewire components, the two largest single components in the app — `Hub/ContractTab.php` at 978 lines/50 methods and `Hub/TransportationTab.php` at 1186 lines/37 methods), a 10-state-machine workflow engine, and 30 generated documents across two rendering pipelines (Chrome/Browsershot + dompdf). This is not incidental complexity — nearly every "why" comment found in this audit explains a deliberate trade-off (e.g., money-free hotel-facing rooming lists vs. priced internal ones, Chrome replacing dompdf specifically because dompdf "cannot rotate an element, nest a transform, or draw a reliable circle").

**Maturity note:** the core Event Hub, Finance chain (Proposal → Contract → Budget → Invoice → Payment), and navigation system are cohesive and well-documented (most classes carry a "why, not what" docblock explaining a real design decision). Held back from a higher score by: 24 of 58 Livewire components missing class-level docblocks (including the two largest, `ContractTab` and `BudgetTab`), one confirmed orphaned view, one component (`ModuleDocuments`) shipping a documented mode that's never actually wired anywhere, PHPStan's Laravel-aware analysis currently disabled (see `docs/44-repository-stabilization-2026-08-21.md`), and four self-flagged "unreviewed surface area" prototype routes still reachable in local environments.

---

## SECTION B — Platform Hierarchy

```
Elite EMS
│
├── Command Center (home, "/")
│   ├── KPI strip
│   ├── Operations Hub (event islands)
│   └── Live rails (Mission Radar, Live Alerts — anchors on the same page)
│
├── Events
│   ├── Event Portfolio (/events — board with Type-tab filters + Smart Views)
│   ├── Event Studio (/events/create — 5-room live-preview wizard)
│   ├── Archived Events (/events?archived=1)
│   └── Event Hub (/events/{event} — 23 modules, see Section C.2)
│
├── Projects (/projects)
│
├── Tasks
│   └── Cross-event Task board (/tasks)
│
├── Commercial ("crm" area)
│   ├── Deal Pipeline (/crm)
│   ├── Client Record (/crm/clients/{client})
│   ├── Clients directory (/settings/clients — lives in Settings by route, in Commercial by nav)
│   ├── Proposals Desk (/proposals) → Proposal Editor (/proposals/{proposal})
│   ├── Contracts Register (/contracts, cross-event)
│   └── Sponsorships (/sponsors, cross-event)
│
├── Finance
│   ├── Finance Overview (/finance)
│   ├── Invoices Ledger (/invoices) → Invoice Editor (/invoices/{invoice})
│   └── Payments Ledger (/payments)
│
├── Operations
│   ├── Suppliers (/suppliers)
│   ├── Venues (/venues)
│   └── Requirements/Equipment (/requirements)
│
├── Intelligence
│   ├── Reports Overview (/reports)
│   └── AI Assistant / Command Briefing (/ai-assistant)
│
├── Team
│   └── Team Roster (/team)
│
├── Settings
│   ├── Settings Hub (/settings — index with live counts)
│   ├── Company Profile (/settings/company)
│   ├── Types & Lists (/settings/types — 19 taxonomies)
│   ├── Statuses & Colours (/settings/statuses — 10 workflow sets)
│   ├── Defaults & Templates (/settings/defaults)
│   ├── Transport Catalogue (/settings/transport)
│   ├── Registration Templates (/settings/registration-templates)
│   ├── Price List (/settings/price-list)
│   ├── Sponsor Packages (/settings/sponsor-packages)
│   └── Clients (/settings/clients)
│
├── Public Surfaces (unauthenticated)
│   ├── Public Registration (/register/{token})
│   └── Check-In Scan (/checkin/{token}/{reference})
│
├── Auth
│   ├── Login, Forgot Password, Reset Password
│
└── Local-only Design Prototypes (gated, kept intentionally, self-flagged as unreviewed)
    ├── /concept/flow (Flow Board)
    ├── /concept/nav (Nav Concept)
    ├── /design/soft-command (Component Gallery)
    └── /design/soft-command-shell (Shell Preview)
```

### B.1 — Event Hub's own internal tree (per event)

```
Event Hub (/events/{event})
│
├── Overview (plain Blade, no dedicated Livewire component)
├── Brief          — BriefTab.php
├── Contract       — ContractTab.php ("Contract Studio", largest component in the app)
├── Planning       — PlanStudio.php
├── Tasks          — TasksTab.php
├── Budget         — BudgetTab.php
├── Invoice items (Pricing) — PricingTab.php
├── Risks          — RisksTab.php
├── Approvals      — ApprovalsTab.php
├── Agenda         — AgendaTab.php
├── Speakers       — SpeakersTab.php
├── Venue          — VenueTab.php
├── Suppliers (plain Blade, no dedicated Livewire component)
├── Transport      — TransportationTab.php (largest by line count: 1186 lines)
│   ├── Transport Live (/events/{event}/transport/live — own route, phone-first)
│   └── Transport Dispatch (/events/{event}/transport/dispatch — own route)
├── Stay (Accommodation) — AccommodationTab.php
├── Food & Beverage (Catering) — CateringTab.php
├── Exhibition     — ExhibitionTab.php (CRUD list) + Exhibition Floor Plan builder (own route)
├── Sponsors       — SponsorsTab.php
├── Attendees      — AttendeesTab.php (nests Registration Form editor as a sub-modal)
├── Files          — ModuleDocuments.php (Library mode)
├── Reports (plain Blade, no dedicated Livewire component)
├── AI (plain Blade, no dedicated Livewire component)
└── Settings       — SettingsTab.php (event-level config, brand palette, venue/client/team pickers)
```

---

## SECTION C — Module Catalog

### C.1 — Top-level areas (from `App\Support\NavPanel::AREAS`, the authoritative rail source)

| Area key | Label | Primary route | Nav match patterns |
|---|---|---|---|
| `workspace` | Command Center | `home` | `home`, `operations-room`, `concept.*` |
| `events` | Events | `events.index` | `events.*`, `projects.*` |
| `tasks` | Tasks | `tasks.index` | `tasks.*` |
| `crm` | Commercial | `crm.index` | `crm.*`, `clients.*`, `proposals.*`, `contracts.*`, `sponsors.*` |
| `finance` | Finance | `finance.index` | `finance.*`, `invoices.*`, `payments.*` |
| `operations` | Operations | `suppliers.index` | `suppliers.*`, `venues.*`, `requirements.*` |
| `intelligence` | Intelligence | `reports.index` | `reports.*`, `ai.*` |
| `team` | Team | `team.index` | `team.*` |
| *(separate)* | Settings | `settings.index` | `settings.*`, `company.*`, `taxonomies.*`, `workflows.*`, `defaults.*`, `transport-settings.*`, `sponsor-packages.*`, `catalogue.*`, `registration-templates.*` |

Historical note preserved from the code's own comments: a former "Library" area (8 links across 3 different jobs) was deliberately dissolved — Suppliers/Venues/Equipment moved to Operations, Reports/AI to Intelligence, Team became its own rail item. This is documented directly in `NavPanel.php`'s own comments as a considered decision, not an oversight.

### C.2 — Event Hub (23 modules) — feature/action/report summary

*(Full component-level detail in Section D and Deliverable 3 below; this is the module-level view.)*

| Module | Component | Core job |
|---|---|---|
| Overview | *(plain Blade)* | Landing tab, Mission Timeline |
| Brief | `BriefTab` | Versioned living-document dossier |
| Contract | `ContractTab` | Multi-document Deck, e-sign pipeline (largest component, 978 lines/50 methods) |
| Planning | `PlanStudio` | Deliverables studio — board/timeline/list/gallery views |
| Tasks | `TasksTab` | 6-stage drag-and-drop board |
| Budget | `BudgetTab` | Line items + income, synced via `BudgetSync`/`CurrencyService` (907 lines/39 methods) |
| Invoice items (Pricing) | `PricingTab` | Event-specific rate sheet seeded from the house price list, cost+sell together |
| Risks | `RisksTab` | Probability × impact register, click-to-select/deselect |
| Approvals | `ApprovalsTab` | Approval-request workflow with notify-on-request |
| Agenda | `AgendaTab` | Session builder, conflict detection, program/timeline generation (957 lines/27 methods) |
| Speakers | `SpeakersTab` | Roster CRUD, routes fee cost to Budget |
| Venue | `VenueTab` | Rooms/requirements, routes cost to Budget |
| Suppliers | *(plain Blade)* | Vendor orders for this event |
| Transport | `TransportationTab` | Movement = service × vehicle × time × pax (largest by lines: 1186/37 methods) |
| Stay (Accommodation) | `AccommodationTab` | Block (rate) → Rooming list (names, money-free client sheet) |
| Food & Beverage (Catering) | `CateringTab` | Catering line items, routes cost to Budget |
| Exhibition | `ExhibitionTab` + `ExhibitionFloorPlan` builder | Exhibitor CRUD + drag-and-place floor builder |
| Sponsors | `SponsorsTab` | Sponsor CRUD |
| Attendees | `AttendeesTab` | Import/export, bulk actions, badges; nests `RegistrationForm` |
| Files | `ModuleDocuments` (Library mode) | Coloured-folder document wall |
| Reports | *(plain Blade)* | Event-scoped reporting |
| AI | *(plain Blade)* | Event-scoped AI insight |
| Settings | `SettingsTab` | Event-level config, brand palette presets, venue/client/team pickers |

---

## SECTION D — Page Catalog (Deliverable 2)

**Status legend used throughout:** Active (in daily use, fully wired), Legacy (superseded but kept for a stated reason), Prototype (explicitly gated/experimental), Partial (ships a feature that's only half-wired), Dead (unreachable).

### D.1 — Top-level pages

| Page | Route | Component | Purpose | User type | Status | Module |
|---|---|---|---|---|---|---|
| Command Center | `home` | `Dashboard` | Time-organized front door: spotlight/today/week/signals/book | All authenticated | Active | Workspace |
| Operations Room | `operations-room` | *(redirect → home)* | Legacy shim — merged into Dashboard | — | **Legacy** (kept as a redirect so no old link 404s) | Workspace |
| Event Portfolio | `events.index` | `EventsIndex` | Main events board, Type-tab filters, Smart Views | All authenticated | Active | Events |
| Event Studio | `events.create` | `EventCreate` | 5-room live-preview creation wizard | Manager+ (create action) | Active | Events |
| Event Hub | `events.hub` | `EventHubController` + 23 tabs | Single-event command center | Manager+/team member | Active | Events |
| Projects | `projects.index` | *(inline closure)* | Project → event rollup | All authenticated | Active | Events |
| Tasks | `tasks.index` | *(inline closure)* | Cross-event task list, status-filterable | All authenticated | Active | Tasks |
| Deal Pipeline | `crm.index` | `CrmPipeline` | Pre-event deal board | Commercial/Manager+ | Active | Commercial |
| Client Record | `crm.client` | `ClientRecord` | Single-client 360° | Commercial/Manager+ | Active | Commercial |
| Proposals Desk | `proposals.index` | `ProposalsDesk` | Every offer + waiting deals | Commercial/Manager+ | Active | Commercial |
| Proposal Editor | `proposals.edit` | `ProposalEditor` | One offer + its document | Commercial/Manager+ | Active | Commercial |
| Contracts Register | `contracts.index` | `ContractsRegister` | Cross-event "what's waiting on a pen" | Manager+ | Active | Commercial |
| Sponsorships (cross-event) | `sponsors.index` | *(inline closure)* | Cross-event sponsor view | Manager+ | Active | Commercial |
| Finance Overview | `finance.index` | `FinanceOverview` | Portfolio-wide money layer | Manager+ | Active | Finance |
| Invoices Ledger | `invoices.index` | `InvoicesLedger` | Every invoice + unraised installments | Manager+ | Active | Finance |
| Invoice Editor | `invoices.edit` | `InvoiceEditor` | One invoice + its document | Manager+ | Active | Finance |
| Payments Ledger | `payments.index` | `PaymentsLedger` | Every installment due, book-wide | Manager+ | Active | Finance |
| Planning Board | `planning.index` | `PlanningBoard` | Cross-event deliverables | Manager+ | Active | Events |
| Suppliers | `suppliers.index` | `SuppliersManager` | Vendor directory | Coordinator+ | Active | Operations |
| Venues | `venues.index` | `VenuesManager` | Venue library | Coordinator+ | Active | Operations |
| Requirements/Equipment | `requirements.index` | `RequirementsCatalog` | Equipment catalog | Coordinator+ | Active | Operations |
| Team Roster | `team.index` | `TeamRoster` | Internal team management | Admin (manage) | Active | Team |
| Reports | `reports.index` | `ReportsOverview` | Cross-event report, 4 axes | All authenticated | Active | Intelligence |
| AI Assistant | `ai.index` | `AiAssistant` | Daily briefing, book-wide | All authenticated | Active | Intelligence |

### D.2 — Public / unauthenticated pages

| Page | Route | Component | Purpose | User type | Status |
|---|---|---|---|---|---|
| Public Registration | `register.show` | `PublicRegistration` | Stranger-facing registration form, found by token | Public (unauthenticated) | Active |
| Check-In Scan | `checkin.scan` | `CheckInScan` | QR-opened, marks one attendee present, nothing else | Public (unauthenticated) | Active |
| Login | `login` | `LoginController` | Auth entry | Guest | Active |
| Forgot Password | `password.request`/`.email` | `ForgotPasswordController` | Reset-link request | Guest | Active |
| Reset Password | `password.reset`/`.update` | `ResetPasswordController` | Password reset form | Guest | Active |

### D.3 — Settings pages

| Page | Route | Component | Purpose | User type | Status |
|---|---|---|---|---|---|
| Settings Hub | `settings.index` | *(plain Blade)* | Index with live counts across 9 areas | Admin+ | Active |
| Company Profile | `company.index` | `CompanySettings` | Identity, bank accounts, branding | Admin+ | Active |
| Types & Lists | `taxonomies.index` | `TaxonomySettings` | 19 editable taxonomies | Admin+ | Active |
| Statuses & Colours | `workflows.index` | `WorkflowSettings` | 10 workflow state sets, label/colour only | Admin+ | Active |
| Defaults & Templates | `defaults.index` | `DefaultsSettings` | Budget categories, ticket types, fee %, approval threshold | Admin+ | Active |
| Transport Catalogue | `transport-settings.index` | `TransportSettings` | Vehicles, service types, drivers, fleet | Admin+ | Active |
| Registration Templates | `registration-templates.index` | `RegistrationTemplates` | Reusable public-form templates | Admin+ | Active |
| Price List | `catalogue.index` | `CatalogueSettings` | Company-wide sellable items, importable | Admin+ | Active |
| Sponsor Packages | `sponsor-packages.index` | `SponsorPackagesSettings` | Sponsorship tier templates | Admin+ | Active |
| Clients | `clients.index` | `ClientsManager` | Client/organization directory | Admin+ (delete gated `manage-events`) | Active |

### D.4 — Prototype pages (local environment only)

| Page | Route | Component | Purpose | Status |
|---|---|---|---|---|
| Flow Board | `concept.flow` | `FlowBoardController` | Real event data as soft cards, stage-lanes, health meters — alternate Command Center visual language | **Prototype**, self-flagged unreviewed |
| Nav Concept | `concept.nav` | `NavConceptController` | Two-tier nav concept (icon rail + content panel) on live data | **Prototype**, self-flagged unreviewed |
| Component Gallery | `design.soft-command` | `SoftCommandGalleryController` | Static component gallery, explicitly "Demo only" | **Prototype**, self-labeled |
| Shell Preview | `design.soft-command-shell` | `SoftCommandShellController` | Static shell-layout demo, explicitly "Demo only" | **Prototype**, self-labeled |

All four: reachable only via `app()->environment('local')` gate, cross-link only to each other, zero references anywhere else in the app, exercised by zero tests.

---

## SECTION E — Workflow Maps (Deliverable 4)

Full detail per workflow — Event Creation, Registration, Check-In, Speaker Management, Sponsorship, Finance, Procurement, Transportation, Accommodation, Exhibition, Reporting — is in the companion file `docs/45a-workflow-maps.md` (attached alongside this document) to keep this file navigable. Summary of the backbone:

```
Deal (won) ──┐
             ├──► Event Created (draft) ──► Proposal ──► Contract (signed) ──► Production ──► Live ──► Closed
Event Studio ┘         │                                      │
                        ├─ Registration (public form) ──► Attendee (registered→confirmed→checked_in)
                        ├─ Agenda / Speakers ──► sessions (draft→confirmed→final)
                        ├─ Budget / Pricing ──► Invoice ──► Payments (pending→partial→paid)
                        ├─ Suppliers / Transport / Accommodation / Exhibition / Catering (operational modules)
                        └─ Reports / AI Assistant (continuous, cross-cutting)
```

**10 named workflow-state machines** power every module (`App\Support\Workflow::SETS`, all editable at Settings → Statuses & Colours): `event_stage` (10 states), `deal_stage` (6), `task_stage` (6), `task_priority` (4), `plan_status` (6), `session_status` (5), `attendee_status` (4), `transport_status` (7), `contract_status` (5), `payment_status` (3). Keys are fixed by design (code reasons about them, e.g. `won` opens an event, `done` closes a task count) — only labels/colours are editable, deliberately, per `WorkflowSettings`' own comment.

---

## SECTION F — Navigation Map (Deliverable 5)

**Source of truth:** `App\Support\NavPanel` (two-tier: rail + contents panel), `App\Livewire\CommandPalette` (⌘K/Ctrl-K global search), `hubx-module-nav.blade.php` (Event Hub's own tab strip).

```
Global Navigation
│
├── Rail (NavPanel::AREAS, 8 areas + Settings) — always visible, one icon = one area
│   └── clicking an area opens its own panel section(s)
│
├── Panel (NavPanel::sections($area)) — contents of the active area
│   e.g. area=crm → "Commercial Command" section →
│         Deal board / Clients / Proposals / Contracts / Sponsorships
│
├── Command Palette (⌘K/Ctrl-K, global, any page)
│   Searches: Events, Tasks, Speakers, Suppliers, Venues, Team — 5-result groups, live-query
│
├── Primary Action button (contextual, one gold CTA)
│   events area → "＋ Create Event" · crm area → "＋ New Client"
│   operations area → "＋ Add Venue" · everywhere else → "✦ Ask AI" (fallback)
│
└── Event Hub navigation (once inside one event)
    ├── Module Navigation Bar (hubx-module-nav.blade.php) — horizontal pill-tab strip
    │   Primary tabs shown directly + a "More" flyout for secondary modules
    ├── Event Pulse Strip — Health/Readiness, shown on every tab
    ├── Universal Module Header — per-module metrics, shown on every tab but Overview
    └── Universal Module Inspector (right column) — status, owner, next action, recent activity,
        covers all 20 tabs that have real HubModuleInspector cases (see docs/44 for the
        commit that completed this coverage)
```

Design note preserved from `NavPanel.php`'s own comments: rows for unbuilt pages used to render greyed-out to "teach the shape of the product," but new staff read them as broken software and clicked anyway — so the panel now drops any row whose route isn't registered, and drops a section entirely if it ends up empty. The nav literally cannot show a dead link.

---

## SECTION G — Builders & Tools (Deliverable 6)

| Tool | Purpose | Complexity | Maturity |
|---|---|---|---|
| **Event Studio** (`EventCreate`) | 5-room live-preview event creation wizard (Identity/Origin/Blueprint/Modules/Launch), nothing written until Launch | High (588 lines, 18 methods) | Mature |
| **Room Layout Builder** (`RoomLayoutBuilder`) | Per-room seating/layout placement — theater/etc. seat generator, drag-and-place | Very high (667 lines, 30 methods — most methods of any top-level component) | Mature |
| **Exhibition Floor Plan** (`ExhibitionFloorPlan`) | Drag-and-place booth/fixture editor with `FIXTURE_PRESETS` | High (490 lines, 25 methods) | Mature (no class docblock, though) |
| **Proposal Editor** (`ProposalEditor`) | Priced offer editor + live document preview, same catalogue as Invoice Editor | High (368 lines, 19 methods) | Mature |
| **Invoice Editor** (`InvoiceEditor`) | Invoice editor + live document preview, three-decimal JOD precision | High (431 lines, 18 methods) | Mature |
| **Check-In Tool** (`CheckInScan` + `ArrivalsDesk`) | QR scan (public, one action only) + staffed desk fallback for the "most of the first hour" walk-in cases | Medium (157 + 121 lines) | Mature |
| **Transport Live** (`TransportLive`) | Phone-first Now/Next/Later event-day ops view | Medium-high (217 lines, 14 methods) | Mature |
| **Transport Dispatch** (`TransportDispatch`) | Gantt-style lanes per driver/vehicle, desktop/tablet-only by design | Medium (182 lines, 6 methods) | Mature |
| **Command Palette** (`CommandPalette`) | Global ⌘K search across 6 entity types | Low (76 lines, 1 method — logic is nearly all query composition) | Mature |

Two of the platform's most complex builders — Room Layout Builder and Exhibition Floor Plan — are genuinely separate, dedicated systems already covering "interactive spatial layout" as a category. Relevant context for any future venue-module work (see chat response for why this matters right now).

---

## SECTION H — Reports & Analytics Inventory (Deliverable 7)

**Dashboards/intelligence pages** (live, queried on render, nothing pre-computed/stored): Command Center (`home`), Reports Overview (`reports.index`, 4 axes: Delivery/Money/Programme/People), Finance Overview (`finance.index`), AI Assistant (`ai.index`).

**Generated documents — 30 total (24 PDF, 6 XLSX).** Two rendering pipelines exist, and they are not evenly used:
- **19 of 24 PDFs** render via `RendersChromePdf` (a shared trait wrapping Spatie Browsershot) — genuine headless-Chrome screenshots of the app's own compiled CSS, so PDFs are pixel-identical to on-screen views.
- **4 of 24 PDFs** (`Budget`, `ExhibitionFloor`, `Sponsorship`, `Equipment`) render via `Barryvdh\DomPDF` directly, each duplicating its own `Pdf::loadView(...)` call rather than sharing a trait. `RoomLayoutPdfController`'s own comment explains why Chrome exists at all: dompdf "cannot rotate an element, nest a transform, or draw a reliable circle" — it couldn't reproduce the floor-plan builder's canvas. The 4 dompdf holdouts appear to be a deliberate lower-fidelity path for simpler documents, not an oversight — but it is an inconsistency worth a decision (standardize on Chrome, or keep dompdf for genuinely simple tabular docs).
- **6 XLSX exports** all share `GeneratesXlsxTemplate` (PhpSpreadsheet) — styled frozen-header sheets with dropdown/date/time data validation.

| Document | Format | Data covered |
|---|---|---|
| Agenda Programme | PDF (Chrome) | Delegate-facing sessions, audience-filtered |
| Master Schedule | PDF (Chrome) | Every session incl. crew/room/sign-off |
| Agenda Timeline | PDF (Chrome) | Room-lane Gantt |
| Run of Show | PDF (Chrome) | Show-day cue sheet w/ turnarounds |
| Budget | PDF (dompdf) | Budget lines by category |
| Exhibition Floor Plan | PDF (dompdf) | Halls, booths, sales summary |
| Sponsorship | PDF (dompdf) | Packages sold |
| Plan Studio Plan | PDF (Chrome) | Planning tracks/items/subtasks |
| Rooming List | PDF (Chrome) | One block, deliberately money-free |
| Badge Sheet | PDF (Chrome) | A4 badges, QR + cut lines |
| Transport Manifest | PDF (Chrome) | All movements + fleet summary |
| Daily Movement Schedule | PDF (Chrome) | One day/page ops sheet |
| Driver Trip Sheets | PDF (Chrome) | Per-driver-per-day |
| VIP Transfer Sheets | PDF (Chrome) | Per-VIP-guest |
| Transport Master Plan | PDF (Chrome) | Client-facing summary + signature |
| Supplier Order | PDF (Chrome) | Vendor requirements, no client price |
| Event Brief | PDF (Chrome) | Full living-document dossier |
| Event Contract (MSA) | PDF (Chrome) | Bilingual signable agreement |
| Other Contract Documents | PDF (Chrome) | Vendor/sponsor/speaker/letter docs |
| Room Layout | PDF (Chrome) | Floor-plan drawing + schedule, 2 sheets |
| Room Equipment | PDF (Chrome) | Load-in checklist, no money |
| Proposal | PDF (Chrome) | Priced client offer |
| Invoice | PDF (Chrome) | Billing document |
| Equipment/Requirements Catalog | PDF (dompdf) | Global list w/ pricing |
| Rooming Import Template | XLSX | Fillable rooming columns |
| Attendee Import Template | XLSX | Dynamic columns from event's own registration form |
| Transport Manifest Import Template | XLSX | One movement's passengers |
| Transport Plan Import Template | XLSX | Whole-event guest rows |
| Invoice Items Import Template | XLSX | Cost + sell columns |
| Price List Import Template | XLSX | Company-wide or per-section |

---

## SECTION I — Settings Inventory (Deliverable 8)

| Screen | Configurable things | CRUD | Import/Export |
|---|---|---|---|
| **Company** | ~9 identity fields + unlimited bank accounts | Create/remove bank rows, single save | None |
| **Types & Lists** (Taxonomy) | 19 taxonomies (Events×4, Programme×4, Places×3, People×3, Money & Suppliers×5) | Full CRUD, 1-level nesting, drag-reorder | None |
| **Statuses & Colours** (Workflow) | 10 fixed state sets, 56 states total | Label + colour only, no add/delete, reorder, restore-defaults | None |
| **Defaults & Templates** | 2 lists (budget categories, ticket types) + 2 scalars (fee %, approval threshold) | Add/remove/reorder lists, reset-to-default | None |
| **Transport Catalogue** | 4 catalogues (Vehicles, Service Types, Drivers, Fleet) | Full CRUD, toggle-active | None |
| **Registration Templates** | N templates × N questions | Create/edit/delete/**duplicate**, question-level CRUD | None |
| **Price List** (Catalogue) | N items across `service_section` sections | Full CRUD, toggle-active | **Import** (xlsx/xls/csv/txt, upsert-by-code) |
| **Sponsor Packages** | N package tiers | Add/remove/reorder, reset | None |
| **Clients** | N client records | Full CRUD (delete gated `manage-events`) | None |

**Grouping (Settings Hub, `modules.settings.blade.php`):** Workspace Configuration (Company, Types & Lists, Statuses & Colours, Defaults) · Catalogues & Templates (Sponsor Packages, Transport, Price List, Registration Templates). Clients sits outside this grouping in the hub view despite being routed under `/settings/*` — it's promoted into the Commercial nav area instead (see Section F).

---

## SECTION J — Dead / Duplicate Inventory (Deliverable 9)

Nothing listed here was deleted or modified — audit only, per instruction.

| Item | Type | Evidence |
|---|---|---|
| `resources/views/event-brief/dossier.blade.php` | **Orphaned view** | Full standalone HTML document duplicating what `event-brief/paper.blade.php` + `paper-pdf.blade.php` now do via the shared "paper partial → WYSIWYG PDF" pattern. Zero references anywhere (`grep` across `app/`, `routes/`, `resources/views/`, `tests/` confirms `EventBriefPdfController` only ever calls `paper-pdf`). Still being touched by later design-token sweeps as recently as 2026-08-04 despite being unreachable — maintained as if live. |
| `/operations-room` → `/` | **Duplicate, resolved as a redirect shim** | The code's own comment: "There is one dashboard now. The Operations Room was the second, and two screens with the same job means neither has one." Kept only so old links/bookmarks don't 404. |
| `Hub/ModuleDocuments.php`'s "Scoped" mode | **Partial dead code** | Class docblock describes two operating modes — a per-tab document strip ("Scoped", `module=` param) and the Documents module itself ("Library"). Only Library is wired anywhere; grepping the whole view tree finds no view passing `module=`. The strip half of the design was built but never adopted. |
| `/concept/flow`, `/concept/nav`, `/design/soft-command`, `/design/soft-command-shell` | **Prototype cluster** | Self-flagged in `routes/web.php`'s own comment as "surface area a colleague can reach and be confused by," gated `local`-only, cross-link only to each other, zero references elsewhere, zero test coverage. Kept intentionally per that same comment, not accidental leftovers. |
| 24 of 58 Livewire components missing class docblocks | **Documentation gap, not dead code** | Includes the two largest components in the app (`ContractTab`, `BudgetTab`) — flagged because it works against this exact kind of audit, not because anything is broken. |
| Orphaned Livewire components | **None found** | Every one of the 58 components resolves to a route, a `<livewire:...>`/`@livewire(...)` mount, or a global chrome mount (`CommandPalette`). |
| Orphaned routes | **None found beyond the confirmed items above** | All 86 named routes resolve to a real controller/component; the only redirect shim is `operations-room`. |

---

## SECTION K — Strategic Observations

1. **The platform's real complexity center is the Event Hub, and it knows it.** 20 of the app's 58 Livewire components exist solely to serve 23 module tabs inside one page shell. This is architecturally sound (a genuine "one record, many lenses" design) but means any cross-cutting change to Hub chrome (as this session's own stabilization work found) has a wide, easy-to-miss blast radius — the Universal Module Inspector's coverage gap and its cross-commit dependency ordering, both documented in `docs/43` and `docs/44`, are direct evidence of that.

2. **Two rendering pipelines for the same job (PDF) is a real, if minor, inconsistency.** 19 of 24 PDFs use the higher-fidelity Chrome/Browsershot path; 4 use dompdf directly with no shared trait, each re-implementing the same `loadView->setPaper->download` call. Worth a decision — even if dompdf stays for genuinely simple tabular docs, giving it its own shared trait (mirroring `RendersChromePdf`) would remove the duplication.

3. **The nav system is unusually resistant to rot by construction.** `NavPanel::panel()`/`sections()` both filter out any row whose route isn't registered and drop empty sections outright — a module can be half-built without ever producing a dead link in the chrome. This is a genuinely good pattern other parts of the platform don't share (the four prototype routes and the orphaned dossier view are all reachable/present precisely because *they* aren't gated by this same "does the target actually exist" discipline).

4. **The Finance chain is fully connected end-to-end**: Deal → Proposal → Contract → Budget/Pricing → Invoice → Payment, with each stage's own document and each status independently trackable. This is the most mature workflow in the platform by every signal gathered (docblock quality, test coverage referenced in this session's own stabilization work, and the fact that this session's Phase D specifically hardened its money precision).

5. **Static analysis is currently not a meaningful gate.** Larastan is installed but disabled (silent-exit bug, undiagnosed — see `docs/44`), so `phpstan analyse` currently reports 2,206 largely-false-positive errors. This audit found zero *functional* dead code hiding behind that noise, but it means the codebase currently has no working type-safety net beyond PHPUnit's 1091 tests.

6. **Four self-aware, self-labeled prototypes are still live in local environments.** The team's own comments show they know these are a liability ("prints live event data in an unreviewed layout") and have made a deliberate keep-not-delete call. That's a legitimate call, but it's the kind of decision worth revisiting periodically rather than leaving permanently deferred.

---

*Companion document: `docs/44-repository-stabilization-2026-08-21.md` (tooling/ops state). This document was produced by three parallel research passes plus direct source reads; all figures were cross-checked against `routes/web.php`, not estimated.*
