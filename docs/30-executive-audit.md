# Elite Business Hub — Independent Executive Audit

**Auditor stance:** external CTO / enterprise architect / event-industry operator
**Date:** 9 August 2026
**Subject:** the application served at `localhost:8912` (`~/Herd/elitehub`, branch `cursor/soft-command-phase1`, tag `v1-soft-command-baseline`)
**Method:** direct code, schema, test-suite and running-UI inspection. Every figure below is measured, not estimated from documentation.

---

## 0. Headline verdict

**Elite Business Hub is a genuinely impressive single-tenant internal operations tool that is being described as a commercial multi-tenant SaaS. It is not one yet, and the gap is not small.**

The event-production domain logic is the best thing here and is genuinely competitive — in places superior to Cvent. Everything that turns domain logic into a *sellable product* is missing or stubbed.

**If Elite Business Hub launched commercially today: ~35% complete.**

That number is low not because the product is thin — 35,000 lines of application code and 1,067 tests say otherwise — but because commercialisation is roughly half the work of a SaaS and almost none of it has started.

### The three findings that matter most

1. **The stated project status is not what is in the code.** The brief for this audit asserts "Soft Command design system implemented · new shell implemented." Measured reality: **12 of 56 page-level views (21%)** use the `x-eo.*` component library. The entire Event Hub interior — the operational heart of the product — is still on the previous chrome. `docs/26` still carries the status line *"AWAITING APPROVAL — no implementation started"* while `docs/27–29` report phases 1–3 shipped. Leadership is reasoning about a system state that does not exist.

2. **Design churn is the single largest threat to this business — larger than any technical gap.** This product family has passed through roughly seven design systems in about thirteen months (SugarCRM monochrome → brand navy/gold → Lufga → Command Center → ORBIT → reverted to Command Center → Soft Command). One of those reversions was measured at 56 commits / 426 files. Every cycle consumes weeks and ships no customer value. **The platform does not have a design problem. It has a design-decision-stability problem.**

3. **The tagged baseline is red.** `v1-soft-command-baseline` has **10 failing tests**, nine of them page-render assertions broken by the redesign, one a design-token guard. A milestone tag on a red build normalises exactly the thing that stops a team from ever trusting its own suite.

---

## 1. Platform audit

### 1.1 Current maturity

| Dimension | Measured |
|---|---|
| Application code | 35,251 LOC |
| Views | 233 Blade files, 30,298 LOC |
| Tests | 124 files, 23,147 LOC, **1,067 tests / 948 pass / 10 fail / 109 not executed** |
| Models | 62 · **Migrations** 120 · **DB tables** 75 |
| Livewire components | 58 · **Controllers** 43 (25 of them PDF generators) |
| Named routes | 86 |
| Services layer | 28 classes (`EventHealthService`, `PortfolioFinance`, `DealPipeline`, `ResourceConflicts`…) |

**Maturity: advanced prototype / strong internal beta.** This is well past MVP in domain terms and well short of a product in commercial terms. The correct mental model is *"a bespoke ops system for one event agency, being retrofitted toward SaaS"* — which is exactly what it is.

### 1.2 Strengths (real, and worth defending)

- **Domain modelling is excellent.** 62 models covering rooming blocks, transport passengers, exhibition halls, contract signatories, budget versions, approval steps. Someone who actually runs events designed this schema.
- **Code quality is high and unusually literate.** Doc comments explain *why*, not *what* — e.g. `BelongsToTenant` explains that a retrofit "does not fail loudly when somebody forgets; it fails in six months when two customers can see each other's contracts." That is senior thinking.
- **Authorization is pervasive, not decorative.** 353 `authorize()` calls and 351 `Gate::` usages across ~49 components, with role seniority ranking. Most products at this stage have none.
- **The tenancy retrofit was done thoughtfully** — global middleware rather than per-route opt-in, `tenant_id` indexed on all 62 tables, and a `TenancyGuardTest` that fails the build when a model is missed.
- **Public surfaces are properly secured** — token lookup, `hash_equals`, per-IP throttling, deliberately minimal capability ("it cannot read the list or say who else is coming").
- **Operational depth that competitors lack**: transport dispatch + live board + 7 transport PDFs, rooming lists, run-of-show, exhibition floor plans, agenda conflict detection, contract e-signature. This is not brochure-ware.
- Sentry, CI workflows, uptime checks, a Postgres cutover plan and a documented backup/restore drill all exist.

### 1.3 Weaknesses

- **21% design-system conversion** with two visual languages live simultaneously.
- **Visible layout defects on the flagship page.** The converted Event Hub Overview renders run-on text where navigation should be ("01 Overview 02 Brief 03 Planning 04 Programme…"), and Mission Radar cards clip and overlap their container. Ironically the *unconverted* Budget tab is the more polished screen.
- **Zero pagination anywhere.** `paginate()` appears **0 times** in 35k lines; 149 `->get()` calls load full result sets.
- **No outbound email.** `MAIL_MAILER=log`, zero `Mail::` usage in the app. Registration confirmations, invoices, approval requests and invites do not reach anyone.
- **No payment gateway.** Payments are manually recorded numbers.
- **No API.** No `routes/api.php`, no Sanctum, no integration surface at all.
- **No async.** 0 jobs, 0 console commands, 1 scheduled entry. PDF generation via Browsershot runs synchronously in the request.
- **No 2FA/SSO.**
- **109 tests not executed** (~10% of the suite) against only 2 explicit skip statements — the reason is unexplained and nobody appears to have asked.

### 1.4 Technical risks

| Risk | Severity | Evidence |
|---|---|---|
| Tenant isolation **fails open** | **Critical** | `Tenancy::id()` returns null when unbound and the global scope simply stands down — an unbound query returns *all tenants' rows*. The code documents this as transitional pending "Slice 4," which is not done. Today only HTTP entry points exist so it holds; the day a queue job, command or webhook is added, it silently breaks. |
| Synchronous Browsershot PDFs | High | A headless-Chrome render inside a web request. Concurrent report generation will exhaust workers. |
| SQLite in the primary environment | High | `DB_CONNECTION=sqlite` in `.env`. Single-writer; will not survive concurrent users. Postgres plan exists but is not executed. |
| No pagination | High | See §1.3. |
| Red baseline / no green-gate discipline | High | 10 failures on a tagged milestone. |
| Static tenancy state in long-lived workers | Medium | `Tenancy` is a static holder; correct today only because `ResolveTenant` uses `finally`. Octane or queue workers will inherit bindings. |
| Parallel-agent branch sprawl | Medium | 30+ local branches across `claude/*` and `cursor/*`, plus a second worktree. Integration risk is structural, not incidental. |

### 1.5 Product risks

- **The product has no attendee-facing surface.** No mobile app, no event app, no networking, no session bookmarking. That is precisely where EventMobi, Whova and Bizzabo compete. Elite is an *organiser-side* system only.
- **Built for one customer's workflow.** JOD currency, Amman venues, Elite's own transport patterns. Nothing wrong with that — but no second tenant has ever existed (`tenants` = 1, `workspaces` = 1), so every multi-customer assumption is untested.
- **"AI Assistant" is rule-based with zero LLM integration.** To the code's credit it says so honestly on the page. Commercially, naming a rules engine "AI Assistant" in 2026 is a credibility risk in a demo against competitors shipping real copilots.
- **No onboarding, no self-serve signup, no plan enforcement.** `Tenant` has `status` and `trial_ends_at` columns and nothing reads them for entitlement.

### 1.6 UX risks

- Two design languages side by side; users cross the seam every time they open an event.
- The flagship redesigned page is the most visually broken one.
- No mobile strategy. On-site event staff work from phones — check-in, arrivals and dispatch are exactly the screens that must be mobile-first, and none are.
- No offline capability for check-in. Venue Wi-Fi fails; this is a real operational failure mode, not a theoretical one.
- Information density is high and largely unpaginated — usable at 18 events, hostile at 500.

### 1.7 Architectural risks

- **No API means no integrations, ever, until one exists.** In this market Salesforce/HubSpot, Mailchimp, Zoom/Teams, Xero/QuickBooks and badge-printer integrations are table stakes.
- Business logic is split between Livewire components and Services inconsistently — `ContractTab` carries 978 LOC and 50 public methods in a UI component. That is a controller-shaped object wearing a component's clothes, and it is where the next regression will come from.
- No domain events / no event sourcing on money. `EventStageChanged` is the only domain event.
- PDF generation (25 controllers) is a de facto reporting engine with no abstraction over it.

### 1.8 Scalability risks

Honest ceiling estimate on current architecture: **~50 concurrent users, ~100 events, ~5k attendees per event** before serious degradation. Blockers in order: SQLite → no pagination → sync PDFs → no cache (2 `Cache::` calls total) → no queue.

---

## 2. Module audit

Completion = feature depth against what the module must do. Production-ready = would it survive a paying external customer.

| # | Module | Status | Compl. | Prod. | Missing functionality / business logic | UX | Commercial value |
|---|---|---|---|---|---|---|---|
| 1 | Command Center | Converted, live | 75% | 60% | Personalisation, saved views, drill-through consistency | Good | High |
| 2 | CRM | Converted | 70% | 55% | Email sync, activity automation, lead capture, dedupe | Good | High |
| 3 | Deals | Converted | 70% | 60% | Forecast accuracy history, multi-currency pipeline, loss analytics | Good | High |
| 4 | Clients | Converted | 65% | 60% | Contact hierarchy, credit terms, client portal | Fair | Medium |
| 5 | Proposals | Converted | 75% | 55% | Online client accept, e-sign on proposal, versioning/compare | Good | **Very high** |
| 6 | Contracts | Legacy chrome | **85%** | 65% | Counter-signature flow, renewal, clause library governance | Good | **Very high** |
| 7 | Event Portfolio | Converted | 80% | 65% | Saved filters, bulk ops, pagination | Good | High |
| 8 | Event Studio | Converted | 75% | 65% | Template marketplace, clone-with-data, validation depth | Good | High |
| 9 | Event Hub | Converted, **broken** | 70% | **45%** | Journey nav renders as run-on text; radar cards clip | **Poor** | Critical |
| 10 | Planning | Legacy | 75% | 60% | Dependencies, critical path, baseline vs actual | Good | High |
| 11 | Tasks | Legacy | 75% | 60% | Recurring tasks, templates, notifications (no email) | Good | High |
| 12 | Agenda | Legacy | **85%** | 65% | Speaker self-service, attendee-facing publish, live changes | Strong | High |
| 13 | Speakers | Legacy | 50% | 45% | Speaker portal, bio/headshot collection, travel linkage, contracts | Thin | Medium |
| 14 | Venue | Legacy | 60% | 55% | Availability calendar, contracted rates, comparison | Fair | Medium |
| 15 | Layout Builder | Legacy | 75% | 55% | Capacity rules, accessibility/egress compliance, export fidelity | Fair | Medium |
| 16 | Transport | Legacy | **85%** | 60% | Driver mobile app, live GPS, passenger notifications | Strong | **Differentiator** |
| 17 | Accommodation | Legacy | 75% | 60% | Hotel API/inventory, direct booking, release deadlines | Good | High |
| 18 | Catering | Legacy | 45% | 40% | Dietary aggregation → kitchen, BEO sheets, per-session linkage | Thin | Medium |
| 19 | Suppliers | Legacy | 45% | 45% | RFQ, purchase orders, scorecards, contract linkage | Thin | High |
| 20 | Exhibition | Legacy | 60% | 50% | Exhibitor portal, lead retrieval, booth sales workflow | Fair | High |
| 21 | Sponsors | Legacy | 65% | 55% | Benefit fulfilment tracking, sponsor portal, ROI reporting | Fair | **Very high** |
| 22 | Attendees | Legacy | 70% | 55% | Segmentation, bulk email, import validation, GDPR delete | Good | High |
| 23 | Registration | Legacy | 65% | **45%** | **No confirmation email, no payment, no waitlist, no group reg** | Fair | **Critical** |
| 24 | Arrivals | Legacy | 55% | 50% | Flight API, driver assignment automation | Fair | Medium |
| 25 | Check-in | Legacy | 60% | **45%** | **No offline mode**, no badge printing on-site, no mobile UI | Fair | **Critical** |
| 26 | Budget Builder | Legacy | **85%** | 65% | Multi-currency FX at line level, approval thresholds | Strong | High |
| 27 | Budget Tracker | Legacy | 80% | 60% | Committed-vs-actual from POs (no PO module), forecasting | Strong | High |
| 28 | Finance | Converted | 65% | 55% | Accounting integration, tax engine, revenue recognition | Good | High |
| 29 | Invoices | Converted | 70% | 50% | **No delivery (email is a log file)**, no dunning, no credit notes | Good | **Critical** |
| 30 | Payments | Converted | 50% | **40%** | **No gateway** — manual entry only; no reconciliation | Fair | **Critical** |
| 31 | Reports | Legacy | 55% | 45% | Export, scheduling, custom report builder, saved reports | Fair | High |
| 32 | AI Assistant | Legacy | 35% | 40% | **Zero LLM.** No NL query, no generation, no forecasting | Thin | High (as told) |
| 33 | Team | Legacy | 55% | 45% | SSO, 2FA, granular permission UI, audit surfacing | Fair | Medium |
| 34 | Settings | Legacy | 70% | 55% | Per-tenant branding, plan/entitlement config, data export | Fair | Medium |
| + | Risks | Legacy | 50% | 45% | Mitigation workflow, escalation, register templates | Thin | Medium |
| + | Approvals | Legacy | 65% | 55% | Delegation, SLA escalation, mobile approve, email | Fair | High |

**Portfolio averages: ~67% complete, ~54% production-ready.** Note the pattern — *the deepest modules are the unconverted ones*. The redesign is currently ahead of the product in the commercial modules and behind it everywhere the actual event work happens.

---

## 3. Event-industry competitive review

### 3.1 Where Elite is stronger

1. **Production and logistics.** Transport dispatch with a live board, driver trip sheets, VIP transfer sheets, rooming lists, supplier orders. **Cvent and Bizzabo do not do this.** EventsAir partially does. For Middle-East full-service agencies this is the daily job.
2. **Commercial lifecycle in one system.** Deal → proposal → contract (with e-signature) → budget → invoice → payment, inside the same tool as delivery. Competitors bolt CRM on or assume Salesforce.
3. **Budget depth.** Versioning, categories, income lines, margin by event type, cost routing. Cvent's budget module is weaker than this.
4. **Document generation.** 25 PDF surfaces, bilingual-capable, branded. Agencies live on these.
5. **Health scoring across a portfolio.** Genuinely useful and not standard in the category.

### 3.2 Where Elite is weaker

1. **No attendee product.** No mobile app, no networking, no session engagement, no gamification. Whova and EventMobi *are* this.
2. **No marketing/email engine.** Cvent's core loop is invite → register → remind → attend. Elite cannot send an email.
3. **No payments.** Cvent, EventsAir and Bizzabo all take money. Elite records that money arrived.
4. **No integrations.** No API, no Zapier, no CRM/marketing/accounting connectors.
5. **No analytics/benchmarking.** Reports are read-only screens with no export.
6. **No enterprise trust layer.** No SSO, no 2FA, no SOC2/GDPR tooling, no data residency, no audit export.
7. **Scale.** Competitors run 50,000-attendee congresses. Elite has no pagination.

### 3.3 What is missing outright

Attendee mobile app · email marketing · payment processing · public API · SSO/2FA · abstract/speaker submission · session capacity & waitlists · badge printing on-site · lead retrieval hardware · virtual/hybrid streaming · survey & NPS · multi-language attendee UI · GDPR self-service.

### 3.4 What could become the differentiator

**"The only platform that runs the event *and* the business that delivers it."**

Cvent sells attendee management to corporates. Elite's actual advantage is the **agency operating system** — the transport, rooming, supplier, contract and margin machinery that a full-service production company needs and that no incumbent covers well. That is a defensible, under-served niche in the GCC/MENA market where agencies deliver end-to-end.

The strategic error would be chasing Cvent on attendee features. The strategic win is owning "agency ERP for events," where the current codebase is already 80% of the way to best-in-class.

---

## 4. Top gaps, ranked by business impact

### Top 20 missing features
1. Transactional email delivery — **blocks everything else**
2. Payment gateway (Stripe/Tap/HyperPay for MENA)
3. Attendee mobile/web app
4. Pagination across all lists
5. Public REST API + webhooks
6. Offline check-in
7. Registration confirmation & reminder emails
8. Purchase orders + supplier RFQ
9. Sponsor benefit-fulfilment tracking
10. Exhibitor self-service portal
11. Speaker self-service portal
12. Report export (PDF/Excel) + scheduling
13. SSO + 2FA
14. Real LLM assistant
15. Multi-currency with FX at line level
16. Accounting integration (Xero/QuickBooks/Zoho)
17. Session capacity + waitlists
18. Bulk attendee email/segmentation
19. Badge printing on-site
20. Client portal (approvals + status)

### Top 10 missing workflows
1. Registration → payment → confirmation → badge → check-in (end-to-end, unbroken)
2. Approval → notification → decision → audit (currently silent — no email)
3. Budget → PO → supplier delivery → invoice → payment reconciliation
4. Proposal → client online accept → contract → deposit invoice
5. Sponsor sold → benefits scheduled → fulfilment tracked → post-event ROI report
6. Speaker invited → confirmed → materials collected → travel booked → on stage
7. Post-event: survey → NPS → report → renewal opportunity back into CRM
8. Tenant onboarding: signup → workspace → branding → users → first event
9. Incident/change management during live events
10. Data export & GDPR deletion per tenant

### Top 10 technical improvements
1. Close the fail-open tenancy gap (make unbound queries throw)
2. Postgres cutover (plan already written — execute it)
3. Pagination everywhere
4. Move PDF generation to queued jobs
5. Get the suite green and gate merges on it
6. Explain and fix the 109 non-executed tests
7. Extract business logic out of 900+ LOC Livewire components into services
8. Add caching to portfolio aggregates
9. Add `routes/api.php` + Sanctum
10. Consolidate to one design system; delete the loser

### Top 10 UX improvements
1. Fix the broken Event Hub journey nav and Mission Radar clipping
2. Finish or roll back Soft Command — do not ship 21%
3. Mobile-first check-in, arrivals and dispatch
4. Empty/loading/error states as a system
5. Global search that spans all entities (palette exists — deepen it)
6. Bulk actions on every list
7. Inline validation and save-state feedback consistency
8. Keyboard navigation for power users
9. Reduce Event Hub tab count via the journey grouping already designed
10. Accessibility pass (contrast, focus order, ARIA) — currently unaudited

---

## 5. Roadmap review

### Immediate — next 30 days
1. **Freeze design work.** No new visual direction until Soft Command is finished or abandoned. Decide in writing, once.
2. Get the suite green; make CI block merges.
3. Fix the Event Hub Overview defects.
4. Turn on real email (SES/Postmark) and wire registration confirmations, invoices, approvals, invites.
5. Execute the Postgres cutover.
6. Decide the strategic question below (§7).

### Medium — next 90 days
7. Payment gateway + registration → payment → confirmation loop.
8. Pagination and queued PDFs.
9. Close the tenancy fail-open gap; create a second tenant and prove isolation with real data.
10. Finish the design system across the Event Hub interior (the 44 unconverted views).
11. Report export + scheduling.
12. SSO/2FA.
13. Purchase orders + supplier RFQ.

### Long-term
14. Attendee mobile app.
15. Public API + webhooks + integration marketplace.
16. Real LLM assistant grounded on the existing rules engine.
17. Sponsor/exhibitor/speaker portals.
18. Tenant self-serve onboarding + billing + entitlements.
19. Analytics and cross-event benchmarking.
20. SOC2 / GDPR readiness.

---

## 6. Executive scorecard

| Dimension | Score | Justification |
|---|---:|---|
| Architecture | **7.0 / 10** | Clean Laravel 13, real services layer, thoughtful tenancy retrofit. Docked for no async, no API, fat components, static tenancy state. |
| UX | **5.0 / 10** | Strong where finished; 21% conversion, visible breakage on the flagship page, no mobile story. |
| Design | **6.5 / 10** | The system itself is genuinely premium and well documented — but largely aspirational, and two systems coexist. |
| Event workflows | **8.0 / 10** | The best part of the platform. Agenda, run-of-show, conflicts, rooming, transport. |
| Finance workflows | **6.0 / 10** | Budget/contract/invoice chain is deep and real; no gateway, no accounting sync, no tax engine. |
| Operations workflows | **7.5 / 10** | Transport, arrivals, check-in, exhibition are strong; suppliers and catering are thin. |
| Scalability | **3.0 / 10** | SQLite, zero pagination, sync PDFs, no cache, no queue. |
| Commercial readiness | **3.0 / 10** | No billing, no entitlements, no onboarding, no email, no API. |
| Multi-tenant readiness | **4.0 / 10** | Good spine, indexed, guard-tested — but fail-open and never exercised beyond one tenant. |
| Production readiness | **4.0 / 10** | Red baseline, SQLite, no email, no queue worker. Sentry + CI + backup drill are real positives. |
| **Overall** | **5.4 / 10** | A strong engine in an unfinished car. |

---

## 7. If Elite Business Hub launched commercially today

### **~35% complete.**

| Layer | Complete | Weight | Contribution |
|---|---:|---:|---:|
| Domain logic & data model | 75% | 25% | 18.75 |
| Organiser-facing UI | 55% | 15% | 8.25 |
| Event workflows | 70% | 15% | 10.50 |
| Financial workflows | 45% | 10% | 4.50 |
| Attendee-facing product | 10% | 10% | 1.00 |
| Multi-tenant / SaaS platform | 20% | 10% | 2.00 |
| Integrations & API | 0% | 5% | 0.00 |
| Scale & operations | 25% | 5% | 1.25 |
| Trust & compliance | 15% | 5% | 0.75 |
| | | | **≈ 47%** |

Weighted arithmetic gives ~47%. I am scoring it **35%** because three of the gaps are **binary blockers**, not partial credit: **you cannot sell a SaaS that cannot send an email, cannot take a payment, and has never run two tenants.** Until those three flip, completion percentage is somewhat academic — the product cannot transact.

**As an internal tool for Elite Business Hub's own operations, it is ~70% complete and already valuable today.** That distinction is the most important sentence in this audit.

---

## 8. Blind spots to confront

1. **You are measuring progress in design systems, not in customer outcomes.** Seven visual directions, zero paying tenants. The next design decision should require naming the customer who asked for it.
2. **Multi-agent parallel development is producing branch sprawl and red builds.** 30+ branches, two worktrees, a tagged red baseline. Velocity is being consumed by integration debt.
3. **"Defined" is being counted as "done."** The audit brief lists Budget Command, Operations Control, Agenda Builder, Exhibition Builder, Layout Builder, Transport Dispatch and Deals Workspace as *defined* — and they are — but definition does not appear on a customer's screen.
4. **The strongest asset is being under-marketed and the weakest over-marketed.** Transport/rooming/contract depth is world-class and barely mentioned. "AI Assistant" is a rules engine and leads the pitch.
5. **No customer has ever used this.** One tenant, one workspace, 18 demo events. Every product assumption remains unvalidated.
6. **The nearest competitor is not Cvent — it's a spreadsheet.** Which is winnable, and a much better first market than an enterprise displacement.

---

## 9. The one decision that unblocks everything

**Choose the product you are building, and say it out loud:**

- **(A) Internal ops system for Elite Business Hub.** Then stop building multi-tenancy, stop redesigning, ship email + payments, and put it in daily use. Reachable in **4–6 weeks**.
- **(B) Commercial SaaS for MENA event agencies.** Then design work stops until email, payments, tenancy hardening, pagination and onboarding are done. Realistically **6–9 months** with the current team.

Both are good businesses. Pursuing both simultaneously — which is what the last thirteen months look like from the outside — is what has produced a 5.4/10 platform out of work that deserves an 8.

---

*Audit based on direct inspection of code, schema, test suite and running application. No code was modified in producing this report.*
