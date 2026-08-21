# Repository Stabilization — Closeout Report (2026-08-21)

**Branch:** `cursor/soft-command-phase1` · **Status:** Stabilization commits (Phases B–F plus the date-drift fix) complete and verified green. Not pushed. Phase G (this document) and final verification below.

This is the operational record for the repository-stabilization pass that took 187 uncommitted paths (46 commits ahead of main at the start) and committed them as a reviewed, tested commit series, plus the tooling (Telescope/Larastan/PHPStan/Lighthouse/Axe) that pass added. It complements `docs/43-event-hub-command-header-e3-consolidation.md`, which covers the Event Hub shell architecture specifically — this document covers the stabilization process itself: what got committed, in what order, what tooling now exists and how to run it, what's deliberately still off, and what's left.

## 1. Commit reference

In order, from the first dead-code cleanup to the last fix landed:

| Commit | Summary |
|---|---|
| `8278dce` | chore: remove confirmed dead UI and support code |
| `75030ca` | chore: remove additional dead component missed in initial sweep |
| `d6c1f53` | style: establish V7 shared tokens and components (C1) |
| `cc913ef` | style: modernize Event Hub shell and shared surfaces (C2) |
| `81851df` | style: migrate Event Hub modules to V7 (C3) |
| `1b8f93e` | style: migrate platform workspaces to V7 (C4) |
| `84d6701` | test: update Attendees and Audit Log assertions for V7 Hub layout |
| `17c6b92` | fix: standardize JOD money precision to three decimals (Phase D) |
| `54fb9be` | fix(guest-layout): point CheckIn and Registration at the V7 guest shell |
| `e67d6f3` | fix(money): complete three-decimal precision on remaining save paths |
| `310ddc8` | fix(risks): support click-to-select and click-to-deselect on risk rows |
| `b5af929` | fix(speakers): eager-load sessions to avoid N+1 queries in the agenda hint |
| `7e011c5` | feat(hub): complete Universal Module Inspector coverage for every module |
| `70694b1` | test: update Transport Workbench assertion for V7 Transport Control panel |
| `a79eec8` | feat(tooling): add Laravel Telescope and Larastan/PHPStan |
| `8200762` | feat(tooling): add Lighthouse and Axe accessibility/performance auditing |
| `d7a0b2c` | test: freeze time in ContractPaymentTest to stop calendar drift |

The safety checkpoint `wip/pre-stabilization-snapshot-2026-08-20` still exists and was never touched. Nothing in this range has been pushed to `origin` — `origin/cursor/soft-command-phase1` is still at `22c66ae`, a much earlier commit; this entire sequence is local-only.

## 2. Telescope — operating instructions and gate status

**What it is:** local request/query/exception debugging (`/telescope`, once a route prefix is reachable).

**Access control, verified:**
- `app/Providers/TelescopeServiceProvider.php`'s `gate()` defines `viewTelescope` as `in_array($user->email, [])` — an empty array. In any non-local environment, **no one** can open the dashboard until a real email is added to that array. This is Laravel's own stock stub, left as-is deliberately: locked-down-by-default rather than open-by-default.
- In local (`app()->environment('local')`), Telescope's own `Authorize` middleware permits access unconditionally — normal local-dev behavior.
- `'enabled' => env('TELESCOPE_ENABLED', true)` — data collection defaults on in every environment unless overridden. No `TELESCOPE_ENABLED` entry exists in `.env.example` today. **Before any non-local deploy**, either add `TELESCOPE_ENABLED=false` to that environment's `.env`, or confirm the empty gate is intentionally left empty (nobody should see the dashboard) — don't assume one or the other, decide it explicitly.
- Watchers are filtered by environment in `register()`: full recording in local; in every other environment, only exceptions, failed requests, failed jobs, scheduled tasks, and explicitly-tagged entries are recorded — not full query/request logging.

**Running it:**
```bash
php artisan migrate   # creates telescope_entries / telescope_entries_tags / telescope_monitoring, already applied locally
```
Visit `/telescope` while `APP_ENV=local`. No further setup needed.

**Data:** stored in the app's own database connection (`config('telescope.storage.database.connection')`, defaults to the app's `DB_CONNECTION`). `tests/Feature/TenantColumnCoverageTest.php` exempts Telescope's three tables from the tenant-column-coverage check — they hold the app's own debug data, not tenant/customer data.

## 3. Larastan / PHPStan — operating instructions and disabled status

**Current state: Larastan's Laravel-aware rules are NOT active.** `phpstan.neon`'s own header comment documents why:

```
## Larastan (vendor/larastan/larastan/extension.neon) is installed but currently
## causes `phpstan analyse` to exit silently with no output in this environment —
## see the "Larastan Diagnosis" section of the audit-stack report for what was
## ruled out (bootstrap.php, cache, DB/migration scan, minimal config, opcache).
## Re-enable by uncommenting the include below once resolved, then confirm
## `vendor/bin/phpstan analyse` prints real output before relying on it.
#includes:
#    - vendor/larastan/larastan/extension.neon
```

`bootstrap.php` (Larastan's own app-container bootstrap, needed for it to resolve facades/models/container bindings during analysis) is wired and present, but the extension include itself is commented out because enabling it was observed to make `phpstan analyse` exit silently with no output at all — worse than not running. Ruled out during earlier diagnosis (not this pass): the bootstrap file itself, PHPStan's own cache, DB/migration scanning, a minimal config, and opcache. Root cause is still open.

**Practical effect:** `phpstan analyse` runs today at plain level 5 against `app/`, with no Eloquent-aware type inference. This produces a large number of false positives on Eloquent magic properties and relations (`Access to an undefined property App\Models\Event::$name`, etc.) that Larastan would normally resolve — these are not real bugs, they're the absence of Larastan's model reflection.

**Current result** (this pass, `vendor/bin/phpstan analyse`): **2,206 errors**, dominated by `property.notFound` on Eloquent models (undefined magic properties) and a smaller set of genuine-looking `argument.type`/`return.type` findings mixed in. No attempt was made to triage which of the 2,206 are real during this stabilization pass — that's real work gated on either fixing the Larastan silent-exit issue (so the false-positive class disappears) or manually baselining/triaging 2,206 findings by hand, neither of which is a stabilization-commit task.

**Running it:**
```bash
vendor/bin/phpstan analyse --no-progress
```
Result cache lives in `storage/framework/phpstan/` — gitignored (added this pass; it was untracked and un-gitignored before, a 6MB+ working-tree file nobody meant to track).

## 4. Lighthouse / Axe — operating instructions

```bash
npm run audit:lighthouse   # runs scripts/audit-lighthouse.mjs against local URLs, writes JSON to storage/audits/lighthouse/
npm run audit:a11y         # runs @axe-core/cli
```

`scripts/audit-lighthouse.mjs` drives the system's own Google Chrome binary directly rather than `@lhci/cli`'s bundled launcher — the bundled launcher hit a sandbox-related Chrome connection failure on the machine this was authored on (documented in the script's own header comment). It looks for Chrome at the standard macOS path or `$CHROME_PATH`; on a machine without either, it exits with a clear message rather than a stack trace.

Verified this pass: `npx lighthouse --version` (12.6.1) and `npx axe --version` (4.13.0) both resolve, and the script passes `node --check`. A live audit against a booted dev server was not run as part of this pass — wiring and resolving the tooling was the scope, not producing a report.

**Output:** `storage/audits/lighthouse/*.json` (and wherever `axe` writes its own output) — both gitignored via `storage/audits/*`, added this pass.

## 5. Migration and rollback notes

Two migrations landed this pass:

- **`2026_08_17_072630_widen_money_columns_to_three_decimals`** — widens `event_transport.cost_cents`, `event_invoice_items.{cost_cents,sell_cents}`, `invoice_lines.unit_cents` from integer to `decimal(15,1)`, adding one fractional digit so JOD's fils (three decimals) survive on-screen and on paper. `down()` reverts each column to its original integer type — verified correct, and reversible with no data loss for any value that was ever a whole-cents integer to begin with (widening then narrowing back to the same integer type is lossless; the risk would only be narrowing *after* a fractional value had actually been saved with a fils digit, which `down()` doesn't guard against — rolling back after go-live would truncate any fils-precision values entered in the meantime). Requires `doctrine/dbal`, added to `composer.json` in `a79eec8` (this migration used `->change()` eight times without ever having declared the dependency until this pass).
- **`2026_08_19_114215_create_telescope_entries_table`** — Telescope's own stock schema (`telescope_entries`, `telescope_entries_tags`, `telescope_monitoring`), unmodified. `down()` drops all three cleanly; Telescope holds no data worth preserving across a rollback.

Both are applied on the local database (`migrate:status` confirms `Ran` for both, batches 93 and 94). Rollback for either is a normal `php artisan migrate:rollback --step=1` from the current tip if ever needed — no data migration or manual cleanup step beyond the schema change itself.

## 6. Known date-drift fix

`tests/Feature/ContractPaymentTest.php` (`d7a0b2c`). `EventContract::ensurePayments()` schedules three of an event's four contract installments as fixed offsets from the event's own `starts_at` (60/30/7 days out), not from "now". ICFT 2026's `starts_at` is a hardcoded demo date (`2026-10-19`); its 60-day-out installment fell due `2026-08-20`, which real calendar time crossed the day before this was caught (`2026-08-21`). From that point, `test_a_missed_due_date_reads_overdue_and_alerts_the_command_center` failed regardless of which installment it was actually exercising — a second, untouched installment was independently overdue too, and its alert stayed in `CommandCenterService`'s output after the test's own installment was paid off.

**Not a production bug** — `EventContractPayment::status()` and the Command Center's overdue query are both working as designed; an installment past its due date and not fully paid really is overdue. Fixed by freezing time (`travelTo()`) 90 days before the event's `starts_at` before `ensurePayments()` runs, restored in `tearDown()`. Full detail and the audit of four other test files with the same latent (but not currently triggered) `ensurePayments()` pattern is in the commit message.

**Known, not fixed:** the full suite's total assertion count is not perfectly stable run to run (4616 → 4612 → 4608 across three consecutive runs on an unchanged working tree, tests always 1091/1091 passing). Something else in the suite has a date- or state-sensitive assertion count that doesn't fail, just counts differently. Not chased down this pass — flagging as a latent instance of the same class of problem just fixed above, likely findable the same way (grep for `now()`/date-relative fixture data feeding into a loop that generates assertions).

## 7. Clarity — hold, unchanged

Still held per standing instruction. `config/services.php`'s Clarity block, `resources/views/components/clarity.blade.php`, and the `<x-clarity />` line in `app.blade.php` remain uncommitted, untouched, and not enabled. Full audit (safety with no project ID, script-injection behavior when unset, consent/privacy requirements, recommended commit group, rollback procedure) reported separately in this pass's chat transcript rather than duplicated here — summary: the component is a no-op with no ID set (the entire `<script>` block is behind `@if ($id = config('services.clarity.project_id'))`), but activating it in any real environment needs a consent/privacy decision this codebase doesn't yet implement (no cookie gate, no Clarity masking config for PII-bearing pages like registration/invoices) before `CLARITY_PROJECT_ID` is ever set anywhere but a throwaway environment.

## 8. Mobile and operational limitations (as of this pass)

Carried over from `docs/43-...md` §13, still current:
- The global icon rail leaves the Module Navigation Bar ~147px of a 375px viewport — auto-scroll-to-active-tab and an edge fade mitigate it, but a phone user still can't see more than ~2 module tabs without swiping.
- The Event Command Header itself (`hubx-header.blade.php`) has not had its own hierarchy/spacing/type-scale pass — validated in place, not refined.
- The "More" module-navigation flyout is a flat list; fine at ~12 entries, would need restructuring if that list grows materially.
- PHPStan is not currently a meaningful static-analysis gate (see §3) — don't treat a clean `phpstan analyse` run as evidence of anything until Larastan's silent-exit issue is resolved, and don't treat the current 2,206 as an actionable backlog without triage first.
- No Lighthouse/Axe baseline has been captured yet — the tooling is wired (§4) but has never been run against a real page, so there's no "before" number to regress against.

## 9. Known deferred issues

- Larastan silent-exit root cause (§3) — unresolved, pre-dates this pass.
- PHPStan's 2,206 findings — untriaged; likely mostly false positives from the above, but not confirmed file-by-file.
- Full-suite assertion-count drift (§6) — not chased down, one other date-sensitive test (or more) likely exists somewhere in the suite.
- `TELESCOPE_ENABLED` has no explicit environment-specific default documented in `.env.example` (§2) — fine for local-only use today, needs a decision before any non-local deploy.
- Lighthouse/Axe have no captured baseline (§8).
- Clarity activation is blocked on a consent/privacy decision that hasn't been made (§7) — tracked, not scheduled.
- `docs/43-event-hub-command-header-e3-consolidation.md` §13/§14's Inspector-coverage limitation and Event Command Header refinement item are now stale in one respect (coverage) and still open in the other (header polish) — see the amendment added to that document.
