# Phase E.2 — Event Hub Orbit Journey: Architecture

**Status: architecture only. Nothing in this document has been implemented.** No
route, controller, Livewire component, or Blade file has been changed as part of
this phase. Everything below is a proposal to evaluate, not a diff to review.

Companion deliverable: a clickable visual mockup (desktop / tablet / mobile) —
see the artifact linked in the chat message this document was delivered with.
Static excerpts of the same mockup are described inline below where useful.

---

## 1. Current journey audit

The current Event Journey lives entirely in one file,
[`resources/views/events/hub.blade.php`](../resources/views/events/hub.blade.php),
as three stacked pieces:

1. **`x-event-header`** — identity card (crest, name, stage pill, readiness %,
   live-desk pill) plus, folded into the same card since Phase E, the
   `mission-card` `hub` variant: Health / Readiness / Days out / Live desk /
   Owner / Next action.
2. **`x-eo.priority-area`** — three columns (Open Risks, Pending Approvals,
   Event Escalations), directly under the header.
3. **The Journey strip** — eight equal-weight pills (`Overview` → `Closeout`),
   rendered inside a flat rounded rectangle:

   ```css
   .eo-journey-strip {
       background: rgba(16, 26, 41, 0.04);   /* a 4%-opacity grey box */
       box-shadow: inset 0 0 0 1px rgba(16, 26, 41, 0.06);
   }
   ```

   Below it, a second, separately-scrolling sticky row lists every enabled
   tab belonging to the active stage only (this is already good — see below).

**What already works and should not be thrown away:**

- **The stage grouping is real and correct.** `hub.blade.php` already maps
  all 23 `Event::HUB_TABS` into exactly 8 stages (`$journey` array, lines
  ~59–67) and already filters the secondary nav row down to *only the active
  stage's* tabs. This was Phase E's own fix for the previous problem
  ("Planning" selected in the Journey, but "Venue" and eighteen unrelated
  doors sitting next to it). **Orbit does not need to re-solve this — it
  needs to re-*skin* it.** The stage→tabs map is the exact input the
  satellite architecture in §6 consumes unchanged.
- **All the data the orbit needs already exists**, computed once per page
  load by `App\Services\EventCommandHeader::for()`:
  - `meters()` — per-module `%` + a human detail line (`agenda`, `speakers`,
    `budget`, `tasks`, `sponsors`, `logistics`).
  - `attention()` — per-tab counts with a `tone` of `alarm` or `wait`
    (overdue tasks, open risks, pending approvals, unconfirmed speakers,
    uncosted budget lines, unsigned contracts).
  - `readiness()` — a gate list (`met: bool`) plus an overall `%`.
  - `critical()` — the one thing that needs a person today, already
    worst-first across risks → approvals → tasks.

  **No new backend computation is proposed anywhere in this document.**
  Every number the orbit shows is a re-presentation of a service call that
  already runs on every hub page load.

**What reads wrong today, and why:**

- **It reads as a progress bar, not a place.** Eight identical rounded
  rectangles in a row, one shaded teal, is the visual grammar of a checkout
  flow or an onboarding wizard — exactly the "wizard / stepper" feeling the
  brief asks to move away from. Nothing about the strip says *this is the
  event*; the event's own identity lives in a separate card above it.
- **The event is decentralized.** The header card (identity + health) and
  the journey (progress) are two unrelated shapes stacked vertically. There
  is no single object the eye lands on and reads as "the mission."
- **The secondary nav row is disconnected chrome.** It sits in its own
  sticky navy-on-grey bar, visually unrelated to either the header above it
  or the journey step it belongs to. A user has to infer the connection
  ("these seven pills are what 'Programme' contains") rather than see it.
- **All 8 stages carry equal visual weight regardless of state.** A stage
  with two overdue tasks and a stage that's fully wrapped up look identical
  except for a checkmark-style `is-complete` colour shift. There is no
  "watch this" or "blocked" language at all today — only complete / active
  / not-yet.

**One important precedent already in the app, unrelated to the Journey:**
the Events *Portfolio* page (`/events`, Radar view) already ships a dark
radial field on an otherwise light page —
[`resources/views/components/eo/mission-radar.blade.php`](../resources/views/components/eo/mission-radar.blade.php)'s
`hero` variant, built and shipped in Phase C.1. It is the exact "80% light,
20% dark, mission-control" register this brief is asking for, already
designed, already approved, already in production. §9–11 below reuse that
component's visual language (dark field, glowing nodes, radial layout)
rather than inventing a new one.

---

## 2. Orbit Journey architecture

Replace the two-shape layout (header card + flat strip) with one hero
region that *is* both at once:

```
Event Command Header  (unchanged — crest / name / stage / CTA)
        ↓
┌─────────────────────────────────────────────┐
│              ORBIT JOURNEY                   │
│                                               │
│         ●  02          ●  03                 │
│      Brief          Planning                 │
│                                               │
│  ●  01          ┌──────────┐         ●  04   │
│ Overview         │  EVENT   │       Programme │
│                  │   CORE   │      (ACTIVE,   │
│                  └──────────┘       expanded) │
│                                    ⤷ satellites│
│         ●  08          ●  05                 │
│      Closeout        Operations              │
│                                               │
│         ●  07          ●  06                 │
│      Control         Commercial               │
└─────────────────────────────────────────────┘
        ↓
Priority Area   (placement — see §7)
        ↓
Workspace (unchanged: @includeIf('events.hub.'.$tab, ...))
```

Three things happen inside the same region that today take three separate
regions:

| Today | Orbit Journey |
|---|---|
| Header card shows identity + health | **Event Core** (center) shows identity + health |
| Flat strip shows 8 steps, complete/active only | **Orbit ring** shows 8 stages with 6 real states |
| Sticky nav row lists the active stage's tabs | **Satellites** around the active node list the same tabs |

Hierarchy requested in the brief — *Event Command Header → Orbit Journey →
Workspace* — is preserved exactly; the Priority Area's position moves (§7),
everything else keeps its order.

---

## 3. Event Core anatomy

The center object. Never the active stage — always the event.

```
┌───────────────────────────────┐
│  ▣  Communication & Outreach   │
│     Workshop                  │
│     National Democratic       │
│     Institute (NDI)           │
│                                │
│   Health      Readiness       │
│    67          67%            │
│                                │
│  26 Jul – 29 · amman, Jordan   │
│  Owner: Unassigned             │
└───────────────────────────────┘
```

**Fields**, all already computed by `EventCommandHeader::for()` — no new
data:

| Field | Source | Notes |
|---|---|---|
| Name (+ sub-brand tail) | `title()` | Already splits `"X . Y"` names |
| Client | `$event->client?->name` | |
| Health | `health.score` | Same figure the mission-card `hub` variant shows today |
| Readiness | `readiness.pct` | Same gate-based % |
| Date range | `$event->starts_at`/`ends_at` | |
| Location | `$event->city`, `$event->country` | |
| Owner | not currently on `EventCommandHeader` — see below | |
| Stage | `$event->stage` via `Workflow::label()` | |

**Owner** is the one field the Event Core needs that `EventCommandHeader`
doesn't currently expose at the event level (it has per-task/per-risk
owners, not one "event owner"). Two honest options, to decide during
implementation, not now:
  (a) add an `owner_id` concept to `Event` if the business has one, or
  (b) surface "primary owner" as *whoever the `critical()` action is
  currently assigned to* — already computed, zero schema change, but not
  strictly "the event's owner." This document flags the gap; it does not
  resolve it.

**The CTA slot is contextual, not fixed.** The brief's own example includes
an "Open Event Hub" button under the core card — correct when the Event
Core is reused *outside* the Hub (Command Center, Mission Board's Radar
quick-select, a future Portfolio card), where the whole point is "click to
go there." **Inside the Hub itself, that CTA is meaningless** — you are
already there. Recommendation: the Event Core is one component with a
`context` prop:

- `context="hub"` (this phase): no navigate CTA. The card is inert chrome —
  identity and vitals only. A small "Portfolio ↗" ghost link (parity with
  today's header) is the only exit.
- `context="portfolio"` / `context="command-center"` (future, out of
  scope): shows "Open Event Hub" as today's Mission Board cards do.

This is the same normalization pattern `mission-card.blade.php` already
uses (`variant="hub"` vs `variant="board"` reading one shared data shape
differently) — Orbit's Event Core should be built as a sibling variant of
the same component family, not a new one.

---

## 4. Orbit node anatomy

Eight nodes, one per journey stage, unchanged set and order:
`Overview · Brief · Planning · Programme · Operations · Commercial ·
Control · Closeout`.

**Six states, precisely derived — no new backend logic, only a
presentation-layer classification over data `EventCommandHeader` already
returns:**

```
state(stage) :=
    if stage.key == activeStage.key           → ACTIVE
    else if any tab in stage has
         attention[tab].tone == 'alarm'        → BLOCKED
    else if any tab in stage has
         attention[tab].tone == 'wait'         → WATCH
    else if stage.index < activeStage.index    → COMPLETE
    else if any tab in stage has
         meters[tab].pct > 0                   → PENDING
    else                                        → FUTURE
```

(Precedence top-to-bottom: a stage that is both "before the active one" and
carrying an overdue task is shown as **Blocked**, not **Complete** — a
completed-looking stage that actually has an unresolved alarm is exactly
the silent failure this redesign should not introduce.)

| State | Meaning | Visual (on the dark orbit field) |
|---|---|---|
| **Active** | The current stage | Enlarged, teal glow, expands into §5 |
| **Complete** | Behind the active stage, nothing alarming left in it | Solid teal ring, small check glyph |
| **Watch** | Not active, has something waiting (`wait` tone) | Amber ring, soft pulse *on state-entry only* (see Law-5 note below) |
| **Blocked** | Not active, has something overdue/critical (`alarm` tone) | Red ring, filled dot |
| **Pending** | Ahead of the active stage, but already has some data | Teal outline, hollow dot — "started early" |
| **Future** | Ahead, untouched | Grey outline, dim label, 40% opacity |

**Every node is a real link** (`route('events.hub', [$event, 'tab' =>
$door])`, same `$door`-resolution the current strip already computes) — the
orbit is navigation, not just a status board, matching the brief's "three
things at once" requirement.

**Motion discipline**, following the one existing motion rule already
written down for this design system (`docs/orbit-ia-brief.md`, note 2):
*transitions* on interaction (hover scale, active-state glow-in) are
unrestricted; *idle loops* are budgeted at two per screen. If Watch/Blocked
states get a pulse, it plays once on arrival, not forever — an orbit with
three permanently-breathing nodes reads as anxious, not premium.

---

## 5. Active stage anatomy

The one node that's more than a dot. Expands in place (does not detach into
a separate panel):

```
┌───────────────────────────────────┐
│  04 · PROGRAMME               ●   │  ← state-coloured dot, not a badge
│                                     │
│  62% Ready          2 Issues       │
│                                     │
│  Next Action                       │
│  Confirm keynote speaker           │
│                                     │
│  ○ Agenda   ○ Speakers   ○ Preview │  ← satellites, §6
└───────────────────────────────────┘
```

**Fields and their exact source:**

| Field | Computation | Existing source |
|---|---|---|
| `62% Ready` | Average of `meters[tab].pct` across the stage's own tabs (Programme = `agenda`, `speakers`) | `EventCommandHeader::meters()` |
| `2 Issues` | Count of `attention[tab]` entries across the stage's tabs | `EventCommandHeader::attention()` |
| `Next Action` | `critical()` if `critical.tab` falls inside this stage; otherwise the nearest open, dated task among the stage's tabs | `EventCommandHeader::critical()` (already worst-first) |

If `critical.tab` belongs to a *different* stage than the one currently
active (a severe risk sitting in Control while you're working Programme),
that is exactly what the Blocked/Watch ring on the Control node already
communicates — the active stage's own card does not need to borrow it.

---

## 6. Satellite module architecture

**This is the section that actually retires the sticky secondary nav row.**

Today, `$journey[$i]['tabs']` (e.g. Programme → `['agenda', 'speakers']`,
Operations → `['venue', 'transportation', 'accommodation', 'catering',
'suppliers', 'exhibition', 'attendees']`) already exists and already
filters the current sticky row down to "only this stage's enabled tabs."

**Proposal: render that same filtered list as satellites orbiting the
active node**, instead of as a bar underneath the whole ring:

- Each satellite = one existing `HUB_TABS` entry (label, icon, route — all
  unchanged), positioned in a short arc around the active node rather than
  in a horizontal strip.
- Each satellite carries its own `attention[tab]` badge if one exists,
  exactly as today's pills do (`n['count']`).
  Colour continues to come from `Event::MODULE_COLORS`, so "Agenda" is
  still teal, "Venue" still amber, etc., unchanged from today's dot colour
  on each pill.
- **Desktop only.** A true arced-satellite layout needs real orbit
  geometry, which only desktop has room for. Tablet and mobile keep the
  filtered tab list as a plain horizontal scroller (§8–10) — same data,
  same order, no arc. This is a deliberate, stated exception: the orbit
  *metaphor* is desktop-native; the orbit *data model* (event → stage →
  its tabs) is universal and degrades to a list everywhere else without
  losing anything real.

**What does not change:** the number of tabs, their routes, which ones are
enabled per event (`$event->moduleEnabled($key)`), or which stage owns
which tab (`Event::HUB_TABS` and the `$journey` map are untouched).

---

## 7. Priority Area placement

Three options evaluated, against the current baseline (directly under the
header, full-width, 3-column grid — unchanged since Phase E).

**Option A — beside the Orbit** (desktop only; a right-hand column next to
the ring)
- *For:* Puts "what needs you" in the same glance as "where you are."
- *Against:* A circular/radial hero next to a rectangular list wastes the
  ring's negative space or forces the ring smaller than it wants to be.
  Fighting for the same horizontal band as the thing that's supposed to be
  the page's calm center actively works against "premium, not cluttered."
  No graceful tablet/mobile equivalent — becomes "under" on every smaller
  breakpoint anyway, which means two different layouts to maintain for one
  component.

**Option B — under the Orbit** (full width, matches today's actual
position relative to the header)
- *For:* Cheapest possible change — the component and its position relative
  to *the page* barely move, only what's above it changes shape. Keeps the
  orbit uncluttered as the page's one hero moment. Matches the read-order
  users already have muscle memory for (mission at a glance, then what
  needs me, then go do the work).
- *Against:* None significant. This is the conservative choice.

**Option C — integrated into the Event Core**
- *For:* Maximum information density in one glance.
- *Against:* The Event Core (§3) is already carrying seven fields in a
  small center card. Open Risks / Pending Approvals / Escalations each
  need a count *and* their worst item to be genuinely useful (today's
  Priority Area shows title + tier + link, not just a number) — cramming
  that into the smallest, most central element on the page is the fastest
  way to make it feel busy rather than calm, which is the opposite of what
  a "center mission object" is for.

**Recommendation: Option B, with one small addition (a "B+" reading, not a
fourth option).** Keep the Priority Area exactly where it is today,
full-width, directly under the Orbit. Additionally, give the Event Core a
**three-number footer strip** — just counts (`⚠ 2 · ⏳ 1 · ⛔ 0`), no
titles, no links, reusing the same `$totalCount`-style tallies
`priority-area.blade.php` already computes — so the center object can
answer "is everything fine?" without forcing a scroll, while the full,
actionable detail (title, tier, link) stays in the roomier full-width
strip below. This gives Option C's at-a-glance benefit without its
crowding cost, and costs nothing structurally: it's the same three numbers,
rendered twice, once as a glance and once as a worklist — the same
duplication-free principle `EventMission` and `EventCommandHeader` already
apply everywhere else in this codebase (one computation, multiple views).

---

## 8. Desktop mockup

See the linked artifact, "Desktop" panel. Summary: full radial orbit
(≈560px field) on a dark navy-gradient hero card sitting inside an
otherwise light page — same visual contract as the existing Radar hero
(§1). Event Core centered, 8 nodes at equal radius, active node enlarged
with satellites arced above it, three-count footer strip on the core,
Priority Area full-width immediately below the hero card.

## 9. Tablet mockup

See the linked artifact, "Tablet" panel. The ring compresses (~340px) and
loses the satellite arc — the active stage's tabs drop to a horizontal
scroll strip directly beneath the (still circular) orbit, visually grouped
under it by proximity and a connecting rule rather than by arc geometry.
Event Core keeps every field; node labels abbreviate (`Prog.` not
`Programme`) below ~500px of ring diameter.

## 10. Mobile mockup

See the linked artifact, "Mobile" panel. **The literal circle is dropped —
deliberately, not as a fallback compromise.** A ring that has to compress
below ~280px stops being readable as a ring and starts being readable as
"eight dots near each other," which is worse than admitting the metaphor
doesn't fit and switching representations. Mobile instead gets:

```
[Event Core — compact bar: name, health dot, readiness %]
[●──●──●──●──●──●──●──●]  ← horizontal stage rail, same 6 states as colour, same order
     Programme (active, labeled beneath the rail)
[62% Ready · 2 Issues · Confirm keynote speaker]
[Agenda] [Speakers] [Preview]  ← satellites as a horizontal chip scroller
```

The **conceptual hierarchy is identical** to desktop — Event → stage
progress → active stage detail → satellites — only the *geometry*
changes, from radial to linear. This is the same "same model, different
shape per breakpoint" principle already applied to Radar → Table on the
Events Portfolio page in Phase C.1 (radial on desktop's Radar view, dense
rows on Table view, same `EventMission::for()` underneath both).

---

## 11. Pros and cons

**Pros**
- Makes the event, not the stage, the visual subject — directly answers
  the brief's core idea.
- Adds real state (Watch / Blocked / Pending) the current strip cannot
  express at all today, using data that already exists and is already
  computed once per page load.
- Retires the visually disconnected secondary nav row by turning its
  existing content (stage → tabs) into satellites, rather than deleting
  functionality.
- Reuses an already-shipped, already-approved dark-hero pattern
  (`mission-radar.blade.php`), so the "new" visual language is one
  breakpoint of change, not a from-scratch system.
- Zero new backend computation; zero route changes; the `?tab=` contract
  every deep link, bookmark, and test currently relies on is untouched.

**Cons**
- A radial layout is a genuinely harder responsive problem than a strip —
  §8–10 propose three distinct geometries for one component, which is more
  surface area to design, build and keep visually coherent than today's one
  flexbox row that just wraps.
- The Event Core's "owner" field has no clean data source yet (§3) —
  either a small schema question needs answering, or the field ships as an
  honest placeholder for one more phase.
- A glowing/animated hero, done carelessly, is exactly the kind of change
  that reads as "decorative" rather than "informative" — the Law-5 motion
  discipline in §4 has to be enforced at build time, not just proposed
  here, or the orbit becomes the thing every future design review has to
  fight to calm back down.
- This is the same shape of ambition — "a radial, orbiting navigation
  system" — as the platform-wide ORBIT sidebar concept that shipped and
  was then reverted in July 2026 (`docs/orbit-ia-brief.md`,
  `docs/orbit-migration-plan.md`). See §13.

---

## 12. Migration strategy

Proposed for a future implementation phase — **not this one.**

1. **Build the Orbit Journey as a new, additive component**
   (`x-eo.orbit-journey`, alongside the existing `x-eo.mission-card`
   family), consuming the exact same `$journey` array and
   `EventCommandHeader::for()` output `hub.blade.php` already produces.
   No existing component is modified in this step.
2. **Feature-flag it per request** (e.g. `?journey=orbit` during
   development, or a `.env` flag) so it can render side-by-side with the
   current strip on a real event without touching the default path anyone
   else is using.
3. **Swap `hub.blade.php`'s journey block only** once the new component is
   visually and functionally verified — the header above it and the
   `@includeIf('events.hub.'.$tab, ...)` workspace below it do not move or
   change in this step.
4. **Retire the old `.eo-journey-strip` CSS and the sticky secondary nav
   row** only after the swap has been live and verified — not deleted
   speculatively ahead of time, matching this session's established
   grep-before-delete discipline.
5. **The Event Core component variant question (§3)** — build it as a new
   `context="hub"` variant inside the existing `mission-card` family rather
   than a standalone component, so a future Command-Center or
   Portfolio-facing reuse (`context="portfolio"`) is a variant addition,
   not a second implementation of the same data.

At every step, `events.hub`'s route signature, the `?tab=` query contract,
and every `route('events.hub', [$event, 'tab' => ...])` call site across
the app remain byte-for-byte unchanged — nothing downstream of the URL
needs to know the journey changed shape.

---

## 13. Risks

- **Direct precedent for reverting an "orbit"-branded navigation concept
  exists in this codebase, seventeen days before this request.** In July
  2026 a platform-wide ORBIT redesign — dark radial *sidebar* navigation
  with 12 modules orbiting a "Command Center" core, plus a full "Command
  Canvas" workspace replacement — shipped, then was reverted back to the
  Command Center design because the user, shown two reference screens,
  preferred the earlier look (`orbit-migration.md`; cost measured at the
  time as 56 commits / 426 files / ~43,000 lines to fully hard-reset).
  **This proposal is a different, much smaller thing** — one component
  (the Journey strip) inside one page (the Event Hub), not the app shell,
  not global navigation, not a canvas replacing every workspace — but the
  *word* "Orbit" and the *shape* of the idea ("a radial thing instead of a
  linear thing") are close enough to the reverted concept that it is worth
  saying explicitly: this is not ORBIT-the-sidebar back from the dead. If
  it ships and doesn't land, the migration strategy in §12 (additive,
  flagged, swapped in one file, easily reverted) is what keeps this risk
  cheap instead of expensive.
- **Radial layouts are harder to keep calm than strips.** A strip that's
  "too much" usually just needs quieter colours. A ring that's "too much"
  usually needs re-drawing. Getting §4's state colours and §4's
  once-not-forever motion rule right the first time matters more here than
  it would for a simpler component.
- **Eight is a lot of nodes for a ring at small sizes.** The tablet/mobile
  geometry changes in §9–10 exist specifically because 8 nodes on a
  ~300px circle stop being legible — if a future stage gets added (a 9th
  journey stage), the desktop ring also starts to feel crowded well before
  tablet does, and the layout should be checked against that before
  committing to a fixed node count assumption anywhere in the build.
- **The Event Core owner field (§3) is a real, unresolved data gap** — not
  a design risk, a data-model question that implementation will hit on
  day one.

---

## 14. Final recommendation

**Proceed to prototype**, scoped exactly as this document scopes it: one
component, inside the Event Hub only, additive and flagged per §12, reusing
the Radar hero's already-approved dark-field visual language rather than
inventing a new one, with:

- **Priority Area: Option B** (full-width, under the Orbit) **plus** the
  three-count footer strip on the Event Core (§7).
- **Node states: the six-state precedence rule in §4**, computed entirely
  from `EventCommandHeader::attention()` / `meters()` — no new backend
  work.
- **Satellites: desktop-only arc; tablet/mobile keep the existing filtered
  horizontal tab list**, unchanged data, unchanged routes.
- **Motion: transitions unlimited, idle loops capped at two**, applied to
  Watch/Blocked states as arrival-only pulses.

The single biggest reason to say yes: **almost everything this proposal
needs already exists** — the stage→tab grouping, the per-module
percentages, the per-tab attention signals, the worst-first critical
action, and a shipped, approved dark-radial hero pattern one page away.
This is a re-skin of already-correct data, not a new subsystem — which is
exactly the kind of change the July revert shows this app can afford to
try, and afford to walk back if it doesn't land.

**Stop here.** No implementation, no route changes, no file other than this
document and its companion mockup artifact were touched to produce this
phase.

---

## Addendum — v2 refinement (still architecture only)

Feedback on the v1 mockup, direction approved, weight rebalanced:

- **Visual weight flipped from Orbit 70% / Event 30% to Event 70% / Orbit
  30%.** The Event Core is now the dominant element (a large, ~320px white
  card with the full field set at full type size); the ring recedes to
  small, quiet 11px dots that read as context around it, not competition
  with it.
- **The active stage now visibly attaches to the orbit** via a dashed
  connector stem (with terminal dots) running from the active node down to
  its satellite cluster, rather than the relationship being implied by
  proximity alone.
- **Satellites redesigned as mini modules, not pills** — each is a small
  bordered card (colour-accented left edge from `MODULE_COLORS`, an icon
  chip, the module name, a meta line, an attention badge when relevant),
  matching the shape of the app's own small module cards rather than a
  chip strip.
- **Palette flipped from dark to significantly lighter.** The v1 hero
  deliberately borrowed the Portfolio Radar view's dark field; v2 drops
  that borrowing specifically for this component and uses a soft
  white-to-workspace wash instead. State colour (teal / amber / red /
  outline / dim) carries all the same meaning without needing a dark
  ground to read against.
- **Unchanged, confirmed by the same feedback:** Priority Area stays
  full-width directly under the orbit (§7's Option B recommendation
  stands); the Event stays the one center object (§2's core idea stands).

New mockup: **Event Hub Orbit Journey v2** (desktop / tablet / mobile,
linked from the same delivery as this document). v1 remains published
separately for side-by-side comparison. Nothing in this addendum changes
§4's node-state derivation, §6's satellite data source, or §12's migration
strategy — only the visual proportions, the satellite component's shape,
and the field's colour treatment changed.

---

## Addendum — v3 refinement (final pass, still architecture only)

v2 approved as the preferred direction. Final feedback, before
implementation:

- **Event Core expanded, still dominant.** Added **Client** and
  **Location** as first-class labelled fields (previously Client was
  subtitle text, Location wasn't shown at all) alongside Health, Readiness,
  Days out and Owner — six fields in a 3-column grid. Next Action keeps its
  own highlighted block underneath, unchanged in kind. Every added field
  still comes from data `EventCommandHeader`/`Event` already expose — no
  new computation. The card grew (320px → 348px) to hold the extra fields
  without crowding; it remains the largest, first-read object in the
  region, per feedback item 1.
- **Stages upgraded from dots to readable stage cards.** Each of the 8
  stages is now a small bordered card — index, label, and a one-line status
  ("✓ Complete," "2 issues," "Blocked," "Started early," "Not started") —
  with a coloured top edge carrying the same state colour §4 already
  defines. Legible without hovering or squinting at an 11px dot.
- **Orbit progress visualised as a segmented ring.** A conic-gradient arc
  runs behind the stage cards, one 45° wedge per stage, coloured by that
  stage's state — solid teal where the journey is genuinely done, amber/red
  where a stage (ahead or behind) needs attention, a pale wedge for a stage
  already started early, dim grey for what's still ahead. This reads "how
  far along" and "is everything behind me actually settled" from the ring
  itself, not just from each card individually — directly answering
  feedback item 4. The wedge boundaries are the same eight 45° slots the
  node positions already use (§4), so no new angle math is introduced
  beyond what v1/v2 already had.
- **Confirmed: the Orbit is a full replacement, not an addition.** There is
  exactly one journey element on the Event Hub page in this design — the
  current `.eo-journey-strip` and its sticky secondary "doors" row are
  retired entirely per §12's migration strategy, not kept alongside the
  Orbit. The mockup carries an explicit on-page confirmation of this so it
  isn't left to inference.

New mockup: **Event Hub Orbit Journey v3** (desktop / tablet / mobile).
v1 and v2 remain published for comparison. One real layout bug was found
and fixed while building this pass: the progress ring's colour band was
sized to sit *underneath* the (now-larger) Event Core card, making it
invisible — corrected by moving the ring's radius outside the Core's
footprint and confirmed visually across all three breakpoints before
publishing.

This is the final mockup before implementation. §4 (node-state rule), §6
(satellite data source), §7 (Priority Area placement), and §12 (migration
strategy) all stand unchanged — v3 only changes what the Core shows, how a
stage is drawn, and how progress is read.
