# Phase 4 — Event Hub Interior: Deep Technical Review & Implementation Backlog

**Date:** 14 August 2026 · **Status:** Planning and analysis only — no code changed
**Scope:** the 13 legacy Event Hub interior modules named in [docs/33 §L](33-enterprise-platform-audit.md) / [docs/34 Phase 4](34-master-improvement-roadmap.md)
**Explicitly not done:** no Phase 3 work, no new features, no Operations Control / Layout Builder / Exhibition Builder, no code changes

Phase 2 remains open in parallel, blocked on external actions already tracked in docs/37–40. This document does not depend on Phase 2 and does not advance it.

---

## Methodology

Every number below was measured against the live codebase and a running instance today, not carried forward from memory:

- **LOC** — `wc -l` on the Livewire class and its Blade view.
- **Complexity proxy** — public property count and public method count on the Livewire class (a rough but honest stand-in for how much state a conversion has to preserve).
- **Token adoption** — occurrences of `x-eo.` / `eo-*` classes in the view (all 13 came back at **zero**, confirming docs/33's finding directly rather than repeating it on faith).
- **Query cost** — live-measured via `DB::enableQueryLog()` against a real seeded event (20 events, 626 attendees), hitting `/events/{id}?tab={tab}` exactly as a browser would. Four of these (Budget, Transport, Agenda, Overview) were already measured in docs/33 §L and are cited from there rather than re-run; the other eight were measured fresh for this review.
- **Test coverage** — every `test_*` method in every file that actually exercises the module, counted directly, not estimated.
- **Cross-module coupling** — every `App\Models\*` / `App\Services\*` import in each Tab class, so the dependency claims below are import lists, not guesses.

---

## Cross-cutting findings (apply to all 13)

1. **Zero design-system adoption is total, not partial.** Every one of the 13 views returned 0 `eo-*` occurrences. There is no "mostly converted" module in this set — conversion starts from the same baseline everywhere.
2. **No shared eo-* supplier/venue picker exists yet.** Budget, Transport, Accommodation and Catering all import `App\Models\Supplier` directly and each currently renders its own supplier-selection UI in raw markup. Converting all four independently means building the same picker four times. This is the single highest-leverage prerequisite for Phase 4 — see Backlog Item 0.
3. **The base Hub page load is already expensive before any tab renders.** `EventHubController::show()` eager-loads 17 relations unconditionally on every tab (`$event->load([...])`), which is why even the lightest tabs (Risks, Exhibition, Files) cost 36–38 queries before their own content runs a single query. Converting a module's *view* does nothing about this; it is a separate, cross-cutting fix (tracked as Item 0b, not scored per-module below since no single module conversion touches it).
4. **PDF generation is in-request Browsershot (headless Chrome) for 23 controllers**, several tied to these 13 modules (Budget, Transport ×6, Exhibition, Room/Equipment). This is a Phase 3 performance concern, not a Phase 4 one — noted here only because a UI conversion that touches a module's data shape can silently break its PDF twin if the two aren't converted together (see per-module risk notes).
5. **Test coverage is bimodal, not evenly thin.** Budget/Transport/Approvals/Catering are well covered. Risks and Exhibition have **zero dedicated tests** — converting them without adding coverage first means shipping a UI rewrite with nothing to catch a regression.

---

## Per-module review

### 1 — Hub Overview
`resources/views/events/hub/overview.blade.php` — no dedicated Livewire class (embedded in `EventHubController`)

| Metric | Value |
|---|---|
| LOC | 572 |
| Tokens | **Mixed** — 6 `x-eo.` uses (the Orbit Journey include sits directly above this file's own legacy chrome) |
| Query cost | **51** (measured, docs/33) |
| Tests | 1 dedicated (`EventOverviewReadinessTest`, plus indirectly covered by every Hub-routing test) |
| Complexity | No Livewire class — logic lives split between `EventHubController::liveAlerts()`/`teamWorkload()` and inline `@php` in the view |

- **UI debt:** the only file in the whole product that is visibly two design systems stacked — Orbit Journey (eo-*) rendering into old chrome immediately below it. Every other module is uniformly legacy; this one is uniformly jarring.
- **UX debt:** it's the first screen on every event open — the highest-traffic page in the product per docs/33 — so this debt is seen more than any other module's.
- **Technical debt:** 28 inline `style="..."` attributes (highest of any file reviewed) — these don't move with a token system and have to be hand-translated, not swept.
- **Query/performance:** 51 queries is the *floor* every other tab pays before its own content runs — see cross-cutting finding 3.
- **Test coverage:** thin for a page this central — one dedicated file, mostly asserting text presence (`substr_count` on "Not started", from the Orbit Journey work).
- **Conversion complexity:** **Medium** — no Livewire state to preserve (it's a plain view), but the visual seam with Orbit Journey means this has to look intentional at 100% completion, not just "less bad."
- **Risks:** converting this without care re-litigates the Orbit Journey's own visual language; get the two out of sync and the seam becomes two seams instead of one.
- **Dependencies:** reads `EventCommandHeader::for()` and `EventHealthService::breakdown()` — both already the Orbit Journey's data sources, so no new service work needed, only markup.

---

### 2 — Budget
`app/Livewire/Hub/BudgetTab.php` (896 LOC, 32 props, 39 methods) + `budget-tab.blade.php` (874 LOC)

| Metric | Value |
|---|---|
| Query cost | **88** (measured, docs/33) — the single most expensive tab in the product |
| Tests | **49** across 3 files (`ModuleBudgetRoutingTest` 7, `BudgetFollowsModulesTest` 14, `BudgetSellPriceTest` 14, plus 14 budget-specific tests inside `EventHubActionsTest`) |
| Coupling | `EventBudgetItem`, `EventIncomeItem`, `Supplier`, `BudgetSync` service, `CurrencyService` |

- **UI debt:** largest legacy view by class complexity in the set (39 public methods) — a lot of surface for a retokening pass to accidentally miss.
- **UX debt:** docs/33 names this the highest-value module in the product — it's where margin gets decided — and it's currently the most expensive page to open.
- **Technical debt:** depends on `BudgetSync`, the same service `EventHubController::liveAlerts()` calls for "commitments not in the budget" — a conversion that changes how budget lines render has to not break that cross-page alert.
- **Query/performance:** 88 queries is 37 above the 51-query Overview floor — the highest per-tab marginal cost measured. A real N+1 candidate given `Supplier` is imported and budget lines commonly render one row per supplier relationship.
- **Test coverage:** the best-covered module in the set (49 tests). This is the module where a retokening pass is safest — the suite will actually catch a regression.
- **Conversion complexity:** **High** — largest LOC, most methods, most cross-module coupling (BudgetSync, CurrencyService, Supplier).
- **Risks:** `BudgetPdfController` renders this module's data via Browsershot — converting the on-screen view without checking the PDF template can silently desync the two.
- **Dependencies:** `BudgetSync` (cross-module), `CurrencyService`, and indirectly `Supplier` — **should follow, not precede, Backlog Item 0** (shared supplier picker) or the picker gets built against Budget's specific needs first and has to be generalised afterward.

---

### 3 — Transport
`app/Livewire/Hub/TransportationTab.php` (1,178 LOC, 35 props, 37 methods) + `transportation-tab.blade.php` (1,038 LOC)

| Metric | Value |
|---|---|
| Query cost | **61** (measured, docs/33) |
| Tests | **89** across 8 files — by far the deepest coverage of any module reviewed (Workbench 6, Documents 11, Dispatch 11, Manifest 15, AttendeeLink 3, Row 4, Health 5, PeopleLayer 13, Live 11 — Live/Dispatch are separate full-screen tools sharing this domain, counted here for context) |
| Coupling | `EventTransport`, `Supplier`, `TransportDriver`, `TransportServiceType`, `TransportVehicle`, `VehicleType` |

- **UI debt:** largest single view by LOC (1,038) and largest class by LOC (1,178) in the entire set.
- **UX debt:** docs/33 calls this "a differentiator" — real operational value — currently trapped in the oldest-feeling chrome of the 13.
- **Technical debt:** six distinct models imported directly (no dedicated Transport service layer the way Budget has `BudgetSync`) — logic that should arguably be extracted into a service is currently living in the Livewire class itself, which is exactly what makes it 1,178 lines.
- **Query/performance:** 61 measured — only 10 above the Overview floor, better than its LOC would suggest, but `TransportManifestPdfController`, `TransportDispatch`, `TransportLive`, and four more Transport PDF routes all read from the same tables independently; a conversion is a good moment to check they're not each re-deriving the same joins.
- **Test coverage:** deepest in the product. A test named `TransportAttendeeLinkTest.php` exists, but no direct `EventAttendee` import appears in `TransportationTab.php` itself — the linkage is real (the test passes today) but lives somewhere this review didn't fully trace (likely raw ID references in manifest JSON rather than an Eloquent relation). **Flag for the conversion session itself to locate before touching manifest rendering**, rather than asserting a mechanism here that wasn't directly confirmed.
- **Conversion complexity:** **High** — largest LOC in the set, though the exceptional test coverage substantially de-risks it.
- **Risks:** six PDF/tool routes share this domain (`transport.pdf`, `transport/dispatch`, `transport/live`, `transport/daily-schedule.pdf`, `transport/trip-sheets.pdf`, `transport/vip-sheets.pdf`, `transport/plan.pdf`, `transport/supplier-order.pdf`) — the widest blast radius of any module if a data-shape assumption changes during conversion.
- **Dependencies:** `Supplier` (shared with Budget/Accommodation/Catering — Item 0), no service-layer dependency on other Hub modules.

---

### 4 — Attendees
`app/Livewire/Hub/AttendeesTab.php` (570 LOC, 26 props, 18 methods) + `attendees-tab.blade.php` (473 LOC)

| Metric | Value |
|---|---|
| Query cost | **41** (measured fresh) |
| Tests | **8** (`AttendeesTest` 3, `AttendeeImportTest` 5) — thin relative to size |
| Coupling | `EventAttendee`, `CompanyProfile` |

- **UI debt:** moderate LOC, moderate complexity (26 props is high for a module without a dedicated service layer backing it).
- **UX debt:** feeds both Registration and Check-in per docs/34's ordering rationale — a confusing or slow Attendees tab has knock-on effects on two other modules' usability, not just its own.
- **Technical debt:** 26 public properties on one Livewire class is a lot of state for 570 LOC — worth checking during conversion whether some of that state belongs in computed properties instead of tracked public ones (affects payload size on every Livewire round-trip, not just code cleanliness).
- **Query/performance:** 41 queries, the lowest of the 8 freshly measured — reasonably efficient already.
- **Test coverage:** **8 tests for the module that feeds two others is thin.** `AttendeeImportTest` covers the bulk-import path but there's no dedicated coverage of the tab's own CRUD/filter/status-change surface beyond `AttendeesTest`'s 3 tests.
- **Conversion complexity:** **Medium** — moderate LOC, but the thin test coverage raises the effective risk above what the size alone suggests.
- **Risks:** downstream of this module are Registration (public-facing) and Check-in (on-site, no offline mode) — a regression introduced here surfaces in two other places, possibly not until event day.
- **Dependencies:** feeds Registration and Check-in (informational data flow, not a code import — neither of those two imports `AttendeesTab`). **Add coverage before or during conversion, not after** — this is the one module in the set where "convert then backfill tests" is the wrong order given what depends on it.

---

### 5 — Approvals
`app/Livewire/Hub/ApprovalsTab.php` (320 LOC, 8 props, 9 methods) + `approvals-tab.blade.php` (275 LOC)

| Metric | Value |
|---|---|
| Query cost | **59** (measured fresh) — **anomalously high for the size** |
| Tests | **19** (`ApprovalWorkflowTest`) — strong |
| Coupling | `EventApproval`, `CompanyProfile`, `User` |

- **UI debt:** small view, low complexity by LOC — should be one of the easier conversions in the set.
- **UX debt:** surfaces directly in the Priority Area on Overview and in `EventHubController::liveAlerts()` — visible outside its own tab, so its data shape can't change without checking both call sites.
- **Technical debt:** email-wired (`docs/34`'s own note) — approval decisions trigger notifications, which the Phase 2 mail-safety-hold rules already govern; a Phase 4 conversion here must not accidentally exercise that path against non-pilot data during testing.
- **Query/performance:** **59 queries is higher than Transport (61 is close, but Transport is 6x the LOC) and far higher than Attendees (41) for a module a quarter the size.** This is the one genuine performance anomaly surfaced in this review — worth a direct look (likely an N+1 across `User` — requester and decider — not eager-loaded per row) *before* or *during* conversion, since a retokening pass touches the same view code that would fix it.
- **Test coverage:** strong (19 tests) — safe to convert with confidence the suite will catch behavioural regressions, though it won't catch the query-count anomaly since none of the 19 tests assert query counts.
- **Conversion complexity:** **Low** by size, but flagged up to **Medium** because of the query anomaly worth investigating in the same pass.
- **Risks:** email side-effects (see technical debt) and the cross-page Priority Area / liveAlerts dependency.
- **Dependencies:** visible in Overview's Priority Area and alert feed — coordinate with Module 1's conversion so the data shape stays consistent across both surfaces.

---

### 6 — Accommodation
`app/Livewire/Hub/AccommodationTab.php` (534 LOC, 20 props, 14 methods) + `accommodation-tab.blade.php` (460 LOC)

| Metric | Value |
|---|---|
| Query cost | **44** (measured fresh) |
| Tests | **4** (`AccommodationImportTest`) — thin |
| Coupling | `EventAccommodation`, `EventRoomBlock`, `Supplier`, `Venue` |

- **UI debt:** moderate LOC and complexity; four models imported directly is the second-widest coupling in the set after Transport.
- **UX debt:** rooming lists feed a PDF export (`rooming/{block}.pdf`, `rooming/{block}/template.xlsx`) used operationally for hotel liaison — a real external-facing document, not just an internal screen.
- **Technical debt:** touches `Venue` as well as `Supplier` — another consumer of the not-yet-built shared picker (Item 0).
- **Query/performance:** 44 queries, mid-range — nothing alarming, but four imported models with only 1 `with()` eager-load call (measured earlier) suggests room for a tighter query during conversion.
- **Test coverage:** **4 tests, all import-path.** No coverage of the tab's own room-block/rate-management surface — similar gap to Attendees.
- **Conversion complexity:** **Medium** — moderate size, thin coverage raises effective risk.
- **Risks:** the rooming-list PDF/XLSX exports are used by real hotel-facing staff workflows per the module's purpose — breaking their data shape during conversion has an external-facing consequence, not just an internal one.
- **Dependencies:** `Supplier` and `Venue` — both Item 0 candidates.

---

### 7 — Registration (public-facing)
`app/Livewire/PublicRegistration.php` (203 LOC, 2 props, 5 methods) + supporting `RegistrationTemplates.php`, `Hub/RegistrationForm.php`

| Metric | Value |
|---|---|
| Tests | **50** across 5 files (`RegistrationTemplateTest` 11, `RegistrationSessionsTest` 9, `RegistrationFieldTest` 13, `PublicRegistrationTest` 11, `RegistrationImportTest` 6) — the best-tested module in the set relative to its own size |
| Security | Token-scoped (`Event::where('registration_token', $token)`), rate-limited (`RateLimiter`, 5 attempts / 900s) — **already reasonably hardened** |

- **UI debt:** small class (203 LOC), but this is the one module in the set an unauthenticated public visitor sees directly — visual debt here is brand-facing, not just internal-staff-facing.
- **UX debt:** docs/33 flags "Registration → confirm → check-in" as **broken in practice** — confirmation goes to a log, not real email (correctly held per the Phase 2 mail-safety-hold rule), so the practical workflow is currently unverifiable end-to-end until Phase 2's SMTP work lands.
- **Technical debt:** low — this is the cleanest module in the set by the numbers (smallest class, best test-to-size ratio, real rate limiting already present).
- **Query/performance:** not measured live in this pass (requires a valid registration token to exercise realistically) — low risk given the class's small size and single-purpose scope.
- **Test coverage:** excellent (50 tests across the wider registration surface).
- **Conversion complexity:** **Low** — small, clean, well-tested. Genuinely one of the easier conversions in the set despite being public-facing.
- **Risks:** it's the one screen actual attendees see before they're customers of the platform — a visual regression here is reputational, not just internal friction. Low technical risk, but warrants a real device/browser check before calling it done, not just a desktop screenshot.
- **Dependencies:** none on other Hub modules; depends on `registration_templates`/`registration_fields`, which are already part of the pilot-reference export (Phase 2) — worth noting the two workstreams touch adjacent data without conflicting.

---

### 8 — Sponsors
`app/Livewire/Hub/SponsorsTab.php` (259 LOC, 18 props, 14 methods) + `sponsors-tab.blade.php` (225 LOC)

| Metric | Value |
|---|---|
| Query cost | **38** (measured fresh) |
| Tests | **2** direct (`SponsorPackagesSettingsTest`) + meaningful indirect coverage inside `EventHubActionsTest` (5 sponsor-specific tests: seeding, autofill, income-target persistence, benefits, slot limits) — **7 effective** |
| Coupling | `EventSponsor` only |

- **UI debt:** small, single-model coupling — one of the more isolated modules in the set.
- **UX debt:** ties into Commercial (sponsorship prospectus/PDF) — docs/34 notes this precedes any future Exhibition/Sponsorship builder work, so getting the data shape right now avoids rework later.
- **Technical debt:** low — 18 props is more state than the 259 LOC would suggest, worth a light look during conversion.
- **Query/performance:** 38, the joint-lowest measured (tied with Files) — efficient already.
- **Test coverage:** thin on paper (2 direct) but the real workflow — package seeding, sale, autofill, benefits, slot limits — is covered from `EventHubActionsTest`. Treat as adequate, not as thin as the raw count suggests.
- **Conversion complexity:** **Low**.
- **Risks:** low — single model, decent effective coverage, no PDF export tied directly to this tab (the sponsorship PDF lives under a separate `SponsorshipController`, not this tab).
- **Dependencies:** none on other Hub modules.

---

### 9 — Risks
`app/Livewire/Hub/RisksTab.php` (83 LOC, 8 props, 4 methods) + `risks-tab.blade.php` (120 LOC)

| Metric | Value |
|---|---|
| Query cost | **38** (measured fresh) |
| Tests | **0 dedicated** — only incidental references via `use App\Livewire\Hub\RisksTab;` in `EventHubActionsTest`, which does not actually exercise risk creation/scoring |
| Coupling | `EventRisk`, `User` |

- **UI debt:** the smallest class in the entire set (83 LOC) — genuinely the simplest conversion by size.
- **UX debt:** docs/33 names this among the weakest-scored modules (62/100) — risk severity feeds the live alert feed on Overview (`liveAlerts()` sorts by severity, takes top 3), so its data quality has visible consequences elsewhere even though the module itself is small.
- **Technical debt:** low code complexity, but **zero test coverage is real** — confirmed by direct search, not assumed.
- **Query/performance:** 38, low.
- **Test coverage:** **the weakest in the set alongside Exhibition.** Per docs/34's own note: "Add tests during conversion" — this review confirms that's still accurate today, nothing has changed since the roadmap was written.
- **Conversion complexity:** **Low** by size, but the **zero test coverage makes it the single highest-risk-per-line-of-code module in the set** — small enough to convert quickly, but nothing will catch a mistake.
- **Risks:** feeds the Overview alert feed's severity sort — a conversion that changes how `severity()` renders or is stored has to be checked against `EventHubController::liveAlerts()` by hand, since no test currently would catch a break there.
- **Dependencies:** read by `EventHubController::liveAlerts()` — coordinate with Module 1.

---

### 10 — Catering
`app/Livewire/Hub/CateringTab.php` (161 LOC, 14 props, 8 methods) + `catering-tab.blade.php` (201 LOC)

| Metric | Value |
|---|---|
| Query cost | **40** (measured fresh) |
| Tests | **13** (`CateringModuleTest`) — solid |
| Coupling | `EventCateringItem`, `Supplier` |

- **UI debt:** small, straightforward.
- **UX debt:** docs/33 scored this 61/100 — "thin logic" — meaning the debt here is more about the module doing less than it should than about it being broken.
- **Technical debt:** low; `Supplier` coupling again (Item 0 candidate).
- **Query/performance:** 40, low.
- **Test coverage:** solid (13 tests) — safe to convert.
- **Conversion complexity:** **Low**.
- **Risks:** minimal — small, tested, no PDF export tied to this specific tab.
- **Dependencies:** `Supplier` (Item 0).

---

### 11 — Exhibition
`app/Livewire/Hub/ExhibitionTab.php` (147 LOC, 14 props, 8 methods) + `exhibition-tab.blade.php` (189 LOC)

| Metric | Value |
|---|---|
| Query cost | **36** (measured fresh) — lowest of all 13 |
| Tests | **0 dedicated to `ExhibitionTab` itself**, but real adjacent coverage: `BoothSalesTest` (5 tests — booth inventory/sale/release/uniqueness), `EventModulesTest` (module-toggle coverage including exhibition), plus floor-plan tests inside `EventHubActionsTest` (`test_exhibition_floor_plan_halls_place_move_and_fixtures`, `test_exhibition_floor_plan_and_pdf_render`, `test_exhibition_target_persists`) |
| Coupling | `Event` only in the Tab class — floor-plan logic lives in a separate `RoomLayoutBuilder`/`ExhibitionFloorPlan` component, not in `ExhibitionTab` |

- **UI debt:** smallest query footprint (36) in the set, small LOC.
- **UX debt:** docs/33 scores this among the weakest (60/100); docs/34 flags it explicitly as **preceding any future Exhibition Builder work** — getting this conversion right now is lower-risk groundwork for a feature this document is explicitly not starting.
- **Technical debt:** the module is split — `ExhibitionTab` handles the tab shell, but booth sales (`BoothSalesTest`) and floor-plan placement (`ExhibitionFloorPlan`, a separate Livewire component behind `/events/{event}/exhibition-floor`) are elsewhere. **A conversion of "Exhibition" is really two or three components, not one** — scope this explicitly before starting, or the LOC estimate above understates the real work.
- **Query/performance:** lowest measured (36).
- **Test coverage:** `ExhibitionTab` itself has no dedicated file, but the domain is not actually untested — booth sales and floor-plan behaviour are real, verified coverage elsewhere. **Correcting docs/34's characterization slightly:** "0 tests" was true of `ExhibitionTab.php` in isolation but understates the domain's real coverage once `BoothSalesTest` and the floor-plan tests are counted.
- **Conversion complexity:** **Medium** — not because the tab shell is large, but because the real Exhibition surface spans three components (`ExhibitionTab`, `ExhibitionFloorPlan`, booth-sale logic) that a scoped "convert Exhibition" task needs to explicitly include or explicitly exclude.
- **Risks:** `ExhibitionFloorPdfController` and the floor-plan builder share this domain — same multi-component blast-radius concern as Transport, at smaller scale.
- **Dependencies:** none on other Hub *tabs*, but real internal dependency on the separate floor-plan component.

---

### 12 — Check-in
`app/Livewire/ArrivalsDesk.php` (121 LOC, 2 props, 5 methods) — **not a Hub tab**, a separate full-screen tool at `/events/{event}/arrivals`

| Metric | Value |
|---|---|
| Tests | **7** (`ArrivalsDeskTest`) |
| Offline capability | **Confirmed absent** — no offline/sync/queue/localStorage signal anywhere in the class or its view |

- **UI debt:** small class, but per docs/33 this is the **weakest-scored page in the entire audit at 59/100** — worth treating this review's number as a confirmation, not a new finding.
- **UX debt:** desktop-shaped, used standing up at a door with a phone — the single largest UX/tool-shape mismatch in the whole product per docs/33 §64 ("Check-in and Arrivals are legacy, desktop-shaped, and have no offline mode. A venue Wi-Fi drop stops door operations with no fallback.").
- **Technical debt:** confirmed directly in this review — grepped the class and its view for any offline/sync/queue signal and found none. The only "queue" in the file is a doc comment describing a *person* standing in a physical line, not a data queue.
- **Query/performance:** not separately measured (small, single-purpose, low LOC — not a performance concern relative to its size).
- **Test coverage:** 7 tests, reasonable for the size.
- **Conversion complexity:** **Low for retokening**, but retokening alone does not address the actual named risk (no offline mode). **Docs/34 already says this correctly: "Full rework in Phase 6 — convert lightly only."** This review confirms that instruction still holds — do not over-invest here in Phase 4.
- **Risks:** the highest real-world risk in the set — an on-site failure here is visible to a room full of arriving guests, not just to staff. But the fix is Phase 6's offline work, not a Phase 4 token pass.
- **Dependencies:** reads `EventAttendee` (the Attendees tab's data) — **should follow Attendees in conversion order** so the two share one visual language for what is functionally one workflow split across two screens.

---

### 13 — Documents
`app/Livewire/Hub/ModuleDocuments.php` (311 LOC, 10 props, 19 methods) + `module-documents.blade.php` (38 LOC)

| Metric | Value |
|---|---|
| Query cost | **38** (measured fresh, as "files" tab) |
| Tests | **16** across 2 direct files (`DocumentInlineSafetyTest` 5, `EventDocumentsTest` 11) — solid, and notably includes a dedicated **inline-safety** test, i.e. security-relevant coverage already exists |
| Coupling | `Event` only |

- **UI debt:** the view itself is tiny (38 LOC) — most of the surface is drawer partials referenced from elsewhere, matching docs/34's note ("Drawer partials").
- **UX debt:** low priority per docs/34 and this review agrees — it's a utility surface, not a daily-use workspace.
- **Technical debt:** 19 public methods on a 311-line class for what renders as a 38-line view is a high method-to-view ratio — most of the logic is upload/download/permission handling that doesn't show up as visual debt but does show up as conversion surface area (every method's output path needs checking, even if the view itself is small).
- **Query/performance:** 38, on par with the lighter modules.
- **Test coverage:** solid, and the existing `DocumentInlineSafetyTest` matters specifically — file-serving code has a real security surface (path traversal, MIME sniffing, private-visibility enforcement — the same `storage/{path}` mechanism docs/33 already checked and found correctly enforced). **Do not weaken or bypass that test's assertions during conversion for convenience.**
- **Conversion complexity:** **Low** for the view itself, but budget real time for the 19-method class underneath it, not just the 38-line template.
- **Risks:** the one genuine security-adjacent module in the set — treat any change here as needing the same care as Module 7 (Registration), not just a cosmetic pass.
- **Dependencies:** referenced as drawer partials from multiple other tabs — check what includes `module-documents` before assuming this is a standalone conversion.

---

## Recommended conversion order

Docs/34 proposed an order based on daily-usage × brokenness, set before this review's direct measurements existed. This review confirms most of it and adjusts where fresh evidence changes the picture:

| Order | Module | Docs/34 said | This review | Why (if changed) |
|---:|---|---|---|---|
| 1 | **Hub Overview** | Critical, first | **Confirmed** | Only mixed file; highest traffic |
| 2 | **Budget** | Critical | **Confirmed** | Highest value, highest query cost, best-tested |
| 3 | **Transport** | Critical | **Confirmed** | Largest module; exceptional test coverage de-risks it |
| 4 | **Attendees** | High | **Confirmed, flag added** | Feeds two other modules; test coverage is thinner than its downstream importance warrants — add coverage in the same pass, don't defer it |
| 5 | **Check-in** | *(was #12)* | **Moved up, immediately after Attendees** | Docs/34 sequenced this near the end; this review found it reads `EventAttendee` directly and is functionally one workflow with Attendees split across two screens — converting them apart risks two visual languages for one task. Still a **light** pass only, per docs/34's own instruction — Phase 6 remains where the real fix (offline mode) happens. |
| 6 | **Approvals** | High | **Confirmed, investigate first** | Query-count anomaly (59, high for its size) should be diagnosed as part of this pass, not left for Phase 3 |
| 7 | **Accommodation** | High | **Confirmed** | |
| 8 | **Registration** | High | **Confirmed, but genuinely easy** | Best test-to-size ratio in the set; already rate-limited; smaller lift than its "public-facing" label suggests |
| 9 | **Sponsors** | Medium | **Confirmed** | |
| 10 | **Catering** | Medium | **Confirmed** | |
| 11 | **Exhibition** | Medium | **Confirmed, scope note added** | Real surface spans 3 components, not 1 — scope explicitly |
| 12 | **Risks** | Medium | **Confirmed, coverage-first** | Zero tests confirmed directly; write tests before or during, not after |
| 13 | **Documents** | Low | **Confirmed** | |

**Net change from docs/34: Check-in moves from position 12 to position 5**, immediately after Attendees, on the strength of a direct dependency this review found that the original roadmap (written before this level of inspection) didn't have visibility into. Everything else holds.

---

## Phase 4 Implementation Backlog

Ordered. Each item is sized to be one conversion session, matching the H-phase discipline already proven in this project: one module per pass, suite run after each, screenshot before/after, no batching.

**Item 0 — Prerequisite: shared `eo-*` supplier/venue picker component**
Not a module conversion — infrastructure. Budget, Transport, Accommodation and Catering all import `Supplier` directly and each currently owns its own selection UI. Build one `x-eo.supplier-select` (and, if Accommodation's `Venue` usage warrants it, a sibling `x-eo.venue-select`) component before converting any of the four modules that would otherwise duplicate it. Skipping this means either four duplicate implementations or a mid-Phase-4 refactor to consolidate them — both worse than doing it once up front.

**Item 0b — Note, not an Item 4 task: the 17-relation base Hub load**
`EventHubController::show()` eager-loads all 17 relations on every tab regardless of which one is being viewed — the reason even the lightest tabs cost 36+ queries before their own content runs. No single module conversion fixes this; it's a controller-level change and belongs in Phase 3 (performance), not Phase 4 (UI). Flagged here so it isn't lost, not scheduled here.

1. **Hub Overview** — retokenize onto `eo-*`, resolve the visual seam with Orbit Journey. No Livewire state to preserve. *Medium.*
2. **Budget** — retokenize; verify against `BudgetPdfController`'s template so the screen and the PDF don't desync. *High — largest class, but best test coverage in the set carries the risk.*
3. **Transport** — retokenize; locate and document the actual Attendee-linkage mechanism (test exists, direct import doesn't) before touching manifest rendering; check the 6 dependent PDF/tool routes still read correctly. *High — largest LOC in the set.*
4. **Attendees** — retokenize; **add coverage for the tab's own CRUD/filter/status surface in the same pass**, not after, given what depends on it downstream. *Medium.*
5. **Check-in (light pass only)** — retokenize visual chrome to match Attendees' new language; **do not** attempt offline/sync work here — that's Phase 6 by design. *Low effort, sequenced early for consistency with Attendees, not because it's urgent on its own.*
6. **Approvals** — retokenize; **diagnose the 59-query anomaly in the same session** (likely requester/decider `User` not eager-loaded per row) since the view code being touched is the same code that would fix it; verify Overview's Priority Area and alert feed still render correctly against any data-shape change. *Medium.*
7. **Accommodation** — retokenize using the Item 0 picker; verify rooming-list PDF/XLSX exports still render correctly — real hotel-facing documents, not just internal screens. *Medium.*
8. **Registration (public)** — retokenize; verify on a real mobile browser, not just desktop, since this is the one screen actual attendees see. *Low.*
9. **Sponsors** — retokenize. *Low.*
10. **Catering** — retokenize using the Item 0 picker. *Low.*
11. **Exhibition** — **scope explicitly first**: decide whether this pass covers only `ExhibitionTab`'s shell or also the separate `ExhibitionFloorPlan`/booth-sale surfaces before estimating effort. *Medium, pending that scoping decision.*
12. **Risks** — **write tests before or during conversion**, not after; verify Overview's severity-sorted alert feed still functions against any change to how severity renders. *Low effort, but do not skip the test-writing step — this is the one module where skipping it leaves genuinely zero safety net.*
13. **Documents** — retokenize the 38-line view; budget real review time for the 19-method class underneath it; **do not weaken `DocumentInlineSafetyTest`'s assertions** for convenience during the pass. *Low.*

**Explicitly excluded from this backlog, per docs/34 and pending confirmation:**
- **Plan Studio** — `docs/08` names it a deliberate design exception. Not touched.
- **Tasks** — shares Plan Studio's visual language; docs/34 §J already flagged this as needing an explicit decision from Emran before scoping. Still unconfirmed as of this review — do not touch until that's settled.

**Method, unchanged from docs/34 and the H-phase precedent that already worked:** one module per working session, full suite run after each, before/after screenshots, no batching. Retokening, not restructuring — structural change belongs in Phase 5.

---

## Summary table

| # | Module | LOC (class+view) | Query cost | Tests | Complexity | Order |
|---:|---|---:|---:|---:|---|---:|
| 1 | Overview | 572 | 51 | 1 | Medium | 1 |
| 2 | Budget | 1,770 | 88 | 49 | High | 2 |
| 3 | Transport | 2,216 | 61 | 89 | High | 3 |
| 4 | Attendees | 1,043 | 41 | 8 | Medium | 4 |
| 5 | Check-in | 121 | — | 7 | Low | 5 |
| 6 | Approvals | 595 | **59** ⚠ | 19 | Medium | 6 |
| 7 | Accommodation | 994 | 44 | 4 | Medium | 7 |
| 8 | Registration | 203 | — | 50 | Low | 8 |
| 9 | Sponsors | 484 | 38 | 7 eff. | Low | 9 |
| 10 | Catering | 362 | 40 | 13 | Low | 10 |
| 11 | Exhibition | 336 | 36 | 8 eff. | Medium | 11 |
| 12 | Risks | 203 | 38 | **0** ⚠ | Low | 12 |
| 13 | Documents | 349 | 38 | 16 | Low | 13 |

⚠ = flagged in this review as needing action beyond a pure retokening pass (Approvals' query anomaly, Risks' zero coverage).

---

*Prepared 14 August 2026. Every query count, LOC figure, and test count in this document was measured directly against the current codebase and a running instance — none carried forward from docs/33/34 without being either cited explicitly or re-verified. No application file was modified producing this review.*
