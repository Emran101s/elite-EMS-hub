# Phase 2 — Completion Report

**Date:** 14 August 2026 · **Commit:** `ec5c244` · **Status:** Phase 2 open — 5 of 8 items closed
**Approved decisions applied:** company profile is production data · price-list rates non-authoritative · placeholder clients/suppliers/venues excluded · shared-mailbox SMTP · managed Postgres

---

## Status at a glance

| Item | State | Evidence |
|---|---|---|
| 2.1 Snapshot demo database | ✅ **Closed** | `elitehub-DEMO-REFERENCE-20260813-142259.sqlite`, 2,170,880 B, sha256 `e88467f0…` |
| 2.2 Restore drill | ✅ **Closed** | Non-destructive `--to` restore; integrity ok; 12 tables matched; live DB untouched |
| 2.6 Green test baseline | ✅ **Closed** | **1082 / 1082** |
| 2.7 CI gate | ✅ **Closed** | All three jobs green on `767daba`; branch protection already enforced on `main` |
| **Company-profile preservation** | ✅ **Closed — new** | `pilot:export` / `pilot:import`, round-trip proven |
| 2.3 Clean pilot database | ⛔ **Blocked** | Needs D1, D2, D4, D6, D9 |
| 2.4 SMTP | ⛔ **Blocked** | Needs D3, D8 + DNS |
| 2.5 Postgres | ⛔ **Blocked** | Needs D7 |

---

## 1. Company Profile Preservation Plan — ✅ DELIVERED AND PROVEN

Approved decision 1 required an export and a recovery strategy, and that no `migrate:fresh` depend on memory. That is now a pair of commands rather than a runbook step somebody might skip.

### 1.1 What was built

| Command | Purpose |
|---|---|
| `php artisan pilot:export` | Writes the unreproducible rows to JSON in `storage/backups/` (gitignored, `chmod 600`) |
| `php artisan pilot:import <file>` | Restores them. Idempotent by natural key, not by id — running twice corrects rather than duplicates |
| `--zero-rates` | Imports the price list with every rate at 0, so no placeholder can be mistaken for a verified price |
| `--dry-run` | Reports what would change, writes nothing |

### 1.2 What it captures

| Table | Rows | Why it is in scope |
|---|---:|---|
| `tenants` | 1 | FK parent — a fresh migrate creates none |
| `workspaces` | 1 | FK parent |
| **`company_profiles`** | **1** | **Address, logo, fee %, budget categories, ticket types, 2 bank accounts. Hand-entered. In no seeder.** |
| `taxonomy_terms` (`is_system = 0`) | 17 | Hand-added vocabulary (16 supplier categories, 1 deal source) |
| `transport_service_types` | 8 | Real operational vocabulary |
| `vehicle_types` | 7 | "Test Van" excluded |
| `service_items` | 19 | Structure only — rates are placeholders |
| `registration_templates` | 3 | Included so nothing is lost, pending **D6** |

### 1.3 Round-trip verification — the part that matters

The export was imported into a **fresh, empty, migrated database** and compared against live:

| Check | Result |
|---|---|
| `bank_accounts` (IBAN/SWIFT) | ✅ **byte-identical**, compared by SHA-256 rather than printed |
| Company name / address / website / logo path | ✅ intact |
| `default_management_fee_pct` = 15 | ✅ intact |
| Budget categories (8) / ticket types (8) | ✅ intact |
| Custom taxonomy terms | ✅ 17 / 17 |
| System taxonomy terms | ✅ 191 recreated by migration — confirming the 191 + 17 = 208 split |
| `company_profiles` after fresh migrate, before import | **0 rows** — confirming the data is genuinely unreproducible |
| Price list with `--zero-rates` | ✅ 19 items, units preserved, **0 non-zero rates** |

**The round trip also found a real defect in the first version.** Every row carries a `tenant_id` foreign key and a fresh migrate creates no tenant, so the very first insert failed with a constraint violation. Tenants and workspaces now export and import first. That failure would have happened on the real pilot host instead.

### 1.4 Recovery strategy

**Routine (pilot build):** `pilot:export` on the old database → build the new one → `pilot:import --zero-rates` → verify counts.

**Disaster (company profile lost or corrupted):** restore the most recent `pilot-reference-*.json` with `pilot:import`. It only writes the reference tables, so it is safe to run against a live database carrying real events.

**Rule going forward:** run `pilot:export` **before any destructive database operation**, and keep the newest file off the laptop. The current export is 29,255 bytes — small enough for a password manager's secure-note attachment.

### 1.5 Handling caveat

The file contains live banking details. It is written `chmod 600` into `storage/backups/`, which `.gitignore` line 33 covers — verified: it does not appear in `git status`. **It must not be emailed, put on a shared drive, or committed.**

---

## 2. Pilot Database Build Plan

Cannot execute — blocked on **D1, D2, D4, D6, D9**. The procedure is fixed and mechanical once they land.

```
 1.  ./scripts/db-backup.sh                          # fresh full snapshot
 2.  php artisan pilot:export                        # reference set + manifest
 3.  Provision managed Postgres (§4)
 4.  php artisan migrate --force                     # against Postgres
 5.  php artisan pilot:import <file> --zero-rates    # tenant, workspace, profile,
                                                     # taxonomies, catalogues
 6.  Correct company_profiles.email          ← D4
 7.  Create the 6 real users with roles      ← D1, D2
 8.  Enter verified price-list rates         ← D9   (§3)
 9.  Verify (below)
10.  Switch the application over; archive the demo snapshot
```

**Never run `DemoDataSeeder` against the pilot database.**

### 2.1 Verification gate before the pilot opens

- [ ] `users` = 6 (or as confirmed), **zero** `@example.*` / `@test.com` / `@ebh.test` accounts
- [ ] **Zero accounts with `super_admin`/`admin` that are not real staff** — 7 Faker accounts currently hold those roles
- [ ] `events` = 0 · `event_attendees` = 0 · `tasks` = 0 · `invoices` = 0
- [ ] `clients` = 0 · `suppliers` = 0 · `venues` = 0 (approved decision 3)
- [ ] `company_profiles` = 1, bank accounts present, email corrected
- [ ] `taxonomy_terms` = 208 (191 system + 17 custom)
- [ ] `service_items` = 19, **all rates 0** until D9 is applied
- [ ] `audit_logs` = 0 — the current 223 are development activity, not business history

### 2.2 Controlled price-list re-entry (approved decision 2)

Rates arrive at zero. That is deliberate: a zero is visibly wrong, a stale placeholder is invisibly wrong.

1. Export the 19 items to a sheet: code · name · category · unit.
2. A **manager** fills in the verified rate and currency for each.
3. A second person checks the sheet against a recent real quotation.
4. Enter through Settings → Price List, or re-import.
5. **Gate: no invoice may be raised until every item a quote touches has a verified, non-zero rate.**

Items likely needing rates before the pilot event: accommodation (3), transport (4), catering (3), crew (2), AV (3). The management fee is intentionally 0 — it is a percentage (`default_management_fee_pct = 15`), not a unit price.

---

## 3. SMTP Readiness Plan

Cannot execute — blocked on **D3** (mail domain) and **D8** (mailbox + M365 admin).

### 3.1 Recommended sender

Approved direction: dedicated shared mailbox, e.g. `notifications@elitebhub.com`.

⚠️ **D3 must be settled first.** Staff use `@elitebhub.com`; the company profile's website is `elitebusinesshub.com`. **These are different domains.** SPF/DKIM/DMARC must be published on whichever the `From:` uses.

### 3.2 DNS records (on the confirmed sending domain)

| Record | Type | Value | Note |
|---|---|---|---|
| **SPF** | TXT @ | `v=spf1 include:spf.protection.outlook.com -all` | One SPF record only. If one exists, **merge** — a second breaks both. `-all` after verifying. |
| **DKIM** | 2 × CNAME | `selector1._domainkey`, `selector2._domainkey` → tenant values from the M365 admin centre | Enable signing in Defender portal after publishing |
| **DMARC** | TXT `_dmarc` | `v=DMARC1; p=none; rua=mailto:dmarc@<domain>` | **Start at `p=none`** and read reports for two weeks before `quarantine` |

### 3.3 SMTP AUTH

**SMTP AUTH is disabled by default on modern M365 tenants.** It must be enabled for the shared mailbox specifically (`Set-CASMailbox -SmtpClientAuthenticationDisabled $false`), the mailbox must be **licensed**, and if security defaults or a conditional-access MFA policy is on, SMTP AUTH is refused regardless — in which case the fallback is Microsoft Graph with a Laravel transport.

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=notifications@<confirmed domain>
MAIL_PASSWORD=<app password>          # never committed
MAIL_FROM_ADDRESS=notifications@<confirmed domain>
MAIL_FROM_NAME="Elite Business Hub"
```

### 3.4 Pilot protection — `Mail::alwaysTo()`

Week 1: register in a service provider, guarded to the pilot environment —

```php
if (app()->environment('production') && config('mail.pilot_redirect')) {
    Mail::alwaysTo(config('mail.pilot_redirect'));
}
```

Every message, whatever its recipient, lands in one internal inbox. Stronger than an allow-list: nothing external can escape even if a flow is missed.

Week 2+: switch to an explicit allow-list once real attendees must receive confirmations.

Four flows to test: `ApprovalRequested`, `ApprovalDecided`, `RegistrationConfirmed`, `TeamInvite`.

**Hard order: 2.3 must verify before SMTP is enabled.** With today's database, 6 real colleagues are attached to 20 fabricated events.

---

## 4. Postgres Deployment Plan

Blocked on **D7** (provider + region).

### 4.1 Architecture

```
  Laptop (dev)                    Pilot
  ───────────                     ─────
  SQLite                          App on small VM  ──►  Managed Postgres 16
  APP_ENV=local                   APP_ENV=production     daily backup + PITR
  APP_DEBUG=true                  APP_DEBUG=false        same region (Frankfurt)
  Dusk routes live                --no-dev, /_dusk → 404
```

Local development stays on SQLite (`docs/17`, unchanged).

### 4.2 Backup

- **Provider-managed:** daily automated + point-in-time recovery. The reason for choosing managed.
- **Application-level:** `scripts/db-backup.sh` supports pgsql. ⚠️ **That path has never been exercised here** — the 2.2 drill was SQLite-specific. It must be run and proven on Postgres before the pilot opens.
- **Reference set:** `pilot:export` weekly, stored off-host.

### 4.3 Restore

1. Provider console PITR for full-database recovery.
2. `scripts/db-restore.sh` for file-level — **must be drilled on Postgres.**
3. `pilot:import` for reference-data-only recovery.

**Exit gate: a restore must be rehearsed on Postgres before anyone relies on the pilot.** A backup nobody has restored is not a backup — that was the lesson of 2.2, and it applies again on a new engine.

### 4.4 Deployment flow

1. Provision managed Postgres, Frankfurt; credentials to a password manager.
2. Provision app VM, same region; `composer install --no-dev --optimize-autoloader`.
3. Apply `docs/35` hardening; **verify `/_dusk/login/1` → 404**.
4. Build the pilot database per §2 — directly on Postgres, do not copy the SQLite file.
5. Smoke-test: log in · create an event · open its Hub · raise an invoice · **open the Operations page** (the supplier-issue count fixed in `767daba`).
6. Backup + rehearsed restore.
7. Enable SMTP with `alwaysTo()` (§3).

### 4.5 Standing risk

The `tests_pgsql` CI job was red until `767daba`. One real SQLite-vs-Postgres incompatibility was found (`wherePivot()` inside `withCount()`), and that class of bug is **invisible in local development**. Treat `tests_pgsql` as a required gate — it has already earned its place.

---

## 5. Remaining Decisions

| # | Decision | Blocks | Status |
|---|---|---|---|
| **D1** | Confirm the 6 pilot users and each role | 2.3 | ⛔ Open |
| **D2** | Real addresses for Rahaf, Abdulla, Mohammad — or exclude | 2.3 | ⛔ Open |
| **D3** | Mail domain: `elitebhub.com` or `elitebusinesshub.com` | 2.4 | ⛔ Open |
| **D4** | Correct value for the truncated `notification@el` | 2.3, 2.4 | ⛔ Open |
| D5 | Clients/suppliers/venues | — | ✅ **Excluded** |
| **D6** | Keep the 3 registration templates? | 2.3 | ⛔ Open |
| **D7** | Postgres provider + region | 2.5 | ⛔ Open |
| **D8** | M365 mailbox + can SMTP AUTH be enabled? | 2.4 | ⛔ Open |
| **D9** | Verified price-list rates | Invoicing | ⛔ Open |
| — | Company profile as production data | — | ✅ **Applied** |
| — | Price-list rates non-authoritative | — | ✅ **Applied** (`--zero-rates`) |
| — | Managed Postgres | — | ✅ **Applied** |

**Seven decisions remain. D1, D2, D4 and D6 are the critical path — 2.3 cannot begin without them, and 2.4 and 2.5 both queue behind 2.3.**

---

## 6. Exact Checklist to Close Phase 2

**Decisions**
- [ ] D1 · D2 · D3 · D4 · D6 · D7 · D8 · D9

**2.3 Clean pilot database**
- [ ] Fresh `db-backup.sh` + `pilot:export`
- [ ] Provision Postgres · migrate · `pilot:import --zero-rates`
- [ ] Correct company email · create 6 users · verified rates
- [ ] All §2.1 verification checks pass
- [ ] Zero Faker accounts, **especially the 7 holding admin rights**

**2.4 SMTP**
- [ ] Only after 2.3 verifies
- [ ] SPF · DKIM · DMARC (`p=none`) on the confirmed domain
- [ ] Licensed shared mailbox, SMTP AUTH enabled
- [ ] `Mail::alwaysTo()` active
- [ ] All four flows tested and arriving
- [ ] Confirm nothing reached an external address

**2.5 Postgres**
- [ ] Managed instance, app same region
- [ ] `docs/35` hardening applied; `/_dusk/login/1` → **404**
- [ ] Five-flow smoke test
- [ ] **Backup and restore rehearsed on Postgres**
- [ ] Local dev still SQLite; `tests_pgsql` green

**Exit criteria**
- [ ] Pilot DB on Postgres, real users, no demo data
- [ ] Mail sends and provably cannot reach an unintended recipient
- [ ] Backup + restore proven **on Postgres**
- [ ] CI green on all three jobs
- [ ] Demo snapshot + reference export archived off-host

**Then Phase 3.**

---

## 7. Disclosure

The demo snapshot (`elitehub-DEMO-REFERENCE-20260813-142259.sqlite`) is **not pristine original demo data.** While debugging the stale test assertions in 2.6, I ran `tasks()->delete()` and `tasks()->create()` against ICFT 2026 in the local database, and that happened before the snapshot was taken. ICFT now carries a single task titled "Late" instead of its original two.

No other business table differs. I verified the live database against the snapshot across 16 tables: only `sessions` changed (9 → 3, expired logins garbage-collected). All of `events`, `users`, `clients`, `tasks`, `event_attendees`, `event_risks`, `event_approvals`, `invoices`, `suppliers`, `venues`, `service_items`, `taxonomy_terms`, `company_profiles` and `audit_logs` match exactly.

Operationally this matters very little — the demo data is being discarded, and the company profile, which is the part that matters, is untouched and now independently exported. Recording it because a snapshot presented as a reference should be described accurately.

---

## 8. Effort remaining

| Task | Effort | Gated by |
|---|---|---|
| 2.3 clean pilot database | 0.5–1 day | D1, D2, D4, D6, D9 |
| 2.4 SMTP | 0.5 day + DNS propagation | D3, D8 |
| 2.5 Postgres + restore drill | 1 day | D7 |
| **Total** | **2–2.5 days** | **7 decisions** |

The work is short and the sequence is fixed. **The schedule is set entirely by how quickly D1–D9 are answered.**

---

*Prepared 14 August 2026 against commit `ec5c244`. Export/import round-trip verified into a scratch database; the live database was not modified in producing this report. Bank details are referenced by existence and verified by hash only — never printed.*
