# Event Brief Module — Product & Content Specification

**Elite Business Hub · Events → [Event] → Event Brief**
Version 1.0 · Blueprint (design + content + data model + ERP integration)

---

## 0. Executive summary

The **Event Brief** is the *single source of truth* for an event. It is the first artifact created after an event is opened, and it is **client-facing, editable, and export-ready** (PDF + Word). Once approved, it **seeds the entire ERP workflow** — phases, workstreams, tasks, budget skeleton, participant targets, sponsorship structure, registration plan, supplier requirements, milestones, risk register, approval chain, and the reporting dashboard.

Two faces of one document:

| | Client View | Internal View |
|---|---|---|
| Audience | Government, embassy, donor, corporate, NGO, sponsor, IO | PM, ops, production, finance, suppliers |
| Depth | Executive, high-level, readable | Full operational detail, ERP-linked |
| Tone | Big-4 consulting brief / premium agency proposal | Delivery playbook |
| Export | Branded PDF / Word for approval & circulation | Working document, task generator |

Design language: **corporate navy + white + cool grey + gold accent** (aligned to the Command Center theme: `#0B1F3A` / `#D4AF37`). Clean type, generous spacing, executive summary boxes, professional tables, section dividers, approval signature page, headers/footers, page numbers, version control.

---

## 1. Module description

The module is a **guided authoring experience** that produces a governed document.

- **5 template families**, each tuned to an event archetype but sharing one design system and one 18-section spine.
- A **10-step wizard** captures structured data; the platform **pre-fills** template-specific components, KPIs, risks, budget lines, and milestones, which the user edits.
- A **live document editor / preview** renders the brief in the premium layout as it is built.
- **Governance**: draft → internal review → client approval gate(s) → approved → locked baseline. Every change is versioned.
- **On approval**, an **ERP generator** converts the brief into the working plan (reusing the existing Planning, Budget, Suppliers, Attendees, Sponsors, Risks, Approvals, Exhibition, and Agenda modules).
- Any brief can be **saved as a reusable template** and **duplicated, edited, approved, exported, or archived**.

### 1.1 The 5 templates

| # | Template | Covers |
|---|----------|--------|
| 1 | **Conference & Summit** | Conferences, summits, forums, congresses, multi-track knowledge events |
| 2 | **Exhibition & Expo** | Expos, trade shows, showcases, innovation zones, business fairs |
| 3 | **Workshop & Training** | Workshops, capacity-building, bootcamps, masterclasses |
| 4 | **Gala Dinner & Awards** | Galas, award ceremonies, VIP/protocol dinners, appreciation events |
| 5 | **Festival & Public Event** | Festivals, outdoor/public gatherings, cultural & community activations |

---

## 2. Global section framework (all templates)

Every brief is built from the same **18-section spine**. Each section carries a **visibility flag** so the same document renders differently for client vs internal audiences. Template-specific content is injected into sections 7–13.

| # | Section | Client | Internal | Purpose |
|---|---------|:---:|:---:|---------|
| 1 | **Cover Page** | ✓ | ✓ | Event name, client, organizer logos, date, version, confidentiality |
| 2 | **Event Information** | ✓ | ✓ | Name, theme, type, format, dates, venue, location, attendance, owner |
| 3 | **Executive Summary** | ✓ | ✓ | 1-page narrative: the "why", the ambition, the headline numbers |
| 4 | **Event Objectives** | ✓ | ✓ | Strategic / business / communication / stakeholder objectives |
| 5 | **Success Metrics / KPIs** | ✓ | ✓ | Target table; internal adds owner + data source per KPI |
| 6 | **Target Audience** | ✓ | ✓ | Segments, VIP tiers, expected numbers, invitation strategy |
| 7 | **Event Components** | ✓ | ✓ | Template-specific building blocks (plenary, booths, stages…) |
| 8 | **Participant Strategy** | ✓ | ✓ | Targets, registration approach, VIP & protocol, hospitality |
| 9 | **Venue Requirements** | summary | full | Spaces, capacities, technical, layouts (internal = full room list) |
| 10 | **Branding & Creative** | summary | full | Identity, key visual, signage, collateral, digital assets |
| 11 | **Operational Requirements** | — | ✓ | Production, AV, staffing, run-of-show, logistics, accessibility |
| 12 | **Supplier Requirements** | — | ✓ | Scopes of work, categories, procurement path |
| 13 | **Risk Assessment** | top risks | full register | Likelihood × impact, owner, mitigation, contingency |
| 14 | **Budget Overview** | summary | full | Category summary (client) → line detail + funding (internal) |
| 15 | **High-Level Timeline** | ✓ | ✓ | Milestones + phase bands; internal adds workstream detail |
| 16 | **Governance Model** | ✓ | ✓ | Sponsor, director, PM, workstream leads, RACI, approval gates |
| 17 | **Approval Page** | ✓ | ✓ | Signature blocks, approval status, version, date |
| 18 | **ERP Integration Logic** | — | ✓ | What this brief generates on approval (mapping appendix) |

**Rule of thumb:** the client document is sections **1–8, 9(summary), 10(summary), 13(top 5), 14(summary), 15–17**. Internal adds **9(full), 11, 12, 13(full), 14(full), 18** and every operational line item.

---

## 3. UX flow (10-step wizard)

```
① Select template  →  ② Basic info  →  ③ Objectives & KPIs  →  ④ Components
   →  ⑤ Requirements  →  ⑥ Budget  →  ⑦ Risks  →  ⑧ Timeline & milestones
   →  ⑨ Governance & approvals  →  ⑩ Generate & export
```

| Step | User provides | Platform pre-fills / does |
|------|---------------|---------------------------|
| **1 · Template** | Picks one of 5 | Loads that template's section set, components, KPI/risk/budget/milestone libraries |
| **2 · Basic info** | Name, client, date, venue, location, expected attendance, format, budget range, event owner, organizer | Creates the `event_brief`, binds to the event, stamps v0.1 draft |
| **3 · Objectives & KPIs** | Strategic / business / communication / stakeholder objectives; success targets | Suggests template KPIs with editable targets, owners, data sources |
| **4 · Components** | Toggles/edits template components (e.g. plenary, breakouts, exhibition) | Shows template-specific component catalog; each ON component flags downstream requirements |
| **5 · Requirements** | Venue, production, branding, supplier, registration, hospitality, safety/security | Derives requirement checklists from the components chosen in step 4 |
| **6 · Budget** | Adjusts amounts | Generates the template's budget category skeleton (maps to `EventBudgetCategory`) |
| **7 · Risks** | Edits/adds, sets likelihood & impact | Loads the template risk library with default likelihood/impact/mitigation |
| **8 · Timeline** | Confirms dates | Generates milestone recommendations by template, anchored to Event Day |
| **9 · Governance** | Names sponsor, director, PM, workstream leads; sets approval gates | Builds RACI + approval chain (maps to Approvals + `event_team_members`) |
| **10 · Generate** | Reviews live preview, edits inline | Renders premium doc; enables **Export PDF / Word**, **Send for approval**, **Generate plan** |

Progress is saved at every step (autosave). The wizard can be exited and resumed; the **document preview** is always available as a side/right pane.

---

## 4. The 5 templates

The **spine (§2) is identical**. Below is what changes per template: **signature components**, **template-specific fields**, **KPIs**, **risks**, **budget categories**, **milestones**. (Fields listed are *in addition to* the global Event Information block.)

### 4.1 Template 1 — Conference & Summit

**Signature fields (§2/§7):** event theme · format (physical/hybrid/virtual) · program structure · agenda overview · keynotes · panels · breakouts · workshops · exhibition (if any) · VIP program · speaker & moderator requirements · interpretation · media requirements · sponsorship opportunities · networking · gala (if any).

**Components (§7) — catalog:** Main plenary hall · Breakout rooms · Speaker prep room · VIP lounge · Media room · Registration area · Exhibition area · Interpretation booths · Livestreaming · Event app · QR check-in · Badge printing · Session attendance tracking · Speaker management · Run of show · Cue sheet · Technical rehearsal.

**KPIs:** Total registrations · Actual attendance · VIP attendance · Speaker satisfaction · Session attendance · Sponsor satisfaction · Media coverage · Social reach · Participant satisfaction · **NPS**.

**Risk library (likelihood/impact):** Low speaker confirmation (M/H) · Keynote cancellation (L/H) · AV/streaming failure (L/H) · Interpretation shortfall (M/M) · Low registration conversion (M/H) · VIP protocol clash (L/H) · Agenda overrun (M/M) · Sponsor deliverable slip (M/M).

**Budget categories:** Venue & rooms · AV & production · Interpretation · Speakers & travel · Registration & badging · Catering & breaks · Branding & signage · Marketing & digital · Media & PR · Exhibition build · Contingency.

**Milestones:** Concept sign-off · Venue confirmed · Speaker line-up locked · Agenda published · Registration live · Sponsors closed · Final delegate list · Tech rehearsal · Event days · Post-event report.

---

### 4.2 Template 2 — Exhibition & Expo

**Signature fields:** exhibition theme · objectives · target sectors · expected visitors · exhibitor categories · sponsor categories · booth types · space allocation · visitor flow · floorplan requirements · registration approach · exhibitor onboarding · sponsor visibility · lead-generation approach · media/PR · partner activations.

**Components:** Exhibition hall · Booth layout · Standard booth package · Custom booth package · Sponsor booths · Government showcase · Innovation zone · Demo area · Networking area · Registration desk · Information desk · Storage · Loading access · Power · Internet · Cleaning · Security · Fire safety · Exhibitor manual · Move-in/move-out schedule.

**KPIs:** Number of exhibitors · Number of visitors · Visitor traffic · Leads generated · Sponsor satisfaction · Exhibitor satisfaction · Booth occupancy rate · Media coverage · Business meetings conducted · Revenue from booths.

**Risk library:** Low booth sales (M/H) · Exhibitor drop-out (M/M) · Low visitor turnout (M/H) · Floorplan/fire-code rejection (L/H) · Move-in congestion (M/M) · Power/internet failure (L/H) · Security incident (L/H) · Sponsor visibility dispute (M/M).

**Budget categories:** Hall rental · Booth build (shell + custom) · Floor & rigging · Power & internet · Registration & lead capture · Signage & wayfinding · Security & cleaning · Marketing & visitor acquisition · Sponsor servicing · Logistics & handling · Contingency.

**Milestones:** Concept & floorplan v1 · Exhibitor sales open · Sponsor packages closed · Floorplan frozen · Exhibitor manual issued · Move-in schedule locked · Build & fit-out · Show days · Move-out · Post-show report.

---

### 4.3 Template 3 — Workshop & Training

**Signature fields:** training theme · learning objectives · target participants · number of participants · trainer/facilitator profile · methodology · session structure · learning outcomes · materials · certificates · evaluation method · pre-assessment · post-assessment · participant communication · registration process.

**Components:** Training room setup · Classroom layout · Roundtable layout · U-shape layout · Breakout group setup · Materials table · Trainer table · Projector/screen · Sound system · Flipcharts · Stationery · Name tags · Certificates · Attendance sheet · Evaluation form · Coffee breaks · Lunch · Accessibility needs.

**KPIs:** Attendance rate · Completion rate · Participant satisfaction · Knowledge improvement (pre→post) · Trainer rating · Certificate issuance · Engagement level · Assessment scores · Learning-outcome achievement.

**Risk library:** Low enrollment (M/M) · Trainer unavailability (L/H) · Material/print delay (M/M) · AV failure (L/M) · Low completion (M/M) · Assessment integrity (L/M) · Accessibility gap (L/M).

**Budget categories:** Venue/room · Trainer fees & travel · Materials & printing · Certificates · Catering (breaks + lunch) · AV & equipment · Assessment/platform · Contingency.

**Milestones:** Curriculum sign-off · Trainer confirmed · Materials finalized · Registration live · Pre-assessment sent · Delivery days · Post-assessment & certificates · Evaluation report.

---

### 4.4 Template 4 — Gala Dinner & Awards

**Signature fields:** occasion · concept · dress code · guest profile · VIP profile · seating plan · protocol requirements · entertainment program · award categories (if any) · ceremony flow · guest journey · arrival/reception/dinner experience · stage program · branding · photography/videography · media coverage.

**Components:** Arrival & welcome · Red carpet · Photo wall · Reception · Dining area · Stage · LED screen · Podium · Awards table · Trophy/gift table · VIP seating · Table seating plan · Place cards · Menu cards · Centerpieces · Lighting design · Live entertainment · Background music · Show caller · Protocol officer · Ushers · Valet parking · VIP transportation.

**KPIs:** Guest attendance · VIP attendance · Seating accuracy · Guest satisfaction · Program timing accuracy · Sponsor visibility · Media/photo coverage · Client satisfaction · Protocol compliance.

**Risk library:** VIP no-show / late change (M/M) · Seating/protocol error (M/H) · Program overrun (M/M) · Entertainment failure (L/H) · Catering timing (M/M) · AV/lighting failure (L/H) · Security/access for VIPs (L/H).

**Budget categories:** Venue & F&B · Stage, AV & lighting · Entertainment & talent · Awards & trophies · Décor & centerpieces · Print (menus/place cards) · Photography/video · Protocol & ushering · Transportation & valet · Contingency.

**Milestones:** Concept & run-of-show v1 · Guest list & VIP confirmed · Seating plan locked · Entertainment booked · Menu tasting · Rehearsal · Gala night · Post-event & media pack.

---

### 4.5 Template 5 — Festival & Public Event

**Signature fields:** concept · theme · public audience profile · expected footfall · venue/location · site plan · zones · stage program · entertainment · F&B zones · sponsor activations · public safety plan · crowd management · ticketing/free-entry logic · permits & authorities · weather contingency · emergency planning · public communication.

**Components:** Main stage · Secondary stage · Activation zones · Sponsor zones · F&B area · Family area · VIP area · Media area · First aid station · Security points · Entry gates · Exit gates · Ticketing/check-in · Crowd barriers · Toilets · Waste management · Generators · Power distribution · Lighting towers · Weather protection · Signage & wayfinding · Emergency assembly points.

**KPIs:** Total footfall · Peak attendance · Ticket sales · Sponsor engagement · Vendor sales · Crowd-flow efficiency · Incident rate · Social reach · Public satisfaction · Media coverage.

**Risk library:** Adverse weather (M/H) · Overcrowding / crush (L/H) · Permit/authority delay (M/H) · Power/generator failure (L/H) · Security/crowd incident (M/H) · Medical emergency (M/M) · Vendor/F&B shortfall (M/M) · Talent cancellation (L/M).

**Budget categories:** Site & infrastructure · Stages, AV & lighting · Power & generators · Talent & entertainment · Security & stewarding · Medical & safety · Sanitation & waste · F&B & vendor ops · Permits & authorities · Marketing & ticketing · Signage & wayfinding · Contingency.

**Milestones:** Concept & site plan v1 · Permits submitted · Talent booked · Sponsor activations closed · Safety/traffic plan approved · Ticketing live · Build & site setup · Event days · De-rig · Post-event & incident report.

---

## 5. Data model

Money stored as **integer minor units** (cents), consistent with the platform. All tables carry `created_at/updated_at`. Visibility column governs client vs internal rendering. FK to `events` cascades on delete unless noted.

### 5.1 `event_briefs`
| Field | Type | Description | Visibility |
|-------|------|-------------|:---:|
| id | bigint PK | | — |
| event_id | FK→events | One brief-of-record per event (history via versions) | — |
| template_id | FK→event_brief_templates | Chosen archetype | — |
| title | string | Document title | client |
| client_name | string | Commissioning entity | client |
| organizer_name | string | Delivering agency | client |
| event_owner_id | FK→users | Internal accountable owner | internal |
| format | enum(physical,hybrid,virtual) | | client |
| status | enum(draft,in_review,pending_approval,approved,locked,archived) | Lifecycle | internal |
| version | string | e.g. `1.2` | client |
| currency | char(3) | | client |
| budget_range_min/max | bigint | Ballpark envelope (cents) | client |
| executive_summary | text (rich) | | client |
| confidentiality | enum(public,internal,confidential) | Cover badge | client |
| approved_at | timestamp | | internal |
| generated_plan_at | timestamp | When ERP generation ran | internal |

### 5.2 `event_brief_templates`
| Field | Type | Description |
|-------|------|-------------|
| id | bigint PK | |
| key | slug | conference, exhibition, workshop, gala, festival |
| name | string | Display name |
| description | text | |
| section_schema | json | Ordered sections + default visibility |
| is_system | bool | Ships with platform vs user-saved |
| owner_id | FK→users nullable | Set when a user saves a brief as a reusable template |

### 5.3 `event_brief_sections`
| Field | Type | Description | Visibility |
|-------|------|-------------|:---:|
| id | bigint PK | | |
| brief_id | FK→event_briefs | | |
| key | slug | cover, event_info, exec_summary, objectives, kpis … | |
| title | string | | client |
| body | text (rich) | Section narrative/content | per-section |
| position | int | Order | |
| visibility | enum(client,internal,both) | Overrides template default | |

### 5.4 `event_brief_fields`
Structured key/value pairs backing the wizard (theme, dates, attendance, etc.).
| Field | Type | Description | Visibility |
|-------|------|-------------|:---:|
| id | bigint PK | | |
| brief_id | FK | | |
| section_key | slug | Owning section | |
| key | slug | e.g. `expected_attendance` | |
| label | string | | client |
| value | text | | per-field |
| data_type | enum(text,number,date,money,select,bool) | | |
| visibility | enum(client,internal,both) | | |

### 5.5 `event_brief_components`
The template-specific building blocks (plenary, booth, stage…).
| Field | Type | Description | Visibility |
|-------|------|-------------|:---:|
| id | bigint PK | | |
| brief_id | FK | | |
| name | string | | client |
| category | slug | space, technical, hospitality, safety… | |
| enabled | bool | Included in this event | client |
| quantity | int nullable | e.g. 6 breakout rooms | client |
| spec | json | Capacity, layout, power, dimensions | internal |
| generates | json | Downstream hooks (room, supplier scope, task tag) | internal |

### 5.6 `event_brief_kpis`
| Field | Type | Description | Visibility |
|-------|------|-------------|:---:|
| id | bigint PK | | |
| brief_id | FK | | |
| name | string | | client |
| target | string | Target value | client |
| unit | string | %, count, score | client |
| owner_id | FK→users nullable | | internal |
| data_source | string | How it's measured | internal |
| baseline | string nullable | | internal |

### 5.7 `event_brief_risks`
| Field | Type | Description | Visibility |
|-------|------|-------------|:---:|
| id | bigint PK | | |
| brief_id | FK | | |
| title | string | | client (top-N) |
| likelihood | enum(low,medium,high) | | |
| impact | enum(low,medium,high) | | |
| severity | int (derived) | L×I score for ranking | |
| owner_id | FK→users nullable | | internal |
| mitigation | text | | internal |
| contingency | text | | internal |
| client_visible | bool | Surface in client top-risks | client |

### 5.8 `event_brief_budget_categories`
| Field | Type | Description | Visibility |
|-------|------|-------------|:---:|
| id | bigint PK | | |
| brief_id | FK | | |
| name | string | | client |
| estimate_cents | bigint | High-level estimate | client (summary) |
| funding_source | enum(client,sponsor,ticket,grant,mixed) | | internal |
| notes | text | | internal |
| position | int | | |

### 5.9 `event_brief_milestones`
| Field | Type | Description | Visibility |
|-------|------|-------------|:---:|
| id | bigint PK | | |
| brief_id | FK | | |
| name | string | | client |
| target_date | date nullable | | client |
| phase_key | slug | Maps to plan phase | internal |
| position | int | | |

### 5.10 `event_brief_approvals`
| Field | Type | Description | Visibility |
|-------|------|-------------|:---:|
| id | bigint PK | | |
| brief_id | FK | | |
| role | enum(sponsor,director,pm,client,finance) | Approval slot | client |
| approver_name | string | | client |
| approver_user_id | FK→users nullable | Internal approver | internal |
| status | enum(pending,approved,rejected) | | client |
| decided_at | timestamp nullable | | client |
| signature_ref | string nullable | e-sign / uploaded image | client |
| gate_order | int | Sequence of gates | internal |

### 5.11 `event_brief_versions` & `event_brief_exports`
**versions** — immutable snapshots for history/rollback: `id, brief_id, version, snapshot(json), author_id, note, created_at`.
**exports** — audit of generated files: `id, brief_id, version, format(pdf,docx), file_path, view(client,internal), generated_by, created_at`.

**Relationship summary:** `events 1—1 event_briefs`; a brief `1—many` sections/fields/components/kpis/risks/budget/milestones/approvals/versions/exports; `event_brief_templates 1—many event_briefs`.

---

## 6. Suggested UI layout

**Entry:** Event Hub tab **"Brief"** (new tab beside Planning). Empty state → *"Create Event Brief"* → template picker (5 premium cards with iconography + one-line fit).

**Working layout — split view:**
```
┌───────────────────────────────────────────────────────────────┐
│ Brief header: title · client · v1.2 · status pill · [PDF][Word]│
├──────────────────────────┬────────────────────────────────────┤
│  WIZARD / EDITOR (left)   │   LIVE DOCUMENT PREVIEW (right)     │
│  Step rail ①…⑩            │   Renders premium layout, the       │
│  Form for current step    │   client/internal toggle switches   │
│  Autosave, validation     │   which sections/detail show        │
├──────────────────────────┴────────────────────────────────────┤
│ Footer: [Client view ⇄ Internal view]  [Send for approval]     │
│         [Generate event plan]  [Save as template]  [Archive]   │
└───────────────────────────────────────────────────────────────┘
```
- **Client/Internal toggle** is a first-class control everywhere; the preview and exports respect it.
- **Section navigator** (jump list) on the preview.
- **Approval banner** appears once sent (gate progress, who's pending).
- **Version dropdown** to view/restore snapshots.
- Reuse existing components: `<x-user-avatar>`, budget category rows, the Planning Gantt for the timeline preview, seating/floorplan links for venue components.

---

## 7. Export logic (PDF + Word)

**Shared render model:** one Blade view tree (`event-brief/{template}.blade.php`) produces the document; a `view` parameter (`client|internal`) filters sections/fields by visibility. The same model feeds both exporters so they never drift.

- **PDF** — `barryvdh/laravel-dompdf` (already installed). A4 portrait, corporate master: navy cover, gold hairline dividers, running header (event name + version) and footer (page X of Y + confidentiality + generated date). Executive-summary callout boxes, KPI/risk/budget/milestone tables, approval signature page. Cover logo slots (organizer + client).
- **Word (.docx)** — `phpoffice/phpword` (add alongside the already-installed PhpSpreadsheet). Map the same sections to Word styles (Heading 1/2, table styles, a cover section, signature table). Word is for clients who must edit/annotate before sign-off.
- **Filename:** `{event-slug}-brief-v{version}-{client|internal}.{pdf|docx}`, logged to `event_brief_exports`.
- **Version stamp & watermark:** drafts render a subtle "DRAFT · v0.x" watermark; approved renders "APPROVED · v1.x" with date.

---

## 8. Client view vs Internal view logic

1. Every section/field/component/risk/budget row carries a **visibility** (`client | internal | both`).
2. **Client render** = items where visibility ∈ {client, both}, plus **summarized** variants of §9/§10/§14 and **top-5** risks (`client_visible = true`, ranked by severity).
3. **Internal render** = everything, with operational spec, owners, funding sources, data sources, ERP mapping (§18), and full risk register.
4. Defaults come from the **template schema**; the author can override per item (e.g. promote one supplier note to client).
5. Exports and the on-screen preview both honor the active toggle; the approval PDF sent to a client is always the **client render**.

---

## 9. ERP integration — auto-generation rules

On **"Generate event plan"** (allowed once the brief is approved, idempotent/re-runnable), the brief becomes the working plan by mapping into **existing platform modules**:

| Brief source | Generates | Target (existing module) |
|--------------|-----------|--------------------------|
| Template + phase_key set | **7 phases** (Initiation → Post-Event) | `EventPlanCategory` (`ensurePlanCategories`) |
| Enabled **components** | **Workstreams → tasks** (component → task pack) | `EventPlanItem` (task + subtasks) |
| **Milestones** | **Milestone plan items** (single-date) on the timeline | `EventPlanItem` (milestone) |
| **Budget categories** | **Budget skeleton** with estimates | `EventBudgetCategory` |
| **Participant strategy** | **Attendance targets & ticket types** | Attendees / registration + `default_ticket_types` |
| **Sponsor categories** | **Sponsorship packages & slots** | `event_sponsor_packages` (`ensureSponsorPackages`) |
| **Supplier requirements** | **Supplier scopes / RFQ stubs** | Suppliers module |
| **Risk register** | **Risks** with likelihood/impact/owner/mitigation | Risks module |
| **Governance + approval gates** | **Approval chain + team roles** | Approvals + `event_team_members` |
| **KPIs** | **Reporting dashboard tiles** | Overview / reporting |
| **Venue components** | **Rooms + layout stubs** (plenary, breakout, booths, stage) | Rooms / Seating / Exhibition floor |

### 9.1 Component → task pack (illustrative — Conference)
```
"Main plenary hall"     → tasks: reserve hall · stage & AV design · rehearsal · cue sheet
"Registration area"     → tasks: choose platform · badge design · QR check-in · staffing
"Interpretation booths" → tasks: languages · book interpreters · booth install · test
"Speaker management"    → tasks: outreach · confirmations · bios/photos · prep room
"Livestreaming"         → tasks: platform · encoder/bandwidth · run test · moderation
```
Each pack lands under the correct **phase** (Build Participant List → Phase 2, Registration → Phase 3, Final list/Badges/Seating → Phase 4, Check-in → Phase 5, Reports/Certificates → Phase 7), matching the master-plan placement rules.

### 9.2 Generation guarantees
- **Idempotent:** re-running updates/creates but does not duplicate (match on `brief_id` + source key).
- **Non-destructive:** never deletes user-added plan items; only fills gaps.
- **Traceable:** each generated item stores `source_brief_id` + `source_key` for round-trip.
- **Baseline lock:** approving the brief snapshots a version; later brief edits prompt a "re-generate delta?" action.

---

## 10. Lifecycle, versioning & actions

**Lifecycle:** `draft → in_review → pending_approval → approved → locked` (with `archived` terminal). Rejection returns to `draft` with comments.

**Actions:** Duplicate · Edit · Send for approval · Approve/Reject (per gate) · Export (PDF/Word, client/internal) · Save as reusable template · Version history (view/restore) · Archive.

**Versioning:** every save past a gate writes an immutable `event_brief_versions` snapshot; the header shows the current version; users can diff/restore. Exports are stamped with the version they were generated from.

**Reusable templates:** "Save as template" copies the section schema + component/KPI/risk/budget/milestone defaults into `event_brief_templates` (owner-scoped) for future events.

---

## 11. Design system for the generated document

- **Palette:** navy `#0B1F3A` (headers, cover), white grounds, cool grey `#64748B` (secondary text), hairline `#E2E8F0`, **gold `#D4AF37`** accents (rules, KPI highlights, approved stamp).
- **Type:** one serif or high-contrast sans for display headings, a clean humanist sans for body; tabular figures in all tables.
- **Components:** cover with logo lockups + confidentiality badge · section dividers with gold hairline + numeral · **executive-summary callout box** · KPI table (target | unit | owner*) · risk table (risk | L | I | severity | mitigation*) · budget summary table · milestone table · **approval signature page** (role, name, signature, date) · running header/footer · page numbers · version/watermark.
- **Fit for:** government proposal, embassy event, donor project, corporate summit, international conference, premium gala, public festival, large exhibition. *(\* = internal view only)*

---

## 12. Implementation roadmap (suggested)

1. **Data + template engine** — migrations (§5), `EventBrief` model, template schema loader, wizard state.
2. **Wizard UI** — 10 steps, autosave, split preview, client/internal toggle.
3. **Document render** — Blade master + 5 template views; PDF exporter first.
4. **Approval workflow** — gates, signatures, statuses, versions.
5. **ERP generator** — component→task packs, budget/risk/milestone/sponsor/approval mapping (idempotent).
6. **Word export** — add `phpoffice/phpword`, map styles.
7. **Reusable templates + archive + version diff.**

This reuses the platform's existing Planning, Budget, Suppliers, Attendees, Sponsors, Risks, Approvals, Rooms, and Exhibition modules — the Brief is the *front door* that populates them.
