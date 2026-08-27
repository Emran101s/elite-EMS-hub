# Elite Orbit Soft Command System — Full Platform Design Proposal

**Status:** AWAITING APPROVAL — no implementation started  
**Date:** 2026-08-08  
**Product:** Elite Business Hub (internal Phase 1)  
**PR #44:** Hold — do not merge until this redesign path is decided  
**Visual north star:** Attached concept board + Soft Command reference screenshots  
**System name:** Elite Orbit Soft Command System

---

## Executive verdict

PR #44 correctly rebuilt **information architecture** (Company Command nav, Event Hub lifecycle, commercial handoffs). The **visual language** still reads as a long ERP sidebar + dense Orbit chrome. This proposal replaces the **entire UI system** with Soft Command — one shell, one token set, one component library, applied to every authenticated and public surface — **without** changing routes, database, or permissions.

**Decision required before any coding:** Approve / amend / reject this proposal.

---

## 1. Design system proposal

### 1.1 Product feeling

| Soft Command IS | Soft Command is NOT |
|---|---|
| Premium event mission control | Traditional admin dashboard |
| Calm futuristic ops OS | Crowded SaaS template |
| Executive cockpit + builder studio | Long ERP vertical menu |
| Soft gray workspace + navy structure | Heavy dark pages everywhere |
| Teal for live operational action | Gold everywhere / purple glow AI look |
| Master → Detail → Action focus | Everything visible at once |

### 1.2 Design philosophy (universal pattern)

```
Domain (rail)
  → Context sidebar (queue / smart views / filters)
    → Active object (workspace center)
      → Action / Inspector panel (right)
```

Applied everywhere it fits: Events, CRM, Proposals, Contracts, Agenda, Venue, Exhibition, Finance, Reports, Settings.

### 1.3 Relationship to PR #44

| Keep from #44 | Replace visually |
|---|---|
| Company Command area taxonomy & route map | Long always-expanded panel list |
| Event Hub lifecycle grouping & tab keys | Pill-tab “admin” chrome |
| Studio commercial/internal + deal link | Form-heavy studio chrome |
| Proposal → draft contract CTA | Proposal page layout |
| Closeout CTA concept | Closeout presentation |
| Gates, EventPolicy, routes, models | — |

**Recommendation:** Land #44 IA on `main` **after Soft Command shell is designed**, or rebase Soft Command **on top of #44** so IA + visual ship as one coherent product. Do **not** merge #44 then leave half-old UI.

---

## 2. Theme tokens

### 2.1 Color

| Token | Hex | Role |
|---|---|---|
| `--eo-navy-deep` | `#0B1322` | Mini rail, deep headers |
| `--eo-navy` | `#101A29` | Context sidebar, selected cards, command panels |
| `--eo-teal` | `#1EACAC` | Primary operational actions, active rail indicator |
| `--eo-gold` | `#D6AE34` | Brand / premium highlights only |
| `--eo-gold-soft` | `#F4D76B` | Soft gold accents (sparingly) |
| `--eo-bg` | `#E6E9EE` | Soft gray app background |
| `--eo-workspace` | `#F8F9FA` | Soft white workspace |
| `--eo-card` | `#FFFFFF` | Pure card surfaces |
| `--eo-text` | `#101827` | Strong text |
| `--eo-muted` | `#6B7280` | Helper / secondary |
| `--eo-risk` | `#F45B5B` | Risk / destructive |
| `--eo-warn` | `#F5A94E` | Warning |
| `--eo-ok` | `#2CC36B` | Success / healthy |

**Mix rule:** ~80% soft light · ~20% navy structure · teal = action · gold = brand · status colors only for status.

### 2.2 Typography

| Role | Direction |
|---|---|
| Family | Plus Jakarta Sans (primary) + Inter fallback; metric numbers may use tabular figures |
| Display | 28–36px bold, tight tracking |
| Title | 20–24px semibold |
| Body | 14–15px regular |
| Label | 11–12px uppercase / wide tracking / muted |
| Metric | 28–40px bold tabular |

Retire Playfair as the default display face for Soft Command (keep only if needed for PDF letterheads).

### 2.3 Spacing / radius / elevation

| Token | Value |
|---|---|
| Space scale | 4 / 8 / 12 / 16 / 24 / 32 / 48 |
| Card padding | 20–28px |
| Gap between soft cards | 16–24px |
| Radius xl | 16px |
| Radius 2xl | 24px |
| Radius pill | 999px |
| Shadow | Soft diffuse only (`0 8px 30px -18px rgba(11,19,34,.18)`) |
| Borders | Prefer none; use 1px `#E6E9EE` when needed |

### 2.4 Status system

| Status | Color | Usage |
|---|---|---|
| Healthy / On track | Success green | Health, readiness |
| Attention | Amber | Soft risk, overdue soon |
| Critical | Risk red | Alarms, blockers |
| Pending | Muted gray | Waiting |
| Live / Active | Teal | Operational live states |
| Premium / Brand | Gold | Brand mark, VIP, executive |

### 2.5 Control styles

| Control | Soft Command style |
|---|---|
| Primary button | Solid teal, white text, pill/2xl |
| Secondary | White + teal border |
| Destructive | Soft red bg / red text |
| Selected list item | Navy fill, white text (`SelectedDarkCard`) |
| Input | Soft gray fill or white + subtle border, 2xl radius |
| Table | Soft header row, spacious cells, row hover, no heavy grid |
| Tab / journey | Segmented pills or stage chips — not dense admin tabs |

---

## 3. Component architecture

### 3.1 Layering

```
tokens (CSS / Tailwind theme)
  → primitives (Button, Pill, SoftCard, Field, Table)
    → shell (AppShell, MiniRail, ContextSidebar, TopCommandBar)
      → patterns (QueueList, DetailPanel, ActionPanel, BuilderCanvas)
        → domain composites (EventJourneyNav, CommercialControlPanel, MissionRadar)
          → pages (Livewire + Blade compose only; no one-off page CSS)
```

### 3.2 Required Blade / Livewire components (new or rewritten)

| Component | Purpose |
|---|---|
| `x-eo.app-shell` | Global authenticated layout |
| `x-eo.mini-rail` | Slim dark icon rail |
| `x-eo.context-sidebar` | Domain-only sidebar |
| `x-eo.top-command-bar` | Search, create, AI, alerts, user |
| `x-eo.workspace-shell` | Soft gray workspace + white soft containers |
| `x-eo.page-header` | Title, subtitle, primary action |
| `x-eo.soft-card` | Default surface |
| `x-eo.selected-dark-card` | Active queue item |
| `x-eo.queue-list` | Master list |
| `x-eo.detail-panel` | Active object |
| `x-eo.action-panel` | Right inspector / CTAs |
| `x-eo.inspector-panel` | Builder property inspector |
| `x-eo.builder-canvas` | Floor / exhibition / layout canvas frame |
| `x-eo.timeline-grid` | Agenda day/time grid |
| `x-eo.smart-table` | Unified table chrome |
| `x-eo.filter-bar` | Filters + smart views |
| `x-eo.status-pill` / `metric-pill` | Status + metrics |
| `x-eo.readiness-card` / `module-card` | Ops readiness |
| `x-eo.event-journey-nav` | Hub journey stages |
| `x-eo.workflow-step` | Studio / wizard steps |
| `x-eo.smart-view-button` | Filtered views |
| `x-eo.floating-action-bar` | Sticky builder/footer actions |
| `x-eo.empty-state` / `alert-card` | Empty + alerts |
| `x-eo.ai-insight-panel` | AI briefing |
| `x-eo.commercial-control-panel` | Proposal/contract right rail |
| `x-eo.finance-metric-card` | Finance metrics |
| `x-eo.operations-readiness-card` | Ops board tiles |

**Rule:** Pages compose these components. No new page invents its own card language.

### 3.3 Mapping from current chrome

| Today | Soft Command |
|---|---|
| `app-rail.blade.php` | `x-eo.mini-rail` |
| `app-panel.blade.php` (full map) | `x-eo.context-sidebar` (domain-scoped) |
| `app-tools.blade.php` | `x-eo.top-command-bar` |
| `layouts/app.blade.php` | `x-eo.app-shell` |
| Mission / dial / figure-strip variants | Consolidated Soft cards + metrics |
| Dense hub pills | `x-eo.event-journey-nav` |

---

## 4. App shell design

```
┌────┬──────────────┬────────────────────────────────────────────┐
│    │ Context      │ Top Command Bar (search · create · AI · 🔔) │
│ R  │ Sidebar      ├────────────────────────────────────────────┤
│ A  │ Domain title │                                            │
│ I  │ + Primary    │         Soft Workspace                     │
│ L  │ Smart views  │   ┌ SoftCard / MDA columns / Builder ┐     │
│    │ Quick acts   │   │                                  │     │
│    │              │   └──────────────────────────────────┘     │
│ ⚙︎ │              │                                            │
└────┴──────────────┴────────────────────────────────────────────┘
```

- **Rail:** icons only; teal active indicator; settings/user at bottom  
- **Context sidebar:** only active domain (not the whole company map expanded)  
- **Workspace:** soft gray; content in large white 2xl cards  
- **Mobile:** rail → bottom or top icon strip; context becomes drawer; MDA stacks vertically  

---

## 5. Navigation behavior

### 5.1 Rail domains (icons)

Align with approved Company Command taxonomy (display labels from PR #44 naming):

| Rail | Label | Landing |
|---|---|---|
| home | Command Center | `home` |
| sales | Sales & CRM | `crm.index` |
| events | Event Portfolio | `events.index` |
| proposals | Proposals | `proposals.index` |
| contracts | Contracts | `contracts.index` |
| planning | Planning & Tasks | `planning.index` |
| operations | Operations Control | `venues.index` (until ops overview exists) |
| finance | Finance | `finance.index` |
| partners | Suppliers & Venues | `suppliers.index` |
| intelligence | Reports & Intelligence | `reports.index` |
| team | Team & Access | `team.index` |
| settings | Settings | `settings.index` (dock / rail bottom) |

### 5.2 Context sidebar content (per domain)

Not the full map. Example — Event Portfolio:

- Title: Event Portfolio  
- Primary: + New Event  
- Smart views: All · Live · Completed · At risk · Favorites  
- Quick: Planning board · New from CRM  

CRM example:

- Title: Sales & CRM  
- Primary: New Deal  
- Smart views: Open pipeline · Won · Lost · Needs proposal  
- Quick: Clients · Draft proposal  

**Routes stay;** presentation becomes domain-scoped Soft Command.

### 5.3 Event Hub journey nav (replace dense pills)

Stages (keys unchanged behind the scenes):

`Overview · Brief · Planning · Programme · Operations · Commercial · Control · Closeout`

Map existing tab keys into stages (same as #44 families, presented as journey).

---

## 6. Page pattern library

| Pattern | Structure | Used by |
|---|---|---|
| **CEO Cockpit** | Metric row + priority queue + health map + AI + money | Command Center |
| **Mission Radar** | Dark radar hero + mission cards + view toggles | Event Portfolio |
| **Mission Builder** | Steps + form + live preview + launch bar | Event Studio |
| **Event Command** | Journey header + MDA readiness + next action | Event Hub Overview |
| **Commercial Control** | Builder · Preview · Control panel | Proposals, Contracts |
| **Builder Studio** | Tools · Canvas · Inspector | Agenda, Venue, Layout, Exhibition |
| **Ops Readiness Board** | Grid of readiness cards + alerts | Operations Control |
| **Finance Desk** | Metrics · pipelines · money-to-collect | Finance, Invoices, Payments |
| **Report Studio** | Categories · dashboard · export/AI | Reports, AI |
| **Settings Desk** | Category list · form · help/impact | All settings |
| **Public Soft** | Minimal guest shell, same tokens | Registration, Check-in |
| **Index Soft Table** | FilterBar + SmartTable + row actions | Ledgers, catalogues |

---

## 7. Module-by-module redesign plan

### 7.1 Navigation / shell

App shell, mini rail, context sidebar, top bar, breadcrumbs, search, command palette, mobile nav.

### 7.2 Dashboards

Command Center (CEO cockpit), Event Portfolio (Mission Radar), Operations Control, Finance, Reports, AI Assistant.

### 7.3 Commercial

CRM pipeline, Clients, Contacts (via client), Deals, Proposals (3-col), Contracts (3-col), Invoices, Payments, Budgets.

### 7.4 Event command + modules

Studio, Hub journey, Overview, Brief, Planning, Tasks, Agenda, Speakers, Venue, Layout, Transport (+ Live/Dispatch), Accommodation, Catering, Suppliers, Exhibition, Sponsors, Attendees, Registration, Arrivals, Check-in, Budget, Pricing, Contract, Risks, Approvals, Files, Reports, AI, Settings, Closeout.

### 7.5 Directory / catalogues

Suppliers, Venues, Equipment & Requirements, Sponsorships, Projects, Team.

### 7.6 System

Company, Types, Statuses, Defaults, Price List, Sponsor Packages, Transport Catalogues, Registration Templates, Settings hub.

### 7.7 Public

Registration + Check-in: Soft guest shell (no ERP chrome).

---

## 8. Builder redesign plan

| Builder | Left | Center | Right | Footer |
|---|---|---|---|---|
| Event Mission Builder | Origin / identity steps | Form stages | Mission preview (dark navy) | Launch readiness + Launch |
| Agenda | Days / rooms | Timeline grid | Session inspector | Export / Add session |
| Venue / Layout | Tool palette | Canvas | Object inspector | Save / Export / Layers |
| Exhibition | Zones / types | Floor map | Booth inspector | Status legend |
| Registration | Field library | Form canvas | Field props | Publish / Preview |
| Transport planner | Movements list | Plan / Gantt | Movement inspector | Docs export |
| Budget | Categories | Line grid | Sync / margin panel | Export PDF |
| Proposal | Line builder | Document preview | Commercial control | Accept / Contract CTA |

---

## 9. Reports redesign plan

- Context: report categories (Sales, Finance, Ops, Team, Supplier, Event)  
- Center: report dashboard / charts  
- Right: filters, date range, export, AI insights  
- Event Reports remain reachable from Hub Control stage  
- Executive Briefing uses AIInsightPanel pattern  

---

## 10. Settings redesign plan

- Context: settings categories only (no Clients/Suppliers/Team as daily homes — already correct in #44 IA)  
- Center: selected settings form in SoftCard  
- Right: “What this affects”, recent changes, help  
- Team & Access stays domain rail item, not buried in Settings  

---

## 11. Event module redesign plan

| Stage | Tabs/keys included | Soft presentation |
|---|---|---|
| Overview | `overview` | Command header, readiness grid, next action, closeout |
| Brief | `brief` | Soft document + approval CTA |
| Planning | `planning`, `tasks` | Board + task queue MDA |
| Programme | `agenda`, `speakers`, `attendees` | Builder / roster patterns |
| Operations | venue, transport, stay, F&B, suppliers, exhibition, sponsors | Readiness + builders |
| Commercial | budget, pricing, contract | Finance/commercial panels |
| Control | risks, approvals, files, reports, ai, settings | Alerts + AI + docs |
| Closeout | checklist (v1 CTA → later full) | Dedicated closeout workspace |

**Tab keys / `enabled_modules` / PDF routes: unchanged.**

---

## 12. Affected files (high-level inventory)

### Foundation (new / heavy rewrite)

- `resources/css/app.css` (token swap + Soft utilities)  
- `tailwind` / Vite font imports  
- `resources/views/components/layouts/app.blade.php`  
- `resources/views/components/app-rail.blade.php` → eo mini-rail  
- `resources/views/components/app-panel.blade.php` → eo context-sidebar  
- `resources/views/components/app-tools.blade.php` → eo top-command-bar  
- New `resources/views/components/eo/**`  
- `app/Support/NavPanel.php` (structure for domain-scoped sidebar; routes preserved)

### Core screens

- Dashboard Livewire + blade  
- EventsIndex, EventCreate + blades  
- `events/hub.blade.php` + all `events/hub/*`  
- ProposalEditor / ProposalsDesk blades  
- ContractsRegister, CRM, Finance, Invoices, Payments blades  

### Hub Livewire views

- All `resources/views/livewire/hub/*`  
- Transport live/dispatch, exhibition floor, room layout, arrivals, public registration/check-in  

### Shared primitives

- `button`/`card`/`table`/`input` utility classes in CSS  
- `empty`, `alert`, `modal`, `confirm`, `status-badge`, `page-head`, `field`, `smart` tables across modules  

### Explicitly untouched

- Migrations, models (except optional display helpers), gates, EventPolicy  
- Route names and public URLs  
- PDF controllers (chrome can adopt Soft tokens later for HTML PDFs)

**Estimate:** 150+ Blade/Livewire view files touch Soft chrome; foundation first prevents one-off rewrites.

---

## 13. Implementation phases

| Phase | Scope | Exit criteria |
|---|---|---|
| **0 — Approval** | This proposal | Written sign-off |
| **1 — Tokens + primitives** | CSS tokens, SoftCard, buttons, pills, tables, forms, empty/alert | Story/demo page or component gallery |
| **2 — Global shell** | AppShell, MiniRail, ContextSidebar, TopCommandBar | Every page uses new shell; old rail/panel retired |
| **3 — Core** | Command Center, Event Portfolio (Mission Radar) | CEO + portfolio Soft Command |
| **4 — Studio + Hub** | Mission Builder + Event Journey Hub | Lifecycle UX Soft |
| **5 — Commercial** | Proposal 3-col, Contract 3-col, CRM polish | Commercial control pattern live |
| **6 — Builders** | Agenda, Venue/Layout, Exhibition | Canvas + inspector pattern |
| **7 — Rest of platform** | Ops, Finance, Tasks, Team, Settings, Reports, AI, public | No old UI islands |
| **8 — Visual QA** | Consistency, responsive, Livewire, modals | Sign-off checklist green |

**PR strategy:** Separate PRs per phase; each must keep routes/tests green. Do not one-shot the whole platform.

---

## 14. Risk assessment

| Risk | Level | Mitigation |
|---|---|---|
| Scope explosion / unfinished mix of old+new | **High** | Phase gates; forbid shipping half-shell |
| Breaking Livewire DOM / wire:targets | **High** | Preserve IDs/wire roots; restyle wrappers |
| Nav regression after shell swap | **High** | Keep NavPanel route safety + PlatformChromeTest |
| Performance (heavier DOM) | Medium | Soft cards, avoid nested glass stacks |
| PDF vs screen visual drift | Medium | Screen Soft first; PDF later |
| Designer teal vs existing gold brand | Medium | Teal = ops action; gold = brand only (per directive) |
| PR #44 conflict | Medium | Rebase Soft Command onto #44 IA |
| Mobile density | Medium | Drawer context + stacked MDA |
| Accidental permission/route changes | Low | Explicit ban; review diffs |

---

## 15. Testing plan

### Automated (must stay green each phase)

- PlatformChromeTest (reachability, no dead nav)  
- EventStudioTest, Proposal accept tests  
- Hub / Agenda / Transport workbench tests  
- Finance/Invoice/Contract nav tests  
- Public registration + check-in feature tests  

### Manual Soft QA (Phase 8)

1. Login → shell renders Soft Command  
2. Every rail domain opens scoped sidebar  
3. No long ERP map permanently expanded  
4. Command Center MDA feels calm  
5. Event Portfolio Mission Radar  
6. Studio origin + launch bar  
7. Hub journey stages; modules still toggle via `enabled_modules`  
8. Proposal 3-col CTAs  
9. Agenda / Layout / Exhibition builders  
10. Finance + tables + filters  
11. Settings desk pattern  
12. Mobile: drawer + stacked panels  
13. Modals/drawers/toasts match tokens  
14. Empty/loading/error states consistent  
15. No leftover Playfair/gold-heavy chrome islands  

### Non-goals for Soft Command

- No DB schema changes  
- No permission model rewrite  
- No SaaS billing / tenant switcher  
- No route renames  

---

## 16. Wireframes / visual direction

### Shell (ASCII)

See §4.

### Command Center (CEO Cockpit)

```
[ Metrics: Active | At Risk $ | Approvals | Ops Risks | Team Pressure ]
[ Priority Queue (SelectedDarkCard) ] [ Health Map ] [ AI Briefing ]
[ Week Ahead ]                        [ Money to Collect → Collect ]
```

### Event Portfolio (Mission Radar)

```
[ Dark navy Mission Radar card — orbit nodes + readiness % ]
[ Deck | List | Flight Path ]
[ Soft mission cards: health · risk · budget · next action ]
```

### Event Hub

```
[ Dark journey header: name · stage · readiness · countdown ]
[ Journey: Overview Brief Planning Programme Ops Commercial Control Closeout ]
[ Readiness grid ] [ Next Action panel ] [ Live Alerts ]
```

### Proposal

```
[ Lines builder ] [ Document preview ] [ Commercial Control: total, margin, CTAs ]
```

**Reference assets on disk (user-provided):**

- Concept board: `assets/Designer_-_2026-08-08T122453.621-….png`  
- Soft Command refs: `assets/original-01453eca…`, `original-326f9a7e…`, `original-f0c004f3…`  

---

## Open decisions for approval

1. **Ship order:** Soft Command on top of PR #44 IA, or redesign shell first then port #44?  
2. **Font:** Confirm **Plus Jakarta Sans** as primary (vs Manrope/Geist).  
3. **Gold vs Teal:** Confirm teal primary actions; gold reserved for brand/VIP.  
4. **Context sidebar width:** ~280px vs ~320px.  
5. **Mission Radar:** Implement as CSS/SVG v1 in Phase 3 (not WebGL).  
6. **Component prefix:** `x-eo.*` vs `x-orbit.*`.  

---

## Stop condition

✅ Proposal complete  
⏸ **No Soft Command coding until explicit approval**  
⏸ **PR #44 remains unmerged pending your decision**  
⏸ No DB / permission / route changes in this track  

**Reply with:** Approve as written · Approve with amendments (list) · Reject / revise direction.
