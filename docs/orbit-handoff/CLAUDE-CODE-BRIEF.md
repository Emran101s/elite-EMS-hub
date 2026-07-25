# ORBIT v2 — Implementation Brief for Claude Code

**Repo:** Elite Event Hub (Laravel · Livewire 3 · Alpine · Tailwind)
**Design source of truth:** `orbit-v2-hub-system.html` (in repo root)
**Owner:** Emran Aletan
**Status:** Not started

---

## How to use this file

1. Copy `orbit-v2-hub-system.html`, `setup-orbit.sh` and this file into your repo root.
2. Commit them: `git add -A && git commit -m "docs: ORBIT v2 design system"`
3. Run `./setup-orbit.sh`. It extracts the design tokens and component CSS out of
   the HTML byte-for-byte and writes `CLAUDE.md` guardrails. It writes no Blade,
   PHP or JS — that is Claude Code's job.
4. Add the four `@import` lines it prints to `resources/css/app.css`, then commit.
5. Open Claude Code and paste **Session 1** from `KICKOFF-PROMPT.txt`.
6. One phase per session. Review and merge before starting the next.

**Why a script rather than letting the agent copy the CSS:** there are 64 design tokens
across two themes and 141 component classes. An agent retyping them will introduce silent
errors, and every wrong hex quietly breaks a verified contrast guarantee. The CSS
is mechanical, so a script does it. Everything that requires judgement — the Blade
components, the screens, the data model — is left to Claude Code.

**The single most important rule: do not ask for a redesign. Ask for a migration.**
"Redesign my app to look like this" produces one beautiful screen and forty broken ones. Every instruction below is bottom-up on purpose — tokens first, screens last — so the app improves at every step and is never half-converted.

---

## Kickoff

All prompts live in **`KICKOFF-PROMPT.txt`** — one per session, plus a set of
corrective prompts for when the agent drifts. Paste Session 1 and nothing else.

Do not open with "redesign my app to look like this." That produces one beautiful
screen and forty broken ones. Every instruction in this brief is bottom-up on
purpose — tokens first, screens last — so the app improves at every step and is
never half-converted.

---

## Context Claude Code needs (it will read this section)

### What the product is
Elite Event Hub manages large events end to end: a portfolio Command Center across all events, then per-event modules — Tasks, Budget, Agenda, Venue, Suppliers, Speakers, Attendees, Exhibition, Sponsors, Transport, Accommodation, Event Brief, Contract, Risks, Approvals, Documents, Reports, Settings. Reference event: *The First World Public Summit*, Nov 8–12 2026, St. Regis Amman, 650 participants, JD 350,000 budget.

### What is wrong with the current UI
- **Gold is used on everything** — buttons, borders, rings, headings, icons — so nothing reads as more important than anything else.
- **Every card has the same visual weight.** A dashboard of nine equal boxes gives the user no entry point.
- **The module rail is a flat list of 20 items**, undifferentiated, scanned in full on every visit.
- **Dark chrome sits on a light body** with no bridge between them; the header reads as a banner.
- **Numbers are set in the UI typeface**, so currency columns don't align and magnitudes can't be compared at a glance.
- **Donut charts with legends** are used for simple two- and three-value splits.

### What ORBIT changes
| Principle | Rule |
|---|---|
| One accent works | **Iris** (indigo) drives every action, focus, active state and progress fill. **Gold** is reserved for the brand mark, VIP/premium tiers, and exported documents. Gold is never a button, never a focus ring, never a status. |
| Distance is relevance | Modules orbit the event core. Inner arc = 5 modules touched daily. Outer arc = structure set once. |
| Light is the default | ~70% of screens are light. Dark is a mode entered deliberately for live operations. |
| Numbers are typography | Every figure in the product uses JetBrains Mono with tabular numerals. |
| Stillness is a feature | Motion only for state change and live risk. Two looping animations exist in the entire system. |
| Gravity | Exactly one `data-gravity="hero"` card per screen. |

---

## Design tokens

All tokens live in the `<style>` block of `orbit-v2-hub-system.html`. `setup-orbit.sh`
has already extracted them into `resources/css/orbit-tokens.css`. **Never retype a
value from that file, and never edit it — it is regenerated from the HTML.**

Structure:
- `:root, [data-theme="light"]` — the light palette (default)
- `[data-theme="dark"]` — the dark palette, same variable names
- `:root` — shared: fonts, spacing, radii, easing, durations

Theme switches by setting `data-theme` on `<html>`. Because both themes define the same variable names, no component needs a dark variant.

### Contrast is already verified — do not "improve" the colours
Every accent and signal colour was measured against every surface it can appear on. Lowest meaningful pair clears 4.5:1. Iris on white is 6.25:1. If you change a hex value you break a WCAG AA guarantee. If a colour looks wrong to you, raise it with the owner instead of adjusting it.

### Tailwind wiring

```css
/* resources/css/app.css */
@import './orbit-tokens.css';
@import 'tailwindcss';

@theme {
  --color-canvas:   var(--abyss);
  --color-surface:  var(--hull);
  --color-raised:   var(--deck);
  --color-rim:      var(--rim);
  --color-ink:      var(--ink);
  --color-ink-2:    var(--ink-2);
  --color-iris:     var(--iris);
  --color-gold:     var(--gold);
  --color-vital:    var(--vital);
  --color-ion:      var(--ion);
  --color-flare:    var(--flare);
  --color-critical: var(--critical);
  --font-display: 'Instrument Serif', Georgia, serif;
  --font-sans:    'Inter', system-ui, sans-serif;
  --font-mono:    'JetBrains Mono', ui-monospace, monospace;
  --radius-xs: 8px;  --radius-sm: 11px; --radius-md: 16px;
  --radius-lg: 22px; --radius-xl: 30px;
}
```

Fonts: self-host via Bunny Fonts or `npm i @fontsource/inter @fontsource/jetbrains-mono @fontsource/instrument-serif`. Do not rely on the Google Fonts CDN in production.

---

## Blade component map

Build these under `resources/views/components/orbit/`. Every one is `@props`-driven — no hard-coded content.

| Component | Renders | Key props |
|---|---|---|
| `<x-orbit.card>` | Surface with gravity + accent bar | `gravity` `accent` `title` |
| `<x-orbit.btn>` | All button variants | `variant` `size` `icon` `disabled` |
| `<x-orbit.badge>` | Status pill | `tone` `pulse` |
| `<x-orbit.ring>` | SVG gauge, single value | `value` `tone` `size` `label` |
| `<x-orbit.meter>` | Segmented split bar + legend | `segments[]` `total` |
| `<x-orbit.stat>` | Metric tile | `label` `value` `unit` `delta` `bar` |
| `<x-orbit.kpi-strip>` | Header metric bar | `items[]` `cta` |
| `<x-orbit.row>` | Object row for list modules | `icon` `title` `meta[]` `amount` `action` |
| `<x-orbit.table>` | Data table, right-aligned money | `columns[]` `rows[]` |
| `<x-orbit.nav-arc>` | Arc orbit navigation | `modules[]` `current` `mode` |
| `<x-orbit.dock>` | Fixed bottom dock | `items[]` `current` |
| `<x-orbit.stepper>` | Horizontal phase timeline | `steps[]` `current` |
| `<x-orbit.twin>` | Venue digital twin | `zones[]` `pins[]` `view` |
| `<x-orbit.pulse>` | Keyed metric list + ring | `score` `metrics[]` |
| `<x-orbit.ai-panel>` | AI Director | `greeting` `insights[]` `chips[]` |
| `<x-orbit.alert>` | Feed row / inline alert | `tone` `title` `sub` `time` `action` |
| `<x-orbit.empty>` | Teaching empty state | `icon` `title` `body` `cta` |
| `<x-orbit.field>` | Label + input + help | `label` `type` `affix` `help` |
| `<x-orbit.palette>` | ⌘K command palette (Livewire) | `wire:model.live` |

### Status colour must be decided server-side, once

```php
// app/Support/Tone.php
enum Tone: string {
    case Iris     = 'iris';      // active, in progress, current
    case Vital    = 'vital';     // done, healthy, paid, confirmed
    case Ion      = 'ion';       // open, informational
    case Flare    = 'flare';     // pending, needs approval, watch
    case Critical = 'critical';  // overdue, at risk, over budget
    case Gold     = 'gold';      // VIP, premium tier — NEVER a status

    public static function forHealth(int $pct): self {
        return match(true) {
            $pct >= 85 => self::Vital,
            $pct >= 70 => self::Iris,
            $pct >= 60 => self::Flare,
            default    => self::Critical,
        };
    }
}
```

No Blade template may contain a colour literal. If a view needs a colour, it asks `Tone` for one.

### Theme policy — the 70/30 split

```php
// app/Support/ThemePolicy.php
class ThemePolicy {
    protected static array $dark = ['pulse','run-of-show','twin','transport','check-in'];

    public static function for(string $module, ?Event $event = null): string {
        if (session()->has('theme.override')) return session('theme.override');
        if ($event?->phase === Phase::EventDays) return 'dark';
        return in_array($module, static::$dark, true) ? 'dark' : 'light';
    }
}
```

Light modules (default): Command Center, Event Hub, Budget, Event Brief, Contract, Venue, Suppliers, Attendees, Sponsors, Exhibition, Reports, Documents, Settings.
Dark modules: Event Pulse (live), Run of Show, Digital Twin, Transport board, On-site check-in — plus everything once an event enters the Event Days phase.

Users can override for a session via a header control; the override clears on logout.

---

## Alpine patterns

```js
// resources/js/orbit.js
Alpine.store('orbit', {
  theme: localStorage.orbitTheme ?? 'light',
  init() { document.documentElement.dataset.theme = this.theme; },
  set(t) {
    this.theme = t;
    localStorage.orbitTheme = t;
    document.documentElement.dataset.theme = t;
  },
});
```

Rings animate with a CSS transition on `stroke-dashoffset` — no chart library anywhere in this product:

```blade
{{-- components/orbit/ring.blade.php --}}
@props(['value' => 0, 'tone' => 'iris', 'size' => 88, 'stroke' => 7, 'label' => null])
@php $r = ($size / 2) - $stroke; $c = 2 * M_PI * $r; @endphp
<div class="o-ring" data-tone="{{ $tone }}"
     x-data="{ v: 0 }" x-init="$nextTick(() => v = {{ $value }})">
  <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}">
    <circle class="o-ring__track" cx="{{ $size/2 }}" cy="{{ $size/2 }}" r="{{ $r }}" stroke-width="{{ $stroke }}"/>
    <circle class="o-ring__value" cx="{{ $size/2 }}" cy="{{ $size/2 }}" r="{{ $r }}" stroke-width="{{ $stroke }}"
            stroke-dasharray="{{ $c }}" :stroke-dashoffset="{{ $c }} - ({{ $c }} * v / 100)"/>
  </svg>
  <div class="o-ring__center">
    <div class="o-ring__num" x-text="Math.round(v) + '%'"></div>
    @if($label)<div class="o-ring__label">{{ $label }}</div>@endif
  </div>
</div>
```

---

## The venue digital twin — data first, render second

The 3D venue view is the only part of the mockup that is not plain CSS. Build it in three phases and **do not merge the renderer before the data model exists.**

### Data contract — build this first

```php
// Migration: venue_zones
Schema::create('venue_zones', function (Blueprint $t) {
    $t->id();
    $t->foreignId('venue_id')->constrained()->cascadeOnDelete();
    $t->string('name');
    $t->string('type');              // main | exhibition | catering | vip | registration | breakout | back_of_house
    $t->decimal('x', 5, 2);          // % of plan width
    $t->decimal('y', 5, 2);          // % of plan height
    $t->decimal('w', 5, 2);
    $t->decimal('h', 5, 2);
    $t->unsignedInteger('capacity')->default(0);
    $t->unsignedTinyInteger('setup_progress')->default(0);
    $t->string('status')->default('planned'); // planned | in_setup | ready | issue
    $t->json('meta')->nullable();
    $t->timestamps();
});
```

`GET /api/events/{event}/venue/zones` returns:

```json
[
  { "id": "main-hall", "name": "Main Summit Hall", "type": "main",
    "rect": { "x": 2, "y": 3, "w": 47, "h": 50 },
    "capacity": 1200, "setup": 65, "status": "in_setup",
    "issues": 1, "sessions": 11 }
]
```

### Phase 1 — CSS-3D isometric plan (≈3 days)
Working reference is in `orbit-v2-hub-system.html`, section 06. Zones are absolutely positioned divs inside a `rotateX(56deg) rotateZ(-42deg)` plane; pins are children of the same plane counter-rotated by the exact inverse so labels stay upright. Colour comes from `status`, size from `rect`. Includes a flat-plan toggle. No dependencies. Prints correctly.

### Phase 2 — Three.js scene (≈2 weeks)
Same zone JSON, extruded into real geometry with orbit controls, wall heights and seating blocks. Pins become `CSS2DObject` instances reusing the exact `.o-pin` markup. Lazy-load the bundle; the CSS version remains the fallback and the print view.

### Phase 3 — real venue floor plans
Import the hotel's DWG/PDF plan, trace zones once per venue, reuse across every event at that venue. This is the version that earns its keep on load-in day.

---

## Phases of work

Ship one PR per phase, behind a feature flag, with before/after screenshots in the description.

### Phase 0 — Audit (no code changes)
Produce `docs/orbit-migration-plan.md` per the Kickoff message.

### Phase 1 — Tokens & type (~2 days)
- `resources/css/orbit-tokens.css`, `orbit.css` and `orbit-theme.css` are **already
  generated** by `setup-orbit.sh`. They are build artefacts — never edit them by
  hand. If a token must change, change the HTML and re-run the script.
- Self-host the three fonts.
- Replace every hard-coded hex, font-size, radius and shadow across the codebase with a token.
- Wire `data-theme` on `<html>` plus the Alpine store.
- **Acceptance:** grep finds zero hex colours in `resources/views/**` and `resources/css/**` outside the token file. Toggling `data-theme` re-skins the entire app without visual breakage.

### Phase 2 — Primitives (~4 days)
- Build every component in the map above as a Blade component.
- Create route `/design` (auth-gated) rendering a gallery of all of them from the real components — your living version of the HTML file.
- Find-and-replace usages across existing views.
- **Acceptance:** `/design` renders every component in both themes. No view constructs a button, badge or card by hand.

### Phase 3 — Navigation (~3 days)
- Arc orbit nav, icon-rail collapse below 1280px, bottom dock below 900px.
- Retire the 20-item flat sidebar.
- ⌘K command palette as a Livewire component searching events, modules, tasks, suppliers.
- **Acceptance:** every module reachable in ≤2 keystrokes. Badge counts render from live queries, not hard-coded.

### Phase 4 — Screen recomposition (~2 weeks)
Order matters: Command Center → Event Hub → Venue (with Phase-1 twin) → Budget → the list modules (Venue/Supplier/Attendee/Sponsor share one template) → documents.
- Apply the gravity scale: exactly one hero card per screen.
- Delete cards that don't earn their place rather than restyling them.
- **Acceptance:** each screen opens with a single clear entry point. Gold appears at most 3 times per viewport.

### Phase 5 — Polish (~1 week)
- Theme policy wired to modules and event phase.
- `prefers-reduced-motion` audit.
- Automated contrast test in CI over the token pairs.
- Print stylesheet for Budget, Event Brief, Contract.

---

## Guardrails — put these in `CLAUDE.md` at the repo root

```
## Design system rules (ORBIT v2)

- Never introduce a colour, radius, shadow, font-size or duration that is
  not already a token in resources/css/orbit-tokens.css.
- Gold (--gold) is prestige only: brand mark, VIP tier, premium sponsor,
  exported document headers. Never a button, focus ring, or status.
- Iris (--iris) is the only action colour.
- Max 3 gold elements per viewport.
- Exactly one data-gravity="hero" card per screen.
- Every number in the UI must be wrapped in .o-num or a metric class.
- Status colour comes from App\Support\Tone. No colour literals in Blade.
- Rings (.o-ring) are for single values only. Splits use .o-meter.
  Never add a donut chart.
- Empty states teach the concept. "No data" is not an acceptable string.
- Any new component must be added to the /design gallery in the same PR.
```

---

## What to watch for

- **The sidebar retirement is the riskiest change.** Ship the arc nav behind a flag for your internal team for a week before clients see it. Track which outer-arc modules get expanded most and promote them into the inner arc.
- **Don't let the agent restyle screens in Phase 1 or 2.** It will want to. The whole point of bottom-up is that screens are last.
- **The `/design` route is not optional.** Without a living gallery rendered from real components, the system drifts within a quarter and you are back where you started.
- **Data inconsistency already in your app:** the Contract module shows JD 350,000 collected while Budget shows income JD 0, producing a false −JD 214,498 P&L. Worth resolving before the Budget screen is rebuilt, or the new design will present the wrong number more beautifully.

---

## Session-by-session prompts

**Session 1 — audit**
> Read CLAUDE-CODE-BRIEF.md and orbit-v2-hub-system.html. Do Phase 0 only. Write docs/orbit-migration-plan.md. No code changes.

**Session 2 — tokens**
> Phase 1 from the brief. Extract the token block from orbit-v2-hub-system.html verbatim into resources/css/orbit-tokens.css, self-host the fonts, wire data-theme and the Alpine store, and replace hard-coded values app-wide. Show me a diff summary and confirm the grep acceptance test passes before you finish.

**Session 3 — primitives**
> Phase 2. Build the Blade components in the map, then the /design gallery route. Start with card, btn, badge, ring, meter, stat — show me those six rendered in the gallery before continuing to the rest.

**Session 4 — navigation**
> Phase 3. Arc nav + responsive collapse + bottom dock + ⌘K palette. Feature-flag the sidebar retirement behind `orbit_nav`.

**Session 5+ — one screen per session**
> Phase 4, Command Center only. Use the assembled screen in section 10 of the HTML as reference. Exactly one hero card. Do not touch other screens.

---

*ORBIT v2.0 — Elite Event Hub. Design system by specification, not by screenshot.*
