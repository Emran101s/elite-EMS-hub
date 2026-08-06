# Parallel Working Agreement — Claude & Cursor

Two AI agents work on this repository. This file is the contract between them.
It exists because we have already lost work to the lack of one: on 5 Aug 2026 a
commit swept another agent's uncommitted changes into itself, and a Blade
compile error reached the branch and returned HTTP 500 in production for four
commits before anyone noticed.

**Read this before your first commit in any session.** It is the only file both
agents are guaranteed to see.

---

## 1. The rules

These are not style preferences. Each one exists because its absence cost us
something.

### 1.1 One worktree per agent, and never work on the default branch

Each agent has its **own directory**. They share one `.git`, so branches,
history and remotes are common, but `git checkout` in one has no effect on the
other.

| Agent | Directory |
|---|---|
| Cursor | `~/Herd/elitehub` (the Herd-served one, `elitehub.test`) |
| Claude | `~/Herd/elitehub-claude` |

Inside your own directory, every change goes on a branch named
`<agent>/<short-topic>` and reaches the default branch through a pull request.

```
claude/tenancy-schema
cursor/docker-postgres
```

**Why a separate directory and not just a separate branch.** `git checkout` acts
on the *working directory*, not on the agent. On 6 Aug 2026 both agents shared
one folder: Cursor checked out `cursor/phase-0-repo-safety`, and Claude's next
commit landed on Cursor's branch — because that was simply what the folder had
checked out. Minutes later the same mechanism left the folder holding the
pre-fix version of files that were already merged. Branch-per-agent cannot fix
this; only directory-per-agent can.

**Setting up a new agent worktree** (learned the hard way — each of these steps
exists because skipping it broke something):

```bash
git worktree add --detach ~/Herd/elitehub-<agent> origin/main
cd ~/Herd/elitehub-<agent>
cp ../elitehub/.env .env         # .env is gitignored, so it does not come along
composer install                  # do NOT symlink vendor — see below
npm ci && npm run build           # public/build is gitignored; without it every
                                  # page-rendering test 500s on the Vite manifest
```

Then point `DB_DATABASE` at an absolute path to the shared dev database so both
agents see the same rows:

```
DB_DATABASE=/Users/emranalitan/Herd/elitehub/database/database.sqlite
```

SQLite takes one writer at a time, so avoid both agents writing at once. Tests
are unaffected — `phpunit.xml` pins them to an in-memory database.

> **Never symlink `vendor/`.** It looks like a harmless 127 MB saving and it
> silently corrupts everything. Composer's autoloader derives `$baseDir` from
> the *resolved* path of `vendor/`, so a symlinked `vendor` maps `App\` to the
> **other agent's** `app/` directory. Tests then run against code you are not
> editing, and pass or fail for reasons that have nothing to do with your
> changes. Symlinking `node_modules/` is fine.

Verify the setup before trusting it:

```bash
php -r 'require "vendor/autoload.php";
  echo (new ReflectionClass("App\Models\Event"))->getFileName(), PHP_EOL;'
```

It must print a path inside **your** directory. Then `php artisan test` must be
green before you write a line of code.

### 1.2 Never `git add .` or `git add -A`

Stage explicit paths, always:

```bash
git add app/Models/Event.php tests/Feature/TenancyTest.php   # yes
git add .                                                     # no
```

Then run `git status` and read it before committing. If a file you did not edit
appears, stop — it belongs to the other agent.

### 1.3 Whoever writes the code writes the tests and pushes it

No handoffs of half-finished work. A change is done when:

1. it has tests that fail without it,
2. `php artisan test` is green locally,
3. it is pushed and CI is green on the branch.

Do not ask the other agent to "add tests later". Later does not arrive.

### 1.4 Stay inside your territory

Section 3 assigns directories per phase. Do not edit a file outside your
territory, even for a one-line fix. If you find a bug in the other agent's
territory, write it in `docs/14-cross-agent-notes.md` and keep moving.

### 1.5 Rebase before you push, never force-push shared branches

```bash
git fetch origin
git rebase origin/<default-branch>
```

`--force` is allowed only on your own unmerged feature branch, never on the
default branch.

### 1.6 Announce schema changes before writing them

Migrations are the one thing that cannot be merged automatically. Before adding
a migration, add a line to `docs/14-cross-agent-notes.md` saying which tables
you are touching. The other agent must not add a migration that touches the
same tables until yours is merged.

### 1.7 Never touch `~/Herd/emranspace`

Different project, unrelated. Always confirm `pwd` before a git command —
shell working directories reset between tool calls more often than you expect.

---

## 2. What CI enforces

`.github/workflows/ci.yml` runs on every push:

| Job | Checks | Blocking |
|---|---|---|
| `tests` | Full suite, 972 tests, ~3 min | yes |
| `static` | `composer audit`, Pint on **changed files only** | yes |

Notes:

- Pint is deliberately scoped to changed files. A full `pint --test` reports ~77
  legacy files; clearing that is its own commit, not a blocker on your work.
- The test job installs `chrome-headless-shell` explicitly. Browsershot
  hard-codes `headless: 'shell'`, and that binary is a separate download from
  `chrome`. Do not remove that step — 34 PDF tests depend on it.
- **Branch protection is on `main`.** Required checks: `Test suite`,
  `Static checks`. PRs required; admins are not exempt. A red run blocks merge.

---

## 3. Territory by phase

Territory is assigned per phase, not permanently. It is re-cut at each phase
boundary.

### Phase 0 — Clear the decks (2–3 days) — **START HERE**

Small, fully independent, and the point is to prove the protocol on low-risk
work before betting the tenancy migration on it.

| | Cursor | Claude |
|---|---|---|
| **Owns** | GitHub settings, `.github/`, ops scripts | `app/Models/`, `app/Http/Controllers/`, `tests/` |
| **Tasks** | Branch protection + required checks · rename default branch to `main` · automated DB backup with **one rehearsed restore** · enable Dependabot | SVG stored-XSS patch (`EventDocument::isViewable`, `EventDocumentController::view`) · `Auditable` on `User` so role changes are recorded |
| **Exit** | A failing CI run blocks a merge; a backup has been restored once | Both patches merged, each with a test |

**Cursor goes first.** Branch protection is the task that makes every later task
safe — until it is on, both agents are still pushing to one unprotected branch.

### Phase 1 — Tenancy vs. Infrastructure (weeks 1–6)

The big one. **Tenancy has exactly one owner.** A half-scoped schema is more
dangerous than an unscoped one, because it looks isolated and is not.

| | Claude | Cursor |
|---|---|---|
| **Owns** | `database/migrations/`, `app/Models/`, `app/Policies/`, `app/Providers/`, `app/Http/Middleware/`, `tests/Unit/TenancyGuardTest.php` | `docker/`, `.github/`, `config/`, `.env.*.example`, monitoring wiring |
| **Tasks** | `tenants` / `workspaces` / `workspace_user` · `BelongsToTenant` global scope on all 71 tables · move `role` from `users` to `workspace_user` · resource-based `EventPolicy` · `can:` on all 30+ export routes · **a guard test that fails the build on any unscoped model** | Dockerise (pin the Chrome version Browsershot needs) · Postgres + Redis as CI services and local containers · staging environment · Sentry + structured logging · `.env.production.example` |
| **Must not** | change infra config | **write any migration** |

The one real collision risk is Postgres. Resolution: Cursor prepares the
*environment* (containers, config, CI services); Claude writes the *schema*.
One agent owns migrations for the whole phase.

### Phase 2 — Scale (weeks 7–12)

Now genuinely parallel, split by module so file boundaries are clean.

| | Claude | Cursor |
|---|---|---|
| **Pagination** | Events, Budget, Contracts, Invoices, Approvals, CRM | Attendees, Transport, Accommodation, Agenda, Speakers, Suppliers, Venues, Documents |
| **Also** | Queued PDFs (`app/Jobs/` + PDF controllers) · composite indexes · `Auditable` across all 57 models · denormalised health score | S3 uploads with tenant-prefixed paths · server-side attendee search · Redis cache/session wiring · notification system + in-app centre |

Add a guard test for pagination in this phase, the same shape as
`AuthorizationGuardTest`: any list query without `paginate()` fails the build.

---

## 4. Daily loop

```
1  git fetch origin && git rebase origin/main
2  read docs/14-cross-agent-notes.md
3  branch:  <agent>/<topic>
4  work — inside your territory only
5  php artisan test          (green before you push, every time)
6  git add <explicit paths> && git status   (read it)
7  commit, push, open PR, wait for CI green
8  note anything the other agent needs in docs/14-cross-agent-notes.md
```

---

## 5. Order of work, and why

Sequenced by dependency, not appetite. Do not start a phase before the previous
one is complete.

1. **Phase 0** — because branch protection is what makes parallel work safe, and
   backups are the last thing between here and safe internal use.
2. **Phase 1 tenancy** — because it is cheapest now (18 users, 18 events) and
   every table added between now and then makes it bigger. Nothing commercial —
   billing, plans, a second customer — can exist before it.
3. **Phase 2 scale** — because pagination and queued PDFs rewrite the same
   queries tenancy touches. Doing them first means doing them twice.

Everything else in the audit — SSO, portals, the API, analytics, the Cvent
feature gap — waits. See `09-development-roadmap.md`.

---

## 6. When the two agents disagree

The repository is the tiebreaker, in this order:

1. a failing test,
2. this file,
3. `09-development-roadmap.md`,
4. ask the human.

Do not resolve a disagreement by editing the other agent's code. Do not resolve
it by reverting their commit. Write it down and escalate.
