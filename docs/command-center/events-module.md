# Events Module — Master Plan

The heart of Elite Business Hub. An event is never a table row — it is a full
operational workspace: the **Event Hub / Event Control Room**.

## 1. Product explanation

Elite Business Hub is an Operations Command Center. The Events Module gives every
event its own hub containing all operations: agenda, tasks, budget, suppliers,
venue, sponsors, attendees, files, risks, approvals, reports, AI insights — each
scored into one explainable **Event Health Score** that feeds the Operations Hub island.

## 2. UX flow

```
Operations Command Center → Events Module (overview: KPIs, filters, views)
  → Create Event (wizard: Basics → Avatar → Color Theme → Review)
  → "Create Event Hub" → Event Hub opens
  → Manage everything from the hub tabs; the island in the Operations Hub reflects it live
```

Product decision: the wizard captures identity fast (steps 1–3 + review); heavy
setup (venue/rooms, agenda, team, budget, suppliers, sponsors) happens **inside the
hub tabs** so an event can be created in under a minute and operated progressively.
The original 10-step capture list maps 1:1 onto hub tabs.

## 3. Wizard flow (implemented)

1. **Basics** — name, type (16 types), client (pick or quick-create), description,
   expected participants, city/country, dates, budget, venue, project, PM.
2. **Avatar** — library grid, auto-suggested from type, sticky manual override.
3. **Color Theme** — 7 presets (Navy+Gold, White+Gold, Black+Gold, Blue+Silver,
   Green+Navy, Maroon+Gold, Purple+Gold) + Custom (primary/secondary/accent/text).
   Defaults inherit from the avatar palette.
4. **Review** — identity summary → **Create Event Hub** → redirects into the hub.

## 4. Event Hub structure

Cover: avatar (xl) + health ring, name, client, type, venue, location, dates,
participants, stage badge, PM, theme-tinted cover band.
Tabs: Overview · Agenda · Tasks · Budget · Suppliers · Venue · Sponsors ·
Attendees · Files · Risks · Approvals · Reports · AI Insights · Settings.

**Overview tab**: health score ring + 6 weighted component bars, lifecycle
timeline (Draft → Proposal → Confirmed → Planning → Production → Live → Completed
→ Closed), pending approvals, risk radar, key deadlines, AI recommendation.

## 5. Status systems

- **Stage (lifecycle)** `events.stage`: draft, proposal, confirmed, planning,
  production, live, completed, closed, cancelled, on_hold.
- **Health status** `events.status`: on_track / in_progress / at_risk / behind /
  planning / completed (color-mapped green/amber/red; blue = pending approval
  badge; gold accent for VIP; gray for closed).

## 6. Health Score (implemented — EventHealthService)

| Component | Weight | Source |
|---|---|---|
| Task completion | 30% | completed / total event tasks |
| Budget health | 20% | actual vs estimated (overrun burns score) |
| Supplier readiness | 15% | pipeline status → readiness % (requested 10 → completed 100, issue 20) |
| Venue readiness | 15% | venue assigned (60) + rooms configured (40) |
| Agenda completion | 10% | confirmed/final sessions share |
| Risk level | 10% | 100 − open-risk severity (probability × impact / 25) |

Missing components renormalize weights (a new event isn't punished for having no
budget lines yet). Bands: 81–100 On Track · 61–80 In Progress · 41–60 At Risk ·
0–40 Behind. **Override:** any open risk with severity ≥ 20/25 caps the event at
At Risk. Pending approvals surface a blue chip (never override risk).

## 7. Database schema (implemented ✓ / planned ○)

✓ `clients` — name, organization, email, phone
✓ `events` + client_id, project_manager_id, description, expected_participants,
  stage, primary/secondary/accent/text_color (null → inherit avatar)
✓ `event_rooms` — name, type (main_hall, breakout, exhibition, registration, vip, catering), capacity
✓ `event_agenda_days` — date, label, sort · `event_agenda_sessions` — day, room,
  title, type (opening…closing), speaker, moderator, track, times, status
  (draft/confirmed/waiting_speaker/needs_review/final), sort
✓ `event_budget_items` — category, estimated/actual cents, supplier, payment_status,
  invoice_number, due_on
✓ `event_sponsors` — name, package (platinum/gold/silver/bronze/strategic/supporting),
  amount, payment_status, booth, logo_path
✓ `event_risks` — category, probability 1–5, impact 1–5, owner, mitigation,
  status (open/monitoring/mitigated/escalated/closed), due_on
✓ `event_approvals` — title, type (budget/supplier/design/venue/agenda/client/payment/report),
  status (pending/approved/rejected/needs_revision), requester, decider, decided_at
✓ `event_team_members` — user + role (project_manager, operations_lead, registration_lead,
  supplier_coordinator, finance_owner, design_owner, production_owner, client_rm)
✓ `event_supplier` pivot + status (requested → completed / issue), notes
○ `event_attendees` (registration/ticket/badge/check-in/QR/dietary/VIP), `event_files`
  (folder taxonomy + versions), `event_reports`, `event_ai_insights` (persisted chats)
  — schemas reserved, land with their tabs.

Tasks reuse the global `tasks` table scoped by `event_id` (one task system platform-wide).

## 8. Backend structure (Laravel 13 + Livewire 4 — this platform's stack)

Models: Client, Event, EventAvatar, EventRoom, EventAgendaDay, EventAgendaSession,
EventBudgetItem, EventSponsor, EventRisk, EventApproval (+ Task, Supplier, Venue).
Services: `EventHealthService` (score + AI daily summary), `CommandCenterService`.
Controllers: `EventHubController`. Livewire: `EventCreate` (wizard), more per tab as
they become interactive. Form Requests/Policies: arrive with multi-role permissions.

## 9. API endpoints (planned, Sanctum — mirrors the web surface)

`/api/events` CRUD · `/api/events/{e}/hub|overview|health-score` ·
`/agenda/days|sessions` · `/tasks` · `/budget-items` · `/suppliers` · `/venue|rooms` ·
`/sponsors` · `/attendees(+import)` · `/files` · `/risks` · `/approvals(+approve|reject)` ·
`/reports/export` · `/ai-summary`, `/ai-ask` · `/avatar-templates(+suggest)`.

## 10. UI design system

Existing theme tokens (navy #0B1F3A, gold #D4AF37, page #F4F6FB/#F8FAFC, line,
muted #64748B, health green/amber/red, info blue). Cards rounded-2xl, soft shadows,
generous spacing, no crowded tables — list rows and stat cards throughout.
Shared components: `x-event-avatar`, `x-health-ring`, `x-donut`, `x-status-badge`.

## 11–12. Avatar + Color Theme systems

Avatar: implemented (EBH-4) — slug-driven SVG digital twins, uploads/3D-ready,
auto-suggestion per type. Theme: per-event 4 colors stored on the event; default
inherits avatar palette; applied to hub cover, event cards, rings, agenda blocks,
report covers.

## 13. Agenda builder

v1 (this build): days + room-aware session list with statuses, seeded ICFT agenda.
v2: drag-and-drop between rooms/times (Livewire sortable), duplicate day,
Excel import, PDF export, Day/Room/Speaker/Track views.

## 14. Permissions (next)

Policies per event: PM + team roles edit their areas; approvals gated to
finance_owner/admin; client-facing share links read-only.

## 15. Prototype plan → shipped in this build

Overview page (KPIs, filters, grid/list), wizard with avatar + theme,
Event Hub with live Overview/Agenda/Tasks/Budget/Suppliers/Venue/Sponsors/Risks/
Approvals/AI tabs on the six demo events.

## 16. Development phases & weekly plan

- **W1 (done):** schema, health service, wizard, overview page, hub + live tabs.
- **W2:** agenda drag-drop + import/export, task board view, budget invoices.
- **W3:** attendees (import, QR check-in), files (folders, versions), approvals workflow actions.
- **W4:** reports (PDF/Excel with event theme), AI insights on live LLM, policies/permissions, API layer.

## 17. Testing checklist

Health score bands + risk override · wizard step validation + theme persistence ·
hub tabs render per event · overview filters/KPIs · agenda/budget/risk seeded
rendering · islands link to hubs. (Automated in tests/Feature.)

## 18. Future enhancements

3D islands (GLB per avatar), client portal, sponsor portal, WhatsApp/email alerts,
budget→invoice sync with Finance module, attendee mobile app, multi-language (AR/EN).
