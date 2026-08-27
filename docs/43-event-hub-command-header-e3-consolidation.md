# Phase E.3 — Event Command Header Architecture: Consolidation Report

**Status:** Approved · **Branch:** `cursor/soft-command-phase1` · **Scope:** Event Hub shell only (module-tab content is untouched except where noted)

> **Update, 2026-08-21 — superseded claims below.** This report is the
> as-built record of the E.3 round only; it was written before the
> repository-stabilization commit sequence that followed. Two of its own
> claims are now factually wrong and are corrected here rather than edited
> in place, so the historical record of what E.3 itself did stays intact:
>
> - **§8 Inspector** says Overview "falls back to the Agenda case." That was
>   true when this was written. It is no longer true: commit `7e011c5`
>   ("feat(hub): complete Universal Module Inspector coverage for every
>   module") added `HubModuleInspector::overview()` — Overview's own
>   event-level inspector (readiness gates, top attention signals, owner,
>   recent activity), not a borrowed Agenda readout.
> - **§13's "Inspector / Universal Module Header coverage is partial"** and
>   **§14.2's "Extend Universal Module Header + Inspector coverage to the
>   remaining ~16 tabs"** are both stale. That work is done, also in
>   `7e011c5`: `$showPanel` in `resources/views/events/hub.blade.php` now
>   covers 20 of `Event::HUB_TABS`' tabs (added tasks, risks, speakers,
>   venue, suppliers, catering, exhibition, sponsors, attendees, pricing,
>   brief, contract, files), each with a real `HubModuleInspector::data()`
>   case — metrics, quick links, and an `addLabel` for the ones that support
>   `?action=add`. Coverage is no longer "module-to-module inconsistent."
>
> One dependency-ordering fact worth recording here since it touches this
> round's own commit (`cc913ef`): `hubx-inspector.blade.php`, committed in
> this round, calls `HubModuleInspector::overview()` unconditionally
> whenever the Inspector panel renders on the Overview tab — the hub's
> default landing tab. That method did not exist until `7e011c5`, nine
> commits later. Every commit in between, checked out on its own, throws a
> fatal `Call to undefined method` loading the Event Hub. Nothing in that
> range was ever pushed or deployed, and the final state is verified green
> (see `docs/44-repository-stabilization-2026-08-21.md`), but a safe
> reorder was assessed and recommended there rather than left unaddressed.
>
> The test counts in this report's own "Test result" section (below) are
> also a point-in-time snapshot from partway through that same commit
> sequence, not the final number — see doc 44 for the current count.



Phase E.3 began as Universal Module Header coverage (`65f85df`) and escalated, this
round, into a full replacement of the Event Hub's top-of-page architecture: Stage
Radar / Orbit Journey / Event Core / Event Cortex / the vertical Module Rail are
gone outright — not replaced by another lifecycle diagram — in favour of an
Event Command Header → Module Navigation Bar → Event Pulse Strip stack sitting
above the existing Command Stack / Workspace / Inspector grid. This document is
the as-built record of that architecture, reviewed and approved across desktop,
tablet, and mobile.

---

## 1. What was removed

| Component | File | Why |
|---|---|---|
| Stage Radar (compact rail + expandable ring) | `orbit-journey.blade.php` | Explicitly rejected — "no radar, no orbit, no lifecycle visualization, of any kind" |
| Event Core (the ring-centre identity card) | `event-core.blade.php` | Lived only inside Stage Radar; its fields (Health, Readiness, Next action, Owner) were already duplicated by the Event Pulse Strip and the Inspector |
| Event Cortex ("Most Valuable Next Action") | `hubx-cortex.blade.php` | Sat in the same chrome zone being restructured; the Inspector's own conditional Next-Action panel already covers this without a second surface |
| Compact Module Rail (vertical icon dock) | `hubx-module-rail.blade.php` | Replaced by the horizontal Module Navigation Bar — same `meters()`/`attention()` data, different geometry |

All four are deleted, not disabled. Nothing references them; `grep`-verified clean
across `resources/views` and `app`.

## 2. Event Command Header

**Retained, not rebuilt.** `hubx-header.blade.php` already matched the "Event
Command Header" concept from an earlier phase — identity, stage/type/live-status
pills, location, dates, client, and the star / expand / Event-Utilities-drawer
icon row plus Portfolio / Open-Event actions. E.3 validated it as the correct
primary-awareness surface and left its structure in place. The only change
touching it this round was indirect: it now sits directly above the Module
Navigation Bar instead of above Stage Radar.

**Not deeply re-polished in E.3.** The prior round's direction ("continue
refining the Event Command Header... hierarchy, spacing, polish, clarity") was
interpreted this round as extending to the *header's own* typography/spacing —
in practice, the review surfaced a mobile Module Navigation Bar issue instead,
which is where the polish budget went. A dedicated hierarchy/spacing pass on
`hubx-header.blade.php` itself remains open — see §14.

## 3. Module Navigation Bar — new

`hubx-module-nav.blade.php`, full-width row between the Header and the Pulse
Strip (not a grid column). Reads the same `EventCommandHeader::meters()` /
`attention()` data the old rail read:

- Icon + label pill per enabled primary module (Overview, Agenda, Budget,
  Transport, Approvals, Venue, Suppliers, Files); active tab is a solid navy
  pill, same convention as Budget's mode switcher and Agenda's view switcher.
- An issue-count badge, not a readiness percentage — percentage detail moved to
  the Pulse Strip and the Inspector, so a tab here is just a door.
- Secondary modules (Brief, Contract, Planning, Tasks, Risks, Speakers,
  Accommodation, Catering, Exhibition, Sponsors, Attendees, Invoice items) sit
  behind a "More" flyout, rendered as its own flex sibling — not inside the
  scrollable strip — specifically so it can't be clipped by the strip's
  `overflow-x: auto`.
- `.hubx-modnav` carries an explicit `z-index: 40` so the flyout reliably paints
  above the Inspector panel (which forms its own stacking context via
  `backdrop-filter`) regardless of viewport width.
- On load, the active tab scrolls itself into view (`scrollIntoView`), and a
  soft right-edge fade (`mask-image`, applied only when the strip actually
  overflows) signals there's more to swipe — see §12.

## 4. Event Pulse Strip

`hubx-kpi-strip.blade.php` — retained from an earlier phase, **relocated and
promoted**. Previously nested inside the centre workspace column and shown on
Overview only; now a full-width row shown on **every tab**, between the Module
Navigation Bar and the three-column grid. Same data, same hierarchy: Health
Score + Readiness as the primary pair (larger, own card), Days Out / Budget
Used / Risk Level as the secondary row. No new figures were added — this is a
position change, not a content change.

## 5. Command Stack

`hubx-command-stack.blade.php` — unchanged in E.3, carried over from the
Mission Control redesign. Left column of the three-column grid. Reads
`EventCommandHeader::attention()`'s six real signals (tasks / risks / approvals
/ speakers / budget / contract); a signal that isn't firing simply isn't in the
list. Empty state reads "Clear — nothing needs a person right now" rather than
being absent, which is what now stands in for the old header's "nothing is
waiting on you" guarantee (see the updated `EventHubTest` assertions, §8).

## 6. Mission Timeline

`hubx-mission-feed.blade.php` / `app/Support/MissionFeed.php` — unchanged in
E.3, carried over. Overview's own workspace content: near-term calendar items
(overdue tasks, upcoming agenda sessions, recent contract activity), the one
workspace element Overview still owns outright.

## 7. More Doors

The four-tile row at the bottom of Overview (Speakers / Brief / Contract /
Registration) — the modules the Navigation Bar doesn't surface as a primary
tab. Unchanged in E.3; its top comment in `events/hub/overview.blade.php` was
updated to describe the new shell (Header / Nav Bar / Pulse Strip / Command
Stack / Inspector) instead of the retired one.

## 8. Inspector

`hubx-inspector.blade.php` — unchanged in E.3. Right column of the grid,
shown for the same seven tabs it always was (`overview` falls back to the
Agenda case): status pill, readiness ring, per-module metrics, owner, a
**conditional** Next-Action panel, recent activity, quick links. Its
conditionality (no panel when there's genuinely nothing next) is why the old
`test_the_header_keeps_its_shape_when_nothing_is_waiting` test needed
rewriting — Event Core used to guarantee an always-present "Next action:
Nothing pressing" row; the Inspector correctly doesn't invent one.

## 9. Universal Module Header

`hubx-module-header.blade.php` — unchanged in E.3, this round. The header
shown once inside a module's own content (every `$showPanel` tab except
Overview): icon, name, purpose line, readiness, status, key metrics, owner,
conditional next action, and a real "Add" button where the module supports
`?action=add`. This is the E.3-lineage component from `65f85df` (Plan Studio
was the most recent module folded into it, immediately before this round).

## 10. Desktop behaviour (≥1280px)

Three real columns: Command Stack (232px) | Workspace (flexible) | Inspector
(280px, `has-panel` tabs only). The Module Navigation Bar and Pulse Strip are
full-width rows above the grid, not columns — so removing the old rail's 60px
column gave the workspace real width back. Reviewed and approved: Overview,
Agenda, Budget, Transport. (Collateral benefit: Budget's own internal 300px
Control Center sidebar, previously cramped against the 4-column shell, now
fits its ledger side-by-side at normal desktop widths.)

## 11. Tablet behaviour (900–1279px)

Command Stack collapses to a horizontal strip above the workspace; Inspector
drops below, full width. Module Navigation Bar and Pulse Strip are unaffected
by the breakpoint — they were already full-width, horizontally-scrollable rows
at any size. Reviewed and approved at 768×1024.

## 12. Mobile behaviour (<900px)

Same single-column stack as tablet. One real issue was found and fixed this
round: the Module Navigation Bar's scroll container only had ~147px of a
375px viewport to work with (the persistent global icon rail — pre-existing,
unrelated to this phase — takes the rest), so on load it showed roughly 1.3
tabs, and landing on a tab other than Overview left the active pill scrolled
out of view entirely — reading as "nothing selected" rather than "swipe right."

Fixed with two small, scoped additions to the existing component (no new
widgets):
- The active tab scrolls itself into view on load (`x-init` +
  `scrollIntoView({inline: 'center'})`).
- A soft right-edge fade (`mask-image`), toggled by a `ResizeObserver`-driven
  `is-overflowing` class, appears only when the strip actually overflows —
  confirmed absent on desktop where nothing overflows.

Reviewed and approved at 375×812 post-fix.

## 13. Remaining limitations

- **Global icon rail eats mobile width.** The ~140px persistent left rail is
  outside this phase's scope (it's the app-wide navigation, not Event Hub),
  but it's the root cause of §12's constraint. The fade/auto-scroll fix
  mitigates the symptom; a phone user still can't see more than ~2 tabs at
  once without swiping.
- **Inspector / Universal Module Header coverage is partial.** `$showPanel`
  covers 7 of `Event::HUB_TABS`' ~23 entries (`overview`, `agenda`, `budget`,
  `transportation`, `approvals`, `accommodation`, `planning`). Every other tab
  (Venue, Suppliers, Risks, Files, Sponsors, Exhibition, Catering, Speakers,
  Brief, Contract, Registration, …) renders with no right-column Inspector and
  no Universal Module Header strip — consistent with what E.3 has always done
  (only migrate a module in when it's actually taken on), but it means the new
  shell's "awareness surface" story is inconsistent module-to-module.
- **Event Command Header itself wasn't structurally revisited.** Retained
  as-is (see §2) — validated, not refined. Its own hierarchy/spacing/type
  scale is exactly what it was before Stage Radar was removed.
- **"More" flyout is a flat list, not searchable.** Fine at ~12 secondary
  modules; would need attention if that list grows materially.

## 14. Recommended next phase

1. **A real hierarchy/spacing/type pass on `hubx-header.blade.php`.** This is
   the piece of "continue refining the Event Command Header" that hasn't
   happened yet — pill weight, name/meta type scale, icon-row alignment,
   whitespace against the Module Navigation Bar directly beneath it.
2. **Extend Universal Module Header + Inspector coverage** to the remaining
   ~16 tabs, one at a time, the same way Plan Studio was folded in in `65f85df`
   — each addition is a `HubModuleInspector` case plus a `$showPanel` entry,
   not a shell change.
3. **Decide on the global icon rail at mobile widths** (drawer? auto-hide?) —
   cross-cutting, outside Event Hub, needs its own scoping before touching it.
4. Continue the *module content* eo-* visual conversion (Budget is done;
   Transport / Attendees / Approvals / Accommodation / Registration /
   Sponsors / Catering / Exhibition / Risks / Documents remain) — a separate,
   already-approved-per-module workstream, unrelated to shell architecture.

---

## Changed files (E.3, this round)

**New**
- `resources/views/components/eo/hubx-module-nav.blade.php`

**Deleted**
- `resources/views/components/eo/orbit-journey.blade.php`
- `resources/views/components/eo/event-core.blade.php`
- `resources/views/components/eo/hubx-cortex.blade.php`
- `resources/views/components/eo/hubx-module-rail.blade.php`

**Modified**
- `resources/views/events/hub.blade.php` — shell restructure: removed the
  `$journey` computation and Radar/Cortex renders; added the Module Nav Bar
  and full-width Pulse Strip rows; dropped the rail grid column.
- `resources/views/events/hub/overview.blade.php` — top comment updated to
  describe the new shell (no functional change).
- `resources/css/eo-hub-redesign.css` — `.hubx-grid` narrowed from 4 to 3
  columns; ~500 lines of dead `.hubx-orbit-*` / `.hubx-radar-toggle` /
  `.hubx-cortex*` / `.hubx-rail*` CSS removed; `.hubx-modnav*` added
  (including the `z-index: 40` stacking fix and the `is-overflowing` fade);
  responsive breakpoints simplified to drop the rail grid-area.
- `tests/Feature/EventHubTest.php` — `test_module_rail_marks_the_active_tab`
  → `test_module_nav_marks_the_active_tab`; the two "header keeps its shape"
  assertions updated from Event Core's retired `Next`/`Next action` text to
  the Command Stack's own unconditional title.
- `tests/Feature/EventOverviewReadinessTest.php` — `Not started` substring
  counts updated from `3 + 8 + 1` / `2 + 8 + 1` to `3 + 1` / `2 + 1` (the
  Orbit ring's 8 stage cards no longer exist to count).

*(The Budget-module eo-* visual conversion and the transport/pricing/invoice
3-decimal money precision fix, both completed earlier this session, touch
files under `livewire/hub/`, `app/Models/`, and `app/Livewire/` — they are
separate, already-reported pieces of work and are not part of this shell
architecture and not included above.)*

## Screenshots reviewed and approved

Captured live in the Browser pane across this round (not persisted as saved
files):

| Breakpoint | Tabs |
|---|---|
| Desktop (1440–1600px) | Overview, Agenda, Budget, Transport |
| Tablet (768×1024) | Overview (full scroll), Budget nav-bar state |
| Mobile (375×812) | Overview (full scroll), Budget (pre- and post-fix) |

Plus targeted checks: the "More" flyout open state (desktop, against the
Inspector, pre- and post- the z-index fix) and the Command Stack empty state.

## Build result

```
✓ built in 472ms
```
No errors, no warnings introduced.

## Test result

```
{"tool":"phpunit","result":"passed","tests":1091,"passed":1091,"assertions":4614}
```
1091 / 1091 passing, full suite, after this round's changes.

## What not to touch next

- Do not reintroduce Stage Radar, an orbit ring, a journey strip, or any
  other lifecycle/stage visualization — explicitly and repeatedly rejected.
- Do not add new dashboard cards or widgets to Overview or the shell chrome.
- Command Stack, Mission Timeline, Inspector, and Universal Module Header are
  approved and stable — leave their own internals alone; extend coverage
  (§14.2) rather than redesigning them.
- Do not fold the global icon rail's mobile behaviour into an Event-Hub-scoped
  change — it's shared, app-wide navigation; changing it needs its own review.
- Do not touch the Budget-module content conversion or the money-precision
  work as a side effect of any Event Hub shell follow-up — they're finished,
  separate, and already verified.
