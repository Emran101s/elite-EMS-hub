# Command Center Architecture

> **This document describes the target information architecture.** It does not authorize
> rebuilding navigation or the sidebar. Today's implementation (see
> [02-platform-architecture.md](02-platform-architecture.md)) already covers most of this
> content under a different grouping (`Event::HUB_MODULES`'s cluster tags). This doc exists
> so future work can converge toward one coherent structure deliberately, not so it gets
> rebuilt on day one in Cursor.

## The two command centers

```
Elite Business Hub Platform
├── Company Command Center      (portfolio altitude — today's Command Center homepage)
│   ├── Client Accounts
│   ├── Workspaces
│   └── Events
└── Event Command Center        (event altitude — today's per-event hub)
    ├── Planning Command Center
    ├── Operations Command Center
    ├── Commercial Command Center
    ├── Experience Command Center
    └── Intelligence Command Center
```

## Company Command Center (portfolio level)

Already built as the homepage: a signal engine over every active event (overdue, approvals,
blocked, money, risks), plus Events (cards/list/calendar), Projects, Finance, Sponsors,
Reports, Team, Settings.

**Client Accounts** and **Workspaces** are not yet modeled as first-class objects — today an
event's client lives on the event/contract/CRM records directly. Introducing a dedicated
Client Account layer is future work, and it's the natural seam multi-tenancy would later
attach to (not now — see [01-product-vision.md](01-product-vision.md)).

## Event Command Center (event level)

Target grouping of the current 19 hub modules into five sub-command-centers:

### 1. Planning Command Center
Timeline, Milestones, Tasks, Calendar, Approvals, Risks, Notes
— maps to today's **Planning**, **Tasks**, **Approvals**, **Risks** modules, plus **Event
Brief** as the living planning document.

### 2. Operations Command Center
Venue, Rooms, Transportation, Accommodation, Catering, Logistics, On-site operations, Team
resources
— maps to today's **Venue**, **Transport**, **Accommodation**, **Food & Beverage**,
**Suppliers** modules. "On-site operations" already exists in miniature as Transport's Live
Operations (Now/Next/Later) and Dispatch Board views — the deepest module in the product
today.

### 3. Commercial Command Center
Budget, Forecast, Suppliers, RFQs, Quotations, Contracts, Invoices, Payments, Financial
reports
— maps to today's **Budget**, **Suppliers**, **Contract** modules and the finance/invoice
screens. RFQs and formal Quotations as first-class objects are **not yet built** — today,
supplier engagement is a status field (confirmed/quoted/contracted/issue) on the
event–supplier relationship, not a document workflow. See
[09-development-roadmap.md](09-development-roadmap.md).

### 4. Experience Command Center
Agenda, Sessions, Speakers, Attendees, Registration, Sponsors, Exhibitors, VIP guests
— maps to today's **Agenda**, **Speakers**, **Attendees**, **Sponsors**, **Exhibition**
modules, plus the public registration flow and badge/QR check-in system.

### 5. Intelligence Command Center
Event health, KPIs, Reports, Analytics, AI insights, Lessons learned, post-event evaluation
— maps to today's **Reports** module and the Health Engine (already computes the 0–100
score with an explainable attention list). AI insights, lessons-learned and formal
post-event evaluation are **not yet built**.

## Event life cycle

See [04-event-life-cycle.md](04-event-life-cycle.md) for how the seven-stage
Initiate → Plan → Prepare → Execute → Monitor → Close → Evaluate model maps onto the
platform's actual `Event::STAGES`.
