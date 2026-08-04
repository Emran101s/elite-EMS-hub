# Cursor Handover

## Read this first, before changing any code

This repo has been through two disciplined hardening passes right before this handover
(authorization gating, then "one definition per fact" for money/dates/status colour — full
detail in [10-current-codebase-assessment.md](10-current-codebase-assessment.md)). The
patterns those passes established are the ones to follow, not rediscover or re-litigate:

- Money → `App\Support\Money`. Dates → `<x-date>`. Status colour → the model's own
  `*Meta()` method or `<x-status-badge>`. Never a local formatter/colour map in a view.
- Every database-mutating Livewire method needs an authorization gate — the test suite
  enforces this and will fail the build if you add one without.
- Don't redesign the sidebar, the navigation, or existing screens. Don't start
  multi-tenancy. Both are explicit, standing instructions, not just "not yet gotten to".

**Cursor should read the rest of `/docs` before making any structural or architectural
change.** The numbered files cover product vision, architecture, the target command-center
IA, the event life cycle, the approval engine, portal strategy, roles/permissions, the
design system, the roadmap, the codebase assessment, and the git workflow this repo
actually uses.

## Opening the project in Cursor

1. Clone or open the existing local checkout at `~/Herd/elitehub` (do **not** confuse it
   with `~/Herd/emranspace` — that's a separate, earlier platform and should never be
   touched from this project).
2. Check out the branch this handover was committed on (see the commit this docs change
   landed in for the exact branch/hash — reported at handover time in chat).
3. `composer install` and `npm install` if either hasn't been run in this checkout, then
   `php artisan test` to confirm the same green baseline reported in
   [10-current-codebase-assessment.md](10-current-codebase-assessment.md).
4. The app is served by Herd at `http://elitehub.test`; the Vite dev server runs separately
   (`npm run dev`) for asset watching.

## A good first prompt for Cursor

```
You're picking up work on Elite Business Hub, a Laravel 13 + Livewire 4.3 + Tailwind v4
event operations platform.

REPO IDENTITY — READ THIS FIRST
- Local path: /Users/emranalitan/Herd/elitehub
- GitHub remote: https://github.com/Emran101s/elite-EMS-hub.git
- Branch: ops-modules-and-design-system
- Served by Herd at http://elitehub.test, dev server on 127.0.0.1:8912

There is a DIFFERENT, separate, earlier project on this same machine at
/Users/emranalitan/Herd/emranspace (served on port 8913 when running). It is not this
project, has no shared code or history with this one, and must never be read, edited, or
used as a reference. If you find yourself looking at anything under ~/Herd/emranspace or
anything from localhost:8913, stop — you're in the wrong project.

BEFORE YOU CHANGE ANYTHING
Read the /docs folder in this repo, in order, starting with:
- docs/09-development-roadmap.md — what's built, what's not, what's next
- docs/10-current-codebase-assessment.md — current health, known gaps, what was just fixed
- docs/08-design-system-rules.md — the live design system and the conventions below
- CLAUDE.md at the repo root — the short version of all of this

Then read whichever other numbered doc is relevant to the task you're given
(01-product-vision through 12-cursor-handover).

HARD RULES — DO NOT BREAK THESE
- Do not redesign the sidebar. Do not redesign the navigation. Do not redesign existing
  screens. This is a standing instruction, not "haven't gotten to it yet."
- Do not start multi-tenant SaaS work. It's a deliberately deferred later phase.
- Money formatting always goes through App\Support\Money
  (forScreen / forDocument / abbreviated) — never a local number_format()/currency closure
  in a view.
- Dates always render through <x-date :value="..." style="short|document|long|withTime"> —
  never a local ->format('...') call in a view.
- Status colour comes from the owning model's own statusMeta()-style method
  (see EventContract, EventRoom, EventSponsor for the pattern) or the shared
  <x-status-badge> for genuinely generic statuses — never a new local colour map hand-rolled
  in a view. Two real bugs already came from breaking this rule once; don't reintroduce it.
- Every database-mutating Livewire method must call an authorization gate
  (Gate::authorize / $this->authorize). tests/Unit/AuthorizationGuardTest.php reflects over
  every app/Livewire/** method and fails the build if one is missing. Default new mutating
  actions to the write gate (coordinator+) unless it's specifically a financial, contract,
  approval, or team-access decision — those have their own named gates at manager/admin.
- Plan Studio (the Planning module) has its own deliberate visual identity and is excluded
  from platform-wide restyles — don't bring it into line with anything else as a side
  effect.

WORKING DISCIPLINE
- Run `php artisan test` before starting and after every change. The baseline is 911/911
  passing — don't leave it lower than you found it.
- If you touch any CSS or resources/js/app.js, run `npm run build` before calling the work
  done. public/build is gitignored and doesn't rebuild itself — the project owner views the
  app at elitehub.test, which serves whatever public/build currently contains, not your
  source edits directly and not Vite's own dev server. A change that's correct in source
  and even correct under `npm run dev` can still be completely invisible to them until this
  runs.
- Investigate before consolidating or refactoring shared-looking code: some things that
  look duplicated across files are actually distinct domain concepts that only share an
  English word (see docs/10-current-codebase-assessment.md for real examples of both kinds
  found in this codebase). Don't force-fit.
- Commit messages should explain why, not just what — look at the last few commits on this
  branch for the expected level of detail.
- Never force-push, never git reset --hard, never delete a branch, without explicit
  approval each time.

FIRST THING TO DO
Before making any change, tell me: which files you read, what you understood the current
state to be, and what you're about to do — then wait for me to confirm before touching
code, if the task is non-trivial.
```

## Addendum for UI / design / polish sessions specifically

UI and polish work has failure modes the general prompt above doesn't cover on its own.
Hand Cursor this in addition when the task is visual/design work rather than a feature build:

```
ADDENDUM — for UI/design/polish work specifically

Before touching any screen, open resources/css/app.css and read the existing token scale
(--color-navy-50 through --color-navy-900, the gold accent, the Playfair Display setup).
Extend from these. Do not introduce a new colour, spacing value, radius, shadow, or
font-size that isn't already a token or a clear step on the existing scale — if something
needs a new value, that's a decision to flag, not to invent silently.

Scope boundary for "polish": tightening spacing, fixing alignment, improving a hover/focus
state, cleaning up an empty state, making a table denser or a card layout breathe better —
yes. Reflowing a screen's structure, changing which components exist, touching the sidebar
or top-level navigation — no, that's a redesign, not polish, and it's explicitly off
limits regardless of how it's framed.

Plan Studio (the Planning module) is the one deliberate exception — it already has its own
distinct visual identity on purpose. Leave it alone even under a "make everything
consistent" instruction.

Test every screen you touch at both density extremes, not just whatever data happens to be
loaded: an empty draft-stage event (most modules have nothing in them yet — that's normal,
not a bug to hide) and the dense end — see docs/02-platform-architecture.md's "Real data
volumes" table for the current verified numbers (don't hardcode them here too; that table
is the one place they live). A layout that only looks right at one end isn't done.

AFTER EVERY CSS/JS CHANGE, REBUILD — THIS IS NOT OPTIONAL
public/build (the compiled assets Herd actually serves at elitehub.test) is gitignored —
it only exists locally, and it does not update itself. Blade template changes render live,
no build needed. But any Tailwind class or resources/js/app.js change needs
`npm run build` run afterward, or it will not appear at elitehub.test even though the
source file is correctly changed and committed. If you're verifying through Vite's own dev
server (`npm run dev`) instead of through elitehub.test directly, your changes can look
correct to you while staying completely invisible to the project owner, who only ever
looks at elitehub.test. Run `npm run build` after every session that touches CSS or JS,
not just at the end of the day — the owner may check in mid-session.

Contracts and the Event Brief run bilingual English/Arabic, right-to-left, in the same
document. Any polish touching those views needs to be checked in both directions, not just
English.

The ~17 PDF documents (contracts, invoices, driver sheets, etc.) are deliberately allowed
to look different from the on-screen UI — they're meant to read like company stationery,
not like a screenshot of the app. Don't "fix" that inconsistency by unifying their styling
with the screens; it's intentional.

Status colour, money, and date formatting are centralized (App\Support\Money, <x-date>,
each model's statusMeta()) specifically because scattered local copies drifted into real
visible bugs before. If a polish pass touches how a status pill or a price looks, change it
at the shared source, not by patching the one screen you're looking at.

Screenshot before/after for anything visual, and show them side by side rather than just
describing the change.
```

## Warnings before continuing development

- `CLAUDE.md` at the repo root was stale before this handover (it described a reverted
  "ORBIT" design system) and has been corrected as part of this same change — see
  [08-design-system-rules.md](08-design-system-rules.md) for what changed and why.
- `orbit-system.html` and `setup-orbit.sh` are inert leftovers from the reverted attempt.
  Nothing generates from them anymore; they're safe to delete later but weren't removed as
  part of this handover since deletion wasn't requested.
- The repo's `.gitignore` and git history were checked directly as part of this handover —
  no secrets, `.env` files, or database dumps are or were tracked. See
  [11-github-workflow.md](11-github-workflow.md) for the safe-commit conventions to keep
  following.
- Multi-tenancy, Supplier Portal, Client Portal and a notification system are all
  deliberately **not built yet** — see [01-product-vision.md](01-product-vision.md) and
  [09-development-roadmap.md](09-development-roadmap.md). Treat any code that looks like it
  half-implements one of these as either dead/experimental or as scope to discuss with the
  project owner first, not as a foundation to build on silently.
