# Elite Business Hub — what the platform is

A working description of the product, written to design from. No visual
direction, no theme, no opinions about colour. Just: what it holds, who touches
it, when, and how much of it there is.

---

## 1. The thesis

Most event software helps you *plan* an event. This one is built to **run** one.

The distinction matters for design. Planning software is used at a desk, in
advance, calmly. Running software is used at 06:40 on show day, standing in a
loading bay, one-handed, when a driver hasn't arrived and 40 delegates are
waiting. The same product has to do both.

An event here is a container that holds a budget, a programme, a venue, a guest
list, a fleet, a supplier roster, a set of contracts and a paper trail — and a
health score that watches all of it and tells you what is slipping.

---

## 2. Who uses it

| Role | What they do | Where they are |
|---|---|---|
| **Super admin** | Owns the company account, settings, catalogues, users | Desk |
| **Manager** | Owns events end to end. Approves money, signs contracts, answers to the client | Desk, client meetings |
| **Coordinator** | Does the work. Chases suppliers, builds the agenda, assigns transport | Desk, then show floor |
| **Viewer** | Reads. Usually a client contact or an internal stakeholder | Anywhere, often a phone |

The load is not evenly distributed. A coordinator lives in three or four modules
all day. A manager visits ten modules a week and reads the health score daily. A
client sees documents, not screens.

---

## 3. The lifecycle

An event moves through ten stages, and **what the product needs to be changes at
each one**:

`draft → proposal → confirmed → planning → production → live → completed → closed`
(plus `cancelled` and `on_hold`)

- **draft / proposal** — almost no data. A name, a date, a client. Every module
  is empty. This is where a design most often looks broken.
- **confirmed / planning** — the build-out. Budget lines, agenda, supplier
  outreach. Data arrives unevenly: 41 sessions but zero attendees.
- **production** — everything at once. Peak density. Peak stress.
- **live** — show days. A different product entirely: real-time, glanceable,
  mobile, two-tap actions.
- **completed / closed** — retrospective. Reports, final P&L, archive.

A design that only works at "production" fails for most of an event's life.

---

## 4. The four altitudes

1. **Portfolio** — every event at once. *"What needs me today, anywhere?"*
2. **Event** — one event's overall state. *"Is this thing healthy?"*
3. **Module** — one domain. *"Show me the transport."*
4. **Record** — one movement, one task, one contract. *"Fix this."*

Navigation exists to move between altitudes quickly. Most real work happens at
altitude 3, most decisions at 1 and 2.

---

## 5. Portfolio level

### Command Center
Not a dashboard — a **signal engine**. It scans every active event and emits
signals, each tied to a record and a link:

- **Overdue** — tasks past their date
- **Approvals** — waiting on a human decision
- **Blocked** — deliverables that can't move
- **Money** — payments slipping, budgets over
- **Risks** — open and escalating

Signals can be filtered by lens or focused on a single event. Alongside them:
team workload, deadline watch, next 14 days, portfolio money, delivery journey.

### Events
Cards, list and calendar views over the whole portfolio, filtered by type
(conference, workshop, exhibition, gala, VIP, outdoor) and stage, with starred
favourites. Duplicate, archive, delete.

### Also at portfolio level
Projects · Finance · Sponsors · Reports · Team · Settings, plus a **⌘K command
palette** that searches events, modules, tasks and suppliers.

---

## 6. The 18 event modules

Modules can be **switched on and off per event**. A one-day workshop might run
six; a five-day summit runs all eighteen. The navigation cannot be a fixed list.

### Core

**Overview** — the event's answer to "what's the state of this?" Health score
with its components, what's overdue, what's next, the delivery timeline.

**Planning (Plan Studio)** — a 7-phase delivery journey (Initiation, Planning &
Design, Marketing & Registration, Pre-Event, Execution, Close-Out, Post-Event).
Plan items carry owners, dates, progress, priority, sub-tasks and approval
signatures. Board, list, timeline and gallery views. Exports to PDF.
*Note: this module has its own established visual identity and is deliberately
excluded from platform-wide restyles.*

**Tasks** — the work engine. Six stages (todo → doing → review → approved → done,
plus cancelled), thirteen functional areas (venue, programme, speakers,
marketing, registration, sponsorship, exhibition, production, logistics,
transport, operations, finance, VIP), assignees, due dates, priorities,
checklists. Board / timeline / list / gallery, drag-and-drop between stages,
quick add, a detail drawer.

**Agenda** — days → sessions → rooms, with speaker linkage, tracks, session
types and conflict detection. Produces three separate documents for three
audiences (see §7).

### Money

**Budget** — categories and line items, each with an estimate, an actual, a
payment status, an invoice number, a due date and an optional supplier link.
Income is tracked separately across client, sponsors, exhibitors and other,
producing a real P&L. Contract payments flow in automatically as client income.

**Approvals** — typed requests (budget, contract, payment) with a requester, a
decider and a decision timestamp.

**Risks** — a register with severity scoring out of 25, an owner, a category, a
mitigation and an open/escalated/closed status.

### Physical

**Venue** — venue catalogue, rooms with capacities and layouts, equipment lists,
and a **drag-and-drop room layout builder** (stages, screens, podiums,
entrances, interpretation booths) that exports to PDF.

**Exhibition** — a floor plan builder with booths, sizes and statuses
(available / reserved / sold), exporting to PDF.

**Transport** — the deepest module in the product:
- Movements with **seven statuses**, each with a route, a time, a vehicle and named passengers
- **Driver and vehicle catalogues** with readiness checks and driver-hours tracking
- A **guest pool** of unassigned passengers, populated by xlsx import
- VIP flagging with separate readiness rules; WhatsApp handoff to drivers
- **Live Operations** — a Now / Next / Later view built for a phone on show day, with two-tap actions: start, arrive, delay, issue, no-show
- **Dispatch Board** — lanes per vehicle, a time axis, automatic conflict detection, drag a movement between lanes to reassign
- Six separate PDF outputs

**Accommodation** — hotels, room blocks, allocations, rooming lists with xlsx
import and PDF export.

### People

**Speakers** — name, title, organisation, topic, bio, keynote flag, fee,
confirmation status, session assignments.

**Attendees** — the largest dataset. Ticket types, statuses
(confirmed / registered / pending / waitlist), organisation, job title, VIP flag,
dietary requirements, amount paid, check-in timestamp. Bulk xlsx import with a
downloadable template.

**Suppliers** — a company-wide catalogue with categories and ratings, engaged
per event with a status (confirmed, quoted, contracted, issue). Generates
supplier order PDFs.

**Sponsors** — packages (platinum → bronze), committed and paid amounts, booth
allocation, payment status. Feeds a client-facing sponsorship prospectus.

### Documents

**Event Brief** — a living document that assembles itself from the event's own
data into a collapsible, editable dossier, exporting as a WYSIWYG PDF.

**Contract (Contract Studio)** — many typed contracts per event: client, vendor,
speaker, sponsorship, letter. Each is built from **editable bilingual
(English/Arabic) clause blocks** drawn from a template library with real legal
bodies per type. Signatories with e-signature status that auto-syncs the
contract state. A deck/pipeline view across all contracts. Payments recorded
against each.

**Documents** — a per-module file library with upload and preview.

**Reports** — operational analytics across the event.

---

## 7. The document layer

**Roughly seventeen PDF documents**, all rendered by headless Chrome from the
app's own markup — so what is on screen is what prints.

This is not a side feature. **Half of what this product produces is paper that
leaves the building**: it goes to clients, ministries, sponsors and drivers.

| Audience | Documents |
|---|---|
| **Client** | Event Brief, Contract, Master Transportation Plan, Sponsorship Prospectus, Budget |
| **Team** | Master Schedule, Run of Show, Plan Studio export, Rooming lists |
| **Delegates** | Programme |
| **Suppliers** | Supplier Order |
| **Drivers** | Driver Trip Sheet, VIP Transfer Sheet, Daily Movement Schedule |
| **Venue** | Room Layout, Room Equipment, Exhibition Floor Plan |

These documents have a different job from the screens. They are formal,
often bilingual, and represent the company to an outside party.

---

## 8. Cross-cutting systems

**Health engine** — computes a 0–100 score per event from budget, tasks,
suppliers, venue, agenda and transport, banded into on-track / watch / at-risk.
Critically, it also produces an **attention list** explaining the score: pending
approvals, risks by severity, supplier issues, transport issues, VIP transfers
missing a driver or vehicle, unassigned pool guests, overdue tasks. The score is
never stored — always computed, always explainable.

**Audit log** — every create, update and delete on every record, with a
human-readable summary and the user who did it.

**Permissions** — four roles gate destructive and financial actions.

**Multi-currency** — per-event currency with symbol, FX rates in settings.

**Import / export** — xlsx templates for attendees, rooming lists, transport
manifests and transport plans; round-trips so a corrected sheet updates rather
than duplicates.

**Module enablement** — per event, changing which modules exist.

---

## 9. Real data volumes

Taken from the flagship event (#7, "The First World Public Summit — Arab
World"). **This is the density any design has to survive.** Verified directly
against the live database — two figures below had drifted from what's
actually there (budget lines was 23, is 13; speakers and risks were both
recorded as near-zero and have since been backfilled to match this table).

| Object | Count | Design implication |
|---|---|---|
| Attendees | **624** | Needs virtualised or paginated rows, bulk select, fast filter |
| Suppliers | **38** | Card grid or list both viable |
| Speakers | **24** | Cards work; needs status at a glance |
| Sponsors | **8** | Too few for a table — wants a pipeline or tier display |
| Agenda sessions | **41** across 5 days, 12 rooms | A genuine timetable problem |
| Plan items | **42** across 7 phases | Hierarchical, needs collapse |
| Tasks | **30** across 6 stages, 13 areas | Kanban columns get long |
| Budget lines | **13** | A table, with money right-aligned |
| Documents | 8 | Small |
| Team | 5 | Small |
| Approvals / Risks | 2 / 4 | On this flagship event specifically — a smaller or earlier-stage event legitimately shows 0 of either, and that emptiness is the normal state, not a bug |

Two things fall out of this. First, the range is enormous: 620 down to 0 in the
same product. Second, **the modules with the fewest records are the ones a
manager checks most often** — approvals and risks are usually empty, and that
emptiness is meaningful, not a gap to hide.

---

## 10. The hard design problems

These are the real constraints. Anything designed without answering them will
break on contact.

1. **Density collapse.** One shell must hold 620 attendee rows and 8 sponsor
   records. Designs tuned for one end look broken at the other.

2. **Three contexts of use.** A desk at 10am, a client meeting at 2pm, a loading
   bay at 6am. The third is currently served by the same interface as the first,
   and shouldn't be. Show-day may deserve to look like a different product.

3. **The paper half.** Seventeen documents leave the building and represent the
   company. They should feel like the company's stationery, not like a screenshot
   of a dashboard. Screens and documents may legitimately need different
   visual languages.

4. **Bilingual.** Contracts and briefs run English and Arabic together —
   right-to-left, a second typeface, mixed-direction numerals.

5. **The nav can't be a fixed list.** Modules toggle per event; a workshop shows
   six, a summit eighteen. Whatever the navigation is, it has to be generated.

6. **Empty is the normal state early on.** Most modules are empty at proposal
   stage. Empty states are not an edge case here — they're the first impression,
   and they should teach the concept rather than apologise.

7. **The health score must be explainable.** A number nobody trusts is worse
   than no number. Wherever the score appears, the reasons need to be one step
   away.

8. **Money needs precision.** Multi-currency, cents, tabular alignment, estimate
   versus actual versus committed. Columns must line up.

9. **Where spectacle legitimately belongs.** If the product needs a striking
   moment, it should come from the things that are inherently spatial — the
   venue, the floor plan, the room layout, the dispatch board. Those are real
   visual problems with real visual answers. Tables of attendees are not where
   drama belongs, and forcing it there is what makes software look decorated
   rather than designed.

---

## 11. In one sentence

An operations platform where a small team runs large, formal, often
government-facing events — planning them at a desk for months, then running them
from a phone for five days — and where half the output is documents that go to
the client with the company's name on them.
