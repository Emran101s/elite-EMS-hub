# Master IA Rebuild Report — Elite Business Hub

**Date:** 2026-08-08  
**Branch:** `cursor/master-ia-rebuild`  
**Source of truth:** Master Platform Plan (user directive, 2026-08)  
**Phase:** Internal Elite Business Hub only (SaaS deferred)

---

## 1. Executive summary

Rebuilt Company Command navigation and Event Command hub grouping around the real commercial → delivery lifecycle, without breaking routes, tab keys, gates, or modules. Event Studio now links to CRM deals; proposal acceptance prepares a draft client contract. MySQL 8+ readiness is audited and documented; no destructive DB cutover was performed. Outdated Orbit planning docs were archived (not deleted as code).

---

## 2. What was changed

1. **Company Command nav** — `NavPanel` rebuilt (rail areas + full panel map).
2. **Settings separated** — daily work (Clients, Suppliers, Venues, Team) removed from Settings panel; Settings is configuration-only.
3. **Event Hub** — primary strip + More families regrouped by lifecycle.
4. **UI chrome** — denser rail for more areas; panel always shows Company Command map.
5. **Event Studio** — commercial vs internal origin; optional open-deal link; auto won-deal on commercial launch.
6. **Proposal accept** — seeds draft client contract + CTA to Contract tab.
7. **Role labels** — business-friendly UI labels; technical slugs/gates unchanged.
8. **Closeout v1** — Overview CTA strip for live/completed events.
9. **Docs** — MySQL audit + this report; Orbit docs archived.

---

## 3. Files changed

| File | Change |
|---|---|
| `app/Support/NavPanel.php` | Company Command structure |
| `resources/views/components/app-rail.blade.php` | Denser Company Command rail |
| `resources/views/components/app-panel.blade.php` | Single map source (no extra concat) |
| `resources/views/events/hub.blade.php` | Lifecycle primary + More families |
| `resources/views/events/hub/overview.blade.php` | Closeout CTA |
| `app/Livewire/EventCreate.php` | Deal / origin linking |
| `resources/views/livewire/event-create.blade.php` | Origin + deal UI |
| `app/Models/Proposal.php` | Draft client contract on accept |
| `resources/views/livewire/proposal-editor.blade.php` | Contract CTA |
| `app/Models/User.php` | Business role labels |
| `tests/Feature/PlanningBoardTest.php` | Label match |
| `docs/22-mysql-readiness-audit.md` | New |
| `docs/23-master-ia-rebuild-report.md` | New |
| `docs/24-cleanup-report.md` | New |
| `docs/archive/*` | Archived Orbit docs |

---

## 4. New main sidebar structure (Company Command)

**Rail areas (icons):** Command Center · Sales & CRM · Events · Proposals · Contracts · Planning · Operations · Finance · Partners · Intelligence · Team  
**Settings:** dock only (unchanged pattern).

**Panel sections (map):**
1. Command Center  
2. Sales & CRM  
3. Event Portfolio  
4. Proposals  
5. Contracts  
6. Planning & Tasks  
7. Operations Control  
8. Finance  
9. Suppliers & Venues  
10. Reports & Intelligence  
11. Team & Access  

**Documents Library:** omitted (no portfolio documents route — see §8).

---

## 5. New Event Hub structure

**Primary strip:** Overview · Brief · Planning · Tasks · Agenda · Budget  
(Active non-primary tab is promoted into the strip.)

**More families:**
- Event Command → Contract  
- Programme → Speakers, Attendees  
- Operations → Venue, Transport, Accommodation, Catering, Suppliers, Exhibition, Sponsors  
- Commercial → Pricing  
- Control → Risks, Approvals, Files, Reports, AI, Settings  

**Preserved:** tab keys, `enabled_modules`, PDF/export routes, Livewire Hub components.

---

## 6. Routes used

All existing registered routes — including filtered deep links where Livewire URL state exists (`stage`, `state`, `status`, `type`).

---

## 7. Routes intentionally not used

- SaaS billing, company switcher, marketplace, white-label  
- New fake portfolio modules without pages  

---

## 8. Links hidden because route does not exist

| Desired child | Status |
|---|---|
| Contacts (standalone) | Hidden — use Client record |
| Activities (standalone) | Hidden — use CRM board |
| Proposal Templates | Hidden — no route |
| Documents Library children | Entire section omitted |
| Transport Control (portfolio) | Hidden — event-level only |
| Arrivals / Run Sheets (portfolio) | Hidden — event-level only |
| Roles / Permissions pages | Hidden — Team Members only for now |
| Archived Events filter | Hidden — archive removes from index |
| Branding / System Preferences | Hidden — no dedicated routes |

---

## 9. Old files archived or removed

| Item | Action |
|---|---|
| `docs/orbit-ia-brief.md` | Moved → `docs/archive/` |
| `docs/orbit-migration-plan.md` | Moved → `docs/archive/` |
| Working routes / models / migrations | **Not deleted** |
| `docs/18`, `docs/19` | Kept (historical audits); this master plan supersedes their nav recommendations |

---

## 10. UI/UX improvements made

- Company Command labels and lifecycle grouping  
- Settings no longer home for Clients / Suppliers / Venues / Team  
- Denser gold/navy rail for 11 areas  
- Event Hub primary spine surfaces Brief / Planning / Budget  
- Closeout strip on Overview for live/completed  
- Proposal editor contract handoff CTA  

Full visual redesign of every page was **not** in this pass — chrome + hub structure first.

---

## 11. Workflow improvements made

| Fix | Implementation |
|---|---|
| Event Studio ↔ CRM | Commercial origin creates/links won deal; internal skips deal |
| Proposal → Contract | Accept creates draft client contract if missing + CTA |
| Closeout | Overview checklist CTAs (v1) |
| Roles UX | Business labels on `User::ROLES` / `roleLabel()` |

**Still open (next):** stronger Contract → Payment → Invoice automation; portfolio Operations Control board; full closeout module; Command Center widget pass.

---

## 12. Database audit summary

See `docs/22-mysql-readiness-audit.md`.  
75 tables observed; tenant_id widespread; soft deletes sparse; 120 migrations.

---

## 13. MySQL readiness status

**Audited & planned. Not cut over.**  
SQLite remains default + PHPUnit. Strategic production DB: MySQL 8+.

---

## 14. Migration risks

See §3 of `docs/22`. Highest risks: FK leniency on SQLite, ID remapping on import, strict MySQL mode surprises.

---

## 15. Permission / role visibility changes

- **Gates / EventPolicy:** unchanged  
- **Role slugs:** unchanged  
- **Labels only:** Super Admin→Owner/CEO, Admin→General Manager, Manager→Event Director, Coordinator→Team Member  

No nav item is role-hidden yet (safe — no accidental lockouts).

---

## 16. Breaking risks

| Risk | Mitigation |
|---|---|
| Longer panel / more rail icons | Density tweaks; mobile horizontal scroll unchanged |
| Event Studio now creates deals by default | Explicit Internal origin |
| Proposal accept creates contract | Idempotent if client contract exists |
| Role label strings in UI | Tests did not assert old English ranks |

---

## 17. Testing checklist result

Run on branch before merge (see CI / local):

| # | Check | Result |
|---|---|---|
| 1–6 | Login, dashboard, sidebar, no dead links, active states | Pending local/CI |
| 7–12 | Events + Event Hub tabs / More / modules | Pending |
| 13–20 | CRM → Proposals → Contracts → Finance | Pending |
| 21–30 | Planning, tasks, library dirs, team, settings | Pending |
| 31–34 | Public reg, check-in, EventPolicy, gates | Pending |
| 35–38 | Mobile nav, MySQL migrate (not run), no fake links | MySQL not cut over |

Automated (local, 2026-08-08): **24/24 passed** — `PlatformChromeTest`, `EventStudioTest`, proposal accept + nav label tests, invoice/payments/contracts/planning nav tests.

---

## 18. Recommended next steps

1. Merge this IA branch after green tests.  
2. Priority 7 — Command Center actionable widgets.  
3. Contract signed → payment schedule → invoice milestones UX.  
4. Provision local MySQL; fresh migrate+seed; adopt as primary dev DB.  
5. Portfolio Operations Control only when a real aggregate route exists.  
6. Role-aware nav visibility (careful, gradual).  
7. Full closeout checklist module.
