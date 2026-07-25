# ORBIT — information architecture brief

**Given 25 Jul 2026, with two reference screens.** This governs sessions 4–7.

**Scope guard, stated by the owner:** do NOT redesign the visual theme, palette,
typography, branding, spacing, or the Orbit Command Center concept. This is only
about *how information is displayed, organised, interacted with and navigated*.

The direction stays: Orbit Command Center · ~80% light / 20% dark · premium
executive SaaS · mission-control feeling · Apple-level simplicity · Linear-level
usability.

**The core idea: this is an Event Operating System, not an Event ERP.** Users
should feel they are *operating* an event from a command centre, not editing
records. Every screen should be alive, contextual and operational.

---

## Shell

| Region | Contents |
|---|---|
| **Command ribbon** (top) | Event name · dates · venue · universal search · Ask AI · quick create · notifications · profile |
| **KPI ribbon** (dark) | Health Score · Participants · Days Left · Open Tasks · Budget Used · Suppliers · Approvals — a real-time snapshot |
| **Orbit nav** (left) | 12 modules around a `COMMAND CENTER` core |
| **Workspace** (centre) | **Adaptive** — changes completely per module. No generic dashboards. |
| **Event Pulse** (right) | Health · risks · approvals · alerts · budget · performance. **Changes with the active module.** A live operational monitor. |
| **AI Event Director** (right) | Not a chatbot — a **Chief of Staff**: daily briefing, risks, recommendations, forecasts, suggested actions |
| **Command dock** (bottom, floating) | Home · Calendar · Tasks · Budget · Venue · Speakers · Suppliers · Reports · AI |

## Orbit modules (12)

Overview · Planning · Tasks · Agenda · Speakers · Venue · Suppliers · Exhibition ·
Sponsors · Reports · Documents · Settings

Behaviour: the active module glows and expands; the orbit reacts on hover; motion
is smooth when switching; selecting a module updates the **entire workspace** and
the contextual panels immediately.

The remaining hub modules (Brief, Contract, Budget, Transport, Accommodation,
Attendees, Risks, Approvals) stay reachable from the dock, ⌘K and cross-links —
they are not on the orbit. `Event::orbitRings()` is the single source of truth.

## Per-module workspaces

| Module | The view it gets |
|---|---|
| **Planning** | **Mission Control Timeline** — phases Discovery → Planning → Production → Marketing → Registration → Execution → Live Event → Close-Out → Post Event, with interactive milestones, dependencies, critical path, phase health, resource allocation |
| **Tasks** | **Command Board** — Kanban / List / Calendar / Timeline; cards carry owner, progress, deadline, priority, dependencies; drag-drop, smart grouping, hover actions, quick edit, AI recommendations |
| **Venue** | **Digital Event Twin** — the most innovative module. An interactive venue map with clickable areas (Main Ballroom, Breakouts, Registration, Exhibition, VIP Lounge, Backstage, Hospitality, Loading). Selecting an area updates tasks, resources, risks, team assignments and progress. |
| **Agenda** | Session timeline · track view · speaker view · room view — what's happening, where, when, who |
| **Speakers** | Cards with status, travel, accommodation, session assignments, pending actions, visual progress |
| **Sponsors** | Pipeline: Prospect → Contacted → Negotiating → Confirmed → Delivered → Renewed |
| **Suppliers** | Operational partners: status, contracts, payments, deliverables, risks, performance score |
| **Reports** | Operational analytics — executive summaries, health, trends, costs, attendance, risks, performance. Not a traditional reporting layout. |

## Interaction

Hover reactions · smooth transitions · intelligent motion · context switching ·
selection highlights · animated progress · real-time feedback. Premium and
*useful* — never decorative.

---

## Notes taken while reading the references

1. **The two reference screens disagree on the accent.** The first uses ORBIT
   gold; the second uses violet/indigo for the core ring, the create button and
   the active dock item. Since the brief says not to change the palette, we stay
   on ORBIT gold and treat the violet as an artefact of the mock.

2. **Law 5 vs. "everything feels alive."** ORBIT permits two looping animations
   in the whole system (overdue pulse, live-phase breathe). The brief asks for
   pervasive motion. These are reconcilable: *transitions* on interaction are
   unlimited, *loops* stay at two. Motion responds to the user or to a state
   change; nothing idles.

3. **Gold budget.** Law 1 allows gold on three things per viewport: primary
   action, current position, the single most important number. With a glowing
   orbit node, a gold dock item and a gold create button, that budget is spent
   before the workspace renders. Resolution: the orbit node is the "current
   position", the dock mirrors it without gold, and the workspace gets the
   primary action.
