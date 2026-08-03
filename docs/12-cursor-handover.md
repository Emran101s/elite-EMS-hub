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
Read /docs in this repo before doing anything, especially 08-design-system-rules.md,
09-development-roadmap.md, and 10-current-codebase-assessment.md. This is Elite Business
Hub, a Laravel 13 + Livewire 4 event operations platform. Do not redesign the sidebar,
navigation, or existing screens. Do not start multi-tenant SaaS work. Money formatting
goes through App\Support\Money, dates through <x-date>, and status colour through each
model's own *Meta() method or <x-status-badge> — never a new local formatter in a view.
Run php artisan test before and after any change. Tell me what you're about to do before
you do it if it touches authorization, navigation, or the design system.
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
