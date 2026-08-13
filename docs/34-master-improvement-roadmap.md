# Elite Business Hub — Master Improvement Roadmap

**Date:** 13 August 2026
**Baseline:** Enterprise Platform Audit (`docs/33`), score **62/100**
**Target:** 85+ as an internal event operations platform
**Status:** PLAN ONLY — no code, no config, no branches. Nothing in this document has been executed.

**Scope exclusions (per instruction):** SaaS, SSO, payment gateway, subscription billing, tenant marketplace, external onboarding, multi-tenant rollout. These do not appear in any phase.

---

## A. Executive Summary

The audit found a capable engine in an unsafe configuration with an unfinished skin. Planning that work surfaced **one risk the audit missed entirely, because the audit inspected the code and not the repository**:

> **~8,600 lines of work exist only in your working tree.** 67 modified files, 6 deletions, and **11 untracked files** that contain the entire Orbit Journey (Phase E.2), the Event Core, the Priority Area component, the `AgendaConflicts` service, the agenda Gantt partial, the shared event detail panel, and both architecture documents. Plus 2 commits not pushed to the remote branch. **A disk failure today loses Phase C.1 and Phase E.2 in full.**

That is now Phase 0, it takes about fifteen minutes, and it precedes everything — including the security fix, because the security issue is confined to a localhost app while this one is unbounded and permanent.

Three corrections to my own audit's effort estimates, discovered while planning:

| Audit said | Reality | Effect |
|---|---|---|
| "Execute the Postgres cutover — 1–2 days" | **CI already runs a full `tests_pgsql` job against Postgres 16.** The schema is proven. `docs/17` is written. | Cutover is environment work, not migration work. Cheaper and lower risk. |
| "Verify the backup restore drill" | **`scripts/db-backup.sh` and `db-restore.sh` exist**, with a non-destructive `--to` drill mode, and snapshots from 6–7 Aug are on disk. | This is *running one command*, not building tooling. |
| "Green the baseline and gate merges" | **`ci.yml` already runs on every push to every branch**, with sqlite + pgsql jobs, build and test. | There is nothing to build. The gate exists; it is simply red and being ignored. |

**And a finding that reframes the test debt:** CI has failed on **6 of the last 8 runs**, continuously since 9 August. The team is already shipping through a red gate. That is the habit to break, not the 7 assertions.

**Honest read on the 85 target:** 85 is reachable at the end of Phase 6 — roughly **10–12 focused weeks** for one developer with AI assistance. Phases 7–9 in your proposed structure (Layout Builder, Exhibition Builder, Operations Control, Reports) are **new capability, not remediation**. They add perhaps 3–4 points and carry real regression risk.

---

## B. Strategic Recommendation

**Accept the phase structure with one significant change: move the real-event pilot from Phase 10 to Phase 7 — before the builder workspaces.**

Your proposed order builds Layout Builder, Exhibition Builder and Operations Control *before* a single real Elite event has ever run through the platform. That is precisely the pattern both audits criticised: the platform's problem has never been missing modules, and every previous cycle added surface area before validating the surface that existed.

The pilot is not a milestone to be earned by finishing the feature list. **It is the cheapest and highest-quality source of priorities you will ever get**, and everything built before it is built on assumption. Three specific reasons to move it:

1. **It will change the builder specs.** You do not yet know whether Layout Builder needs seating charts or just a room diagram, because no one has been asked to produce one under deadline. Building it first means building it twice.
2. **On-site readiness (Phase 6) is only provable by an event.** Offline check-in cannot be validated in an office.
3. **It de-risks the score.** Reaching 85 through remediation is predictable. Reaching it through new features is not.

**Revised structure:**

| Phase | Content | Type |
|---|---|---|
| **0** | Source-control safety | Risk containment |
| **1** | Critical safety & configuration | Configuration |
| **2** | Pilot data, mail, database, test baseline | Configuration + bug fixing |
| **3** | Performance & reliability | Performance |
| **4** | Event Hub interior conversion | UI polish |
| **5** | Operational core deepening | Bug fixing + UI polish |
| **6** | On-site readiness | New features |
| **7** | **Internal pilot with one real event** ← *moved* | Validation |
| **8** | Post-pilot triage | Remediation |
| **9** | Builder workspaces | New features |
| **10** | Operations Control | New features |
| **11** | Reports, intelligence & executive outputs | New features |

Phases 0–8 take you to ~86. Phases 9–11 are the *next* product cycle and should be re-planned after the pilot, not now.

---

## C. What Must Stop Now

1. **Stop working in an uncommitted tree.** Nothing else on this list matters if the work evaporates.
2. **Stop shipping through a red CI.** Six of eight runs red since 9 August. Every additional red run lowers the cost of the next one.
3. **Stop starting new workspaces.** Operations Control, Layout Builder, Exhibition Builder, Transport Dispatch redesign, Budget redesign — all deferred to Phase 9+ by this plan.
4. **Stop evaluating design systems.** Elite Command OS is good and 55% converted. An eighth direction in fifteen months would be the single most expensive possible decision.
5. **Stop treating "defined" as "done."** Phase E.2 is *architected and built*; Layout Builder is *described*. Those are not the same state and should not sit in the same roadmap column.

---

## D. What Must Happen First

**Phase 0, today, in this order. Total: ~15 minutes.**

1. `git status` — review the 84 changed paths.
2. Commit the Phase C.1 + E.2 work in coherent commits (Events Portfolio restructure; Orbit Journey; audit + architecture docs).
3. Push the branch to `origin`.
4. Confirm the 11 untracked files are now tracked — especially `orbit-journey.blade.php`, `event-core.blade.php`, `priority-area.blade.php`, `AgendaConflicts.php`, `event-detail-panel.blade.php`.

**Verification:** `git status` clean, `git log origin/cursor/soft-command-phase1..HEAD` empty.

Only then proceed to Phase 1.

---

## E. Full Roadmap By Phase

| # | Phase | Type | Effort | Risk | Score after |
|---:|---|---|---|---|---:|
| 0 | Source-control safety | Risk containment | 15 min | None | 62 |
| 1 | Critical safety & configuration | Configuration | 0.5 day | Low | **68** |
| 2 | Pilot data, mail, DB, test baseline | Config + bug fix | 3–4 days | Medium | **73** |
| 3 | Performance & reliability | Performance | 1.5–2 weeks | Medium | **77** |
| 4 | Event Hub interior conversion | UI polish | 2.5–3 weeks | Low | **81** |
| 5 | Operational core deepening | Bug fix + polish | 2 weeks | Medium | **83** |
| 6 | On-site readiness | New features | 2 weeks | High | **85** |
| 7 | Internal pilot, one real event | Validation | 1 event cycle | High | **85**† |
| 8 | Post-pilot triage | Remediation | 1–2 weeks | Medium | **86** |
| 9 | Builder workspaces | New features | 4–6 weeks | High | 88 |
| 10 | Operations Control | New features | 3–4 weeks | High | 89 |
| 11 | Reports & intelligence | New features | 2–3 weeks | Medium | 90 |

† The pilot does not raise the score. It *validates* it, and will very likely reveal issues that temporarily lower it before Phase 8 recovers it. A pilot that raises your score is a pilot that was not honest.

**Phases 0–8: ~10–12 weeks to 86.**

---

## F. Phase Details

### PHASE 0 — Source-Control Safety
**Type: risk containment · 15 min · Risk: none · Score: 62 → 62**

| Problem | ~8,600 lines uncommitted; 11 untracked files holding all of Phase E.2; 2 unpushed commits |
| Risk | **Total, permanent loss** of Phase C.1 + E.2 on disk failure |
| Fix | Commit in logical units; push |
| Verification | `git status` clean; nothing in `origin/<branch>..HEAD` |
| Expected result | Work is recoverable |

*This adds zero platform score and removes the highest-consequence risk on the list. Both statements are true simultaneously, which is why it is Phase 0 and not Phase 1.*

---

### PHASE 1 — Critical Safety & Configuration
**Type: configuration · 0.5 day · Risk: low · Score: 62 → 68**

| # | Item | Problem | Risk | Fix | Verification |
|---|---|---|---|---|---|
| 1.1 | **Dusk login bypass** | `GET /_dusk/login/1` → HTTP 200; `Auth::login()` with no credentials; accepts email | **Critical** — total auth bypass | `APP_ENV=production` on any shared host; `composer install --no-dev` | `curl /_dusk/login/1` → **404** |
| 1.2 | `APP_DEBUG` | `true` | Critical — stack traces, env leakage | `APP_DEBUG=false` | Force a 500; confirm generic error page |
| 1.3 | Dev dependencies | Dusk is `require-dev`, present in tree | High — ships test tooling | `--no-dev` on deploy | `vendor/laravel/dusk` absent |
| 1.4 | `/concept/*` routes | 2 demo routes live | Medium | Remove or gate behind `admin` | Routes 404 for non-admin |
| 1.5 | `/design/*` routes | 2 design-gallery routes live | Medium | Remove or gate | Routes 404 for non-admin |
| 1.6 | `/operations-room` | Dead legacy route | Medium | Remove | Route absent from `route:list` |
| 1.7 | **Mail safety gate** | Demo users hold real `@elitebhub.com` addresses | **High** — real colleagues receive mail about fake data | Keep `MAIL_MAILER=log` **until Phase 2.4 completes** | Confirmed in 2.4 |

**Sequencing note:** 1.7 is a *hold*, not an action. Do not enable SMTP in this phase. The single worst outcome available in the next fortnight is real approval emails about seeded demo events reaching your team.

**Exit criteria:** `/_dusk/login/1` returns 404; debug off; demo routes gone. Desk pilot becomes *technically* safe (data safety still pending Phase 2).

---

### PHASE 2 — Pilot Data, Mail, Database & Test Baseline
**Type: configuration + bug fixing · 3–4 days · Risk: medium · Score: 68 → 73**

**Order matters here. Do not reorder 2.1 → 2.4.**

**2.1 Snapshot the demo database** *(30 min)*
Run `./scripts/db-backup.sh`. Label the snapshot explicitly as the demo/reference dataset. This is the fallback for every later step.

**2.2 Verify restore actually works** *(30 min)*
Run `./scripts/db-restore.sh <snapshot> --to /tmp/drill.sqlite` — the non-destructive drill mode. Open the restored file, confirm row counts.
*This is the first time restore will have been proven. Backups you have never restored are not backups.*

**2.3 Build a clean pilot database** *(1 day)*
- Fresh migrate.
- Seed **only**: real staff users (real addresses, real roles) and reference/catalogue data (`PriceListSeeder`, transport catalogue, sponsorship packages, registration templates).
- **Do not** run `DemoDataSeeder`.
- **Do not** purge in place — build alongside, then switch.

*Decision required (see §J): which catalogue data is authoritative vs. demo-invented?*

**2.4 Mail** *(0.5 day)*
- Real `MAIL_FROM_ADDRESS` on the elitebhub.com domain.
- Real SMTP.
- **Recipient allow-list for the first two weeks** — pilot participants only. This is the safety net if any seeded row survived 2.3.
- Test all four flows: `ApprovalRequested`, `ApprovalDecided`, `RegistrationConfirmed`, `TeamInvite`.

**2.5 Postgres cutover** *(1 day — cheaper than audited)*
CI already proves the schema on Postgres 16 via `tests_pgsql`. `docs/17` is written and explicitly keeps local Herd on SQLite. Work is: provision, rehearse the data copy, cut staging, then the pilot host.
*Do not flip `phpunit.xml` until `tests_pgsql` is green on main for a sustained stretch — `docs/17` says this and it is correct.*

**2.6 Green the 7 stale tests** *(0.5 day)*
All 7 are copy/token assertions, verified individually in `docs/33` §M. Update assertions to current copy. **Do not** change product behaviour to satisfy a stale test.

**2.7 Make the CI gate real** *(0.5 day)*
CI exists and runs correctly. Required changes: branch protection so red blocks merge, and — culturally — the rule that a red run is fixed before the next feature commit.

**Risks:** the clean-DB switch is the highest-risk step in this phase. Mitigation is 2.1 + 2.2 preceding it.
**Must not:** enable SMTP before 2.3 completes; purge the demo DB in place; flip phpunit to pgsql early.
**Exit:** desk pilot safe on real data with working mail and a green gate.

---

### PHASE 3 — Performance & Reliability
**Type: performance · 1.5–2 weeks · Risk: medium · Score: 73 → 77**

| # | Target | Measured now | Likely cause | Strategy | Effort | Expected |
|---|---|---:|---|---|---|---|
| 3.1 | Command Center | **92 queries** | Per-event iteration in `CommandCenterService` (423 LOC) | Profile first; batch aggregates; cache portfolio rollups with event-touch invalidation | 3–4 d | → ~15–20 |
| 3.2 | Event Portfolio | 37 q, all rows | `->get()` then in-memory `forPage` | Real `paginate()`; push filter/sort to SQL; compute missions per page only | 2–3 d | → ~12, flat with growth |
| 3.3 | Hub Budget | **88 queries** | `BudgetTab` 896 LOC, per-line lookups | Eager-load; batch `BudgetSync::pending()` | 2 d | → ~25 |
| 3.4 | Hub Agenda | 69 q | Per-session speaker/room lookups | Eager-load in `AgendaTab` | 1–2 d | → ~25 |
| 3.5 | Hub Transport | 61 q | Per-movement manifest lookups | Eager-load | 1 d | → ~20 |
| 3.6 | Reports | 50 q | Unaggregated rollups | Push to SQL aggregates | 1–2 d | → ~15 |
| 3.7 | Index audit | 47 idx / 75 tables | Never audited | Index `events.stage`, `events.starts_at`, `tasks.due_on`, `approvals.status`, `event_attendees.event_id` | 1 d | Query-plan wins |
| 3.8 | Queue worker | 0 jobs, worker absent | Never needed | Stand up worker; queue the 4 notifications | 1 d | Removes mail latency from request |
| 3.9 | PDFs off-request | 25 sync Browsershot controllers | Headless Chrome in-request | Queue generation + download-when-ready **only if** pilot shows contention | 3–4 d | Deferred — see note |

**Note on 3.9:** at 19 users this is likely *not* yet a real problem. Measure during the pilot before spending four days. Premature optimisation is how the last three cycles lost time.

**Method requirement:** profile before fixing each item. The 92-query figure is measured; its *cause* is inferred and must be confirmed.

---

### PHASE 4 — Event Hub Interior Completion
**Type: UI polish · 2.5–3 weeks · Risk: low · Score: 77 → 81**

The Hub shell and Orbit Journey are done. 13 interior modules remain on legacy tokens — and they are where events are actually delivered.

**Conversion order by (daily usage × brokenness), not alphabetically:**

| Order | Module | Tokens | LOC | Tests | Difficulty | Priority | Notes |
|---:|---|---|---:|---:|---|---|---|
| 1 | **Hub Overview** | **MIXED** | 572 | 1 | Medium | **Critical** | Only mixed file; first screen on every event; new Orbit sits directly above old chrome |
| 2 | **Budget** | LEGACY | 874 | 21 | High | Critical | Highest-value module; excellent test cover de-risks it |
| 3 | **Transport** | LEGACY | 1038 | 16 | High | Critical | Largest legacy view; differentiator |
| 4 | **Attendees** | LEGACY | 473 | 12 | Medium | High | Feeds registration + check-in |
| 5 | **Approvals** | LEGACY | 275 | 8 | Low | High | Email-wired; visible in Priority Area |
| 6 | **Accommodation** | LEGACY | 460 | 13 | Medium | High | |
| 7 | **Registration** | LEGACY | 180 | 11 | Low | High | Public-facing; also Phase 6 |
| 8 | **Sponsors** | LEGACY | 225 | 5 | Low | Medium | |
| 9 | **Risks** | LEGACY | 120 | **0** | Low | Medium | **Add tests during conversion** |
| 10 | **Catering** | LEGACY | 201 | 9 | Low | Medium | Thin logic (`docs/33` scored 61) |
| 11 | **Exhibition** | LEGACY | 189 | **0** | Low | Medium | **Add tests**; precedes Phase 9 builder |
| 12 | **Check-in** | LEGACY | 103 | 5 | Low | Medium | Full rework in Phase 6 — convert lightly only |
| 13 | **Documents** | LEGACY | — | — | Low | Low | Drawer partials |

**Explicitly excluded: Plan Studio and Tasks.** `docs/08` designates Plan Studio a deliberate design exception. Do not "fix" it. Tasks shares its visual language — confirm intent before touching (see §J).

**Method:** convert one module per working session; run the suite after each; screenshot before/after. Do not batch conversions — the H-phase pattern of one-module-per-pass is what kept regressions at zero.

**Do not** redesign during conversion. Retokening ≠ restructuring. Structural change belongs in Phase 5.

---

### PHASE 5 — Operational Core Deepening
**Type: bug fixing + UI polish · 2 weeks · Risk: medium · Score: 81 → 83**

Each module targets the established Elite Command OS shell: **header → KPI strip → workspace → inspector/action panel → explicit empty/loading/error states.**

| Module | Target experience | Required fixes | Reuse | Do not touch |
|---|---|---|---|---|
| **Budget Command** | Category workspace + variance inspector | Money via `Money::` helper; commitments surfaced in-budget not just as an alert | `eo-table-wrap`, detail-panel | Budget calculation logic — it is correct and well tested |
| **Transport Dispatch** | Movement board + driver inspector | Retoken; readiness states; mobile driver view (Phase 6) | Existing dispatch board | The 7 PDF generators |
| **Registration** | Template-driven form + submission inspector | Confirmation email (Phase 2); validation surfacing | `RegistrationTemplate` | Token model — security is sound |
| **Check-in** | Scan-first, one-handed | Full rework in Phase 6 | Token separation | — |
| **Documents** | Folder workspace + preview | Retoken drawer; upload states | `ModuleDocuments` | Private-disk config — verified safe |
| **Risks** | Register + mitigation inspector | **Tests (0 today)**; escalation workflow | `status-pill` tones | Severity model (probability × impact) |
| **Approvals** | Queue + decision inspector | Retoken; SLA/ageing visible | `ApprovalStep::decidableBy()` | Notification recipient logic |

**Cross-cutting in this phase:**
- **Money standardisation** — 175 local `number_format` → `Money::`. Highest-value logic fix in the plan; rounding drift is silent and permanent.
- **Date standardisation** — 172 raw `->format()` → `<x-date>`.
- **State system** — one loading, one error, one empty treatment (`wire:loading` is currently unsystematic; there is no shared error surface).

**Tests needed:** Risks (0→6), Exhibition (0→4), RBAC matrix (0→1 dedicated file covering 6 gates × 5 roles).

---

### PHASE 6 — On-Site Readiness
**Type: new features · 2 weeks · Risk: high · Score: 83 → 85**

The only phase that adds genuinely new capability before the pilot, because **the pilot cannot run without it.**

**Minimum viable (required for pilot):**
1. Mobile-first check-in — one-handed, large targets, scan-first.
2. QR scanning via device camera.
3. **Offline check-in** — local queue, sync on reconnect, visible pending count.
4. Mobile arrivals view.
5. Printed fallback — attendee manifest PDF as the paper backup when everything fails.

**Ideal (defer unless pilot demands):**
6. Driver mobile transport view.
7. Live badge printing.
8. Real-time arrivals board for the welcome desk.
9. Per-device on-site staff role.

**Risk — this is the highest-risk phase before the pilot.** Offline sync is genuinely hard: conflict resolution, duplicate check-ins, clock skew. Budget for it to take longer than estimated.

**Design constraint:** an offline check-in that silently loses a scan is far worse than no offline mode. If sync cannot be made provably correct in the window, ship **airplane-mode-detection + explicit paper fallback** instead. A tool that says "you are offline, use the printed list" is honest and safe.

**Rule: no venue Wi-Fi dependency in the critical path.** Every on-site flow needs a defined non-network fallback before the pilot.

---

### PHASE 7 — Internal Pilot With One Real Event *(moved from 10)*
**Type: validation · one event cycle · Risk: high · Score: 85 → 85**

**Scope:** one real Elite event, end to end, six people — PM, operations, finance, commercial, admin, plus you.

**Pilot checklist (in workflow order):**
Client → deal → proposal → contract → event creation → Event Hub / Orbit Journey → budget → agenda → transport → registration → check-in (on-site) → invoice → payment tracking → risks → approvals → post-event report.

**Success criteria:**
- The event is delivered using the platform as primary system — not shadowed by spreadsheets.
- Zero data loss.
- Zero security incidents.
- Check-in works on the day, including at least one deliberate network-failure drill.
- Finance can reconcile the event without exporting to Excel first.
- Every participant can complete their own workflow unaided after one training session.

**Failure criteria (any one triggers stop-and-fix):**
- Anyone reverts to a spreadsheet for a core workflow.
- Data loss or corruption.
- Check-in fails on the day.
- A finance figure disagrees between two screens.
- More than two P1 issues in a single day.

**Process:** daily 15-minute review during the event; issue log with severity and module; go/no-go checkpoints at **T-30 days** (readiness), **T-7 days** (data loaded), **T-1 day** (on-site drill), and **T+7 days** (post-mortem).

**Expect the score to dip before it rises.** A pilot that surfaces nothing was not a real pilot.

---

### PHASE 8 — Post-Pilot Triage
**Type: remediation · 1–2 weeks · Risk: medium · Score: 85 → 86**

Triage the issue log by (frequency × severity). Fix P1/P2. Write operational documentation from what people actually asked during the event — not from the feature list. Identify training gaps. Decide the full-rollout date.

**This phase produces the specification for Phases 9–11.** Do not write those specs before this point.

---

### PHASE 9 — Builder Workspaces *(deferred)*
**Type: new features · 4–6 weeks · Risk: high · Score: 86 → 88**

Use **Agenda Builder as the blueprint** — it is the proven pattern (six views, inspector, conflict detection, 10 test files).

**Shared shell:** left rail (objects/palette) · centre canvas · right inspector · bottom command bar · conflict/readiness check · export/publish.

**Layout Builder** — canvas, object palette (seating, stage, screens, booths, registration desk, furniture), layers, inspector, capacity + egress checks, PDF export. *Existing: `RoomLayoutBuilder` 667 LOC.*

**Exhibition Builder** — floor map, booth types, zones, booth status, exhibitor assignment, sponsor separation, revenue view, inspector. *Existing: `ExhibitionFloorPlan` 490 LOC, **0 tests**.*

**Registration Builder** — templates already exist (`RegistrationTemplate`/`RegistrationField`, 11 tests). Likely needs a builder UI, not a new engine. **Confirm during pilot whether this is actually needed.**

**Agenda Builder follow-up:** none planned. It scored 77 and is the strongest builder. Revisit only if the pilot surfaces a specific gap.

**Precondition:** specs rewritten after Phase 8. What is written above is a *shape*, not a specification.

---

### PHASE 10 — Operations Control *(deferred)*
**Type: new features · 3–4 weeks · Risk: high · Score: 88 → 89*

**Purpose:** a **cross-event command layer** — explicitly *not* a replacement for per-event operations tabs.

**Answers:** What is happening across all active events? Which transport movements are at risk? Which venues are not ready? Which suppliers have issues? What needs attention in accommodation/catering/exhibition? Who owns each problem? What is the next action?

**Users:** operations lead, PM covering multiple events, you.

**Data sources (all existing):** `EventMission`, `EventCommandHeader::attention()`, `ResourceConflicts`, transport readiness, supplier pivot status, `PortfolioAdvisor`.

**Views:** live board (today/this week) · risk-ranked movement list · venue readiness matrix · supplier issue queue · ownership map.

**Dependencies:** Phase 3 (query cost — this is a cross-event aggregate over the same services already issuing 92 queries for one dashboard), Phase 5 (operational modules must be consistent first).

**Risk:** highest of any deferred phase. A cross-event aggregation layer built on unoptimised per-event services will be slow, and slow operations tools do not get used on event day.

---

### PHASE 11 — Reports, Intelligence & Executive Outputs *(deferred)*
**Type: new features · 2–3 weeks · Risk: medium · Score: 89 → 90*

Reports export (PDF/Excel) · finance/event/operations/team reports · Command Briefing · executive summary · after-event report · board output.

**Non-negotiable honesty requirement:** the AI Assistant is **rule-based**. `EventHealthService` and `PortfolioAdvisor` are deterministic rules engines with **zero LLM integration**. The current UI already says so, to its credit. Do not label it "AI" in any executive-facing output unless a real model is wired in. If an LLM is added later, ground it on the existing rules engine rather than replacing it — the rules are auditable and the model is not.

---

## G. Dependencies

```
Phase 0 ──> Phase 1 ──> Phase 2 ──> Phase 3 ──> Phase 4 ──> Phase 5 ──> Phase 6 ──> PILOT (7)
                          │                        │                                   │
                          │                        └── Money/Date standardisation ──────┤
                          │                            (Phase 5 cross-cutting)          │
                          └── Postgres before pilot data volume grows                   │
                                                                                        ▼
                                                                            Phase 8 triage
                                                                                 │
                                                    ┌────────────────────────────┼──────────────┐
                                                    ▼                            ▼              ▼
                                              Phase 9 Builders          Phase 10 Ops Ctrl   Phase 11 Reports
```

**Hard dependencies:**
- 2.4 (SMTP) **must** follow 2.3 (clean DB) — else real mail about fake data.
- 2.1/2.2 (backup + verified restore) **must** precede 2.3 (DB switch).
- Phase 6 **must** precede the pilot — no on-site capability, no event.
- Phase 3 **must** precede Phase 10 — Ops Control aggregates across events.
- Phase 8 **must** precede Phases 9–11 — specs depend on pilot findings.

**Soft:** Phase 4 (conversion) and Phase 3 (performance) can interleave; different files, low collision.

---

## H. Risk Matrix

| Risk | Likelihood | Impact | Phase | Mitigation |
|---|---|---|---|---|
| **Work lost from uncommitted tree** | Medium | **Catastrophic** | 0 | Commit + push today |
| Clean-DB switch loses real data | Low | Critical | 2 | Verified restore drill first (2.2) |
| Real mail sent about demo data | **High if 2.4 precedes 2.3** | High | 2 | Strict ordering + recipient allow-list |
| Offline sync loses a check-in | Medium | **Critical on event day** | 6 | Provable sync or explicit paper fallback |
| Pilot event fails publicly | Medium | High | 7 | Non-flagship event; paper fallback; go/no-go gates |
| Money rounding drift | **Already occurring** | High | 5 | `Money::` enforcement |
| Postgres cutover regression | Low | High | 2 | CI already proves schema on pgsql |
| Conversion regressions | Low | Medium | 4 | One module per pass; suite after each |
| Scope creep into builders | **High** | High | all | §K discipline; re-plan only after Phase 8 |
| Red CI normalised further | **Already occurring** | Medium | 2 | Branch protection + fix-before-feature rule |
| Design direction changes again | Medium | **Critical** | all | Elite Command OS is decided. No eighth system. |

---

## I. Score Improvement Forecast

| Milestone | Score | Primary driver |
|---|---:|---|
| **Current** | **62** | — |
| After Phase 0 | 62 | Risk removed; no score change (honest) |
| After Phase 1 | **68** | Deployed-security 25 → 75 |
| After Phase 2 | **73** | Ops readiness, data readiness, green gate |
| After Phase 3 | **77** | Performance 45 → 72 |
| After Phase 4 | **81** | UI 58 → 78 (conversion complete) |
| After Phase 5 | **83** | Logic + state consistency |
| After Phase 6 | **85** ✅ | Mobile 40 → 70 · **target reached** |
| After Pilot (7) | **85** | Validation, not improvement — may dip first |
| After Phase 8 | **86** | Real-world remediation |
| After Phase 9 | 88 | New capability |
| After Phase 10 | 89 | New capability |
| After Phase 11 | 90 | New capability |

**The 85 target is met at the end of Phase 6, before any new workspace is built.** That is the single most important line in this document: your goal does not require Layout Builder, Exhibition Builder, or Operations Control. It requires finishing what exists.

---

## J. Required Decisions From Emran

| # | Decision | Why it blocks | Recommendation |
|---|---|---|---|
| 1 | **Which event is the pilot?** | Sets the entire timeline; Phase 6 must land before it | A mid-size, non-flagship event with a forgiving client. Not ICFT. |
| 2 | **Who are the 6 pilot users?** | Training and role config | PM, ops, finance, commercial, admin, you |
| 3 | **Which catalogue data is authoritative?** | Blocks 2.3 clean DB | Audit price list, transport catalogue, sponsorship packages before seeding |
| 4 | **Where does the pilot run?** | Determines urgency of 1.1/1.2 | A real host, not a laptop — but then `APP_ENV=production` is mandatory |
| 5 | **Is Tasks in or out of the Plan Studio exception?** | Blocks Phase 4 scope | Confirm — `docs/08` names only Plan Studio explicitly |
| 6 | **Offline check-in: full sync or paper fallback?** | Two-week swing in Phase 6 | Start with paper fallback; add sync only if the pilot proves it necessary |
| 7 | **Demo data: archive or delete?** | Blocks 2.3 | Archive the snapshot; never run `DemoDataSeeder` against pilot |
| 8 | **Who fixes red CI, and by when?** | Cultural, not technical | Rule: red is fixed before the next feature commit |

---

## K. What Not To Touch Yet

- **Plan Studio** — documented deliberate exception (`docs/08`).
- **Orbit Journey** — approved and just landed. Let it sit through the pilot.
- **The tenancy spine** — dormant by the internal-first decision. Do not extend; do not remove. (`Tenancy::id()` fails open — harmless while 0 jobs and 0 console commands exist; revisit only if that changes.)
- **Layout Builder / Exhibition Builder / Operations Control / Transport Dispatch redesign / Budget redesign** — Phase 9+ at the earliest, specs rewritten after the pilot.
- **PDF generation architecture** — 25 controllers work. Do not abstract before the pilot proves contention.
- **Agenda Builder** — strongest module. No planned work.
- **The design system itself** — finish it; do not re-evaluate it.
- **Payment gateway, SSO, API, multi-tenancy** — out of scope by decision.

---

## L. Recommended First 7 Days

| Day | Work | Type | Output |
|---|---|---|---|
| **0 (today)** | Phase 0 — commit + push | Risk | Work recoverable |
| **1** | Phase 1 complete | Config | `/_dusk/login/1` → 404; debug off; demo routes gone |
| **2** | 2.1 snapshot · 2.2 **verified restore drill** · 2.6 green the 7 tests | Config + bug fix | Proven backup; green suite |
| **3** | 2.7 CI branch protection · begin 2.3 clean pilot DB | Config | Gate is real |
| **4** | 2.3 complete — clean DB with real users + audited catalogues | Config | Pilot DB exists |
| **5** | 2.4 mail — real FROM, SMTP, allow-list, test 4 flows | Config | Platform can tell people things |
| **6** | 2.5 Postgres cutover | Config | Concurrency ceiling removed |
| **7** | Review · answer §J decisions 1–4 · profile Command Center | Planning | Phase 3 scoped from data |

**End of week 1: score ~73. Desk-team pilot safe.** All configuration and bug fixing — **zero new features, zero UI work.**

---

## M. Recommended First 30 Days

| Week | Focus | Type | Score |
|---|---|---|---|
| **1** | Phases 0–2 | Config + bug fix | 62 → 73 |
| **2** | Phase 3 — Command Center, Portfolio pagination, Budget/Agenda queries, index audit, queue worker | Performance | 73 → 77 |
| **3** | Phase 4 part 1 — **Hub Overview**, Budget, Transport | UI polish | 77 → 79 |
| **4** | Phase 4 part 2 — Attendees, Approvals, Accommodation, Registration | UI polish | 79 → 81 |

**Day 30: ~81.** Remaining to 85: Phase 4 tail (5 modules), Phase 5 (operational deepening + Money/Date standardisation), Phase 6 (on-site) — roughly six further weeks.

**Deliberately absent from the first 30 days:** every new feature. The first month is entirely configuration, bug fixing, performance and finishing existing UI. That is what takes 62 to 81.

---

## N. Final Recommendation

**Commit your work today.** Everything else in this plan is recoverable; that is not.

Then run Phases 0–2 in the first week. They are almost entirely configuration, they take the platform from 62 to ~73, and they convert the product from "unsafe to share" to "safe for a desk pilot." **The highest-value week in this roadmap contains no feature work at all.**

The strategic change I recommend is moving the pilot ahead of the builder workspaces. Your instinct in the audit brief — "do not start Operations Control, Layout Builder, Exhibition Builder" — was correct, and this plan extends it: do not start them *until a real event has run through the system*. The pilot will rewrite those specifications, and building them first means building them twice.

The 85 target is reached at the end of Phase 6, through remediation alone. Everything beyond that is a genuinely new product cycle, and it should be planned with pilot evidence rather than with assumptions — including mine.

Two things I would flag as the most likely ways this plan fails:

1. **Scope creep into the builders.** The pull toward new workspaces has been the dominant pattern for fifteen months. Phases 9–11 exist in this document mainly so they have somewhere to live that is not "now."
2. **Offline check-in being harder than budgeted.** It is the one item where I would not trust my own estimate. Decide early (§J.6) whether paper fallback is acceptable, because that decision is worth two weeks.

---

*Plan only. No code was written, no configuration changed, no branch created. Evidence base: `docs/33` enterprise audit plus direct inspection of CI configuration, backup tooling, Postgres cutover plan and repository state on 13 August 2026.*
