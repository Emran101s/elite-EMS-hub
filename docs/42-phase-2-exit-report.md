# Phase 2 — Exit Report

**Date:** 15 August 2026 · **Commits:** `8a79af5` (code) — the pilot-DB rehearsal is data, not a commit
**Scope:** everything executable now, per docs/38 and docs/40, against the approved D1–D9 decisions

**Recommendation up front:** Phase 2 is **not** closed. What follows is exactly why, and exactly what closes it.

---

## 1. Files changed

| File | What |
|---|---|
| `app/Providers/AppServiceProvider.php` | `guardPilotMail()` — dormant `Mail::alwaysTo()` safeguard |
| `config/mail.php` | new `mail.pilot_redirect` key, reads `MAIL_PILOT_REDIRECT` |
| `tests/Unit/PilotMailGuardTest.php` (new) | 4 tests, guard logic proven in isolation |

Nothing else. The pilot database build (§2 below) produced a rehearsal SQLite file in the scratchpad, outside the repo — no application file, no live database, touched.

---

## 2. Clean pilot database report

**2.3 could not run against the real target** — DigitalOcean Postgres doesn't exist yet (§6). What I did instead: ran the **exact** `pilot:export` → migrate → `pilot:import --zero-rates` → correct email → create 6 users pipeline against a **fresh, isolated SQLite file**, so the procedure itself is proven correct with real approved data before it ever touches Postgres, and you have real verified numbers today instead of a plan.

```
pilot-reference-20260815-080930.json · 29,255 bytes · sha256 ab5285174387…
```

**Final user list** (6, exactly D1):

| Name | Email | Role |
|---|---|---|
| Emran Ahmed | emran.itan@elitebhub.com | super_admin |
| Sara Al-Rashid | sara.alrashid@elitebhub.com | manager |
| Omar Nassar | omar.nassar@elitebhub.com | coordinator |
| Khalid Mansour | khalid.mansour@elitebhub.com | coordinator |
| Layla Haddad | layla.haddad@elitebhub.com | manager |
| Dana Qasem | dana.qasem@elitebhub.com | coordinator |

**Verification counts:**

| Check | Count | Required |
|---|---:|---|
| Users | **6** | 6 |
| `@example.*` / `@test.com` / `@ebh.test` accounts | **0** | 0 |
| admin/super_admin accounts | **1** | Emran only |
| Events | **0** | 0 |
| event_attendees | **0** | 0 |
| Invoices | **0** | 0 |
| Clients | **0** | 0 |
| Suppliers | **0** | 0 |
| Venues | **0** | 0 |
| Tasks | **0** | 0 |
| audit_logs | **0** | 0 |

Every line matches. No Faker accounts, no `@test.com` accounts (Rahaf/Abdulla/Mohammad correctly excluded per D2), no demo business data.

**Import/export verification:** ran clean, all 8 reference tables written (tenants, workspaces, company_profiles, taxonomy_terms_custom ×17, transport_service_types ×8, vehicle_types ×7, service_items ×19, registration_templates ×3). SQLite `PRAGMA integrity_check` on the rehearsal file: **ok**.

**Live dev database confirmed untouched:** 19 users, 20 events — unchanged throughout. `git status --short` before this work: clean.

---

## 3. Company profile verification

| Field | Value |
|---|---|
| Rows | 1 |
| Email | `notifications@elitebhub.com` ✓ (D4, corrected from the truncated original) |
| Bank accounts | present ✓ |
| Management fee | 15% |

---

## 4. User verification

Covered in §2. All 6 real, correct domain, correct roles, zero contamination. Rahaf, Abdulla and Mohammad remain excluded — no real addresses have been supplied for them (D2 still open, not blocking).

---

## 5. SMTP readiness report

**What's now built and verified:** the pilot-week `Mail::alwaysTo()` redirect guard (§ code changes above) — dormant on every host today, activates only under `APP_ENV=production` **and** `MAIL_PILOT_REDIRECT` set. 4 tests prove both halves of that gate independently.

**What's confirmed still fully blocked:** local `.env` — `MAIL_MAILER=log`, `MAIL_FROM_ADDRESS="hello@example.com"` — untouched, unchanged, exactly as it must stay until 2.3 completes for real.

**DNS records ready to publish** (from docs/40, restated here since they don't depend on any credential):

| Record | Type | Value |
|---|---|---|
| SPF | TXT @ | `v=spf1 include:spf.protection.outlook.com -all` |
| DMARC | TXT `_dmarc` | `v=DMARC1; p=none; rua=mailto:notifications@elitebhub.com` |
| DKIM | 2 × CNAME | **Cannot be prepared without M365 admin access** — the two selector values are tenant-specific, generated only inside the Exchange admin centre |

**Outstanding — genuinely requires your action, not mine:**
- Whether `notifications@elitebhub.com` exists yet and holds a license
- Whether SMTP AUTH can be enabled for it (needs the actual `Set-CASMailbox` run, and a check of whether Security Defaults/Conditional Access blocks it — docs/40 §4 gives the exact `openssl` test)
- The two DKIM CNAME targets from the M365 admin centre
- `MAIL_PASSWORD` — never to be typed into this chat regardless

**Pilot mail routing strategy:** confirmed — `alwaysTo()` first (week 1), explicit allow-list after (week 2+), exactly as decided.

---

## 6. PostgreSQL readiness/deployment report

**Cannot be executed** — no DigitalOcean account, API token, or connection credentials exist in this session, and per your explicit instruction I have not assumed API access or created any billing-linked resource.

**What was actually checked, honestly:**

| Item | Status |
|---|---|
| CI `tests_pgsql` job | **Green** — reconfirmed on the 3 most recent commits (`14e0380`, `3308ef2`, `e5ea62c`), all `success` |
| `scripts/db-backup.sh` pgsql branch | **Reviewed by reading, not executed.** `pg_dump -h -p -U -d --no-owner --format=plain`, piped to gzip, password via `PGPASSWORD`. Structurally correct. One minor gap: no explicit `sslmode=require` — `pg_dump`'s default (`prefer`) will still connect successfully against a managed instance, just without verifying the server certificate. Worth a `PGSSLMODE=require` addition when this is actually run, not a blocker. |
| `scripts/db-restore.sh` pgsql branch | **Reviewed by reading.** `psql -v ON_ERROR_STOP=1`, handles both gzipped and plain dumps. Structurally correct. |
| Connection, migration, pilot DB creation, restore drill, smoke tests | **All require the actual instance.** None can be attempted without it. |

**Outstanding — yours to do per docs/40 §2:** provision the cluster (console click-path already written), then hand me host/port/database/username/password (via password manager, never chat) so 2.3's rehearsal above can be re-run for real, and the backup/restore/smoke-test sequence in docs/38 §3.3 can actually execute.

---

## 7. Backup and restore status

| Layer | Status |
|---|---|
| Demo SQLite snapshot (2.1) | ✓ closed, previously verified |
| SQLite restore drill (2.2) | ✓ closed, previously verified |
| Pilot-reference export/import round-trip | ✓ **re-verified today** on fresh data |
| Postgres backup | Not executable — no instance |
| Postgres restore | Not executable — no instance |

---

## 8. Test results

**1091 / 1091 passing** (4 new — the mail-guard tests). Contract suite separately: 98/98 (unaffected by today's changes, re-confirmed clean).

## 9. Build results

Pint: clean on every file touched today. No `npm run build` needed — no frontend asset changed this session.

---

## 10. Outstanding blockers

| # | Blocker | Whose action |
|---|---|---|
| 1 | DigitalOcean Postgres not provisioned | **You** — manual console provisioning, per your own instruction |
| 2 | Postgres connection credentials | **You**, once provisioned — via password manager |
| 3 | M365 mailbox existence/license/SMTP AUTH status unconfirmed | **You / your M365 admin** |
| 4 | DKIM CNAME values | **You** — only obtainable from the M365 admin centre |
| 5 | SMTP password | **You** — must go directly into a host `.env`, never through chat |
| 6 | Rahaf/Abdulla/Mohammad real addresses (D2) | **You** — not blocking, deferred by design |

Nothing on this list is something I can resolve by writing more code or running more commands — all six are account-state or credential facts that only exist outside this session.

---

## 11. Phase 2 completion percentage

**~75%.**

| Sub-phase | Status |
|---|---:|
| 2.1 Snapshot | 100% |
| 2.2 Restore drill | 100% |
| 2.3 Clean pilot DB | **~85%** — full pipeline proven correct with real data on SQLite; final step (re-run against real Postgres) blocked on #1–2 |
| 2.4 SMTP | **~50%** — code-side safeguard done, DNS text ready; mailbox/DKIM/password all blocked on #3–5 |
| 2.5 Postgres | **~20%** — scripts reviewed, CI proven; everything else blocked on #1–2 |
| 2.6 Green baseline | 100% |
| 2.7 CI gate | 100% |

---

## 12. Recommendation

**Phase 2 is not closed.** What's left is not more of my work — it's six pieces of account-state information that live outside this session, listed exactly in §10. Once DigitalOcean credentials and M365 mailbox status land, the remaining steps are short (docs/38 §3.1/§3.3 are already written as literal runbooks) and can run to completion in the same session they arrive in.

What today's session adds concretely toward closing it: the pilot-week mail safeguard is built and tested (not just planned), and the exact 2.3 pipeline has been run end-to-end on real approved data with every requested number verified — so when Postgres exists, standing up the real pilot database is a rehearsed procedure, not a first attempt.

Not proceeding to Phase 3. Waiting on the six items above.
