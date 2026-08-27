# Deployment hardening — pilot and shared hosts

**Phase 1 of the master roadmap (`docs/34`).** This is the checklist for any host
other than a developer's own machine.

`.env` is gitignored, so none of the values below travel with the repository.
They have to be set on the host. This document is the thing that travels.

---

## The one-paragraph version

Three environment values and one composer flag separate a safe deployment from
one where anybody who can reach the URL becomes a super-admin in a single GET
request. Set `APP_ENV=production`, `APP_DEBUG=false`, install with
`--no-dev`, and leave `MAIL_MAILER=log` until the clean pilot database exists.

---

## 1. The critical one: Laravel Dusk's login route

`laravel/dusk` registers `GET /_dusk/login/{userId}` which calls
`Auth::guard()->login($user)` with **no credential check**, and accepts an email
address as the identifier. It is a total authentication bypass.

Dusk registers these routes whenever the environment is **not** `production`.

### ⚠️ `APP_ENV=staging` does NOT protect you

Measured on this codebase, 13 August 2026:

| `APP_ENV` | `_dusk/*` routes registered | `/concept`, `/design` |
|---|---:|---:|
| `local` | **3** | 4 |
| `staging` | **3** ⚠️ | 0 |
| `production` | **0** ✓ | 0 |

A pilot host set to `staging` — which is the natural instinct for a pilot —
**still carries the bypass.** Only `production` removes it.

### Two independent defences. Use both.

1. **`APP_ENV=production`** — stops Dusk's provider registering the routes.
2. **`composer install --no-dev --optimize-autoloader`** — Dusk is a
   `require-dev` package, so it is not installed at all and the provider is
   never autoloaded. This is the stronger defence because it does not depend
   on an environment variable being right.

### Verification (run on the host, after deploy)

```
curl -i https://<host>/_dusk/login/1
```

**Required result: `HTTP/1.1 404 Not Found`.**

If it returns 200, stop and do not let anyone else reach the host.

Also check:

```
php artisan route:list | grep _dusk     # must print nothing
```

---

## 2. `APP_DEBUG`

| Environment | Value | Why |
|---|---|---|
| Developer machine | `true` | Stack traces are the point |
| Pilot / shared host | **`false`** | A trace exposes file paths, env values and query fragments to whoever triggered the error |

Verify by triggering a 404 or 500 on the host and confirming a generic error
page rather than Ignition's trace view.

---

## 3. Design prototypes and component galleries

`/concept/flow`, `/concept/nav`, `/design/soft-command`,
`/design/soft-command-shell` are now **gated to `local` only** in
`routes/web.php`.

They were kept rather than deleted because they are genuinely useful reference
while the remaining modules are converted onto `eo-*` (roadmap Phase 4). They
render real event records in an unreviewed layout, which is fine on a laptop and
not fine on a host a colleague can reach.

Gated on `local` rather than `!production` deliberately — see the staging table
above. No test exercises them; tests run as `testing`.

**No action needed on the host.** They disappear automatically anywhere that is
not a developer machine.

---

## 4. `/operations-room` — deliberately kept

The enterprise audit (`docs/33`) listed this as a dead route. **That was wrong,
and this document corrects it.**

It is `Route::redirect('/operations-room', '/')` — a compatibility redirect for
bookmarks and old links from when the Operations Room was a second dashboard. It
is auth-gated, renders nothing, exposes nothing, and `CommandCenterTest` asserts
the redirect. Removing it would turn saved links into 404s for no security gain.

**Keep it.**

---

## 5. Mail — deliberately held

**Do not enable SMTP yet.** `MAIL_MAILER` stays `log` until roadmap Phase 2.3
delivers the clean pilot database.

The current database contains seeded demo users on **real `@elitebhub.com`
addresses** (Emran, Layla, Omar, Sara, Khalid, Dana). Four flows send mail —
`ApprovalRequested`, `ApprovalDecided`, `RegistrationConfirmed`, `TeamInvite`.
Enabling SMTP before the demo users are gone means real colleagues receive
approval notifications about fabricated events.

Also outstanding, to be fixed in Phase 2.4 rather than now:

- `MAIL_FROM_ADDRESS` is `hello@example.com` — a placeholder. Any mail sent from
  it will look spoofed and will likely be rejected by receiving servers.

**Order is: clean database first, then real `MAIL_FROM`, then SMTP, then a
recipient allow-list for the first two weeks.**

---

## 6. Host checklist

Copy this to the host and work down it.

```bash
# 1. Install without dev dependencies
composer install --no-dev --optimize-autoloader

# 2. Environment
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<real-host>        # not localhost
MAIL_MAILER=log                    # HELD until Phase 2.3

# 3. Verify the bypass is gone  — must be 404
curl -i https://<host>/_dusk/login/1
php artisan route:list | grep _dusk        # must print nothing

# 4. Verify prototypes are gone — must be 404
curl -o /dev/null -w '%{http_code}\n' https://<host>/design/soft-command

# 5. Verify debug is off
#    trigger any error; confirm no stack trace
```

---

## 7. What this phase deliberately did **not** do

- Did not change `.env` on the development machine. `APP_ENV=local` and
  `APP_DEBUG=true` are correct there, and the roadmap explicitly warned against
  breaking local development to satisfy a production concern.
- Did not enable SMTP (§5).
- Did not create the pilot database, cut over to Postgres, or touch
  performance, UI or any module. Those are Phases 2 and 3.

---

## 8. Open decision for Emran

**Should `laravel/dusk` be removed from the project entirely?**

Evidence: one browser test (`tests/Browser/DockAndModalTest.php`), CI does not
run Dusk at all, and the package's only other contribution to this codebase is
the authentication-bypass route above.

- **Remove it** — the bypass stops being a deployment concern permanently, and
  one unused test file plus `DuskTestCase.php` goes with it.
- **Keep it** — if browser-level testing is intended to grow later.

This is a product decision, not a configuration one, so Phase 1 left it alone.
The `--no-dev` defence above holds either way.

---

*Verified by direct measurement on 13 August 2026 against commit `89d5d5a`:
route registration counts per `APP_ENV`, and live HTTP responses from a
temporary server running `APP_ENV=production APP_DEBUG=false`. The `--no-dev`
defence is by package design (`laravel/dusk` is `require-dev`) and was not
separately executed, since doing so would have torn down the development
environment.*
