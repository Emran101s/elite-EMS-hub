# Elite Business Hub — New Platform Blueprint

Source: `docs/command-center/platform-plan.pdf` + Command Center reference mockup.
This is a fresh, standalone platform (separate repo from the earlier emranspace build).

## Product

An operations platform for an events/projects business. The **Command Center** is the
homepage: a real-time overview of the whole events ecosystem.

## Modules (sidebar order)

1. **Command Center** — dashboard/homepage (see composition below)
2. **Events** — conferences, galas, workshops, expos, private dinners (multi-country: Jordan, Bahrain, UAE, Qatar, KSA)
3. **Projects**
4. **Tasks** — statuses: Completed / In Progress / Pending
5. **CRM**
6. **Finance** — budgets: total / spent / committed / remaining
7. **Suppliers** — with categories (Support, AV & Lighting, Catering) and star ratings
8. **Venues**
9. **Team**
10. **Assets**
11. **Reports**
12. **AI Assistant** — "Ask anything about your operations" (persistent sidebar card + module)
13. **Settings**

## Command Center composition (reference mockup)

- **Header**: "Welcome back, {name}" + global search, notifications, messages, profile (role badge, e.g. Super Admin).
- **KPI row (5)**: Total Events, Active Projects, Total Revenue, Open Tasks, At Risk — each with delta vs last month/week.
- **Operations Hub**: centerpiece visual — event "islands" arranged around an **AI Command Core** ("All systems operational"), each island = one event with name, type, location, and a health ring (e.g. 92% ON TRACK / 61% AT RISK / 45% BEHIND). Map controls: locate, globe, 3D toggle, zoom.
- **Right rail**: Live Alerts (color-coded, relative timestamps) · Resource Utilization (Team Members, Venues, Suppliers, Equipment — % bars) · Budget Overview (donut: spent/committed/remaining of total).
- **Bottom row**: Upcoming Deadlines · Tasks Overview (donut, counts by status) · Top Suppliers (rating) · Events by Status (bar chart: On Track / In Progress / At Risk / Completed).

## Design language

- Palette: navy `#0B1F3A` + gold `#D4AF37` on a light background (continuation of the approved Command Center direction).
- Brand: ELITE BUSINESS HUB wordmark with gold diamond logo.
- Health semantics: green = on track, amber = in progress/warning, red = at risk/behind.

## Stack

- Laravel 13 + Livewire 4, Vite + Tailwind, SQLite for local dev.
- Served by Herd at `http://elitehub.test`.

## Build phases (working plan)

- **Phase 0 — Foundation**: auth, app shell (sidebar + header), theme tokens, module route stubs.
- **Phase 1 — Core data**: events, projects, tasks, suppliers, venues, team; seeders with demo data.
- **Phase 2 — Command Center**: KPI engine, health score, Operations Hub view, alerts, right rail, bottom row.
- **Phase 3 — Money & people**: finance/budgets, CRM.
- **Phase 4 — Assets, reports, AI assistant, settings.**
