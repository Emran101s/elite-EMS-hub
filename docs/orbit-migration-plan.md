# ORBIT v2 — Migration Plan (Phase 0 Audit)

**Repo:** Elite Event Hub (Laravel 13 · Livewire 4 · Alpine · Tailwind v4)
**Audit date:** 2026-07-25
**Scope of this document:** Phase 0 only — inventory + mapping + sequenced plan. **No application code was changed.**

---

## 0. Read-this-first context (important)

Two facts materially affect this migration; both need a decision before Phase 1 starts.

1. **The app already has a full, recently-shipped design system** — a *navy + white-glass + gold* "Command Center" language (tokens in `resources/css/app.css`, component classes `.card` / `.op-card` / `.cc-panel` / `.pill` / `.glass-dock`, plus the `x-*` Blade components). It is guarded by `tests/Feature/DesignSystemTest.php` (type-scale + no-hardcoded-brand-hex rules) and is green. **ORBIT v2 is a different system**, not a refinement of it:
   - **Action colour:** ORBIT = **iris/indigo** everywhere; current = navy/gold. Gold in ORBIT is *prestige-only, max 3 per viewport*; current uses gold as the primary accent.
   - **Type:** ORBIT = Inter (UI) + Instrument Serif (display) + **JetBrains Mono (all numbers)**; current = Instrument Sans + Spectral, numbers in the UI face.
   - **No donuts** in ORBIT (`.o-ring` single value, `.o-meter` splits); current uses donuts/rings in Tasks, Plan, Budget, and the event ribbon.
   Adopting ORBIT therefore **replaces** the current look, it does not layer onto it. That is consistent with ORBIT's own rule ("ask for a migration, not a redesign"), but the owner should confirm they want to retire the navy/gold system before Phase 1.

2. **The ORBIT CSS is not generated yet.** `setup-orbit.sh` has **not** been run, and the source files live in `docs/orbit-handoff/`, not the repo root. So `resources/css/orbit-tokens.css` and `resources/css/orbit.css` **do not exist**. Session 1/2 both assume they do. **Prerequisite before Phase 1:** copy `orbit-v2-hub-system.html` + `setup-orbit.sh` to the repo root, run the script, add the four `@import` lines to `app.css`, commit. This audit read the tokens/classes directly from the HTML instead.

Extracted from the HTML for reference: **64 design tokens** (`--iris`, `--gold`, `--vital`, `--ion`, `--flare`, `--critical`, `--abyss/hull/deck/rim`, `--ink…ink-4`, spacing `--o-1…o-20`, radii `--r-xs…r-xl`, `--t-body/-sm/-micro`, easings, durations) and **~140 component classes** (`.o-card .o-btn .o-badge .o-ring .o-meter .o-stat .o-kpistrip .o-row .o-table .o-arc .o-dock .o-stepper .o-twin .o-pulse .o-ai .o-alert .o-empty .o-field .o-seg .o-tabs .o-pin .o-metric .o-num …`).

---

## 1. Inventory — every Blade view & Livewire component, grouped by module

**Totals:** 143 Blade views · 34 Livewire components.

### Shell / cross-cutting components
| File | Type | Notes |
|---|---|---|
| `views/components/layouts/app.blade.php` | layout | header + right module rail + full-width main (current) |
| `views/components/command-spine.blade.php` | component | old left sidebar — now **unused** |
| `views/components/{brand,user-avatar,crumbs,icon,health-ring,donut,status-badge}.blade.php` | components | brand/atoms |
| `views/components/{modal,field,empty,page-head,dock}.blade.php` | components | shell primitives |
| `views/components/avatars/*` (7), `event-crest`, `event-avatar`, `event-card`, `event-preview` | components | generative event art (raw-colour, print-like) |

### Command Center (portfolio home)
`app/Livewire/CommandCenter.php` → `views/livewire/*` + `views/livewire/partials/{event-panel, events/detail}.blade.php`

### Events portfolio
`app/Livewire/EventsIndex.php` → `views/livewire/events-index.blade.php` + `partials/events/{card,detail}.blade.php`

### Event Hub shell + module includes
`views/events/hub.blade.php` (identity + rail + ribbon) → per-tab includes `views/events/hub/{overview,ai,reports,files,suppliers}.blade.php`

### Per-event modules (Livewire `app/Livewire/Hub/*` + `views/livewire/hub/*`)
| Module | Component | View(s) |
|---|---|---|
| Tasks | `TasksTab` | `tasks-tab` + `partials/tasks-studio/{board,card,control-center,list,timeline,gallery,drawer,actions}` |
| Planning | `PlanStudio` | `plan-studio` + `partials/plan-studio/{board,card,list,timeline,gallery,drawer,tracks,actions}` |
| Budget | `BudgetTab` | `budget-tab` |
| Contract | `ContractTab` | `contract-tab` + `views/event-contract/{paper,paper-pdf,mini,document,contract}` |
| Brief | `BriefTab` | `brief-tab` + `partials/brief-section` + `views/event-brief/{paper,paper-pdf}` |
| Agenda | `AgendaTab` | `agenda-tab` |
| Speakers | `SpeakersTab` | `speakers-tab` |
| Venue | `VenueTab`, `RoomLayoutBuilder` | `venue-tab`, `room-layout-builder` |
| Suppliers | (static) | `events/hub/suppliers` |
| Transport | `TransportationTab`, `TransportDispatch`, `TransportLive` | `transportation-tab`, `transport-dispatch`, `transport-live` |
| Accommodation | `AccommodationTab` | `accommodation-tab` |
| Exhibition | `ExhibitionTab`, `ExhibitionFloorPlan` | `exhibition-tab`, `exhibition-floor-plan` |
| Sponsors | `SponsorsTab` | `sponsors-tab` |
| Attendees | `AttendeesTab` | `attendees-tab` |
| Documents | `Hub\ModuleDocuments` | `module-documents` + `partials/document-drawer` |
| Risks | `RisksTab` | `risks-tab` |
| Approvals | `ApprovalsTab` | `approvals-tab` |
| Settings (event) | `Hub\SettingsTab` | `settings-tab` |

### Global / Settings (Livewire)
`EventCreate`, `CommandPalette`, `VenuesManager`, `TeamRoster`, `ClientsManager`, `CompanySettings`, `DefaultsSettings`, `SponsorPackagesSettings`, `RequirementsCatalog`, `TransportSettings`, `TransportMasterPlan` → `views/modules/{suppliers,settings}` + matching `views/livewire/*`.

### PDF / print views — **out of ORBIT's UI scope** (Chrome-rendered, own paper system)
`views/events/*-pdf.blade.php` (budget, room-layout, room-equipment, agenda-master/program/timeline, run-of-show, rooming-list, exhibition-floor, transport-*), `views/event-contract/{paper-pdf,document,contract}`, `views/event-brief/paper-pdf`, `views/components/{pdf-header,pdf-footer,agenda-program}`, `views/equipment-pdf`. **~28 views.** These already have a deliberate print design and should be left as-is (ORBIT's `@media print` rules apply, but they are not screen components).

---

## 2. Hard-coded colour / font-size / radius / shadow findings

**Headline numbers:** 57 view files contain a hard-coded hex; 146 arbitrary `text-[Nrem|px]` occurrences; arbitrary `shadow-[…]` in ~8 component files. Current `app.css` already defines **47 colour tokens**, so the *brand palette* is largely tokenised — the offenders are inline `style="…"`, generative art, and PDF views.

### 2a. Legitimately raw colour — leave alone (ORBIT's own rules exempt these)
- **PDF/print views** (top offenders): `events/room-layout-pdf` (40), `event-contract/contract` (30), `event-contract/document` (16), `events/room-equipment-pdf` (18), `events/agenda-master-pdf` (18), `events/agenda-timeline-pdf` (16), `events/run-of-show-pdf` (15), `events/budget-pdf` (14), `events/sponsorship` (14). Chrome-rendered; keep.
- **Generative event art** (SVG avatars/crests): `components/avatars/{vip-event(22),workshop(19),gala-dinner(19),international-conference(17),festival-outdoor(14)}`, `event-crest`. Raw colour is the point; keep.

### 2b. Real migration targets — interactive views with hard-coded values (file:line)
| File:line | Value | Becomes (ORBIT) |
|---|---|---|
| `livewire/hub/contract-tab.blade.php:4-8` | type-ink gradients `linear-gradient(#1F6FB2,#164e78)` … | tokenised accents or `Tone` |
| `livewire/hub/contract-tab.blade.php:79-80,144-145` | wax-seal `radial-gradient(#E4C874,#C8A44A,#9c7d2e)`, `text-[#5a4718]` | `--gold` family tokens |
| `livewire/hub/plan-studio.blade.php:43` | `#F97316` | `--flare` |
| `livewire/hub/partials/tasks-studio/control-center.blade.php:157` | `from-[#7C5CFF]` (AI) | `--iris`/`--iris-2` |
| `livewire/event-create.blade.php:3` | stage hex `#94A3B8`,`#06B6D4` | `Tone` / tokens |
| `livewire/exhibition-floor-plan.blade.php:4-6,247-254,299-301` | booth status + grid hexes | `venue_zones.status` → `Tone` (ties into the twin) |
| `livewire/room-layout-builder.blade.php:154,166` | canvas `#FBFCFE`,`#FFFFFF` | `--hull`/`--deck` (twin) |
| 146× `text-[Nrem|px]` across `livewire/**` + `components/**` | arbitrary sizes | ORBIT type tokens `--t-body/-sm/-micro` + `.o-metric*` |
| 8× `shadow-[…]` (`components/{event-preview,event-card,layouts/app,command-spine}`, `livewire/{event-create,contract-tab}`, `partials/{event-panel,document-drawer}`) | arbitrary shadows | `--lift-1/2/3` |

> A full line-by-line list for every one of the 146 `text-[]` + 57 hex files is mechanical; Phase 1 will drive it to zero via the two acceptance greps in the kickoff. This audit lists the *classes* of offender and the notable interactive ones so the work can be scoped.

---

## 3. Current view → ORBIT component mapping

| Current construct (where) | ORBIT component |
|---|---|
| `.card` / `.op-card` / `.cc-panel` boxes (everywhere) | `<x-orbit.card>` (`gravity`, `accent`) |
| `.btn-gold` / `.btn-navy` / `.btn-ghost` / `.btn-danger` | `<x-orbit.btn>` (`variant=iris\|gold\|ghost\|danger`) |
| `x-status-badge`, `.pill`, `.chip` | `<x-orbit.badge>` (`tone` via `App\Support\Tone`) |
| `x-health-ring` (single %) | `<x-orbit.ring>` |
| `x-donut` splits + ribbon gauges (budget/tasks/suppliers) + Tasks/Plan progress donuts | `<x-orbit.meter>` (**donuts removed**) |
| `cc-tile` KPI tiles, attendee/overview stat cards | `<x-orbit.stat>` / `<x-orbit.kpi-strip>` |
| Event Hub ribbon (hub hero) + health block | `<x-orbit.kpi-strip>` + `<x-orbit.pulse>` |
| Supplier/venue/hotel/speaker/user **cards** & sponsor/attendee **tables** | `<x-orbit.row>` (one shared row, used ×5+) and `<x-orbit.table>` |
| Budget ledger, transport manifests, roster, sponsors tables | `<x-orbit.table>` (right-aligned `.o-num`) |
| Right module rail / top nav (current) | `<x-orbit.nav-arc>` + `<x-orbit.dock>` |
| Control Centers' "AI Assistant" + `events/hub/ai` | `<x-orbit.ai-panel>` |
| Health alerts / feed rows | `<x-orbit.alert>` |
| Empty states (many `card … text-center`) | `<x-orbit.empty>` (must *teach*, not "No data") |
| `.input`, `x-field`, `x-modal` | `<x-orbit.field>` + `<x-orbit.modal>` |
| View switchers / focus toggles (Tasks/Plan) | `<x-orbit.seg>` / `<x-orbit.tabs>` |
| `CommandPalette` (⌘K) | `<x-orbit.palette>` |
| Planning/agenda timelines, event stage timeline | `<x-orbit.stepper>` (+ bespoke agenda grid) |
| `RoomLayoutBuilder`, `ExhibitionFloorPlan` | `<x-orbit.twin>` (needs `venue_zones` — see §5) |

---

## 4. File-by-file order of work (Phases 1–5) with rough estimates

> Prereq (0.5 day): run `setup-orbit.sh` from repo root; add the 4 `@import`s; self-host fonts; commit. *Then* the phases below.

### Phase 1 — Tokens & type (~2 days)
1. `resources/css/app.css` — swap `@theme` to ORBIT tokens; keep tokens generated by the script. (2h)
2. Self-host Inter / Instrument Serif / JetBrains Mono; wire `--font-*`. (2h)
3. `components/layouts/app.blade.php` — `data-theme` on `<html>` + Alpine `orbit` store. (1h)
4. `app/Support/ThemePolicy.php` + `app/Support/Tone.php` (new). (3h)
5. Drive the two acceptance greps to **0** — replace inline hex/font-size across `livewire/**` + `components/**` (the §2b set + the 146 `text-[]`). (1 day)
6. Reconcile with `DesignSystemTest` — its rules will need rewriting for ORBIT tokens (or the test retired in favour of the ORBIT contrast CI in Phase 5). (2h)

### Phase 2 — Primitives (~4 days)
Build under `views/components/orbit/`, six first: `card, btn, badge, ring, meter, stat` → then the `/design` gallery route (auth-gated) → review → then `kpi-strip, row, table, alert, empty, field, seg, tabs, stepper, pulse, ai-panel, nav-arc, dock, palette`. Extract the HTML's SVG sprite to `sprite.blade.php`. (~0.3 day each; gallery 0.5 day)

### Phase 3 — Navigation (~3 days)
`nav-arc` (polar-computed nodes) + 1280px icon-rail + 900px bottom `dock`; retire the current right rail / `command-spine` behind flag `orbit_nav`; ⌘K `palette` as Livewire over events/modules/tasks/suppliers; live badge counts. (Riskiest change — flag it.)

### Phase 4 — Screens, one per session (~2 weeks)
`5a` Command Center → `5b` Event Hub overview → `5c` Venue (**data model first**, then CSS twin) → `5d` Budget (**investigate the JD 0 income vs JD 350k contract P&L bug first**) → `5e` shared `row` across Venue/Suppliers/Attendees/Sponsors/Speakers → `5f` Documents (Brief + Contract, print-clean). One hero card per screen; delete cards that don't earn their place.

### Phase 5 — Polish (~1 week)
`ThemePolicy` → module/phase auto-dark; `prefers-reduced-motion` audit; **CI contrast test** over every token pair (fail < 4.5:1); print-stylesheet verification for Budget/Brief/Contract.

**Rough total: ~5.5 weeks** of focused work beyond the prereq.

---

## 5. What ORBIT does not cover (decide before we hit these)

1. **PDF / print document suite (~28 views).** ORBIT has `@media print` rules but no PDF component set. The contract/brief/agenda/transport sheets have a bespoke Chrome-rendered "paper" system (incl. **bilingual EN/AR, RTL, Amiri font**). ORBIT is silent on RTL and on document layout. → Keep the paper system; only theme its on-screen preview.
2. **Venue digital twin needs a new data model.** `<x-orbit.twin>` assumes a `venue_zones` table + seeded zones that **do not exist**. `RoomLayoutBuilder` and `ExhibitionFloorPlan` are the current, different floor-plan tools. → Session 5c must build the migration/model/seed first; decide whether the twin replaces or augments the existing builders.
3. **Generative event art** (7 avatar SVGs + crest). Raw colour by design; ORBIT has no equivalent. → Keep.
4. **Drag-and-drop boards.** Tasks/Plan Kanban + Transport Dispatch lanes use SortableJS. ORBIT specs `card`/`row` but not the DnD interaction. → Reuse cards inside the existing DnD.
5. **Bespoke operational surfaces** with no ORBIT analogue: Transport **Dispatch board** (time-axis lanes + conflict detection) and **Live ops** (Now/Next/Later), the **"Living Document" editors** (Brief/Contract WYSIWYG), multi-currency **FX** display, the **Contract Deck/Pipeline**. → Style with ORBIT primitives but keep the bespoke layout; flag any that ORBIT wants to delete.
6. **The just-shipped navy/gold system + its `DesignSystemTest`.** Not a "gap" so much as a collision — see §0.1. Retiring it is a decision, and its guard test must be replaced, not left to fail.

---

## Stop point

Phase 0 is complete. **No code changed.** Awaiting go-ahead before Phase 1 — and specifically a decision on §0.1 (retire navy/gold for ORBIT?) and §0.2 (run `setup-orbit.sh` prereq).
