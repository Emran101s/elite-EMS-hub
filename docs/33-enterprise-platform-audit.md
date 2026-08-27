# Elite Business Hub — Enterprise Platform Audit

**Date:** 13 August 2026
**Subject:** `~/Herd/elitehub` @ `c436407`, served at `http://localhost:8912`
**Stance:** external enterprise architect / CTO review. Critical by mandate.
**Method:** direct inspection of code, schema, routes, running application and live query profiling. Every number below was measured during this audit. Where I did not verify something directly, I say so.

**No files were modified in producing this report.**

---

## A. Executive Summary

Elite Business Hub is a **strong internal event-operations engine with an unfinished surface and two deployment-blocking configuration defects.**

The domain logic is genuinely good — better than the previous audit gave it credit for, because that audit measured several things with the wrong instrument. The data model is disciplined (59/59 models carry explicit mass-assignment allow-lists; 122 foreign keys; 63 cascade rules). Authorization is real and, on Event, genuinely instance-scoped. Test coverage is broad (125 files, 1,082 tests) and — critically — **all 7 current failures are stale copy assertions, not logic defects.**

What holds it back is not capability. It is three things:

1. **A live authentication bypass.** `GET /_dusk/login/{id}` returns HTTP 200 on the running app and calls `Auth::login()` with no credential check. Anyone who can reach the app can become any user, including by email address. This is registered whenever `APP_ENV !== 'production'`, which is the current state.
2. **The design system has converted the management layer and not the operational layer.** 17 modules are on `eo-*`; 13 are still legacy — and the 13 are precisely the Event Hub interior where the actual event work happens (Transport, Budget, Attendees, Accommodation, Registration, Check-in, Approvals, Risks…). The most-opened page in the product, Hub Overview, is the one file that is *mixed*.
3. **Performance characteristics are unmeasured and already poor at demo scale.** Command Center issues **92 queries** against 20 events. Event Hub Budget issues 88. Portfolio "pagination" is cosmetic — the query loads every row, then slices in memory.

**Correction to the prior audit (docs/30):** it reported "21% design-system conversion" and implied a mass-assignment gap. Conversion has moved materially since (17 of 31 core modules now converted), and mass assignment was never a gap — the codebase uses PHP 8 `#[Fillable]` attributes, which a `grep 'protected $fillable'` does not see. I made the same mistake mid-audit and corrected it. Recording both.

**Verdict: safe for solo exploration and a supervised desk pilot. Not safe for on-site operations or unsupervised company-wide use** until §C's blockers clear.

---

## B. Overall Platform Score

Scored against the stated product — **an internal operations platform for Elite's own team** (per the 2026-08-09 internal-first decision), not a commercial SaaS.

| Dimension | Score | Basis |
|---|---:|---|
| Data model & integrity | **82** / 100 | 59/59 fillable, 122 FKs, 63 cascades, 58 nullOnDelete, 47 indexes |
| Business logic | **78** / 100 | 15 services, single-source `EventMission`/`EventCommandHeader`; some logic still in 900+ LOC components |
| Test readiness | **74** / 100 | 1,075/1,082 pass; all failures stale; zero coverage on Risks, Exhibition, RBAC |
| Security (design) | **72** / 100 | 356 `authorize()`, event-scoped `EventPolicy`, throttled auth, token surfaces sound |
| Security (deployed config) | **25** / 100 | Dusk auth bypass live, `APP_DEBUG=true`, `MAIL_FROM=hello@example.com` |
| Event delivery flow | **72** / 100 | Deep and real — agenda, transport, rooming, run-of-show |
| Commercial flow | **70** / 100 | Deal → proposal → contract → invoice chain intact and tested |
| Finance flow | **62** / 100 | Strong budget/ledger logic; money formatting not centralised (175 local `number_format`) |
| UI / visual system | **58** / 100 | 17 converted, 13 legacy, 1 mixed — seam runs through the busiest page |
| UX & IA | **64** / 100 | Navigation now coherent; Hub interior still dense and inconsistent |
| Navigation | **72** / 100 | Materially improved this cycle; Orbit Journey resolved the worst friction |
| Performance | **45** / 100 | 92 queries @ 20 events; cosmetic pagination; SQLite; no cache; no queue |
| Mobile / tablet | **40** / 100 | 81/235 views carry breakpoints; on-site screens are legacy and desktop-shaped |
| Operational readiness | **38** / 100 | No queue worker, no backups verified, mail to log, debug on |
| Internal production readiness | **55** / 100 | Blocked by config, not by capability |

### **Overall: 62 / 100**

A capable system running in an unsafe configuration with an unfinished skin. The gap between 62 and ~80 is mostly **configuration and finishing**, not new engineering — which is unusually good news.

---

## C. Current Readiness Verdict

| Scenario | Verdict | Blocking reason |
|---|---|---|
| **1. Solo exploration** (you, localhost) | ✅ **Safe now** | None. |
| **2. Desk-team pilot** (5–10 staff, office network) | ⚠️ **Conditional** | Must fix Dusk bypass + `APP_DEBUG=false` + real `MAIL_FROM` first. Otherwise safe: data model and authz hold. |
| **3. On-site operations** (live event, phones, venue Wi-Fi) | ❌ **Not safe** | Check-in and Arrivals are legacy, desktop-shaped, and have no offline mode. A venue Wi-Fi drop stops door operations with no fallback. |
| **4. Full internal company use** | ❌ **Not safe** | SQLite single-writer under ~19 concurrent users; no verified backup/restore; no queue worker; demo data intermixed with real staff accounts. |

---

## D. Module-by-Module Scores

Design-token status, view size and test-file count are **measured**. UI/UX/logic scores are judgement informed by that evidence plus direct inspection of the modules I opened.

| Module | Tokens | View LOC | Tests | UI | UX | Logic | Data | Perf | Sec | Ready | **Overall** |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| Command Center | `eo-*` | 275 | 7 | 82 | 78 | 80 | 82 | **35** | 70 | 68 | **72** |
| Event Portfolio | `eo-*` | 568 | 3 | 88 | 85 | 82 | 82 | **40** | 75 | 72 | **76** |
| Event Hub shell / Orbit | `eo-*` | 102 | 2 | 88 | 84 | 82 | 80 | 55 | 78 | 74 | **78** |
| Hub Overview | **MIXED** | 572 | 1 | **48** | 58 | 70 | 78 | 50 | 72 | 55 | **60** |
| Event Studio | `eo-*` | 685 | 1 | 80 | 76 | 72 | 78 | 65 | 72 | 66 | **72** |
| Agenda Builder | `eo-*` | 1110 | 10 | 84 | 76 | 86 | 82 | 45 | 74 | 70 | **77** |
| Planning (Plan Studio) | LEGACY* | 129 | 10 | 62 | 70 | 76 | 78 | 60 | 72 | 66 | **69** |
| Tasks | LEGACY | 137 | 9 | 55 | 68 | 76 | 80 | 60 | 72 | 66 | **68** |
| CRM / Deals | `eo-*` | 440 | 6 | 82 | 78 | 78 | 80 | 70 | 70 | 70 | **75** |
| Clients | `eo-*` | 185 | 9 | 80 | 76 | 74 | 80 | 72 | 70 | 70 | **74** |
| Proposals | `eo-*` | 190 | 7 | 80 | 76 | 80 | 82 | 72 | 72 | 72 | **76** |
| Contracts | `eo-*` | 197 | 12 | 80 | 76 | 84 | 84 | 72 | 74 | 74 | **78** |
| Budget | **LEGACY** | 874 | 21 | **50** | 66 | 86 | 84 | **40** | 74 | 64 | **68** |
| Finance Overview | `eo-*` | 171 | 5 | 80 | 74 | 74 | 78 | 68 | **62** | 68 | **72** |
| Invoices Ledger | `eo-*` | 181 | 9 | 80 | 76 | 80 | 82 | 68 | 70 | 70 | **75** |
| Payments Ledger | `eo-*` | 151 | 11 | 80 | 74 | 76 | 80 | 68 | 70 | 68 | **73** |
| Suppliers | `eo-*` | 146 | 8 | 78 | 70 | 60 | 76 | 70 | 70 | 62 | **69** |
| Venues | `eo-*` | 161 | 20 | 78 | 72 | 70 | 80 | 70 | 72 | 70 | **73** |
| Transport | **LEGACY** | 1038 | 16 | **48** | 66 | 86 | 84 | 45 | 74 | 60 | **68** |
| Accommodation | **LEGACY** | 460 | 13 | 48 | 66 | 78 | 80 | 55 | 72 | 62 | **66** |
| Catering | **LEGACY** | 201 | 9 | 48 | 62 | 58 | 74 | 62 | 72 | 56 | **61** |
| Exhibition | **LEGACY** | 189 | **0** | 48 | 62 | 62 | 74 | 60 | 70 | 52 | **60** |
| Sponsors | **LEGACY** | 225 | 5 | 48 | 62 | 66 | 76 | 62 | 70 | 58 | **63** |
| Attendees | **LEGACY** | 473 | 12 | 48 | 64 | 76 | 80 | **42** | 70 | 60 | **65** |
| Registration (public) | **LEGACY** | 180 | 11 | 52 | 66 | 78 | 80 | 65 | **78** | 62 | **68** |
| Check-in | **LEGACY** | 103 | 5 | 45 | 55 | 70 | 78 | 65 | 76 | **35** | **59** |
| Risks | **LEGACY** | 120 | **0**† | 48 | 62 | 66 | 76 | 65 | 70 | 55 | **62** |
| Approvals | **LEGACY** | 275 | 8 | 48 | 64 | 80 | 80 | 62 | 74 | 64 | **68** |
| Reports | `eo-*` | 239 | 1 | 76 | 66 | 55 | 72 | **45** | **62** | 52 | **63** |
| Team | `eo-*` | 126 | 7 | 78 | 72 | 70 | 78 | 78 | 72 | 68 | **73** |
| Settings / Catalogues | `eo-*` | 65 | 29 | 78 | 72 | 74 | 80 | 74 | 72 | 72 | **75** |

\* Plan Studio is a **deliberate** design exception per `docs/08-design-system-rules.md` — not debt.
† Risks has no dedicated test file; it is touched indirectly by health-score tests.

**Weakest modules: Check-in (59), Exhibition (60), Hub Overview (60), Catering (61), Risks (62).**
**Strongest: Event Hub shell/Orbit (78), Contracts (78), Agenda Builder (77), Proposals (76), Event Portfolio (76).**

---

## E. Page-Level Audit

I inspected these directly in the running application during this and the immediately preceding work. I am **not** fabricating page-level scores for pages I did not open.

### Directly inspected

**Command Center** — Purpose clear, header strong, KPI strip real. Action-First layout works. **Critical issue: 92 queries.** Drill-through is consistent. *Priority: performance only.*

**Event Portfolio** — Best-executed page in the product. Five views, stable controls, one shared detail panel, honest Calendar placeholder. **Issue: pagination is cosmetic** (`->get()` then in-memory `forPage`). *Priority: high (perf), low (UI).*

**Event Hub shell + Orbit Journey** — Newly rebuilt; Event Core dominant, stage cards readable, progress ring real, satellites replace the old nav strip. Renders correctly at desktop/tablet/mobile. *Priority: none — just landed.*

**Hub Overview** — **The single worst page relative to its importance.** It is the only MIXED-token file in the product, 572 LOC, and it is what every user sees first on every event. Old chrome sits directly beneath the new Orbit. *Priority: **highest** UI item in the platform.*

**Agenda Builder** — Six views, conflict detection, inspector, drag. Genuinely strong. 1,110 LOC view is large but coherent. **69 queries.** *Priority: medium (perf).*

**Operations / Intelligence / Team / Settings** — Converted this cycle; headers, tables and empty states consistent. Reports is converted but thin on function (see §D). *Priority: low.*

### Inspected in code only (not opened in browser this audit)

Transport, Accommodation, Catering, Exhibition, Sponsors, Attendees, Registration, Check-in, Risks, Approvals, Budget, Tasks. Scores in §D for these are derived from token status, view size, test coverage and code reading — **not** from visual inspection. Any page-level UI score here should be treated as provisional until walked.

---

## F. UI / UX Audit

| Aspect | Score | Evidence |
|---|---:|---|
| Design-system adoption | **55** | 17 converted / 13 legacy / 1 mixed across 31 core modules |
| Typography | 74 | `eo-display`/`eo-title`/`eo-label` consistent *within* converted set |
| Spacing & rhythm | 72 | Consistent in `eo-*`; legacy uses a different scale |
| Cards | 76 | `eo-domain-card`/`mission-card` variant system is genuinely good |
| Tables | 62 | `eo-table-wrap` good; legacy tables use fixed `min-w-[820px]`+ |
| Forms | 64 | `eo-input`/`eo-select` solid; legacy forms diverge |
| Buttons / chips / pills | 78 | `x-eo.button`, `status-pill` tone system is coherent |
| Empty states | 70 | `x-eo.empty-state` exists and is used — but not in legacy modules |
| Loading states | **35** | No systematic `wire:loading` treatment found |
| Error states | **40** | No shared error-surface component; validation is per-field |
| Dark/light balance | 80 | Correctly 80/20; dark reserved for hero surfaces |
| Accessibility | **30** | Unaudited. 21 `@can` in views, but no focus-order/contrast/ARIA pass |
| Premium feel | 72 | High in converted set; the seam undercuts it |
| Enterprise feel | 62 | Undermined by legacy screens and missing loading/error states |

**The core UI finding:** the design system is *good* and the *conversion order was wrong*. Converting portfolio/commercial/admin first meant the design investment landed where staff spend least time. The Event Hub interior — where an event actually gets delivered — is still on 2024 chrome and is 3,000+ LOC of legacy view code.

---

## G. Navigation / IA Audit

**Score: 72/100.** Materially improved this cycle.

Working: 8-area rail; per-area context sidebar with core links only; Orbit Journey collapsing 23 tabs into 8 stages + contextual satellites; breadcrumbs on every hub page; command palette exists.

Remaining friction:
- **Two navigation models coexist.** Orbit governs the Hub; everything else uses rail + sidebar. Defensible, but the Hub feels like a different application.
- **Command palette is shallow** (76 LOC). It is a search box, not a command surface — no actions, no recent items, no cross-entity jump.
- **No global "what needs me" view across events.** Priority Area is per-event; Command Center's queue is portfolio-level. A coordinator on four events has no single worklist.
- **`/operations-room`, `/concept/*`, `/design/*` routes are still registered** — dead or demo surfaces reachable in a pilot.

---

## H. Workflow Audit

| Workflow | State | Gap |
|---|---|---|
| Event create → hub → deliver | ✅ Intact | — |
| Deal → proposal → contract → invoice | ✅ Intact, tested | Payment is manual by design (correct for internal) |
| Budget → actuals → variance | ✅ Strong | Commitments not in budget surface only as an alert |
| Agenda → conflicts → run-of-show → PDF | ✅ Strong | — |
| Approval → notify → decide → audit | ✅ Wired | **Email goes to a log file** |
| Registration → confirm → check-in | ⚠️ Broken in practice | Confirmation email → log; check-in has no offline mode |
| Transport plan → dispatch → driver sheet | ✅ Strong | Driver-facing surface is desktop-shaped |
| Risk → mitigation → closure | ⚠️ Thin | No escalation workflow, no tests |
| Supplier → RFQ → PO → invoice | ❌ Absent | No PO concept exists |
| Post-event → report → close | ⚠️ Thin | Reports module is read-only, no export |

---

## I. Logic Audit

**Strengths (verified):**
- `EventMission::for()` is a genuine single source of truth — Portfolio's five views all read it, so a card and a row cannot disagree. This is the right pattern and it is applied consistently.
- `EventCommandHeader` computes header/meters/attention/readiness once per request; Orbit Journey re-presents it rather than recomputing. **No duplicate computation was introduced.**
- Health scoring is centralised in `EventHealthService` and excludes unscored events from averages rather than treating them as zero.
- `AgendaConflicts` was correctly extracted from the component into a service.

**Defects and risks:**
1. **Money formatting is not centralised, contrary to documented rule.** `docs/08` mandates `App\Support\Money`. Reality: **24** `Money::` calls vs **175** local `number_format()` and **58** ad-hoc `/100` conversions. Rounding and symbol placement *will* drift.
2. **Date formatting likewise.** `<x-date>` used **4** times; raw `->format()` in views **172** times.
3. **Fat components hold business logic.** `TransportationTab` 1,178 LOC, `ContractTab` 978, `AgendaTab` 957, `BudgetTab` 896. These are controllers wearing component clothes; they are where the next regression lands.
4. **`Tenancy::id()` still fails open** — an unbound query returns all rows. Safe *today* because there are **0 jobs and 0 console commands** and `ResolveTenant` is global middleware. It becomes a live bug the day anything runs outside HTTP.
5. **Orbit stage `pct` is `null` for 4 of 8 stages** by design (meters() covers 6 of 23 tabs). Honest, but means half the ring carries no readiness signal.

---

## J. Database / Model Audit

**Score: 82/100 — the strongest layer in the platform.**

| Metric | Measured |
|---|---:|
| Models | 59 |
| Migrations | 120 |
| Tables | 75 |
| Foreign keys | 122 |
| `cascadeOnDelete` | 63 |
| `nullOnDelete` | 58 |
| `index()` | 47 |
| `unique()` | 25 |
| Models with explicit fillable | **59 / 59** |
| Models with `$guarded = []` | **0** |
| `SoftDeletes` | **1 model** |

**Strengths:** every model has an explicit mass-assignment allow-list (via `#[Fillable]`); FK coverage and delete semantics are deliberate (`cascade` vs `nullOnDelete` chosen per relationship, not blanket); `tenant_id` present and indexed platform-wide with `TenancyGuardTest` failing the build if a model is missed.

**Concerns:**
1. **Only one model uses soft deletes.** Event deletion is permanent and cascades to tasks, budgets, documents, contracts and bookings. `DeleteEventTest` covers it, and the UI confirms twice — but there is **no recovery path** for a mis-click by a manager. For an internal tool with 19 users this is the single highest data-loss risk.
2. **47 indexes across 75 tables is thin** for the query patterns in use. Status/date columns filtered on every dashboard (`events.stage`, `events.starts_at`, `tasks.due_on`, `approvals.status`) should be verified as indexed.
3. **No `Money` value object** despite `_cents` integer discipline in the schema — the discipline stops at the DB boundary (see §I.1).
4. **Status fields are string constants, not enums.** `Event::STAGE_COLORS`, `Task::STAGES`, `PlanItem::STATUSES` are class constants. Workable and greppable, but nothing prevents an invalid status reaching the column.

---

## K. Security Audit

### 🔴 CRITICAL

**K1 — Laravel Dusk test-login route is live and grants authentication to anyone.**
- **Evidence:** `curl http://localhost:8912/_dusk/login/1` → **HTTP 200**. `vendor/laravel/dusk/.../UserController::login()` calls `Auth::guard($guard)->login($user)` with **no credential check**, and accepts an email address as the identifier (`/_dusk/login/emran.itan@elitebhub.com`). `DuskServiceProvider` registers these routes whenever `APP_ENV !== 'production'`; current `APP_ENV=local`.
- **Why it matters:** total authentication bypass. Anyone who can reach the host — office LAN, a shared tunnel, a demo link — becomes super_admin in one GET request.
- **Fix:** set `APP_ENV=production` for any shared deployment **and** `composer install --no-dev`. Verify `/_dusk/login/1` returns 404.
- **Effort:** minutes. **Dependency:** none.

**K2 — `APP_DEBUG=true`.**
- **Evidence:** `.env`.
- **Why it matters:** any unhandled exception renders a stack trace exposing file paths, environment variables, and query fragments to the browser.
- **Fix:** `APP_DEBUG=false` outside local dev. **Effort:** minutes.

### 🟠 HIGH

**K3 — Demo accounts use real company email addresses.** 19 seeded users on `@elitebhub.com` (plus `@test.com`). The moment `MAIL_MAILER` becomes real SMTP, seeded approval and invite notifications reach **real colleagues' inboxes** from a system holding fake data. *Fix: purge demo users before enabling mail, or gate mail to an allow-list during pilot.*

**K4 — `MAIL_FROM_ADDRESS=hello@example.com`.** Any mail that does send will be spoofed-looking and likely rejected by receiving servers.

**K5 — Four Livewire components carry no authorization call.** `Dashboard` (286), `ReportsOverview` (172), `ContractsRegister` (190), `FinanceOverview` (71). All are route-protected by `Authenticate` middleware, so this is **not** an anonymous-access hole — but it means **any authenticated user, including a `viewer`, can read portfolio finance and the full contracts register.** For an internal tool that may be acceptable; it should be a *decision*, not an accident.

### 🟡 MEDIUM

**K6 — `/_dusk/*`, `/concept/*`, `/design/*`, `/operations-room` are registered routes.** Demo and scaffold surfaces reachable by any authenticated user.

**K7 — `{!! !!}` unescaped output: 23 instances.** Reviewed: the non-PDF ones are `$css` injection into a `<style>` tag, an Alpine expression in `x-confirm`, and inline SVG helpers — all developer-controlled, none user-controlled. **No XSS vector found**, but `confirm.blade.php:27` interpolates a `run` expression directly into JavaScript; if a caller ever passes user data there it becomes one.

**K8 — Storage route is unauthenticated but not exploitable.** `config/filesystems.php` sets `'serve' => true` on the private `local` disk, registering `GET|PUT /storage/{path}` with **no middleware**. I tested this against a real uploaded event document (`/storage/event-documents/7/…pdf`) unauthenticated: **HTTP 403**. Laravel's serve route honours file visibility, and documents stored via `->store(…, 'local')` are private by default. Event documents are additionally served through `events/{event}/documents/{document}/download|view`, both carrying `Authenticate` + `Authorize:view,event`. **No exposure. Downgraded from suspected-High to informational** — but the unauthenticated `PUT /storage/{path}` route should still be reviewed before any shared deployment.

### ✅ Verified protections (genuine strengths)

- **356 `authorize()` calls, 354 `Gate::` usages, 38 explicit 403 assertions in tests.**
- **`EventPolicy` is instance-scoped**, not a flat role check: below manager, access narrows to actual team membership on that event — and the export routes (~35 PDF endpoints) inherit it via `can:view,event`. This closed a real IDOR (changing the event ID in a contract PDF URL).
- **59/59 models have explicit mass-assignment allow-lists.**
- **Auth throttling:** `throttle:10,1` on login, `throttle:5,1` on password reset/forgot.
- **Public token surfaces are minimal by design** — registration and check-in tokens are separate, so rotating the public sign-up URL does not invalidate confirmations already sent.
- **Zero queued work** means the tenancy fail-open gap has no non-HTTP entry point to leak through today.

---

## L. Performance Audit

**Measured live, authenticated, against current data (20 events, 626 attendees, 19 users):**

| Page | Queries | Time |
|---|---:|---:|
| Command Center | **92** | 95 ms |
| Event Hub — Budget | **88** | 23 ms |
| Event Hub — Agenda | **69** | 24 ms |
| Event Hub — Transport | **61** | 20 ms |
| Event Hub — Overview | **51** | 20 ms |
| Reports | **50** | 46 ms |
| Event Portfolio | 37 | 41 ms |
| Finance Overview | 29 | 23 ms |
| CRM Pipeline | 16 | 8 ms |
| Team | 9 | 9 ms |

**Findings:**

1. **Command Center at 92 queries for 20 events is the platform's worst hot spot.** Response time is masked by SQLite locality and tiny data; on Postgres over a network this becomes user-visible.
2. **Portfolio pagination is cosmetic.** `EventsIndex::render()` calls `->get()` (all rows, with `EventMission::RELATIONS` + `EventHealthService::RELATIONS` eager-loaded), maps every row into a mission, *then* wraps a `forPage()` slice in a `LengthAwarePaginator`. Page 1 of 10 costs exactly as much as loading everything. **`paginate()` appears 0 times in 36,433 lines of app code.**
3. **`Cache::` appears twice.** Portfolio and dashboard aggregates recompute per request.
4. **No queue.** `QUEUE_CONNECTION=database` is configured but **0 jobs exist**; notifications send synchronously inside the request; 25 PDF controllers render Browsershot (headless Chrome) in-request.
5. **SQLite in the primary environment.** Single-writer. With 19 users this will produce `database is locked` under concurrent writes. *(Note: the audit brief states MySQL — the running app is SQLite. A Postgres cutover plan exists at `docs/17` and has not been executed.)*

**Honest scaling ceiling on current architecture:** comfortable to ~10 concurrent users and ~50 events. Beyond that, SQLite write contention bites first, then the unpaginated portfolio.

---

## M. Test Audit

| Metric | Value |
|---|---:|
| Test files | 125 (115 Feature, 9 Unit, 1 Browser) |
| Tests | **1,082** |
| Passing | **1,075** |
| Failing | **7** |
| Explicit skips | 2 |
| Test LOC | 23,507 |

**All 7 failures are stale assertions against changed copy or tokens — not logic defects.** Verified individually:

| Test | Asserts | Reality |
|---|---|---|
| `ContractsRegisterTest::the_register_page_renders` | `"Every agreement in the book"` | copy changed |
| `ContractsRegisterTest::the_nav_links_to_it` | literal `http://localhost:8912/contracts` | env-coupled assertion |
| `FlashMessageSeverityTest` | `text-emerald-700` | token replaced by `eo-*` |
| `PaymentsLedgerTest::the_ledger_renders` | `"in the order the money is due"` | copy changed |
| `UnscoredEventHealthTest` | `"Not Started"` | now `"Not started"` |
| `EventHubTest::events_overview_kpis_and_filters` | `"Deck View"`, `"Flight Path"` | views retired in Phase C.1 |
| `EventHubTest::header_keeps_its_shape` | pre-redesign header shape | header rebuilt |

**This is design-churn debt in the test suite, not a broken product.** It is cheap to clear and should be cleared, because a permanently-red baseline trains the team to ignore red.

**Coverage gaps (zero or near-zero dedicated tests):**
- **Risks — 0** · **Exhibition — 0** · **RBAC/permissions — 0 dedicated file**
- Approvals — 1 · Supplier — 1 · Reports — 1 · Catering — 1 · Accommodation — 1 · Sponsor — 1 · Event Studio — 1 · Hub Overview — 1

**Strong coverage:** Budget (21), Venues (20), Event (17), Transport (16), Accommodation (13), Attendees (12), Contracts (12), Payments (11), Registration (11).

**Highest-risk untested area:** role/permission enforcement has no dedicated suite despite 6 gates and an instance-scoped policy governing ~35 export routes. 38 forbidden-assertions exist but are scattered.

---

## N. Data Readiness Audit

**Current state: demo and real data are already intermixed.**

- 20 events, 8 clients, 19 users, 45 tasks, 626 attendees — all seeded.
- Seeders are **deterministic, not faker-based** (0 faker references in `database/seeders`) — which makes the demo data look convincingly real.
- **Users #1–#6 carry genuine `@elitebhub.com` addresses** (Emran, Layla, Omar, Sara, Khalid, Dana). Users #8–#9 are real names on `@test.com`.
- 1 tenant, 1 workspace.

**Risk:** there is no marker distinguishing seeded rows from real ones. Once staff begin entering real events alongside 20 demo events, separating them later becomes manual archaeology.

**Recommendation: create a clean database for the pilot.** Do not purge in place.
1. Snapshot the current SQLite file as the demo/reference dataset.
2. Migrate fresh, seed **only** real users and the price/catalogue reference data (`PriceListSeeder`).
3. Enter the first real event by hand — this is also the best end-to-end test the platform will get.
4. Keep `DemoDataSeeder` for local development only; never run it against the pilot DB.

**Backup/restore:** a drill is documented (`docs/15`) but I did **not** verify a restore during this audit. Treat as unproven.

---

## O. Internal Production Readiness Audit

| Check | State | Verdict |
|---|---|---|
| `APP_ENV` | `local` | ❌ enables Dusk bypass |
| `APP_DEBUG` | `true` | ❌ leaks traces |
| `APP_URL` | `http://localhost:8912` | ⚠️ no TLS, single host |
| `DB_CONNECTION` | `sqlite` | ❌ single-writer |
| `MAIL_MAILER` | `log` | ❌ nothing reaches anyone |
| `MAIL_FROM_ADDRESS` | `hello@example.com` | ❌ placeholder |
| `QUEUE_CONNECTION` | `database`, 0 jobs, no worker | ⚠️ configured, unused |
| `CACHE_STORE` | `database`, 2 call sites | ⚠️ effectively unused |
| Login / roles / invites | working, throttled, 6 gates | ✅ |
| Event creation → hub → delivery | working | ✅ |
| Finance chain | working | ✅ |
| Agenda / run-of-show | working | ✅ |
| Registration | works; confirmation → log | ⚠️ |
| Check-in | works desktop; **no offline** | ❌ for on-site |
| Reports | render; **no export** | ⚠️ |
| Mobile | 81/235 views responsive | ❌ for on-site |
| Backups | documented, **unverified** | ⚠️ |

---

## P. Top 25 Issues

| # | Severity | Module | Issue | Fix | Effort |
|---:|---|---|---|---|---|
| 1 | 🔴 **Critical** | Platform | `/_dusk/login/{id}` grants auth to anyone (HTTP 200 verified) | `APP_ENV=production` + `--no-dev` | 15 min |
| 2 | 🔴 **Critical** | Platform | `APP_DEBUG=true` leaks stack traces | `.env` | 5 min |
| 3 | 🔴 **Critical** | Data | Demo users on real `@elitebhub.com` addresses + mail about to go live | Clean pilot DB | 0.5 day |
| 4 | 🟠 High | Platform | SQLite single-writer under 19 users | Execute `docs/17` Postgres cutover | 1–2 days |
| 5 | 🟠 High | Platform | Mail goes to a log file; approvals/registrations silent | Real SMTP + `MAIL_FROM` | 0.5 day |
| 6 | 🟠 High | Check-in | No offline mode; venue Wi-Fi failure stops the door | Local queue + sync | 3–5 days |
| 7 | 🟠 High | Hub Overview | Only MIXED-token page; most-opened screen | Convert to `eo-*` | 1–2 days |
| 8 | 🟠 High | Command Center | 92 queries @ 20 events | Cache aggregates, audit `CommandCenterService` | 1–2 days |
| 9 | 🟠 High | Portfolio | Pagination cosmetic — loads all rows | Push filtering/paging into the query | 1–2 days |
| 10 | 🟠 High | Data | Only 1 model soft-deletes; event delete unrecoverable | Soft-delete Event + restore path | 1 day |
| 11 | 🟠 High | Finance | 175 local `number_format` vs 24 `Money::` — rounding will drift | Enforce `Money` helper | 2–3 days |
| 12 | 🟠 High | Testing | 7 red baseline tests train the team to ignore red | Update stale assertions | 0.5 day |
| 13 | 🟠 High | Security | RBAC has no dedicated test suite | Add permission matrix tests | 1–2 days |
| 14 | 🟡 Medium | Hub interior | 13 legacy modules, 3,000+ LOC of old chrome | Staged conversion | 2–3 weeks |
| 15 | 🟡 Medium | Platform | No loading states (`wire:loading` unsystematic) | Shared loading treatment | 2–3 days |
| 16 | 🟡 Medium | Platform | No shared error-surface component | Add one | 1–2 days |
| 17 | 🟡 Medium | Mobile | 81/235 views responsive; on-site screens desktop-shaped | Mobile pass on Check-in/Arrivals/Dispatch | 1 week |
| 18 | 🟡 Medium | Platform | Accessibility never audited | Contrast/focus/ARIA pass | 3–5 days |
| 19 | 🟡 Medium | Architecture | 4 components >870 LOC holding business logic | Extract services | 1 week |
| 20 | 🟡 Medium | Tests | Risks (0), Exhibition (0) untested | Add feature tests | 2 days |
| 21 | 🟡 Medium | Reports | Read-only; no export | CSV/PDF export | 2–3 days |
| 22 | 🟡 Medium | Platform | `/concept/*`, `/design/*`, `/operations-room` live | Remove or gate | 1 hour |
| 23 | 🟡 Medium | Dates | 172 raw `->format()` vs 4 `<x-date>` | Enforce component | 1–2 days |
| 24 | 🟢 Low | Tenancy | `Tenancy::id()` fails open | Throw when unbound (before first job) | 0.5 day |
| 25 | 🟢 Low | Nav | Command palette is a search box, not a command surface | Deepen | 2–3 days |

---

## Q. Top 25 Opportunities

1. Finish the Hub interior conversion — biggest perceived-quality jump available.
2. Cache portfolio/dashboard aggregates — 92 → ~15 queries.
3. Real pagination — unlocks growth past 50 events.
4. Postgres — removes the concurrency ceiling.
5. Offline check-in — turns an unusable on-site tool into a differentiator.
6. Mobile pass on the three on-site screens.
7. Soft-delete + restore for Event.
8. Enforce `Money` and `<x-date>` — kills a whole class of future bug.
9. Green the baseline and gate merges.
10. RBAC test matrix.
11. Extract the four fat components into services.
12. Report export.
13. Systematic loading/error/empty states as one component family.
14. Purchase orders — closes the only genuinely absent workflow.
15. Risk escalation workflow.
16. Accessibility pass.
17. Global cross-event "my work" view.
18. Deepen the command palette into real actions.
19. Queue worker + move PDFs off-request.
20. Index audit on status/date columns.
21. Enum-back the status fields.
22. Verify the backup restore drill for real.
23. Archive demo data; start clean.
24. Document the seam explicitly so nobody "fixes" Plan Studio by accident.
25. Add a `/health` dashboard for the pilot (queue, mail, DB, last backup).

---

## R. Recommended Improvement Roadmap

### Phase 1 — Immediate blockers before any pilot *(1–2 days)*
| Item | Module | Effort | Score Δ |
|---|---|---|---|
| `APP_ENV=production`, `--no-dev`, verify `/_dusk/*` → 404 | Platform | 15 min | Security 25 → 70 |
| `APP_DEBUG=false` | Platform | 5 min | included |
| Real SMTP + `MAIL_FROM` | Platform | 0.5 day | Ops 38 → 55 |
| Clean pilot database (snapshot demo, seed real users only) | Data | 0.5 day | Data readiness → 80 |
| Remove/gate `/concept/*`, `/design/*`, `/operations-room` | Platform | 1 hr | — |

**Exit criteria: desk-team pilot is safe.**

### Phase 2 — Two weeks
Postgres cutover · green the 7 stale tests + CI gate · Hub Overview conversion · Command Center query reduction · real Portfolio pagination · soft-delete Event · RBAC test matrix.
**Expected overall: 62 → 72.**

### Phase 3 — 30 days
Hub interior conversion (13 modules, staged: Budget → Transport → Attendees → Approvals → rest) · `Money`/`<x-date>` enforcement · loading/error state system · report export · Risks + Exhibition tests.
**Expected overall: 72 → 80.**

### Phase 4 — Operational excellence
Offline check-in · mobile pass on on-site screens · queue worker + off-request PDFs · accessibility pass · extract fat components · index audit · verified restore drill · pilot health dashboard.
**Expected overall: 80 → 87.**

### Phase 5 — Strategic
Purchase orders / supplier RFQ · cross-event personal worklist · command palette as command surface · risk escalation · real LLM assistant grounded on the existing rules engine · (only if SaaS is ever revived) close the tenancy fail-open.

---

## S. What To Fix First

**In this order. Do not reorder — items 1–3 are safety, not quality.**

1. **`APP_ENV=production` + `composer install --no-dev`.** Verify `curl /_dusk/login/1` returns 404. Nothing else matters until this is done if the app is reachable by anyone but you.
2. **`APP_DEBUG=false`.**
3. **Clean pilot database** — before enabling real mail, so no seeded notification reaches a real colleague.
4. **Real SMTP.**
5. **Postgres.**
6. **Green the 7 tests, gate CI.**
7. **Hub Overview conversion.**

Items 1, 2 and 4 are configuration. Item 3 is half a day. **The platform's single largest risk is closed by editing three lines of `.env`** — which is the most actionable finding in this report.

---

## T. What Not To Touch Yet

- **Plan Studio** — a documented, deliberate design exception (`docs/08`). Leave it.
- **The tenancy spine** — dormant by decision. Do not extend it; do not rip it out.
- **Orbit Journey** — just landed and approved. Let it sit before iterating.
- **Any new workspace** — Operations Control, Layout Builder, Exhibition Builder, Transport Dispatch redesign, Budget redesign. **The platform does not need more surface area; it needs the surface it has finished.**
- **Payment gateway / SSO / public API / multi-tenancy** — removed from the critical path by the internal-first decision.
- **The design system itself.** It is good. Seven visual directions in fourteen months is the historical pattern; the correct move now is to *finish* Elite Command OS, not to evaluate an eighth.

---

## U. Final Recommendation

**Elite Business Hub scores 62/100 and is roughly three days of configuration work away from being safely pilotable, and roughly six weeks away from being genuinely good.**

The engineering underneath is better than the platform looks. The data model is disciplined, authorization is real and thoughtfully instance-scoped, and the test suite is broad and — once seven stale copy assertions are updated — green on logic. The domain depth in transport, agenda, budget and contracts is the real asset and is not at risk.

Three things are actually wrong, and only one of them is expensive:

1. **The deployment configuration is unsafe** — a live authentication bypass and debug mode on. Cheap. Fix today.
2. **The design conversion ran in the wrong order** — the management layer is finished and the delivery layer is not, so the design investment landed where staff spend the least time. Moderately expensive. Fix over 30 days.
3. **Performance was never measured** — 92 queries on the home page and cosmetic pagination mean the ceiling is much lower than the feature set implies. Moderate. Fix in two weeks.

**The strategic recommendation is to stop adding surface area entirely until Phase 3 completes.** The audit brief itself lists five new workspaces not to start, which is the right instinct — the platform's problem has never been missing modules. It is that 13 of the ones it already has are unfinished, and the team keeps being pulled to the next one.

**Run one real Elite event end-to-end in this system before building anything new.** That single exercise will produce a better priority list than any audit, including this one.

---

*Audit conducted by direct inspection of code, schema, routes, running application and live query profiling. No files were modified. Figures measured 13 August 2026 at commit `c436407`.*

### Corrections issued during this audit
1. **Retracted mid-audit:** an initial route scan appeared to show ~84 unauthenticated routes. The filter matched the string `auth` against middleware entries that are fully-qualified class names (`Illuminate\Auth\Middleware\Authenticate`). Corrected count: **17 unauthenticated routes**, all expected except `/_dusk/*`.
2. **Retracted mid-audit:** an initial scan for `protected $fillable` suggested 49 models lacked mass-assignment protection. The codebase uses the PHP 8 `#[Fillable]` attribute. Corrected: **59/59 models protected.** This also corrects an implication in `docs/30`.
3. **Downgraded mid-audit:** the unauthenticated `storage/{path}` route was initially flagged High on the assumption it exposed uploaded event documents. Tested directly against a real document: **HTTP 403**. Downgraded to informational.

**Method note on scope.** The brief requested page-level scores for 45 modules and every view type. I scored what I could evidence — token status, view size, test count, route protection, query counts and the pages I opened directly. For the 12 modules inspected in code but not walked in the browser (§E), UI scores are provisional and labelled as such. I did not invent page-level detail for screens I did not open; an audit that pads its coverage is worth less than one that states its edges.

**Two factual corrections to the brief itself:** the stack is described as MySQL — the running application uses **SQLite** (`database/database.sqlite`, 75 tables). And `docs/30`'s "21% design-system conversion" is now **17 of 31 core modules (~55%)**; that figure moved substantially in the last four days.
