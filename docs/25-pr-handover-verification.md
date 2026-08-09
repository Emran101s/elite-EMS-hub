# PR Handover Verification Package

**Branch:** `cursor/master-ia-rebuild`  
**Date:** 2026-08-08  
**Do not merge until reviewed.** No MySQL cutover. No SaaS work.

---

## 1. Changed files

| File | What changed | Why | Risk |
|---|---|---|---|
| `app/Support/NavPanel.php` | Company Command rail areas + panel map; Settings panel separate; filtered deep links; route-safe mapping | Rebuild IA per master plan | **High** (chrome-wide) |
| `resources/views/components/app-rail.blade.php` | Denser icons for 11 areas; compact brand | Fit Company Command rail | Medium |
| `resources/views/components/app-panel.blade.php` | Always use `NavPanel::panel()`; drop extra concat | Single map source | Medium |
| `resources/views/events/hub.blade.php` | Primary keys + More families regrouped | Event Command lifecycle | **High** (hub UX) |
| `resources/views/events/hub/overview.blade.php` | Closeout CTA strip | Closeout v1 | Low |
| `app/Livewire/EventCreate.php` | `origin`, `deal_id`, `attachCommercialDeal()` | Studio ↔ CRM spine | **High** (writes deals) |
| `resources/views/livewire/event-create.blade.php` | Origin radios + deal select | Surface commercial/internal choice | Medium |
| `app/Models/Proposal.php` | On accept, create draft client contract if missing | Proposal → Contract handoff | Medium |
| `resources/views/livewire/proposal-editor.blade.php` | “Review draft contract →” CTA | Guided next step | Low |
| `app/Models/User.php` | Business-facing `ROLES` labels; `roleLabel()` uses workspace pivot | Display-only role UX | Low |
| `tests/Feature/PlatformChromeTest.php` | Escape `&` in rail title assert | Match Blade escaping | Low |
| `tests/Feature/PlanningBoardTest.php` | Label `Planning Board` | Match new nav label | Low |
| `docs/14-cross-agent-notes.md` | Handoff note for Claude | Avoid nav collisions | Low |
| `docs/22-mysql-readiness-audit.md` | New MySQL audit | DB strategy | Low |
| `docs/23-master-ia-rebuild-report.md` | Implementation report | Handover | Low |
| `docs/24-cleanup-report.md` | Cleanup inventory | Safe archive rules | Low |
| `docs/25-pr-handover-verification.md` | This package | PR review | Low |
| `docs/archive/README.md` | Archive index | Clarify supersession | Low |
| `docs/archive/orbit-ia-brief.md` | Moved from `docs/` | Superseded IA | Low |
| `docs/archive/orbit-migration-plan.md` | Moved from `docs/` | Superseded plan | Low |

**Not changed:** `EventPolicy`, `AppServiceProvider` gates, migrations, models (except Proposal/User labels), PDF routes, public routes.

---

## 2. Company Command navigation

### Rail (icons → landing routes)

| Area key | Rail label | Landing route |
|---|---|---|
| `workspace` | Command Center | `home` |
| `sales` | Sales & CRM | `crm.index` |
| `events` | Events | `events.index` |
| `proposals` | Proposals | `proposals.index` |
| `contracts` | Contracts | `contracts.index` |
| `planning` | Planning | `planning.index` |
| `operations` | Operations | `venues.index` |
| `finance` | Finance | `finance.index` |
| `partners` | Partners | `suppliers.index` |
| `intelligence` | Intelligence | `reports.index` |
| `team` | Team | `team.index` |
| *(dock)* | Settings | `settings.index` |

### Panel groups → children → routes

| Group | Child | Route | Filter / note |
|---|---|---|---|
| Command Center | Executive Overview | `home` | — |
| | Live Alerts | `home` | `#live-alerts` |
| | Upcoming Events | `events.index` | — |
| | Financial Signals | `finance.index` | — |
| | My Priorities | `tasks.index` | — |
| Sales & CRM | Deal Pipeline | `crm.index` | — |
| | Clients | `clients.index` | Daily work (not Settings) |
| | New Deal | `crm.index` | Same board (no create route) |
| Event Portfolio | All Events | `events.index` | — |
| | New Event | `events.create` | — |
| | Live Events | `events.index` | `?stage=live` |
| | Completed Events | `events.index` | `?stage=completed` |
| | Projects | `projects.index` | — |
| Proposals | Proposals | `proposals.index` | — |
| | Draft / Sent / Accepted / Declined | `proposals.index` | `?state=` |
| Contracts | Contracts | `contracts.index` | — |
| | Client / Vendor / Speaker / Sponsorship | `contracts.index` | `?type=` |
| | Pending Signatures | `contracts.index` | `?status=sent` |
| | Payment Schedules | `payments.index` | — |
| Planning & Tasks | Planning Board | `planning.index` | — |
| | My Tasks / Team Tasks | `tasks.index` | Same route (no owner filter URL yet) |
| Operations Control | Venue & Layout Overview | `venues.index` | Only portfolio Ops route that exists |
| Finance | Financial Dashboard | `finance.index` | — |
| | Invoices | `invoices.index` | — |
| | Payments | `payments.index` | — |
| Suppliers & Venues | Suppliers | `suppliers.index` | — |
| | Venues | `venues.index` | — |
| | Equipment & Requirements | `requirements.index` | Catalogue, not warehouse |
| | Sponsorships | `sponsors.index` | — |
| Reports & Intelligence | Reports Overview | `reports.index` | — |
| | AI Assistant | `ai.index` | — |
| Team & Access | Team Members | `team.index` | — |
| Settings (when in settings area) | Hub, Company, Types, Statuses, Defaults, Price List, Sponsor Packages, Transport Catalogues, Registration Templates | matching `*.index` routes | Config only |

### Hidden / not implemented (no dead links)

| Desired item | Why hidden |
|---|---|
| Documents Library (+ children) | No portfolio documents route |
| Contacts (standalone) | No route — use Client record |
| Activities (standalone) | No route — use CRM |
| Proposal Templates | No route |
| Active / Upcoming / Archived event filters (beyond live/completed) | No multi-stage or archived list URL |
| Transport Control, Arrivals, Run Sheets, Ops Risks (portfolio) | Event-level only |
| Roles / Permissions / Workload / Activity Logs pages | No dedicated routes |
| Branding / System Preferences | No dedicated routes |
| Supplier Ratings / Venue Rooms | Not supported as routes |

**Route safety:** All `NavPanel` route names resolve (`Route::has`). Rows without routes are filtered out.

---

## 3. Event Hub navigation

### Primary strip (keys)
`overview` · `brief` · `planning` · `tasks` · `agenda` · `budget`  
(Active non-primary tab is promoted into the strip.)

### More menu groups → keys
| Family | Tab keys |
|---|---|
| Event Command | `contract` |
| Programme | `speakers`, `attendees` |
| Operations | `venue`, `transportation`, `accommodation`, `catering`, `suppliers`, `exhibition`, `sponsors` |
| Commercial | `pricing` |
| Control | `risks`, `approvals`, `files`, `reports`, `ai`, `settings` |

### Labels
**Unchanged** — still from `Event::HUB_TABS` (e.g. Venue, Transport, Stay, Food & Beverage, Invoice items). No tab key renames.

### Confirmations
- `enabled_modules` / `moduleEnabled()` still filters primary + More.
- Hub still `events.hub` + `?tab=` — no tab routes removed.
- PDF/export child routes untouched.

**Before primary:** overview, tasks, agenda, venue, transportation, budget  
**After primary:** overview, brief, planning, tasks, agenda, budget

---

## 4. Route safety verification

| Check | Result |
|---|---|
| `php artisan route:list` | ~85 app routes (excl. Livewire internals) |
| NavPanel route names | **All resolve** |
| Sidebar dead links | **None** (`Route::has` filter) |
| `register.show` | Registered |
| `checkin.scan` | Registered |
| `EventPolicy` diff vs main | **0 lines** |
| Gates / `AppServiceProvider` diff | **0 lines** |

---

## 5. Workflow verification

### A. Event Studio
| Path | Behavior |
|---|---|
| Commercial (default) + client | On launch: attach selected open deal **or** create won deal linked to event |
| Internal | No deal created/linked |
| Existing deal selected | Open deal for that client → `stage=won`, `event_id` set, value/date filled if empty |
| Auto won-deal | When commercial, client present, no `deal_id` (or invalid) → `Deal::create` won |
| No client | Validation still requires client or new client name — launch blocked |
| No deal selected (commercial) | New won deal auto-created (not an error) |

### B. Proposal accept
1. `Proposal::accept()` → status accepted  
2. Deal value = proposal total → `DealPipeline::win()` → event  
3. `proposal.event_id` set  
4. `BudgetSync::syncProposal`  
5. If no client contract: `EventContract::createFor($event, 'client')`  
6. CTA: `events.hub` with `tab=contract` (“Review draft contract →”)

### C. Closeout v1
| Item | Value |
|---|---|
| When | Event stage `live` or `completed`, not archived |
| Links | Budget, Reports, Invoice items, Settings (archive) |
| Later | Full checklist module, final supplier payments, lessons learned, formal closeout status |

---

## 6. Permissions / roles

| Item | Status |
|---|---|
| Slugs | `super_admin`, `admin`, `manager`, `coordinator`, `viewer` — **unchanged** |
| `ROLE_RANK` | Unchanged |
| Gates | Unchanged |
| `EventPolicy` | Unchanged |
| Labels | Display-only (`Owner / CEO`, etc.) |
| Nav role hiding | **Not implemented** — no accidental lockout |

---

## 7. Database / MySQL

| Item | Value |
|---|---|
| Audit file | `docs/22-mysql-readiness-audit.md` |
| Tables reviewed | **75** |
| Fresh MySQL migrate tested? | **No — planned only** |
| Major risks | SQLite FK leniency; ID remap on import; strict mode; boolean/json/datetime differences |
| Indexes | Follow-ups recommended (events/deals/tasks/approvals/invoices) — not applied |
| Next steps | Provision MySQL 8 → `.env` mysql → `migrate:fresh --seed` on empty DB → smoke + tests → then adopt as primary local |

---

## 8. UI/UX (description; screenshots optional in review)

- **Rail:** Dark navy, denser gold orbit icons for 11 Company Command areas; Settings still in dock.  
- **Panel:** Section gold rules; full Company Command map; Settings swaps to config-only list.  
- **Event Hub primary:** Rounded pills; Brief/Planning elevated into spine.  
- **More:** Lifecycle family headers.  
- **Event Studio:** Two origin cards (Commercial / Internal) + optional deal select.  
- **Proposal accepted:** Emerald “Open the event” + navy “Review draft contract”.  
- **Closeout:** Gold-bordered strip on Overview for live/completed.

---

## 9. Testing proof (24/24 passed)

| # | Test | Verifies | Result |
|---|---|---|---|
| 1 | `PlatformChromeTest::test_global_pages_carry_the_breadcrumb` | Breadcrumbs | Pass |
| 2 | `…test_every_module_in_the_registry_is_reachable_from_the_chrome` | config/modules reachable | Pass |
| 3 | `…test_the_panel_draws_no_rows_that_go_nowhere` | No dead/ghost nav | Pass |
| 4 | `…test_every_area_is_on_the_rail` | All areas on rail | Pass |
| 5 | `…test_the_rail_does_not_clip_the_labels_that_hang_off_it` | Tooltip clip safety | Pass |
| 6 | `…test_the_event_hub_breadcrumb_names_event_and_module` | Hub crumbs | Pass |
| 7 | `…test_the_command_palette_finds_things_across_the_workspace` | ⌘K | Pass |
| 8 | `…test_the_palette_asks_for_two_characters` | Palette UX | Pass |
| 9 | `EventStudioTest::test_it_opens_on_the_first_room` | Studio open | Pass |
| 10 | `…test_a_room_is_checked_when_you_leave_it` | Step validation | Pass |
| 11 | `…test_the_preview_reports_only_what_has_been_answered` | Readiness | Pass |
| 12 | `…test_every_module_tile_is_wired_to_its_own_key` | Module toggles | Pass |
| 13 | `…test_the_review_room_launches_and_a_failed_launch_shows_you_why` | Launch errors | Pass |
| 14 | `…test_nothing_is_written_until_launch` | No premature write | Pass |
| 15 | `…test_launching_builds_the_event_the_preview_described` | Launch creates event | Pass |
| 16 | `ProposalTest::test_accepting_wins_the_deal_and_opens_the_event_at_the_agreed_figure` | Accept → event | Pass |
| 17 | `…test_accepting_seeds_the_budget_from_the_priced_lines` | BudgetSync | Pass |
| 18 | `…test_accepting_twice_does_not_duplicate_budget_lines` | Idempotent budget | Pass |
| 19 | `…test_accepting_is_idempotent` | One event | Pass |
| 20 | `…test_the_nav_links_to_it_now_that_it_exists` | Proposals nav | Pass |
| 21 | `PlanningBoardTest::test_the_nav_links_to_it_now_that_it_exists` | Planning Board nav | Pass |
| 22 | `InvoiceTest::test_the_nav_links_to_it_now_that_it_exists` | Invoices nav | Pass |
| 23 | `PaymentsLedgerTest::test_the_nav_links_to_it_now_that_it_exists` | Payments nav | Pass |
| 24 | `ContractsRegisterTest::test_the_nav_links_to_it_now_that_it_exists` | Contracts nav | Pass |

**Summary:** 24 tests, 24 passed, 223 assertions.

---

## 10. Rollback plan

1. Do not merge if review fails.  
2. If merged later and issues appear: `git revert` the merge commit on `main`.  
3. No DB migrations in this PR — revert is code-only.  
4. MySQL was not cut over — no DB rollback needed for this PR.
