# Elite Business Hub — Implementation Inspection

**Date:** 8 August 2026 · **Inspector:** Claude (worktree `elitehub-claude`)
**Against:** [`18-phase1-platform-audit.md`](18-phase1-platform-audit.md) · [`19-internal-improvement-plan.md`](19-internal-improvement-plan.md)
**Method:** every claim below was checked against the running application and the
source — not read off the plan. Where I clicked a screen, I say so. Where I only
read code, I say that too.

---

## 1. Why this document exists

`docs/19` was written as a forward plan. Several of its P0 and P1 items describe
work that **was already built** before the plan was written, and a few describe
work that is **still genuinely missing**. Acting on the plan as written would
have meant rebuilding two finished modules and skipping four real gaps.

This report separates the three cases, with evidence, so the plan can be trusted
again. §7 lists the exact corrections `docs/18` and `docs/19` need.

**The one-line finding:** the platform is further along than the plan says on
*module UX*, and further behind on *workflow handoffs*. Polish is not the
constraint. The joins between modules are.

---

## 2. Platform scale (measured, 8 Aug 2026)

| | Count |
|---|---|
| Eloquent models | 59 |
| Livewire components | 58 |
| Migrations | 120 |
| Routes | 100 |
| PDF document controllers | 23 |
| Automated tests | **1,043** (all green, sqlite + pgsql) |
| Test files | 120 |

CI enforces three required checks on `main`: `Test suite`, `Static checks`,
`Test suite (pgsql)`.

---

## 3. P0 scorecard

`docs/19` §11 "Priority order when starting fresh", item by item.

| # | P0 item | Verdict | Evidence |
|---|---|---|---|
| 1 | Hide nav ghosts | ✅ **Already done** | No Messages / Guests / Assets / Help entries exist in `command-spine.blade.php`. Nothing to hide. |
| 2 | Invite / set-password + forgot-password | ✅ **Built 7 Aug** | PR #27. `ForgotPasswordController`, `ResetPasswordController`, `TeamInvite` notification, routes `password.request/email/reset/update`. Verified end-to-end in browser: submitted the form, read the real link out of `laravel.log`, followed it to the reset screen. |
| 3 | CRM Draft-proposal CTA | ❌ **NOT built** | `ProposalsDesk::draftFor()` exists, but `crm-pipeline.blade.php` contains **no link to proposals at all**. The handoff works only in the reverse direction (the Proposals desk lists deals awaiting an offer). A salesperson working the board cannot start an offer from it. |
| 3b | Soft win gate | ❌ **NOT built** | `CrmPipeline::moveTo()` — dragging to **Lost** opens a reason prompt; dragging to **Won** calls `DealPipeline::win()` immediately, with no check for an accepted proposal. See §5 finding **F1** — this now has a money consequence it did not have when the plan was written. |
| 3c | Proposal accept → seed budget | ✅ **Built 7 Aug** | PR #29. `BudgetSync::syncProposal()` seeds one linked budget line per non-optional proposal line, carrying `sell_cents`. Optional lines correctly excluded. Idempotent. |
| 4 | Dashboard + Overview next-steps / module readiness | ⚠️ **Half done** | Event Overview: ✅ built 7 Aug (PR #31) — seven module-readiness doors, states driven by `EventHealthService`'s own components so they cannot disagree with the health tile. Portfolio Dashboard: ❌ **not role-weighted** — `Dashboard.php` contains no `role` or `isAtLeast` branching; a CEO and a coordinator see byte-identical pages. |
| 5 | Hub primary strip = daily ops | ✅ **Built 7 Aug** | PR #32. Strip is now exactly Overview · Tasks · Agenda · Venue · Transport · Budget · More. Displaced tabs rebucketed into existing `More` families. Verified in browser. |
| 6 | Agenda builder UX pass | ✅ **Not needed — already at the bar** | See §4. |
| 7 | Transport Live/Dispatch UX pass | ✅ **Not needed — already at the bar** | See §4. |

**Score: 4 built, 2 already-done, 1 half, 3 genuinely missing.**

---

## 4. The two "UX pass" modules — inspected, not assumed

`docs/19` singles out Agenda and Transport for dedicated UX passes and makes
them the exit criteria for Sprint 2. **Both already meet the standard the plan
describes.** I checked each against the plan's own §3.1 quality bar, on real
seeded data and on a genuinely empty event.

### Agenda — `events/7` (41 sessions, 5 days) and `events/9` (empty)

| Plan asks for | Found |
|---|---|
| Builder clarity, day/session hierarchy | Day tabs with per-day readiness rings; Timeline ⇄ Programme toggle |
| Room timeline | Room-based timeline grid, drawn to scale, plus a searchable room rail with capacity and session count per room |
| Conflict cues | `AgendaTab::detectConflicts()` catches **both** double-booked rooms **and** double-booked speakers; surfaced per-room and per-session |
| Empty states that teach | *"Nothing scheduled for this day / Add a session and it'll plot on the timeline here"* + primary **＋ Add Session** — exactly the icon-sentence-CTA pattern §3.1 specifies |
| PDF pack | **Four** already exist: Timeline, Programme, Master Schedule, Run of Show |

The Add Session modal is well-composed: title, type with free-text override,
format toggle, status with explanatory helper text, optional capacity with a
clear default.

### Transport — `events/14`, Live and Dispatch

| Plan asks for | Found |
|---|---|
| Movement board clarity | Dispatch board with time axis, By-driver ⇄ By-vehicle toggle, drag-to-reassign, clash badge, dedicated unassigned lane, colour legend |
| Live/Dispatch glanceability | Live clock; *"2 runs with no driver"* warning surfaced above the fold; Now/Next grouping |
| Status progression | Large primary **On the way**, red **Issue**, one-tap delay chips **+15m / +30m / +60m** — the two-tap show-day interaction the plan asks for, already built |
| PDF packs | Driver Trip Sheet, VIP Transfer Sheet, Daily Movement Schedule, Master Plan, Supplier Order |

**Recommendation: strike both UX passes from the plan.** Doing them would be
precisely the "activity that is not progress" `docs/19` warns against. Any
further Agenda/Transport work should come from a coordinator hitting real
friction, not from the plan.

---

## 5. Findings — real gaps, ranked

### F1 · Winning a deal by drag bypasses the budget seed — **highest impact**

`CrmPipeline::moveTo($deal, 'won')` calls `DealPipeline::win()` directly.
`Proposal::accept()` is the *only* path that calls `BudgetSync::syncProposal()`.

So there are now two ways to win the same deal with **different financial
outcomes**:

| Path | `Event.budget_cents` | Itemised budget lines |
|---|---|---|
| Proposal → Accept | ✅ set | ✅ seeded from the priced lines |
| CRM board → drag to Won | ✅ set | ❌ **none** |

A PM opening the second kind of event finds a budget total with nothing behind
it and no indication anything is missing. This gap is **partly created by my own
PR #29** — before it, both paths were equally empty, so the divergence is new.
Flagging it rather than leaving it for someone to trip over.

*Fix:* a soft gate on the Won lane — "This deal has no accepted proposal. Win it
anyway, or draft one first?" This closes docs/19's missing P0 **3b** and F1 in
one change, and it reuses the confirm pattern the Lost lane already uses three
lines above.

### F2 · The Suppliers tab is read-only

`resources/views/events/hub/suppliers.blade.php` has **no Livewire component, no
embedded component, and zero `wire:click` / `wire:model` / `wire:submit`**. It is
the only module tab in the hub that is purely presentational.

Consequence: inside an event, a coordinator can see who is assigned and what
state they are in, but **cannot** change a supplier's status, raise an issue,
add a note, or attach/remove a supplier. The pivot is written only by seeders.

This directly contradicts `docs/19` B4: *"Suppliers — Status + issue signal
obvious."* The signal is rendered; nothing can produce it.

*Verification:* module-by-module sweep of all 23 hub tabs; Suppliers is the sole
tab with a blade, no component, and no interactive markers. (`overview`,
`reports` and `ai` are also non-interactive — correctly so, they are read-only
dashboards. `planning` and `files` embed components.)

### F3 · 24 supplier records carry a status that does not exist

The legal vocabulary is `requested → quoted → approved → contracted →
in_production → delivered → completed`, plus `issue`.
`FlagshipEventSeeder.php:133` writes **`confirmed`**, which is not in it.

Measured: **24 of 54** supplier links (44%), all on the flagship demo event.

Two visible symptoms, side by side in the same row:

- `status-badge.blade.php` maps `confirmed` → **green "Confirmed"**
- the readiness map has no `confirmed` key, so it falls through to `?? 10` → a
  **red 10% bar**

So a supplier reads as both settled and barely-started at once, and the event's
"Avg readiness 21%" headline is dragged down by two-thirds of its suppliers
being scored as if nobody had contacted them.

This is **demo-seed data only** — no real event data is affected. But it exposes
a genuine robustness gap: nothing validates the pivot status against the
vocabulary, and an unknown value fails *confidently* (green badge) rather than
visibly. When F2's write path is built, it must validate against
`SUPPLIER_READINESS`'s own keys.

### F4 · The portfolio Dashboard is not role-weighted

`docs/19` B2 lists this P0: *"money + approvals for managers; my tasks /
movements / sessions for coordinators."* `Dashboard.php` has no role branching
at all. Everyone gets the same page.

The ops/money signal half of that item **is** built (`CommandCenterService`
surfaces overdue tasks, open risks, at-risk events). Only the weighting is
missing.

### F5 · Smaller, confirmed

- **Invoice-from-budget** (`docs/19` §5, P1) — not built. No `fromBudget` path
  on `InvoiceEditor` or `Invoice`.
- **No CD pipeline** — `.github/workflows/` holds `ci.yml` and `uptime.yml`
  only. Deployment is manual. (Cursor's backlog, noted here for completeness.)
- **Alert bell** (`docs/19` B1, P1) — resolves to `#live-alerts`, an anchor to a
  section that does exist on the dashboard. Works; it is simply not the separate
  "Signals" surface the plan imagines. Low value, safe to drop or defer.

### F6 · Checked and clear — do not re-raise

- **Risks / Speakers / Exhibition have no test file of their own**, which looks
  like a coverage hole in a directory listing. It is not: all three are covered
  inside `EventHubActionsTest`, `EventModulesTest`, `BoothSalesTest` and
  `AuthorizationGuardTest`.
- **Authorization** is mechanically enforced. `AuthorizationGuardTest` reflects
  over every public Livewire method, and fails the build if a method body writes
  to the database without `Gate::authorize`. Exemptions are individually
  documented. This is stronger than the plan's "gate every mutating method"
  asks for.
- **"Equipment vs Price List" labelling** (§5, P0) — the two surfaces are now
  distinctly named ("Equipment Catalog" vs "Invoice items · Prices for this
  event"). Reads as done; worth an owner's eye rather than a dev's.

---

## 6. What changed on `main` — 7–8 Aug

| PR | Title | Effect |
|---|---|---|
| #28 | `league/commonmark` 2.8.2 → 2.9.0 | Six advisories disclosed 6 Aug were failing `composer audit` on **every** PR. Unblocked all CI. |
| #27 | Invite / set-password + forgot-password | Closes P0 #2. New staff can now reach their own account. |
| #29 | Proposal accept → seed budget | Closes P0 #3c. Also creates F1. |
| #31 | Overview module-readiness doors | Closes the Overview half of P0 #4. |
| #32 | Hub daily-ops tab strip | Closes P0 #5. |

---

## 7. Corrections `docs/18` and `docs/19` need

To be folded in once PR #30 (`cursor/phase1-audit-internal-plan`) lands — it
holds both files and was still open with CI running at the time of writing, so
editing them here would have collided with active work.

**In `docs/19`:**

1. §11 "Priority order" items **1, 2, 3c, 5** → mark **done**, with PR numbers.
2. §11 items **6 (Agenda)** and **7 (Transport)** → **strike**, replacing with a
   line recording that both were inspected on 8 Aug and already meet the §3.1
   bar (§4 above).
3. §2.3 "CRM → Draft proposal CTA; soft win gate" → keep P0, and **raise it to
   the top** — it is now the highest-impact open item (F1).
4. §3.2 B2 "Role-weighted home" → split. The signals half is done; only the
   role weighting is open (F4).
5. §3.2 B4 Suppliers → change from a *UX* pass to a **build** item: the tab has
   no write path (F2).
6. §6 Sprint 2 exit criterion ("Programme + Logistics leads prefer hub over
   spreadsheets") → still valid as a goal, but it no longer depends on the two
   UX passes; re-point it at pilot usage.

**In `docs/18`:**

7. Any finding that describes Agenda or Transport as needing UX work → annotate
   with §4's evidence.
8. Add the Suppliers read-only finding (F2) — it is a genuine functional gap the
   audit did not catch.

---

## 8. Recommended order from here

1. **F1 + P0 3b — soft win gate on the CRM board.** One method, reuses the Lost
   lane's own confirm pattern, closes a live money-path divergence. Highest
   value per line changed.
2. **F2 — a write path on the Suppliers tab.** Status, issue flag, note. The
   only module a coordinator cannot operate.
3. **P0 3 — "Draft proposal" CTA on the CRM board.** Completes the sales handoff
   in the direction a salesperson actually works.
4. **F3 — validate supplier status, and correct the seeder.** Small; do it with
   F2 so the write path validates from day one.
5. **F4 — role-weight the Dashboard.** Larger, and the least certain: worth a
   conversation about what each role should actually see before building.

Everything below this line in `docs/19` should wait for a real coordinator
running a real event to say what hurts. Two modules have now been inspected and
found not to need the work the plan assumed. A third pass over a paper list is
lower value than one afternoon of somebody using the platform in anger.

---

*Related: [18-phase1-platform-audit.md](18-phase1-platform-audit.md) ·
[19-internal-improvement-plan.md](19-internal-improvement-plan.md) ·
[13-parallel-working-agreement.md](13-parallel-working-agreement.md)*
