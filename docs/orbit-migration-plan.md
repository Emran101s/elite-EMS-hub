# ORBIT migration plan — Phase 0 audit

**Design source of truth:** `orbit-system.html` (repo root) · served at `/design`
**Spec:** `CLAUDE-CODE-BRIEF.md` · **Guardrails:** `CLAUDE.md`
**Audited:** 25 Jul 2026, on `ops-modules-and-design-system` at `9e6f2c8`

This replaces the earlier audit written against the dark-first ORBIT draft. That
draft is superseded: ORBIT is **light-first**, gold is **two tokens with two
jobs**, and chrome stays dark in both themes.

No application code was changed to produce this document.

---

## 1. Inventory

**150 Blade views · 34 Livewire components.**

### Livewire components by level

| Level | Components |
|---|---|
| Portfolio | `CommandCenter`, `EventsIndex`, `EventCreate`, `CommandPalette`, `TeamRoster` |
| Event core | `Hub/BriefTab`, `Hub/PlanStudio`, `Hub/TasksTab`, `Hub/AgendaTab` |
| Money | `Hub/BudgetTab`, `Hub/ApprovalsTab`, `Hub/RisksTab`, `Hub/ContractTab` |
| Physical | `Hub/VenueTab`, `Hub/ExhibitionTab`, `Hub/TransportationTab`, `Hub/AccommodationTab`, `RoomLayoutBuilder`, `ExhibitionFloorPlan`, `TransportDispatch`, `TransportLive` |
| People | `Hub/SpeakersTab`, `Hub/AttendeesTab`, `Hub/SponsorsTab`, `ClientsManager`, `VenuesManager` |
| Documents | `Hub/ModuleDocuments`, `Hub/SettingsTab` |
| System | `CompanySettings`, `DefaultsSettings`, `TransportSettings`, `SponsorPackagesSettings`, `RequirementsCatalog` |

### Views by directory

| Directory | Count | Contents |
|---|---|---|
| `components/` | 23 | `agenda-program, brand, bulk-bar, command-spine, crumbs, dock, donut, empty, event-avatar, event-card, event-crest, event-preview, field, health-ring, icon, layout-element, modal, page-head, pdf-footer, pdf-header, section-head, status-badge, user-avatar` |
| `components/avatars/` | 6 | SVG event-type illustrations |
| `components/layouts/` | 2 | `app`, `guest` |
| `components/orbit/` | 6 | `badge, btn, card, meter, ring, stat` — the new library |
| `events/hub/` | 21 | one per module tab |
| `events/` | 20 | 17 of these are PDF documents |
| `livewire/` | 16 | portfolio + operations screens |
| `livewire/hub/` | 17 | module tabs |
| `livewire/hub/partials/` | 18 | plan-studio (8), tasks-studio (8), brief/document (2) |
| `event-brief/`, `event-contract/` | 8 | document + paper + PDF variants |
| `modules/` | 7 | portfolio-level stubs |
| `auth/` | 1 | `login` |

---

## 2. Hard-coded values

Counted with the brief's own acceptance greps.

| Where | Files | Hex literals | Verdict |
|---|---|---|---|
| PDF / print documents | 29 | **355** | **Exempt — see §5.** Standalone documents rendered by headless Chrome. |
| SVG artwork (`components/avatars/`) | 6 | **142** | **Exempt.** Illustration, not UI. |
| **Live UI** (`livewire/`, `components/`) | **16** | **113** | **Convert.** This is the real Session 2 job. |
| Other | 6 | 21 | Convert. |
| `resources/css/` (excl. generated) | — | 47 | Convert — the navy/gold `@theme` block. |

`font-size:` literals: **192**, all but a handful inside PDF templates.

### The 16 live-UI files, heaviest first

| File | Hex | Note |
|---|---|---|
| `components/agenda-program.blade.php` | 31 | Whole palette inlined at lines 10–11 |
| `components/layout-element.blade.php` | 20 | Room-layout element colours |
| `livewire/exhibition-floor-plan.blade.php` | 17 | Booth status colours |
| `components/event-card.blade.php` | 17 | Event-type + status colour maps, lines 5–17 |
| `components/event-preview.blade.php` | 9 | Navy gradients |
| `components/command-spine.blade.php` | 4 | `#16294A`, `#0B1F3A`, `#D4AF37` ×2 |
| `livewire/event-create.blade.php` | 3 | |
| `livewire/room-layout-builder.blade.php` | 2 | |
| `livewire/hub/plan-studio.blade.php` | 2 | |
| `components/brand.blade.php` | 2 | `#D4AF37` ×2 |
| `livewire/hub/tasks-tab.blade.php` | 1 | |
| `livewire/hub/partials/tasks-studio/drawer.blade.php` | 1 | |
| plus `command-center`, `contract-tab`, `event-crest`, `plan-studio/drawer` | 4 | one each |

**The pattern:** almost every literal is a *status or category colour map* built in
Blade (`$colours = ['confirmed' => '#22C55E', …]`). These are exactly what
`App\Support\Tone` exists to replace — the fix is one mechanical move per map, not
16 bespoke rewrites.

**Radii and shadows** are already tokenised through the Tailwind `@theme` block;
they migrate when that block is repointed at ORBIT tokens, not file by file.

---

## 3. Mapping — existing view → ORBIT component

| Existing | ORBIT | Notes |
|---|---|---|
| `components/donut.blade.php` | **delete** → `<x-orbit.meter>` | Donuts are banned by the system. Every caller becomes a meter. |
| `components/health-ring.blade.php` | `<x-orbit.ring>` | Already the right idea; swap markup, keep callers. |
| `components/status-badge.blade.php` | `<x-orbit.badge>` | Colour comes from `Tone`, not the current hex map. |
| `components/event-card.blade.php` | `<x-orbit.card>` + `<x-orbit.badge>` | One shared card for Cards/List/Calendar (session 5b). |
| `components/field.blade.php` | `<x-orbit.field>` | |
| `components/empty.blade.php` | `<x-orbit.empty>` | Must teach the concept — session 6c. |
| `components/modal.blade.php` | `<x-orbit.modal>` | |
| `components/page-head`, `section-head`, `crumbs` | `<x-orbit.topbar>` + card `__head` | Absorbed by the new chrome. |
| `components/command-spine.blade.php` | `<x-orbit.nav-arc>` + `<x-orbit.dock>` | The 20-item rail this replaces. |
| `components/dock.blade.php` | — | Already retired from screens; delete with the old CSS. |
| `components/bulk-bar.blade.php` | keep, restyle | No ORBIT equivalent; it's a good pattern. |
| `.card` / `.op-card` / `.cc-panel` (app.css) | `.o-card` / `.o-panel` | The whole navy/gold component layer retires in Phase 5. |
| `.kanban-head`, `.pill`, `.qa-btn` | `.o-card__head`, `.o-badge`, `.o-btn` | |
| KPI rows across tabs | `<x-orbit.kpi-strip>` | Dark chrome — `--chrome`, not `--hull`. |
| Every "Control Center" panel | `<x-orbit.card>` + `<x-orbit.stat>` | These already exist per module and map cleanly. |
| `CommandPalette` (exists) | `<x-orbit.palette>` | **Already built** — needs restyling and ⌘K binding, not writing. |

---

## 4. Order of work

Bottom-up. Screens last, because every screen re-skins for free once tokens and
components land.

| # | Work | Files | Est. |
|---|---|---|---|
| **1** | **Tokens** — repoint the `@theme` block at ORBIT; convert the 16 live-UI files' colour maps to `Tone`; `data-theme` from `ThemePolicy` | `resources/css/app.css`, 16 views | **6h** |
| 2 | Primitives — remaining components from the map, each added to `/design/gallery` in the same commit | `components/orbit/*` (≈24 new) | 14h |
| 3 | Navigation — `nav-arc`, `dock`, `topbar`, responsive collapse, ⌘K wiring, flag `orbit_nav` | layout, `command-spine` retirement | 10h |
| 4 | Screens 5a–5n — one per session, 25 modules | `livewire/**`, `events/hub/**` | 60h |
| 5 | Cross-cutting 6a–6g + polish | auth, empties, errors, print, email, responsive | 24h |

Phase 1 items already done in the bootstrap commit: token files generated, fonts
self-hosted, `Tone`, `ThemePolicy`, `/design`, `/design/gallery`, six primitives.

### Sequencing risks

- **`DesignSystemTest` (158 lines) currently enforces the navy/gold system.** It will
  fail the moment Session 2 lands. It must be rewritten to enforce ORBIT's rules
  (no literals, one hero per screen, gold ≤3 per viewport) in the same commit —
  not deleted and not left failing.
- **`Event::HUB_MODULES`** is the single source of module names and already feeds
  the nav. The arc component should read it rather than re-listing modules.
- **Plan Studio keeps its own colourful design** by explicit earlier decision. It is
  the one screen that does not adopt the ORBIT palette wholesale; treat its board
  as a deliberate exception and confirm before converting.

---

## 5. What ORBIT does not cover

These need a decision rather than a conversion.

1. **PDF and print documents (29 files, 355 literals).** These are standalone
   documents rendered by headless Chrome, not app screens. Custom properties do
   work there, but the documents are deliberately paper-like — cream, seals,
   bilingual Arabic/English type — and are client-facing artefacts. *Recommendation:*
   generate an `orbit-print.css` from the token file with hex inlined (the same
   approach the brief mandates for email), and exempt these from the "zero hex"
   grep rather than pretending they're screens. Session 6e is the right place.

2. **SVG event-type artwork (6 avatars, 142 literals).** Illustration. Leave as-is;
   exempt from the grep.

3. **The Arabic type stack.** Contracts and briefs are bilingual and use Amiri.
   ORBIT specifies no Arabic face. *Needs a decision:* pair an Arabic face with
   Instrument Serif, or keep Amiri as a fourth token.

4. **Floor plans and room layouts** (`RoomLayoutBuilder`, `ExhibitionFloorPlan`,
   `layout-element`). Interactive spatial canvases with per-element colour. ORBIT's
   nearest concept is the venue "twin" (a dark module). Their colours should become
   tokens, but their layout is outside the component map.

5. **The Gantt/timeline views** in Plan Studio and Tasks Studio exist and are richer
   than `.o-gantt`. ORBIT's gantt is a band timeline; ours has dependencies and
   drag. *Recommendation:* keep ours, restyle to tokens.

6. **Transport Dispatch Board and Live Operations.** Real-time operational screens
   with lanes and conflict detection. They map to the dark theme (`transport-board`
   is already in `DARK_MODULES`) but have no component in the map.

7. **Two animations are permitted system-wide.** The dispatch board and live ops
   currently animate more than that. They will need an exemption or a redesign —
   flagging now because it is a genuine conflict with Law 5, not an oversight.

---

## 6. Data issue to resolve before Session 5h

The brief flags a false −JD 214,498 P&L: Contract showed JD 350,000 collected while
Budget showed income JD 0.

**Already investigated and fixed.** It was a real integration bug, not a display
bug: `BudgetTab` computed client income from manual budget line items only and never
read contract payments. `Event::incomeSummary()` now derives client income from
`contract->payments`, and event 7's P&L reads **+JD 135,502**. No further action
needed at 5h beyond presenting it.

---

*Session 1 ends here. Session 2 is the token sweep.*
