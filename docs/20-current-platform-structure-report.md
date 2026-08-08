# Elite Business Hub — Current Platform Structure Report (A–Z)

**Product name in UI:** Elite Business Hub  
**Codebase inspected:** `/Users/emranalitan/Herd/elitehub` (Laravel 13 + Livewire 3 + Blade + Tailwind)  
**Inspection date:** 2026-08-08  
**Default branch inspected:** `main` (local, after sync)  
**Purpose of this document:** Factual inventory of what exists today, for an external consultant. **No redesign. No rename proposals. No improvement recommendations.**

**Evidence sources:** `routes/web.php`, `app/Support/NavPanel.php`, `app/Models/*`, `app/Livewire/*`, `app/Policies/EventPolicy.php`, `app/Providers/AppServiceProvider.php`, `config/modules.php`, hub views under `resources/views/events/hub/`, `php artisan route:list` (99 application routes excluding framework internals).

**Legend for status:**  
`complete` = implemented UI + persistence with meaningful actions · `partial` = usable but known gaps · `placeholder` = thin/list-only or empty shell · `orphan` = file exists but not the live route · `not found` = searched, absent from codebase.

---

## How the platform works (top → bottom)

1. **Auth** — Guest routes for login / forgot-password / reset-password. Authenticated users hit `/` (`Dashboard`).
2. **Tenancy** — Middleware `ResolveTenant` binds `Tenancy` from the user’s `tenant_id`. Models use `BelongsToTenant`. Roles for authorization are read from `workspace_user.role` when present, else `users.role` (`User::isAtLeast()`).
3. **Chrome** — Layout `components/layouts/app.blade.php`: left **rail** (`app-rail`) + **panel** (`app-panel`) + **tools bar** (`app-tools`: breadcrumbs, ⌘K `CommandPalette`, event radar, primary action, alert bell → `home#live-alerts`).
4. **Portfolio layer** — Cross-event screens: Events book, CRM, Planning board, Proposals, Contracts, Invoices, Payments, Finance, Reports, Library directories, Settings.
5. **Event Hub** — `/events/{event}?tab=…` via `EventHubController@show`, gated by `EventPolicy::view`. Body is `@includeIf('events.hub.'.$tab)`. Most tabs mount a Livewire Hub component. Modules can be toggled per event (`enabled_modules` JSON).
6. **Commercial spine (as coded)** — Deal → Proposal (optional) → Accept/Win → `DealPipeline::win()` creates Event → Brief / Contract / Budget / ops modules → Archive (or stage → closed).
7. **Public surfaces** — Registration `/register/{token}`, Check-in `/checkin/{token}/{reference}` (no auth).

---

# PART 1 — FULL PLATFORM STRUCTURE (MODULES)

---

### Module: Command Center / Dashboard

- **Purpose:** Time-oriented home: spotlight event, 7-day window, portfolio signals via `PortfolioAdvisor`.
- **Pages:** `/` Dashboard Livewire view.
- **Routes:** `home` → `App\Livewire\Dashboard`; `operations-room` → redirect to `/`.
- **Components:** `Dashboard.php`, `resources/views/livewire/dashboard.blade.php`; services `EventHealthService`, `EventCommandHeader`, `PortfolioAdvisor`, `PortfolioFinance`.
- **Database models/tables:** Reads `events` and related hub tables; no dedicated dashboard table.
- **Controllers/services:** As above.
- **NAV links:** Rail “Command Center”; panel “Dashboard”; settings area default includes Dashboard.
- **Visible roles:** All authenticated (`auth` middleware). Content not role-weighted in code (same dashboard for all ranks).
- **Current status:** complete (single dashboard; Operations Room retired to redirect).
- **Completed features:** Spotlight, signals, 7-day window, health relations.
- **Incomplete features:** Role-weighted widgets (documented as desired in `docs/18`/`19`, not implemented as separate lenses).
- **Placeholder areas:** None for this module.
- **Connected modules:** Events, AI Assistant (signals href), Finance figures via PortfolioFinance.
- **Disconnected areas:** Alert bell anchors `#live-alerts` on home — section id must exist in dashboard markup for that jump to work.
- **Notes:** Concept prototypes at `/concept/flow` and `/concept/nav` are separate; not in primary nav.

---

### Module: Events (portfolio)

- **Purpose:** Book of events; create; open hub; archive.
- **Pages:** Events index; Event Studio create; Event Hub.
- **Routes:** `events.index`, `events.create`, `events.hub` (+ many event child routes under `can:view,event`).
- **Components:** `EventsIndex`, `EventCreate`, `EventHubController`, hub blades.
- **Database:** `events` (+ pivots/relations listed under Event Hub modules).
- **NAV:** Rail Events; panel All events / New event.
- **Visible roles:** List: all auth. Hub/PDF: `EventPolicy::view` (manager+ OR on `event_team_members`; coordinator+ floor).
- **Status:** complete.
- **Completed:** CRUD-ish create, board/list, archive, favorites (model), stage machine.
- **Incomplete / gaps:** Event Studio create path does **not** create a Deal link (parallel to CRM win).
- **Connected:** CRM (via Deal), all hub modules, Projects (optional FK).

---

### Module: CRM / Clients / Pipeline

- **Purpose:** Pre-event sales: deals board, clients, contacts, activities.
- **Pages:** `/crm` pipeline; `/crm/clients/{client}`; `/settings/clients` directory.
- **Routes:** `crm.index`, `crm.client`, `clients.index`.
- **Components:** `CrmPipeline`, `ClientRecord`, `ClientsManager`.
- **Database:** `clients`, `contacts`, `deals`, `deal_activities`.
- **NAV:** Rail CRM; panel area sections Deal board + Clients; Settings also lists Clients.
- **Gates:** `manage-events` for win/move; `manage-contract` for draft proposal; `write` for deal/activity edits.
- **Status:** complete / partial on handoffs.
- **Completed:** Stages enquiry→lost/won; soft win gate without accepted proposal; Draft proposal from inspector; activity log; Client 360-ish record.
- **Incomplete:** No separate “Leads” entity (Deal stages cover enquiry); no hard block on win without proposal.
- **Connected:** Proposals (`deal_id`), Events (`DealPipeline::win`), Clients.
- **Not found:** Dedicated Leads module / RFQ object.

---

### Module: Proposals

- **Purpose:** Priced offer before contract; accept wins deal and seeds budget.
- **Pages:** Desk `/proposals`; Editor `/proposals/{id}`; PDF.
- **Routes:** `proposals.index`, `proposals.edit`, `proposals.pdf`.
- **Components:** `ProposalsDesk`, `ProposalEditor`, `ProposalPdfController`.
- **Database:** `proposals`, `proposal_lines`.
- **NAV:** Panel Business → Proposals.
- **Gates:** `manage-contract`.
- **Status:** complete.
- **Completed:** Draft from deal, send, accept, decline, extend, PDF; accept → `DealPipeline::win` + `BudgetSync::syncProposal`.
- **Connected:** Deal, Client, Event, Budget.

---

### Module: Contracts

- **Purpose:** Per-event contract deck + portfolio register of agreements.
- **Pages:** Hub tab Contract; `/contracts` register; PDFs.
- **Routes:** Hub `?tab=contract`; `contracts.index`; `events.contract.pdf`, `events.contract.doc.pdf`.
- **Components:** `Hub\ContractTab`, `ContractsRegister`, PDF controllers.
- **Database:** `event_contracts`, `event_contract_payments`, `contract_signatories`.
- **NAV:** Panel Business → Contracts; hub More → Contract (not in daily primary strip).
- **Gates:** `manage-contract`.
- **Status:** complete / partial.
- **Completed:** Types (client, vendor, speaker, sponsorship, letter, acceptance); statuses draft→void; payment schedule; signatories; PDF.
- **Incomplete:** Approvals do not hard-gate contract send (soft/process only).
- **Connected:** Payments ledger, Invoices (`Invoice::fromPayment`), Event.

---

### Module: Planning / Plan Studio / Tasks

- **Purpose:** Deliverables plan (tracks/items) and execution tasks; portfolio views.
- **Pages:** Hub Planning + Tasks; `/planning` board; `/tasks` portfolio list.
- **Routes:** hub tabs; `planning.index`; `tasks.index`.
- **Components:** `Hub\PlanStudio`, `Hub\TasksTab`, `PlanningBoard`; Blade `modules/tasks.blade.php` for portfolio tasks.
- **Database:** `plan_tracks`, `plan_items`, `plan_subtasks`, `plan_item_user`, `tasks`.
- **NAV:** Panel Planning board + Tasks; rail Tasks.
- **Status:** complete (event) / partial (portfolio tasks is a paginated Blade list, not Livewire board).
- **Connected:** Brief generate can seed plan-related artefacts via `BriefGenerator` (categories/risks/packages — verify generate targets in code); Tasks link to events.

---

### Module: Event Hub — Overview

- **Purpose:** Command view for one event: health, alerts, module readiness doors, summaries.
- **Pages:** `?tab=overview` → `events/hub/overview.blade.php` (controller-driven, not Livewire).
- **Routes:** `events.hub`.
- **Services:** `EventHealthService`, `EventCommandHeader` (header next/readiness).
- **Status:** complete / partial (readiness doors landed; further UX open per docs).
- **Connected:** All enabled modules.

---

### Module: Brief

- **Purpose:** Scope lock document; approve; generate categories/risks/packages.
- **Components:** `Hub\BriefTab`; PDF `EventBriefPdfController`.
- **Database:** `event_briefs`.
- **Status:** complete.
- **Connected:** Budget categories, Risks, Sponsor packages via `BriefGenerator`.

---

### Module: Budget / Pricing (invoice items)

- **Purpose:** Cost→sell budget; sync from modules; per-event priced lines.
- **Components:** `Hub\BudgetTab`, `Hub\PricingTab`; `BudgetSync`; PDF.
- **Database:** `event_budget_items`, `event_budget_categories`, `event_budget_versions`, `event_income_items`, `event_invoice_items`.
- **NAV:** Hub primary strip includes Budget; Pricing under More.
- **Gates:** `manage-budget`.
- **Status:** complete / partial (invoice-from-budget draft optional/not fully closed-loop).
- **BudgetSync sources (auto):** room blocks, accommodation, transport, speakers, rooms, catering, event requirements; proposal lines on accept.
- **Not auto-synced:** agenda, sponsors, exhibition booths, attendees (as money lines).

---

### Module: Agenda / Speakers / Run of Show

- **Purpose:** Programme days/sessions; speakers; show-day cue sheet.
- **Components:** `Hub\AgendaTab`, `Hub\SpeakersTab`; `RunOfShowController`; PDFs (programme, master, timeline, run-of-show).
- **Database:** `event_agenda_days`, `event_agenda_sessions`, speaker pivot, `event_speakers`.
- **NAV:** Hub primary: Agenda; Speakers under More.
- **Status:** complete (rich builder + PDFs + day tools).
- **Connected:** Venue rooms, Speakers, Registration session booking.

---

### Module: Venue / Layout / Equipment (event)

- **Purpose:** Rooms, hire, requirements on rooms, metre layout builder, equipment PDFs.
- **Components:** `Hub\VenueTab`, `RoomLayoutBuilder`; PDFs layout/equipment.
- **Database:** `event_rooms` (layout JSON), requirements on rooms; catalogue `requirements`.
- **Status:** complete / partial — Layout Builder is canvas + seating, **not** warehouse inventory.
- **Connected:** BudgetSync from rooms/requirements; Agenda room assignment.

---

### Module: Transport

- **Purpose:** Movements, guest pool, manifests, Live ops, Dispatch Gantt, PDF suite.
- **Components:** `Hub\TransportationTab`, `TransportLive`, `TransportDispatch`, `TransportSettings`; many PDF/XLSX controllers.
- **Database:** `event_transport`, `event_transport_passengers`, `transport_vehicles`, `transport_drivers`, `vehicle_types`, `transport_service_types`.
- **NAV:** Hub primary Transportation; Settings catalogues Transport; Live/Dispatch linked from tab.
- **Status:** complete.
- **Connected:** Attendees (pull), BudgetSync, Suppliers.

---

### Module: Accommodation (Stay)

- **Purpose:** Hotel blocks + rooming lists.
- **Components:** `Hub\AccommodationTab`; rooming PDF/template.
- **Database:** `event_room_blocks`, `event_accommodations`.
- **Status:** complete / partial (ops depth varies).
- **Connected:** Venues, BudgetSync, Attendees.

---

### Module: Catering (Food & Beverage)

- **Purpose:** F&B occasions per event.
- **Components:** `Hub\CateringTab`.
- **Database:** `event_catering_items`.
- **Status:** complete (CRUD + statuses).
- **Connected:** Rooms, Suppliers, BudgetSync.

---

### Module: Exhibition / Sponsors

- **Purpose:** Floor/booths/exhibitors; sponsorship packages and deals.
- **Components:** `Hub\ExhibitionTab`, `ExhibitionFloorPlan`, `Hub\SponsorsTab`; sponsorship show/PDF; portfolio `/sponsors`.
- **Database:** halls, booths, exhibitors, `event_sponsors`, `event_sponsor_packages`.
- **Status:** complete / partial (exhibition fees not all in BudgetSync).
- **NAV:** Hub More; Library Sponsorships; Settings sponsor packages.

---

### Module: Attendees / Registration / Arrivals / Check-in

- **Purpose:** Registrations, form builder, badges, arrivals desk, public register + QR check-in.
- **Components:** `Hub\AttendeesTab`, `Hub\RegistrationForm`, `ArrivalsDesk`, `PublicRegistration`, `CheckInScan`, `RegistrationTemplates`.
- **Database:** `event_attendees`, `registration_fields`, `registration_templates`, session pivots.
- **Routes:** hub attendees; `events.arrivals`; public `register.show`, `checkin.scan`; badges PDF; templates.
- **Status:** complete.
- **Connected:** Agenda sessions, Transport pull, badges.

---

### Module: Approvals / Risks

- **Purpose:** Decision workflows; risk register.
- **Components:** `Hub\ApprovalsTab`, `Hub\RisksTab`.
- **Database:** `event_approvals`, `approval_steps`, `event_risks`.
- **Gates:** `decide-approvals`.
- **Status:** complete / partial (notifications for pending approvals minimal/absent as product feature).
- **Connected:** Health/header attention; alert bell counts pending approvals + open risks.

---

### Module: Documents (Files)

- **Purpose:** Event document library / folders.
- **Components:** Hub `files` tab → documents Livewire/drawer patterns (`ModuleDocuments` used across modules).
- **Database:** `event_documents`, `event_document_folders`.
- **Routes:** download/view under `can:view,event`.
- **Status:** complete / partial.

---

### Module: Reports / AI

- **Purpose:** Cross-event reports; rule-based AI briefing.
- **Components:** Hub Reports tab; `ReportsOverview`; `AiAssistant`; hub AI tab.
- **Routes:** `reports.index`, `ai.index`, hub tabs.
- **Status:** partial (rule-based, not LLM product).
- **NAV:** Library Grow section.

---

### Module: Finance / Invoices / Payments

- **Purpose:** Portfolio P&L; invoice book; installment collection view.
- **Components:** `FinanceOverview`, `InvoicesLedger`, `InvoiceEditor`, `PaymentsLedger`.
- **Database:** `invoices`, `invoice_lines`; reads contract payments.
- **NAV:** Rail Finance; panel Invoices/Payments; Finance area Profit & loss.
- **Gates:** `manage-contract` for invoice actions (as used in components).
- **Status:** complete.
- **Connected:** Contract payments → raise invoice; Event budget separately.

---

### Module: Library — Suppliers, Venues, Equipment, Projects, Team, Sponsorships

- **Purpose:** Master data directories.
- **Routes:** `suppliers.index`, `venues.index`, `requirements.index` (+ PDF), `projects.index`, `team.index`, `sponsors.index`.
- **Components:** Livewire managers for suppliers/venues/requirements/team; Projects and Sponsors are Blade list pages.
- **Status:** Suppliers/Venues/Equipment/Team: complete. Projects: **partial/thin** (list + event count). Sponsors portfolio: partial rollup.
- **Equipment note:** `Requirement` catalogue = requirements/load-in, **not** inventory/warehouse (confirmed by product docs + model usage).

---

### Module: Settings / Company / Taxonomies / Workflows / Defaults / Catalogues

- **Purpose:** Workspace configuration.
- **Pages:** Settings hub Blade; Company; Types; Statuses & Colours; Defaults; Price list; Sponsor packages; Transport settings; Registration templates; Clients (also under Settings).
- **Routes:** `settings.index`, `company.index`, `taxonomies.index`, `workflows.index`, `defaults.index`, `catalogue.index`, `sponsor-packages.index`, `transport-settings.index`, `registration-templates.index`, `clients.index`.
- **Gates:** `manage-team` for team invite/edit.
- **Status:** complete.
- **Team invite:** Creating a user sends `TeamInvite` notification with password token (`TeamRoster::save`).

---

### Module: Notifications

- **Purpose:** In-app alert bell + email invites/password resets.
- **Status:** partial.
- **Found:** Alert bell counts + link to `#live-alerts`; `TeamInvite` notification; password reset emails.
- **Not found:** General notification center, Messages module, Guests (CRM) module, in-app approval email product (may be absent beyond invite/reset).

---

### Module: Calendar

- **Not found in current codebase** as a standalone calendar module. Agenda timeline and Dashboard 7-day window provide time views.

---

### Module: Procurement / Purchase Orders

- **Not found** as a dedicated PO/procurement module. Supplier assignment and transport supplier orders (PDF) exist; no PO entity found.

---

### Module: Client Portal / Multi-tenant SaaS UX

- **Not found** as productized client portal. Public registration + check-in only. Tenancy schema exists (`tenants`, `workspaces`, `tenant_id` columns) for isolation; company switcher / billing **not found** as product UI.

---

### Module: Messages / Guests / Assets / Help

- **Not found in navigation** (removed). Routes `messages.index`, `guests.index`, `assets.index` **not registered**. Stub view `modules/stub.blade.php` **not found** (deleted). Orphan: `resources/views/modules/team.blade.php` exists but `/team` uses `TeamRoster`.

---

# PART 2 — FULL PAGE INVENTORY (summary table)

| Page name | Path | Module | Component/file | In sidebar? | Access | Status |
|---|---|---|---|---|---|---|
| Sign in | `/login` | Auth | LoginController + guest layout | No | guest | complete |
| Forgot password | `/forgot-password` | Auth | ForgotPasswordController | No | guest | complete |
| Reset / set password | `/reset-password/{token}` | Auth | ResetPasswordController | No (email link) | guest | complete |
| Dashboard | `/` | Command Center | Dashboard | Yes | auth | complete |
| Operations Room | `/operations-room` | — | Redirect → `/` | Match includes area | auth | redirect |
| Concept Flow | `/concept/flow` | Prototype | FlowBoardController | No | auth | prototype |
| Concept Nav | `/concept/nav` | Prototype | NavConceptController | No | auth | prototype |
| Events book | `/events` | Events | EventsIndex | Yes | auth | complete |
| Event Studio | `/events/create` | Events | EventCreate | Yes (New event) | auth | complete |
| Event Hub | `/events/{event}?tab=` | Event Hub | EventHubController + tab views | Via events | `can:view,event` | complete |
| Hub tabs (23 keys) | same + `tab` | per module | see HUB_TABS | Hub strip/More | same | complete/partial |
| Arrivals | `/events/{event}/arrivals` | Attendees | ArrivalsDesk | Via attendees actions | can:view,event | complete |
| Run of Show | `/events/{event}/run-of-show` | Agenda | RunOfShowController | Via Agenda | can:view,event | complete |
| Transport Live | `…/transport/live` | Transport | TransportLive | Via Transport tab | can:view,event | complete |
| Transport Dispatch | `…/transport/dispatch` | Transport | TransportDispatch | Via Transport tab | can:view,event | complete |
| Room layout | `…/rooms/{room}/layout` | Venue | RoomLayoutBuilder | Via Venue | can:view,event | complete |
| Exhibition floor | `…/exhibition-floor` | Exhibition | ExhibitionFloorPlan | Via Exhibition | can:view,event | complete |
| Sponsorship pack | `…/sponsorship` | Sponsors | SponsorshipController | Via Sponsors | can:view,event | complete |
| Many PDFs/XLSX | see Part 3 | various | Controllers | Action menus | can:view,event | complete |
| Projects | `/projects` | Projects | `modules.projects` Blade | Library | auth | partial |
| Tasks (portfolio) | `/tasks` | Tasks | `modules.tasks` Blade | Yes | auth | partial |
| Planning board | `/planning` | Planning | PlanningBoard | Yes | auth | complete |
| CRM Pipeline | `/crm` | CRM | CrmPipeline | Yes | auth | complete |
| Client record | `/crm/clients/{client}` | CRM | ClientRecord | Via CRM | auth | complete |
| Clients directory | `/settings/clients` | CRM/Settings | ClientsManager | Settings + CRM section | auth | complete |
| Proposals desk/editor | `/proposals`, `/proposals/{id}` | Proposals | Livewire | Yes | auth | complete |
| Contracts register | `/contracts` | Contracts | ContractsRegister | Yes | auth | complete |
| Invoices / editor | `/invoices`, `/invoices/{id}` | Finance | Livewire | Yes | auth | complete |
| Payments | `/payments` | Finance | PaymentsLedger | Yes | auth | complete |
| Finance P&L | `/finance` | Finance | FinanceOverview | Yes | auth | complete |
| Reports | `/reports` | Reports | ReportsOverview | Library | auth | partial |
| AI Assistant | `/ai-assistant` | AI | AiAssistant | Library | auth | partial |
| Suppliers / Venues / Equipment | `/suppliers`, `/venues`, `/requirements` | Library | Livewire | Library | auth | complete |
| Team | `/team` | Team | TeamRoster | Library + Settings | auth (`manage-team` for edits) | complete |
| Sponsors rollup | `/sponsors` | Sponsors | Blade | Library | auth | partial |
| Settings hub + children | `/settings…` | Settings | Blade + Livewire | Dock Settings | auth | complete |
| Public registration | `/register/{token}` | Attendees | PublicRegistration | No | public | complete |
| Public check-in | `/checkin/{token}/{reference}` | Attendees | CheckInScan | No | public | complete |

**Pages not in sidebar but reachable:** Event PDFs, Live/Dispatch, Arrivals, Run of Show, Layout builder, Exhibition floor, Client record, Proposal/Invoice editors, concept prototypes, public register/check-in.

**Duplication notes:** Clients appear under Settings and CRM section. Team appears under Library and Settings. Suppliers/Venues/Equipment appear under Library and Settings directories.

**Orphan view:** `resources/views/modules/team.blade.php` — **not** used by `team.index`.

---

# PART 3 — FULL ROUTE MAP

### Public (no auth)

| Route name | Path | Purpose | Status |
|---|---|---|---|
| `register.show` | `/register/{token}` | Public registration | working |
| `checkin.scan` | `/checkin/{token}/{reference}` | Badge QR check-in | working |
| `login`, `login.store` | `/login` | Auth | working |
| `password.request`, `password.email` | `/forgot-password` | Reset request | working |
| `password.reset`, `password.update` | `/reset-password…` | Set password | working |
| `logout` | POST `/logout` | Sign out | working |

### Auth — portfolio

| Route name | Path | Component | NAV label | Status |
|---|---|---|---|---|
| `home` | `/` | Dashboard | Dashboard / Command Center | working |
| `operations-room` | `/operations-room` | Redirect `/` | (area match) | working redirect |
| `concept.flow` | `/concept/flow` | FlowBoardController | — | prototype / unused in nav |
| `concept.nav` | `/concept/nav` | NavConceptController | — | prototype / unused in nav |
| `events.index` | `/events` | EventsIndex | All events | working |
| `events.create` | `/events/create` | EventCreate | New event | working |
| `projects.index` | `/projects` | Blade | Projects | working thin |
| `tasks.index` | `/tasks` | Blade | Tasks | working thin |
| `planning.index` | `/planning` | PlanningBoard | Planning board | working |
| `crm.index` | `/crm` | CrmPipeline | Deal board | working |
| `crm.client` | `/crm/clients/{client}` | ClientRecord | — | working |
| `proposals.index/edit/pdf` | `/proposals…` | Livewire + PDF | Proposals | working |
| `contracts.index` | `/contracts` | ContractsRegister | Contracts | working |
| `invoices.index/edit/pdf` | `/invoices…` | Livewire + PDF | Invoices | working |
| `payments.index` | `/payments` | PaymentsLedger | Payments | working |
| `finance.index` | `/finance` | FinanceOverview | Profit & loss | working |
| `reports.index` | `/reports` | ReportsOverview | Reports | working |
| `ai.index` | `/ai-assistant` | AiAssistant | AI Assistant | working |
| `suppliers.index` | `/suppliers` | SuppliersManager | Suppliers | working |
| `venues.index` | `/venues` | VenuesManager | Venues | working |
| `requirements.index/pdf` | `/requirements…` | Catalog + PDF | Equipment | working |
| `team.index` | `/team` | TeamRoster | Team | working |
| `sponsors.index` | `/sponsors` | Blade | Sponsorships | working |
| `settings.index` + settings.* | `/settings…` | hub + Livewire | Settings tree | working |
| `clients.index` | `/settings/clients` | ClientsManager | Clients | working |
| `catalogue.index` | `/settings/price-list` | CatalogueSettings | (Settings; not in NavPanel PANEL) | working |
| `registration-templates.index` | `/settings/registration-templates` | RegistrationTemplates | (Settings counts; check panel listing) | working |

### Auth — event-scoped (`middleware can:view,event`)

| Route name | Path | Purpose |
|---|---|---|
| `events.hub` | `/events/{event}` | Hub shell + `?tab=` |
| `events.arrivals` | `…/arrivals` | Arrivals desk |
| `events.run-of-show` (+ `.pdf`) | `…/run-of-show` | Cue sheet |
| `events.agenda.program.pdf` | `…/programme.pdf` | Programme PDF |
| `events.agenda.master.pdf` | `…/master-schedule.pdf` | Master schedule |
| `events.agenda.timeline.pdf` | `…/timeline.pdf` | Timeline PDF |
| `events.budget.pdf` | `…/budget.pdf` | Budget PDF |
| `events.brief.pdf` | `…/brief.pdf` | Brief PDF |
| `events.contract.pdf` | `…/contract.pdf` | Client contract PDF |
| `events.contract.doc.pdf` | `…/contracts/{contract}.pdf` | Other contract types |
| `events.planning.pdf` | `…/plan.pdf` | Plan Studio PDF |
| `events.exhibition-floor` (+ `.pdf`) | `…/exhibition-floor` | Floor plan |
| `events.sponsorship` (+ `.pdf`) | `…/sponsorship` | Sponsorship pack |
| `events.rooming.pdf` / `.template` | `…/rooming/{block}…` | Rooming |
| `events.attendees.template` | `…/attendees/template.xlsx` | Import template |
| `events.badges.pdf` | `…/badges.pdf` | Badge sheet |
| `events.pricing.template` | `…/invoice-items/template.xlsx` | Pricing import |
| `events.transport.*` | Live, Dispatch, PDFs, XLSX | Transport suite |
| `events.room-layout` (+ pdfs) | `…/rooms/{room}/layout…` | Layout builder |
| `events.documents.download/view` | `…/documents/{document}/…` | Files |

**Broken nav routes:** None currently registered for Messages/Guests/Assets (removed).  
**Unused / prototype:** `concept.*`.  
**Admin-only:** No separate admin subdomain; `manage-team` / higher ranks gate actions inside pages.

---

# PART 4 — NAVIGATION AND SIDEBAR STRUCTURE

### Rail (left icon strip) — order

1. Command Center (`home`)  
2. Events (`events.index`)  
3. Tasks (`tasks.index`)  
4. CRM (`crm.index`)  
5. Finance (`finance.index`)  
6. Library (`suppliers.index` landing)  

Settings is **not** on the rail; it sits in the panel **command dock**.

### Fixed panel map (always shown) — order

```
Events
  - Dashboard → home
  - All events → events.index
  - New event → events.create
Planning
  - Planning board → planning.index
  - Tasks → tasks.index
Business
  - Proposals → proposals.index
  - Contracts → contracts.index
Finance
  - Invoices → invoices.index
  - Payments → payments.index
```

When area is **not** workspace/events/tasks, **extra sections** append (CRM / Finance / Library / Settings trees — see Part 1 NavPanel inventory).

### Command dock

- Settings → `settings.index`  
- Sign out  
- Help & Support: **removed** (not found)

### Hub tab strip (per event)

**Primary (daily):** Overview, Tasks, Agenda, Venue, Transport, Budget (+ current tab if overflow).  

**More menu families:** Plan (planning, pricing, approvals, brief, contract, risks) · Programme (speakers) · Logistics (suppliers, accommodation, catering) · Partners (exhibition, sponsors) · Sell (attendees) · Library (files, reports) · System (ai, settings).

Enabled via `Event::moduleEnabled()` / `enabled_modules`.

### Tools bar

- Breadcrumbs  
- Mobile area pills (AREAS)  
- Command palette (Livewire)  
- Event radar  
- Contextual primary action (`NavPanel::primaryAction`)  
- Alert bell → `route('home')#live-alerts`  

### Navigation tree (actual)

```
Rail
├── Command Center → /
├── Events → /events
├── Tasks → /tasks
├── CRM → /crm
├── Finance → /finance
└── Library → /suppliers

Panel (fixed)
├── Events: Dashboard, All events, New event
├── Planning: Planning board, Tasks
├── Business: Proposals, Contracts
└── Finance: Invoices, Payments

Panel (when in CRM)
└── Pipeline: Deal board, Clients

Panel (when in Finance area)
└── Money: Profit & loss

Panel (when in Library)
├── Directories: Suppliers, Venues, Projects, Team
├── Catalogues: Equipment, Sponsorships
└── Grow: Reports, AI Assistant

Panel (when in Settings) + Dock Settings
├── Workspace: Company, Types & Lists, Statuses & Colours, Defaults, Team & Roles
├── Directories: Clients, Suppliers, Venues
└── Catalogues: Equipment, Sponsorship packages, Transport
(+ Settings hub page also links Price list, Registration templates via counts UI)

Event Hub (/events/{id})
├── Primary: Overview, Tasks, Agenda, Venue, Transport, Budget
└── More: Brief, Contract, Planning, Pricing, Risks, Approvals, Speakers,
          Suppliers, Stay, F&B, Exhibition, Sponsors, Attendees, Files, Reports, AI, Settings
```

### Role visibility on nav

**Not found:** Per-link role hiding in `NavPanel`. Entire chrome is shown to all authenticated users; **actions** inside pages use Gates / EventPolicy.

---

# PART 5 — FULL WORKFLOW MAP (CURRENT BEHAVIOUR ONLY)

### 1. CEO / Management workflow (as coded)

| Step | Where |
|---|---|
| Start | `/` Dashboard |
| Signals | Dashboard + AI Assistant (`PortfolioAdvisor`) |
| Event status | Events book + Hub Overview health/readiness |
| Finance overview | `/finance`, invoices, payments |
| Team | `/team` roster |
| Approvals/risks attention | Alert bell counts; hub Approvals/Risks |
| **Missing connections (factual):** No dedicated CEO home layout; no forced path from signal → decision screen beyond deep links in advisor items. |

### 2. Sales / CRM workflow

| Step | Coded path |
|---|---|
| Client create | ClientsManager / CRM |
| Deal create | CrmPipeline `newDeal` |
| Stage moves | `moveTo` / drag → `DealPipeline::moveTo` |
| Draft proposal | Inspector / Proposals desk `draftFor` / `Proposal::forDeal` |
| Soft win gate | Confirm if no accepted proposal |
| Mark won | `DealPipeline::win` → Event `stage=confirmed` → redirect hub |
| Accept proposal | `Proposal::accept` → update deal value → win → `BudgetSync::syncProposal` |
| **Parallel path:** Event Studio create — **no Deal** |
| **Missing:** Dedicated lead object; hard win gate; automatic contract from proposal. |

### 3. Proposal workflow

Create → lines/fee → send → accept/decline/extend → PDF. Accept opens event + seeds budget.  
**Missing:** Formal internal approval step on proposals (not found as gate); convert-to-contract button (contract created in hub separately).

### 4. Contract workflow

Hub Contract tab: draft/send/sign statuses; payment schedule; signatories; PDF.  
Portfolio register lists across events.  
Installments → Payments ledger → Raise invoice (`Invoice::fromPayment`).  
**Missing:** Hard approval→contract gate; e-signature provider integration (status fields only).

### 5. Event / Project workflow

Create (Studio or CRM win) → enable modules → Brief approve/generate → team (`event_team_members`) → planning/tasks → ops modules → stage transitions in Settings → archive (`archived_at`) or stage `closed`.  
Projects: thin folder linking events.  
**Missing:** Formal post-event evaluate module; Projects as full workspace.

### 6. Planning / Timeline workflow

Plan Studio tracks/items/subtasks; Tasks board stages; portfolio PlanningBoard; portfolio Tasks list.  
**Missing:** Standalone calendar sync; automatic task generation from all modules.

### 7. Operations workflow

Venue/Layout/Equipment · Agenda/Speakers · Transport (List/Live/Dispatch) · Stay · F&B · Exhibition · Suppliers (hub) · Arrivals/Check-in · Run of Show · Documents.  
**Missing:** Unified “run sheet” across all ops (Run of Show is agenda-centric); dedicated crew module.

### 8. Equipment / Layout workflow

Catalogue `Requirement` → room requirements → Layout Builder JSON → PDFs. BudgetSync from rooms/requirements.  
**Missing:** Inventory/availability/PO; layout object library as warehouse.

### 9. Finance workflow

Budget (+ versions/approvals) · Income items · Contract schedule · Invoices · Payments ledger · Finance overview · Reports. Proposal seeds budget.  
**Missing:** Full tax engine; closed-loop budget→invoice without installment; multi-currency conversion product.

### 10. Supplier / Procurement workflow

Supplier directory + hub suppliers + ratings; transport supplier order PDF.  
**Missing:** RFQ, PO, formal procurement compare workflow entities.

---

# PART 6 — ROLES AND PERMISSIONS (CODE)

### Ranks (`User::ROLES` / `ROLE_RANK`)

| Rank | Slug |
|---|---|
| 4 | `super_admin` |
| 3 | `admin` |
| 2 | `manager` |
| 1 | `coordinator` |
| 0 | `viewer` |

Effective role: `workspace_user.role` ?? `users.role`.

### Gates (`AppServiceProvider`)

| Gate | Minimum |
|---|---|
| `write` | coordinator |
| `decide-approvals` | manager |
| `manage-budget` | manager |
| `manage-contract` | manager |
| `manage-events` | manager |
| `manage-team` | admin |

### EventPolicy

| Ability | Rule |
|---|---|
| view/update | coordinator+ AND (manager+ OR on event team) |
| create | coordinator+ |
| archive/duplicate/delete | manager+ |

### Event team roles

Cosmetic/operational labels on pivot (`Event::TEAM_ROLES`) — **not** separate Gate matrix.

---

# PART 7 — DATA MODEL GROUPS (RELATIONSHIPS)

```
Tenant ──< Workspace >── User (workspace_user.role)
Tenant ── CompanyProfile

Client ──< Contact
Client ──< Deal ── Event (on win)
Deal ──< Proposal ── Event (on accept)
Deal ──< DealActivity

Event ── Brief, Contract(s), Budget*, Tasks, Plan*, Agenda*, Speakers,
         Rooms/Layout, Transport*, Accommodation*, Catering*, Exhibition*,
         Sponsors*, Attendees*, Documents*, Risks*, Approvals*, Invoices,
         Income, InvoiceItems, TeamMembers, Documents…

Catalogues: Supplier, Venue, Requirement, ServiceItem, TaxonomyTerm,
            VehicleType, TransportVehicle, TransportDriver, TransportServiceType,
            RegistrationTemplate
```

~60 Eloquent models under `app/Models/`; ~120 migrations; tenancy columns on customer tables.

---

# PART 8 — FEATURE COMPLETENESS MATRIX (FACTUAL)

| Area | Status | Evidence note |
|---|---|---|
| Auth login/logout | complete | routes + controllers |
| Invite + forgot password | complete | TeamInvite + password routes |
| CRM pipeline | complete | CrmPipeline + DealPipeline |
| Proposals | complete | accept seeds budget |
| Contracts + payments schedule | complete | ContractTab + PaymentsLedger |
| Invoices | complete | raise from installment |
| Event Hub depth | complete | 23 tabs |
| Layout Builder | partial | canvas ≠ inventory |
| Equipment inventory | not found | requirements catalogue only |
| Messages / Guests / Assets | not found | nav removed; no routes |
| Client portal | not found | public register/check-in only |
| Notifications platform | partial | bell + email invite/reset |
| Calendar module | not found | — |
| Procurement/PO | not found | — |
| SaaS billing / company switcher | not found | tenancy schema only |
| Concept prototypes | prototype | not in nav |

---

# PART 9 — KEY CONTROLLING FILES (QUICK INDEX)

| Concern | Path |
|---|---|
| Routes | `routes/web.php` |
| Navigation | `app/Support/NavPanel.php`, `resources/views/components/app-{rail,panel,tools}.blade.php` |
| Layout | `resources/views/components/layouts/app.blade.php` |
| Gates | `app/Providers/AppServiceProvider.php` |
| Event access | `app/Policies/EventPolicy.php` |
| Hub shell | `EventHubController`, `resources/views/events/hub.blade.php` |
| Hub tabs registry | `app/Models/Event.php` (`HUB_TABS`, `HUB_MODULES`) |
| Win deal | `app/Services/DealPipeline.php` |
| Budget sync | `app/Services/BudgetSync.php` |
| Health / header | `EventHealthService`, `EventCommandHeader` |
| Module registry (legacy) | `config/modules.php` |
| Roles doc | `docs/07-user-roles-and-permissions.md` (partially outdated vs workspace pivot) |

---

# PART 10 — EXPLICIT “NOT FOUND” LIST

- Messages module / `messages.index` route  
- Guests (CRM) module / `guests.index` route  
- Assets inventory module / `assets.index` route  
- Help & Support page  
- `modules/stub.blade.php` (deleted)  
- Standalone Calendar module  
- Procurement / Purchase Order module  
- Client Viewer portal product  
- Quotation / RFQ entity (Proposal is the quote)  
- Warehouse equipment availability  
- Multi-company switcher / subscription billing UI  
- `AuthServiceProvider` / `Gate::before`  
- Per-nav-item role visibility rules  
- Hard approval gate before contract send  

---

# APPENDIX A — EVENT HUB TABS (exact keys)

`overview`, `brief`, `contract`, `planning`, `tasks`, `budget`, `pricing`, `risks`, `approvals`, `agenda`, `speakers`, `venue`, `suppliers`, `transportation`, `accommodation`, `catering`, `exhibition`, `sponsors`, `attendees`, `files`, `reports`, `ai`, `settings`

Toggleable modules: all of the above except always-on `overview`, `ai`, `settings` (and `pricing` is always offered in tabs though not in `HUB_MODULES` list — see `moduleEnabled()`: keys not in `HUB_MODULES` return true).

---

# APPENDIX B — RELATED INTERNAL DOCS (EXISTING, NOT REPLACED)

These are prior documents in the repo; this report is the factual inventory for consultants. Cross-check if needed:

- `docs/02-platform-architecture.md`  
- `docs/04-event-life-cycle.md`  
- `docs/07-user-roles-and-permissions.md`  
- `docs/10-current-codebase-assessment.md`  
- `docs/18-phase1-platform-audit.md`  
- `docs/19-internal-improvement-plan.md`  

---

*End of factual report. No redesign content included by instruction.*
