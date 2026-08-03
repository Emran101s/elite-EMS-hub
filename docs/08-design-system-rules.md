# Design System Rules

## Read this before touching any view

**Do not redesign the sidebar. Do not redesign the navigation. Do not redesign existing
UI screens.** This applies in Cursor exactly as it applied here. The design system below
describes what already exists so new work matches it — it is not an invitation to revisit
it.

## Correcting a stale doc: ORBIT was reverted

`CLAUDE.md` at the repo root, as it stood before this handover, described an in-progress
"ORBIT" design system (`orbit-system.html`, `App\Support\Tone`, `resources/css/orbit-tokens.css`)
as the **incoming** replacement for the navy/gold palette. That migration was **reverted**.
`App\Support\Tone` no longer exists in the codebase, `resources/css/orbit-tokens.css` and
`orbit-tokens.css`/`orbit.css` don't exist, and the app shell, sidebar and Command Center
homepage are back on the navy/gold Command Center system. `CLAUDE.md` has been corrected as
part of this handover (see the diff) so it no longer points Cursor at a system that isn't
there. `orbit-system.html` and `setup-orbit.sh` are still present in the repo root as
leftover artifacts of the reverted attempt — they are inert (nothing loads or generates from
them anymore) and can be removed whenever convenient; they were left in place rather than
deleted during this handover since deleting files wasn't asked for.

## The live design system: Command Center (navy / gold / Playfair)

- **Palette**: navy `#0B1F3A` (900) down through a 50–900 scale, gold `#D4AF37` as the
  accent. Light canvas is the default background — dark is reserved for live/show-day
  contexts, not the whole app.
- **Display type**: Playfair Display for titles and headline numbers (`.pf` class); a
  standard sans for body text and UI chrome.
- **Status colour is centralized, not decorated.** `<x-status-badge :status="...">` is the
  shared component for generic status pills; several models additionally expose their own
  `statusMeta()` / `equipmentStatusMeta()` / `paymentStatusMeta()` static methods
  (`EventContract`, `EventRoom`, `EventSponsor`) precisely because their status vocabularies
  are domain-specific and shouldn't be force-fit into one global colour map — see
  [10-current-codebase-assessment.md](10-current-codebase-assessment.md) for why this
  distinction matters and what it fixed.
- **Money** always renders through `App\Support\Money::forScreen()` / `forDocument()` /
  `abbreviated()` — never a local `number_format()`/currency-symbol closure in a view.
- **Dates** always render through `<x-date :value="..." style="short|document|long|withTime">`
  — never a local `->format('...')` call in a view, unless the value is genuinely not a
  "date shown to a person" (an input's raw ISO value, a time-of-day-only display).

## Plan Studio is the one deliberate exception

The Planning module (Plan Studio) has its own established visual identity and is
**deliberately excluded** from platform-wide restyles. Don't bring it into line with the
rest of the app as a side effect of an unrelated change.

## The paper half

Roughly seventeen PDF documents leave the building and represent the company — they should
read like the company's stationery, not like a screenshot of a screen. Screens and
documents can legitimately use different visual languages where that serves the document
(e.g. `EventContract`'s bilingual English/Arabic layout).

## Hard constraints for any new UI

- Bilingual support (English/Arabic, right-to-left) is load-bearing for contracts and
  briefs — don't assume left-to-right-only layout in anything document-adjacent.
- Money needs precision: multi-currency, cents, tabular alignment, estimate vs actual vs
  committed. Columns must line up (`font-variant-numeric: tabular-nums` where digits stack).
- Design for both extremes of density: 620 attendee rows and an empty `draft`-stage event
  with nothing in it yet (see [02-platform-architecture.md](02-platform-architecture.md)
  for exact volumes).
