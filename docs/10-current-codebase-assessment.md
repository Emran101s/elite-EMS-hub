# Current Codebase Assessment

## Health at handover time

- **901 tests passing**, 0 failing, run via `php artisan test`.
- Git history is clean: no secrets, no `.env`, no database files, no vendor/node_modules
  ever committed (verified directly — see the commit this handover produced).
- `.gitignore` already correctly covers Laravel's usual risk surface (`.env*`, sqlite/db
  files via `database/.gitignore`, `storage/framework/*` and `storage/logs/*` via nested
  Laravel-default `.gitignore` files, `vendor/`, `node_modules/`).

## Two consolidation passes just completed

**Authorization gate (Stage 1).** A mechanical test
(`tests/Unit/AuthorizationGuardTest.php`) reflects over every `app/Livewire/**` method and
fails the build if a database-mutating public method has no authorization gate. Building it
surfaced dozens of real, previously-undetected gaps across the Hub tabs and elsewhere —
these were fixed with the *correct* gate per action (not blanket-gated), verified by
checking what each file's own pre-existing sibling gate actually protected rather than
guessing. Two mistakes were made and caught by real test failures during that work
(`EventCreate::save()` was briefly over-gated to `manage-events`; `Hub/BudgetTab`'s ~26
methods were briefly blanket-gated to `manage-budget`) — both corrected. This class of bug
is now caught automatically for any future Livewire method.

**One definition per fact (Stage 2).** Three facts had each drifted apart from being
spelled out separately at every call site:

- **Money** — 21 ad-hoc formatting closures replaced by `App\Support\Money`. Found a real,
  live bug in the process: `client-record.blade.php` hardcoded `"JD "` as the currency
  label regardless of the deal/event's actual currency — both JOD and USD genuinely coexist
  in production data.
- **Dates** — 38 distinct `->format()` strings across 72 call sites in 38 files, replaced
  by `<x-date>` with four named styles. Three genuinely different formatters (input
  parsers, time-of-day-only displays) were correctly left alone rather than force-fit.
- **Status colour** — three cases where the *same* status enum was hand-rolled twice and
  had drifted into different colours on different screens: `EventContract` status
  (register vs. document editor: brand gold vs. semantic amber for the same status),
  `EventRoom` equipment status (the screen carried a second, dead, *wrong* colour array
  alongside the real one), and `EventSponsor` payment status (a generic shared badge
  component collapsed "nothing paid" and "partially paid" into the same colour). All three
  now read from a single `statusMeta()`-style method on their model. Five other files with
  their own local status-colour maps were investigated and found to be genuinely distinct
  domain vocabularies (speaker/catering/exhibition/booth-sales/proposal status) that only
  share English words — correctly left alone rather than merged.

Both passes are documented in the corresponding commit messages
(`Stage 1: gate every mutating Livewire method...`, `Stage 2: one definition for money,
dates, and status colour`) — read those for the exact file lists.

## Known, documented gaps (deferred, not hidden)

- **Performance**: the Dashboard's `dayAt()` helper does an N+1 query; `EventHealthService::RELATIONS`
  is missing an `invoices` relation, which likely means invoice data isn't eager-loaded
  into the health score computation.
- **Robustness**: no HTTP security headers configured, no soft deletes, some missing
  foreign-key indexes, authorization is gate-based rather than backed by a formal
  `EventPolicy` per model.
- **UX**: Stage 3 landed shared `<x-confirm>` / `<x-alert>` / `<x-busy>`; remaining
  `wire:confirm` call sites still migrate opportunistically.
- **Stale doc, corrected in this handover**: `CLAUDE.md` described an in-progress "ORBIT"
  design system that was actually reverted weeks earlier; it referenced a
  `App\Support\Tone` class and CSS token files that no longer exist. Fixed as part of this
  handover — see [08-design-system-rules.md](08-design-system-rules.md).

## What this means for Cursor

The codebase is in a genuinely stable, tested state, but the deferred items above are real
and known — they weren't found by this handover, they were already tracked from an earlier
audit and intentionally sequenced after Stage 1/2. Don't rediscover them and re-litigate
priority; pick them up in the order in
[09-development-roadmap.md](09-development-roadmap.md) unless the user directs otherwise.
