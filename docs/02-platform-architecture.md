# Platform Architecture

## Stack

- **Laravel 13**, **Livewire 4.3** (server-rendered reactive components — no separate SPA/API layer for the app itself)
- **Tailwind v4** via Vite
- **SQLite** for local development, served by Herd at `http://elitehub.test`
- PDFs are rendered by **headless Chrome from the app's own Blade markup** — what's on screen
  is what prints. This is not a side feature: roughly seventeen PDF documents leave the
  building and represent the company to clients, ministries, sponsors and drivers.

Run the suite with `php artisan test`; build assets with `npm run build`.

## Directory conventions

- `app/Livewire/` — top-level page components (e.g. `EventCreate`, `ContractsRegister`,
  `CrmPipeline`). `app/Livewire/Hub/*` — the per-module tabs inside an event's hub
  (`BudgetTab`, `SponsorsTab`, `TransportationTab`, etc.)
- `app/Models/` — one Eloquent model per domain object. Status/type vocabularies live as
  `const` arrays on the model that owns them (e.g. `EventContract::STATUSES`,
  `EventSponsor::PAYMENT_STATUSES`), not scattered across views.
- `app/Support/` — shared, stateless helpers used across models and views: `Money`
  (canonical money formatting), `Workflow` (renameable status/label lookups backed by the
  taxonomy system), `ContractClauses` / `ContractTemplates` / `ContractAppendices`.
- `resources/views/components/` — flat Blade components (`<x-status-badge>`, `<x-date>`,
  `<x-event-avatar>`, etc.), used across the whole app.
- `resources/views/livewire/hub/` — the Blade half of each hub tab.
- `resources/views/events/*-pdf.blade.php` — the ~17 PDF documents.

## The four altitudes

Navigation exists to move between four altitudes of the same data:

1. **Portfolio** — every event at once. *"What needs me today, anywhere?"*
2. **Event** — one event's overall state. *"Is this thing healthy?"*
3. **Module** — one domain. *"Show me the transport."*
4. **Record** — one movement, one task, one contract. *"Fix this."*

Most real work happens at altitude 3 (module); most decisions get made at 1 and 2.

## Portfolio level

- **Command Center** (homepage) — a signal engine, not a static dashboard. Scans every
  active event and emits signals (overdue, approvals waiting, blocked, money, risks), each
  tied to a record and a link.
- **Events** — cards / list / calendar views over the whole portfolio.
- Also at this level: Projects, Finance, Sponsors, Reports, Team, Settings, and a ⌘K
  command palette across events, modules, tasks and suppliers.

## Event level — the hub modules

Modules are switched **on and off per event** (`Event::HUB_MODULES` is the registry; which
ones are active per event is per-event state). A one-day workshop might run six modules; a
five-day summit runs all nineteen. The navigation is generated from this registry, not a
fixed list — this is intentional and should not be flattened into a static menu.

Current modules (from `Event::HUB_MODULES`, grouped by their internal cluster tag):

| Cluster | Modules |
|---|---|
| **Plan** | Event Brief, Contract, Planning, Tasks, Budget, Risks, Approvals |
| **Programme** | Agenda, Speakers |
| **Logistics** | Suppliers, Venue, Transport, Accommodation, Food & Beverage |
| **Exhibition** | Exhibition, Sponsors |
| **Sell** | Attendees |
| **Grow** | Documents, Reports |

See [03-command-center-architecture.md](03-command-center-architecture.md) for how this
maps onto the target Company/Event Command Center structure, and note that the mapping is
a **documentation target**, not an instruction to rebuild navigation now.

## Cross-cutting systems

- **Health engine** — computes a 0–100 score per event from budget, tasks, suppliers,
  venue, agenda and transport, banded into on-track / watch / at-risk, with an
  **attention list** that explains the score (pending approvals, risks by severity,
  supplier issues, transport issues, unassigned pool guests, overdue tasks). The score is
  never stored — always computed, always explainable.
- **Audit log** — every create/update/delete on every record, with a human-readable
  summary and the acting user (`Auditable` trait/concern).
- **Permissions** — role-rank gates on every mutating action. See
  [07-user-roles-and-permissions.md](07-user-roles-and-permissions.md).
- **Multi-currency** — per-event currency with symbol; FX rates in settings. All money
  formatting goes through `App\Support\Money`.
- **Import/export** — xlsx templates for attendees, rooming lists, transport manifests and
  transport plans; round-trips so a corrected sheet updates rather than duplicates.

## Real data volumes

Taken from the flagship real-world event — this is the density any change has to survive:

| Object | Count |
|---|---|
| Attendees | 620 |
| Suppliers | 38 |
| Speakers | 24 |
| Agenda sessions | 41 across 5 days, 12 rooms |
| Plan items | 42 |
| Tasks | 30 |
| Budget lines | 23 |
| Sponsors | 8 |
| Approvals / Risks | frequently 0 — empty states are the normal state, not an edge case |
