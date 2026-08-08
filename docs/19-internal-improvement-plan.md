# Elite Business Hub — Internal Company Improvement Plan

**Audience:** Owner, managers, and developers working on Elite Business Hub for **our company only**.  
**Out of scope:** Multi-company SaaS, 50-company onboarding, billing/subscriptions, public self-serve signup, marketplace, venue digital twin / 3D, multi-tenant product UX.  
**Basis:** [18-phase1-platform-audit.md](18-phase1-platform-audit.md) · design language [08-design-system-rules.md](08-design-system-rules.md)  
**Goal:** Make the platform the **only daily tool** for management **and** operations — with **enterprise-level workflow, UI, and UX** across every module we use.

---

## 1. North star (internal only)

| We will | We will not (for now) |
|---|---|
| Run **all** EBH event work in this hub | Sell seats to other agencies |
| Improve **every module** we use for management and operations | Leave Agenda / Transport / F&B / etc. as “later” |
| Reach **enterprise-grade workflow + UI/UX** for our team | Generic admin-panel clutter or half-finished screens |
| One guided path from lead → live show → close | Disconnected screens that force WhatsApp/Excel |
| One company, one team, one book of work | Company switcher / SaaS billing |
| Elevate within navy / gold / Playfair Command Center language | Random new theme or full sidebar rewrite for its own sake |

**Success in 90 days:** Two+ live events run end-to-end in-app (Agenda, Transport, Venue, Tasks, Budget, Attendees, …) **and** a new coordinator can follow the workflow without a trainer standing over them. Screens feel premium, clear, and operational — not decorative. Every staff member logs in with their own account.

---

## 2. Pillar A — Enterprise workflow (primary focus)

Workflow is how work moves. UI without workflow is wallpaper.

### 2.1 Official end-to-end spine (train + enforce in product)

```
Client → Deal → Proposal (= quote) → Accept / Win → Event
  → Brief (scope lock) → Team assigned
  → Contract → Payments → Invoices / Budget   ← commercial lock-in early
  → Planning + Tasks
  → Agenda + Speakers
  → Venue + Layout + Equipment
  → Transport (+ Live / Dispatch)
  → Stay / F&B / Exhibition / Sponsors (as needed)
  → Registration → Arrivals / Check-in
  → Show day (Dashboard + Transport Live + Arrivals + Agenda)
  → Completed / Closed → Archive → Client 360
```

**Rule:** After Win, lock commercial documents (Brief → Contract → Invoice path)
before treating the event as “ops-complete.” Ops modules may open in parallel
once the Event exists — they must not replace early commercial lock-in.

Same spine as [`docs/18`](18-phase1-platform-audit.md) §5. Every step must answer:
**Where am I? What do I do next? Who owns it? Is it blocked?**

### 2.2 Workflow quality bar (enterprise)

| Standard | Meaning | How we enforce it |
|---|---|---|
| **Guided handoffs** | Leaving one step lands you on the next with context | CTAs: “Draft proposal”, “Open Agenda”, “Add first movement” |
| **No orphan states** | No won event without commercial figure; no live event with empty agenda if programme is on | Soft gates + Dashboard signals |
| **Single source of truth** | Numbers and statuses live in one place | Budget sync; no dual Excel; catalogues labeled |
| **Role-clear next actions** | CEO / PM / Logistics / Finance see different “do next” | Role-weighted Dashboard + hub Overview attention |
| **Show-day continuity** | Ops tools are one click from Overview | Primary hub strip: Tasks, Agenda, Venue, Transport, Budget |
| **Close the loop** | Archive updates client history | Close-out checklist on Overview |

### 2.3 Workflow work items

| Work item | Effort | Priority |
|---|---|---|
| Spine one-pager + per-role “my day” cards (CEO, PM, Logistics, Programme, Finance, Registration) | Ops | **P0** |
| CRM → Draft proposal CTA; soft win gate | Dev | **Done** (#37) — Event Studio hint still open, **P1** |
| Proposal accept → seed budget / pricing (stop retype) | Dev | **Done** (`b2212db`) — verify PricingTab |
| Hub Overview: “Next steps” strip driven by stage + module readiness (brief, agenda, venue, transport, contract, registration) | Dev | **Done** (`005e4c4`) |
| Soft gate / warn: send contract without approval; go live with empty agenda / no transport when those modules on | Dev | **P1** |
| Approval ping (email or in-app) | Dev | **P1** |
| Cross-links everywhere: Agenda↔Venue room, Transport↔Attendee, Budget↔Invoice, Brief→generate→Planning | Dev | **P1** |
| Close-out wizard: completed → reports snapshot → archive → client | Dev + Ops | **P1** |
| Module readiness checklist on Overview (traffic lights per enabled module) | Dev | **P1** |

---

## 3. Pillar B — Enterprise UI / UX (primary focus)

Look and feel must match a premium event agency command center: **clean, executive, fast, hierarchical** — navy / gold / light canvas (≈80% light, dark for live/show contexts), Playfair for titles. Improve **within** the live design system ([08](08-design-system-rules.md)); do not invent a parallel skin.

### 3.1 UX quality bar (enterprise)

| Standard | Rule |
|---|---|
| **One job per screen region** | Header = identity + stage; body = work; rail = navigation — no competing heroes |
| **Hierarchy** | Title → status → primary action → secondary → table/canvas. Primary CTA always visible |
| **Scan in 3 seconds** | Overview and Dashboard answer “what needs me?” without scrolling past noise |
| **Consistent chrome** | Same buttons, badges, confirms (`<x-confirm>`), alerts (`<x-alert>`), money (`Money`), dates (`<x-date>`), status pills |
| **Empty states that teach** | Every empty module explains why it matters + one primary action (“Add first session”) |
| **Density control** | Laptop: collapse side panel; hub More menu for rare tabs; no 21 equal pills |
| **No false affordances** | Hide Messages / Guests / Assets / Help until built |
| **Premium, not busy** | Prefer whitespace + clear type over card stacks and badge soup |
| **Operational, not decorative** | Every widget deep-links to the record or next action |
| **Show-day glanceable** | Transport Live / Arrivals / Agenda readable at arm’s length |

### 3.2 UI / UX workstreams

#### B1 — Shell & navigation
| Work item | Priority |
|---|---|
| Hide nav ghosts | **Done** (#36) |
| Fix alert bell → Signals | **P1** |
| Collapse panel default &lt; xl; clearer active states | **P1** |
| Hub primary strip = daily ops (Overview, Tasks, Agenda, Venue, Transport, Budget) + More | **Done** (`ced8016`) |
| ⌘K / command palette remains the power user path — document it | **P1** |

#### B2 — Dashboard & Overview (executive command)
| Work item | Priority |
|---|---|
| Role-weighted home (money + approvals for managers; my tasks / movements / sessions for coordinators) | **P0** |
| Ops + money signals (overdue invoices, approvals, empty agenda, transport not ready, risks) | **P0** |
| Event Overview “command strip”: health, next steps, module readiness | **P0** |
| Remove or relocate decorative density that blocks action | **P1** |

#### B3 — Pattern library (apply to ALL modules)
| Pattern | Apply on |
|---|---|
| Page header: title, status/stage, primary + secondary actions | Every hub tab + portfolio list |
| Filters / search row above tables | CRM, Events, Invoices, Attendees, Transport, Suppliers, Agenda |
| Empty state: icon + one sentence + primary CTA | Every module |
| Destructive / money actions via `<x-confirm>` | Already partial — finish gaps |
| Sticky save / context on long forms | Proposal, Contract, Brief, Layout |
| Mobile/tablet: critical ops (Arrivals, Transport Live) usable | Show-day surfaces first |

#### B4 — Module UX passes (enterprise depth, not cosmetic renames)

Each pass = hierarchy audit + empty states + primary CTA + table/canvas readability + “what’s next” + PDF/export where ops needs it.

| Module | UX focus | Priority |
|---|---|---|
| **Agenda** | Builder clarity, day/session hierarchy, room timeline, conflict cues, PDF pack | **P0** |
| **Transport** | Movement board clarity, Live/Dispatch glanceability, status progression, PDF packs | **P0** |
| Venue / Layout / Equipment | Canvas vs equipment tab teaching; PDF packs; less clutter | **P0** |
| Tasks / Planning | Board/timeline clarity; fewer competing views without labels | **P0** |
| Budget / Invoices / Payments | Fee/sell readability; less spreadsheet fear; clear totals | **P0** |
| CRM / Proposals / Contracts | Handoff CTAs; document preview confidence | **P0** |
| Brief / Approvals / Risks | Decision clarity; generate outcomes visible | **P1** |
| Attendees / Registration / Arrivals / Check-in | Desk speed; big tap targets show-day | **P1** |
| Speakers | Session link + billing fields clear | **P1** |
| Suppliers | Status + issue signal obvious | **P1** |
| Stay / F&B | Rooming/menu structure; cost visible | **P1** |
| Exhibition / Sponsors | Floor/booth and package status scannable | **P1** |
| Documents / Reports | Pack export path obvious | **P2** |
| Team / Settings / Catalogues | Admin clarity; labels Equipment vs Price List | **P0** |

#### B5 — Visual polish (premium within system)
| Work item | Priority |
|---|---|
| Consistent primary gold/navy CTAs on Wave 1–2 screens | **P1** |
| Typography: Playfair titles only where hierarchy needs it — not on every label | **P1** |
| Table readability (zebra sparingly, alignment, truncation rules) | **P1** |
| Status badge consistency (model `statusMeta` / shared component) | **P1** |
| PDF stationery quality check for Agenda, Transport, Layout, Equipment, Invoice | **P1** |

**Do not:** wholesale ORBIT revive, purple SaaS tropes, redesign sidebar from scratch, or dark-mode the whole app.

---

## 4. Pillar C — Full module coverage (still in scope)

Same as before: **every hub module** we use for management and operations is in scope. Workflow + UI/UX passes above **include** Agenda, Transport, Venue, etc. — they are not optional add-ons.

| Cluster | Modules |
|---|---|
| Plan | Overview, Brief, Contract, Planning, Tasks, Budget, Pricing, Risks, Approvals |
| Programme | **Agenda**, Speakers |
| Logistics | **Transport** (+ Live, Dispatch), Venue, Layout, Equipment, Suppliers, Stay, F&B |
| Exhibition | Exhibition, Sponsors |
| Sell | Attendees, Registration, Arrivals, Check-in |
| Grow | Documents, Reports |
| Portfolio | Dashboard, Events, CRM, Finance, Library, Team, Settings |

**Hide until built:** Messages, Guests, Assets, Help.  
**Park:** Client portal product, SaaS multi-company.

---

## 5. Supporting workstreams (access, money, stability)

### Access
Invite / set-password + forgot-password — **Done** (`2b32188`). Role matrix + event team roster for pilots — **P0**.

### Money & catalogues
Proposal→budget seed — **Done** (`b2212db`); verify PricingTab. Equipment vs Price List labels — **P0**; split venue budget lines — **P1**; invoice from budget — **P1**; verify Transport/Stay/F&B/Speakers → budget sync — **P1**.

### Stability
CI green; **Test suite (pgsql)** required on `main` — **Done**. Backups — ongoing / **P1**.

---

## 6. Timeline (workflow + UI/UX + full ops)

### Sprint 0 — Days 1–7 · Standards & stabilize
- Hide nav ghosts  
- Publish **workflow spine** + **UX quality bar** to the team  
- Role matrix; seed accounts; 2 pilots; enable modules  
- Agenda / Transport / Venue playbooks  
- Audit top 10 screens against §3.1 (checklist)  

**Exit:** Standards agreed; no fake nav; pilots named.

### Sprint 1 — Days 8–21 · Workflow spine + shell UX
- Access: train invite / password (already on main)  
- Commercial handoffs (CRM proposal CTA, soft win gate); budget seed already on main — verify in pilots  
- Dashboard + Overview next-steps / readiness (workflow UI)  
- Hub primary strip for daily ops  
- Pilot 1: Brief + Agenda started + Transport catalogues + Venue open  

**Exit:** Guided path visible in product; Pilot 1 programme/logistics containers exist.

### Sprint 2 — Days 22–35 · Enterprise UX on programme & transport
- **Agenda builder UX pass** (hierarchy, empty states, conflicts, PDF)  
- **Transport UX pass** (Live/Dispatch glanceability, movement board)  
- Speakers / Tasks / Planning UX pass  
- Catalogue labels; budget line clarity  
- Pattern library applied to Wave 2 screens  

**Exit:** Programme + Logistics leads prefer hub over spreadsheets for Pilot 1.

### Sprint 3 — Days 36–60 · Show-day UX + remaining modules
- Layout / Equipment / Suppliers UX + packs  
- Registration / Arrivals / Check-in **show-day UX** (speed, targets)  
- Stay / F&B / Exhibition / Sponsors UX when used  
- Soft workflow warns (live readiness)  
- Pilot 1 dress rehearsal from Overview → Agenda / Transport Live / Arrivals  
- Pilot 2 starts with full spine  

**Exit:** One show-day (or rehearsal) feels enterprise-operational in UI.

### Sprint 4 — Days 61–90 · Harden UX + workflow from friction
- Per-module UX bug burn-down from pilots  
- Close-out wizard  
- CTA / empty-state / confirm consistency sweep  
- PDF stationery QA  
- Platform default for all new EBH events  

**Exit:** New coordinator can follow spine with Overview next-steps alone.

### Months 4–6
Deepen Agenda density, Transport at scale, layout assists, concurrent events — still **internal UX/workflow**, not SaaS.

---

## 7. Role map

| Title | Rank | Workflow focus |
|---|---|---|
| Owner | `super_admin` | Command + standards |
| Office admin | `admin` | Users, catalogues |
| GM / Ops / Event manager | `manager` | Overview, Approvals, all hubs |
| Finance / Sales lead | `manager` | CRM → Proposal → Contract → Invoice / Budget |
| Project manager | `coordinator` + team | Brief, Planning, Tasks, handoffs |
| Programme / Agenda owner | `coordinator` + team | **Agenda**, Speakers |
| Logistics / Transport lead | `coordinator` + team | **Transport**, Live, Dispatch |
| Venue / Production / Design | `coordinator` + team | Venue, Layout, Equipment, Suppliers |
| F&B / Stay | `coordinator` + team | Catering, Accommodation |
| Registration lead | `coordinator` + team | Attendees, Arrivals, Check-in |
| Exhibition / Sponsor lead | `coordinator` + team | Exhibition, Sponsors |

---

## 8. Acceptance (workflow + UX + ops)

- [ ] Staff login with own accounts  
- [ ] No fake nav destinations  
- [ ] Spine followed for new sales events (or explicit confirm)  
- [ ] Overview shows **next steps** and module readiness  
- [ ] Dashboard is action-driven for managers and coordinators  
- [ ] Agenda UX pass done; PDF in packs  
- [ ] Transport Live/Dispatch usable on show-day; PDF in packs  
- [ ] Empty states + primary CTA on all pilot modules  
- [ ] Money/dates/status use shared components (no one-off formats on touched screens)  
- [ ] Pilot events run without parallel Excel ops books  
- [ ] New coordinator can complete a guided path with minimal hand-holding  
- [ ] Tests green  

---

## 9. Risks

| Risk | Result |
|---|---|
| UI polish without workflow handoffs | Pretty screens, same WhatsApp chaos |
| Workflow gates without UX clarity | Frustrated team, abandoned app |
| Skipping Agenda/Transport UX | Ops never adopts the hub |
| Redesigning chrome instead of module work surfaces | Burned weeks, same friction |
| SaaS features instead of enterprise internal UX | Wrong product for this year |

---

## 10. This week

1. Owner: approve plan with **workflow + enterprise UI/UX as equal pillars** to full modules.  
2. Dev: hide nav ghosts; Overview next-steps; CRM Draft proposal + soft win gate.  
3. Admin: staff + ranks + pilot event-team roles; verify invite email delivery.  
4. Ops: two pilots; module checklist; Agenda & Transport owners named.  
5. Design/PM: print UX quality bar (§3.1) and spine (§2.1) for the wall.  
6. Programme + Logistics: Agenda & Transport playbooks aligned to UX passes.  

**SaaS stays on the shelf. Enterprise workflow and UI/UX for EBH do not.**

---

## 11. Professional execution prompt (copy to Claude / Cursor / developer)

Use the block below as the **system/task prompt** when asking an agent or developer to implement this plan. Paste it whole, then add the sprint or module you want done first.

````markdown
# Elite Business Hub — Internal Improvement Execution Prompt

You are acting as a world-class Enterprise SaaS Product Architect, UI/UX Director,
Senior Laravel / Livewire Engineer, Event Operations Consultant, and QA Lead —
working only on **Elite Business Hub** as an **internal company platform**.

## Mission

Execute the approved internal improvement plan so Elite Business Hub becomes the
**only daily tool** for our company and team to **manage and operate** events —
with **enterprise-level workflow, UI, and UX** across every module we use.

Read and follow:
- `docs/19-internal-improvement-plan.md` (this plan — source of truth)
- `docs/18-phase1-platform-audit.md` (audit evidence)
- `docs/08-design-system-rules.md` (live navy/gold Command Center language)
- `docs/07-user-roles-and-permissions.md` (5 ranks)
- `docs/13-parallel-working-agreement.md` + `docs/14-cross-agent-notes.md` if
  working in parallel with another agent

## Scope — IN

1. **Enterprise workflow** — guided spine, handoffs, Overview next-steps,
   soft gates, show-day continuity, close-out.
2. **Enterprise UI/UX** — premium command-center quality within the existing
   design system; empty states; primary CTAs; density; module UX passes.
3. **Full module operations** — Agenda builder, Transport (Live + Dispatch),
   Venue/Layout/Equipment, Tasks/Planning, Budget/Invoices, CRM/Proposals,
   Attendees/Arrivals/Check-in, Brief/Approvals/Risks, Stay/F&B,
   Exhibition/Sponsors, Documents/Reports, Dashboard — as needed for real EBH events.

## Scope — OUT (do not build)

- Multi-company SaaS, company switcher, subscriptions/billing
- Public self-serve signup, client portal product, marketplace
- Warehouse inventory / SKU availability calendars
- Venue digital twin, 3D, CAD import
- 14 functional roles (keep 5 ranks + event team assignment)
- Separate Quotation/RFQ entity (Proposal = quote)
- Redesigning the sidebar/navigation from scratch or inventing a new theme
- Reviving ORBIT as a platform-wide redesign

## Design & UX rules

- Elevate within **navy / gold / Playfair** Command Center system (~80% light).
- Premium, executive, operational — **not** a generic admin panel.
- Every screen: clear hierarchy, one primary CTA, empty state that teaches,
  shared `<x-confirm>`, `<x-alert>`, `<x-date>`, `Money`, status badges.
- No fake nav (Messages / Guests / Assets / Help stay hidden until real).
- Show-day surfaces (Transport Live, Arrivals, Agenda) must be glanceable.
- Do not break Plan Studio’s deliberate visual exception without asking.
- PDFs are company stationery — keep them faithful and professional.

## Workflow spine (enforce in product + copy)

Client → Deal → Proposal → Accept/Win → Event → Brief → Team →
Planning/Tasks → Agenda/Speakers → Venue/Layout/Equipment → Transport →
Stay/F&B/Exhibition/Sponsors (as needed) → Contract → Payments → Invoices/Budget →
Registration → Arrivals/Check-in → Show day → Completed/Closed → Archive → Client 360.

## Engineering rules

- Repo: Elite Business Hub (`elitehub`). Branch `cursor/<topic>` or `claude/<topic>`;
  PR into `main`; never `git add .`.
- No migrations unless schema lock in `docs/14` allows it; prefer app/UX/workflow fixes.
- Gate every mutating Livewire method; keep `AuthorizationGuardTest` green.
- Run relevant tests; keep Test suite + Static checks green; respect pgsql job.
- Prefer small vertical slices: one workflow handoff or one module UX pass per PR.
- Be concrete: name file, component, route, role, and acceptance criteria.

## Work very smartly (mandatory)

You must work **smart, not wide**. Elite Business Hub is already deep — your job is
high-leverage improvement, not rebuilding the product.

- **Read before you write.** Inspect the real Livewire/Blade/model path for the
  task. Reuse existing patterns (`Money`, `<x-date>`, `<x-confirm>`, gates,
  BudgetSync, hub tabs). Do not invent parallel systems.
- **Highest leverage first.** Prefer one workflow handoff or one UX pass that
  removes daily friction over decorative redesigns.
- **Smallest change that ships the outcome.** Vertical slice → PR. No drive-by
  refactors, no unrelated file churn, no “while I’m here” rewrites.
- **Train + soft gate beats new product.** If process + CTA + confirm dialog
  solves it, do not build a new module or role system.
- **Touch the work surface, not the chrome.** Improve Agenda / Transport /
  Overview / handoffs before redesigning the sidebar or inventing a new theme.
- **One problem per PR.** Name the workflow/UX pain in the PR summary. If scope
  grows, stop and split.
- **Verify with evidence.** Run the relevant tests; click the path mentally
  (or in browser if available). Don’t claim “enterprise UX” without empty
  states, primary CTA, and hierarchy on the screens you touched.
- **Ask only when blocked.** Owner decisions (policy, soft vs hard gate) —
  otherwise choose the plan’s default and proceed.
- **Never confuse activity with progress.** A large diff that doesn’t move the
  spine, Overview next-steps, Agenda, Transport, or access is the wrong work.

If two approaches exist, pick the one that: (1) matches `docs/19`, (2) reuses
code, (3) is testable this week, (4) a coordinator would feel tomorrow morning.

## Priority order when starting fresh

1. ~~Hide nav ghosts~~ **done** (#36)  
2. ~~Invite / set-password + forgot-password~~ **done** — train + verify email  
3. ~~CRM Draft proposal + soft win gate~~ **done** (#37); budget seed **done** — verify PricingTab  
4. ~~Event Overview next-steps / module readiness~~ **done**; Dashboard role weighting + money signals still open  
5. ~~Hub primary strip for daily ops~~ **done**  
6. Agenda builder UX pass  
7. Transport Live/Dispatch UX pass  
8. Then remaining module UX passes + money clarity + show-day polish  

## Definition of done (each task)

- Matches enterprise workflow + UI/UX bars in `docs/19`
- No SaaS scope creep
- Touched screens use shared money/date/status/confirm patterns
- Tests for changed behaviour pass
- Short note in PR: what workflow/UX problem this fixed

## How to respond

1. Restate which sprint/module you are executing — and the **smartest** cut
   (what you will *not* do).  
2. List files you will change.  
3. Implement the smallest vertical slice.  
4. Summarize UX/workflow impact for the EBH team (not SaaS).  
5. Call out anything that should wait or needs an owner decision.

Now execute: **[FILL IN — e.g. “Sprint 0 + Sprint 1 P0 items” or “Agenda builder UX pass” or “Overview next-steps strip”].**
````

### Shorter kickoff line (optional)

If you only need one sentence before the big prompt:

> Execute `docs/19-internal-improvement-plan.md` for Elite Business Hub internal use only — enterprise workflow + enterprise UI/UX + all operations modules (especially Agenda and Transport). Work very smartly: highest leverage, smallest slice, reuse existing patterns, no SaaS or chrome redesigns. Start with: **[task].**

---

*Related: [18-phase1-platform-audit.md](18-phase1-platform-audit.md) · [08-design-system-rules.md](08-design-system-rules.md) · [09-development-roadmap.md](09-development-roadmap.md)*
