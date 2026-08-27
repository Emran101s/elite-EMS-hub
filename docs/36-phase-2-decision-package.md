# Phase 2 — Decision Package

**Date:** 13 August 2026 · **Commit:** `767daba` · **Status:** Phase 2 open, awaiting decisions
**Closed already:** 2.1 snapshot · 2.2 restore drill · 2.6 green baseline (1082/1082) · 2.7 CI green on all three jobs
**Open:** 2.3 clean pilot database · 2.4 SMTP · 2.5 Postgres target

Every figure below was measured against the live database on 13 August 2026. Nothing here has been executed.

---

## ⚠️ Two findings to read before anything else

**1. The company profile is real, authoritative, and not reproducible from any seeder.**

`company_profiles` holds genuine Elite Business Hub data entered by hand — legal address (Khelda, Nimr Alhmoud St, building 48, office 401, Amman), uploaded logo, website, `default_management_fee_pct = 15`, eight budget categories, eight ticket types, and **two live bank accounts with full IBAN and SWIFT details** (Bank Al Etihad, USD and JOD, in the name of Al Sattam for Exhibitions, Conferences and Consulting Services).

None of this comes from a seeder. **A fresh `migrate:fresh --seed` destroys it permanently.** It must be exported before the pilot database is built, and it is the single most important thing to carry across.

It also raises the stakes on Phase 1: real banking details are currently sitting in a SQLite file on a laptop running `APP_DEBUG=true`. Nothing is exposed today because the host is local-only — but that is now a *reason* the pilot host must be hardened, not a theoretical one.

**2. `company_profiles.email` is corrupt: it reads `notification@el`.**

Truncated, mid-domain. This is almost certainly the intended notification sender, which makes it a direct blocker for 2.4. It must be corrected as part of the pilot data, not after.

---

## A. Pilot User Matrix

19 accounts exist. They fall into three groups.

### A1. Real staff — recommended pilot users (6)

| # | Name | Email | Current role | On teams | Tasks | Recommended pilot role |
|---:|---|---|---|---:|---:|---|
| 1 | Emran Ahmed | emran.itan@elitebhub.com | `super_admin` | 3 | 2 | **`super_admin`** — platform owner |
| 2 | Layla Haddad | layla.haddad@elitebhub.com | `manager` | 2 | 1 | **`manager`** — PM for the pilot event |
| 4 | Sara Al-Rashid | sara.alrashid@elitebhub.com | `manager` | 2 | 1 | **`manager`** — finance/commercial |
| 3 | Omar Nassar | omar.nassar@elitebhub.com | `coordinator` | 2 | 4 | **`coordinator`** — operations |
| 5 | Khalid Mansour | khalid.mansour@elitebhub.com | `coordinator` | 2 | 1 | **`coordinator`** |
| 6 | Dana Qasem | dana.qasem@elitebhub.com | `coordinator` | 1 | 2 | **`coordinator`** |

These six map cleanly onto the roadmap's pilot roster (PM · operations · finance · commercial · admin · owner). **Decision needed: confirm the six, and confirm each role.**

### A2. Real people on a test domain — decision required (3)

| # | Name | Email | Role | Issue |
|---:|---|---|---|---|
| 8 | Rahaf Alsaqqa | `Rahaf@test.com` | coordinator | Real name, **placeholder domain** |
| 9 | Abdulla Alnajaar | `it@test.com` | coordinator | Real name, placeholder domain |
| 10 | Mohammad Khalaf | `m@test.com` | coordinator | Real name, placeholder domain |

**These cannot go into a pilot as-is.** `@test.com` is a real registered domain that is not yours — mail sent there leaves your control. Either supply real `@elitebhub.com` addresses, or exclude them from the pilot.

### A3. Test artefacts — exclude, and note the security issue (10)

| # | Name | Email | Role |
|---:|---|---|---|
| 11 | Prof. Emilio Yundt | adolfo.stark@example.net | **`super_admin`** |
| 12 | Mr. Koby Dibbert Sr. | kgusikowski@example.com | **`super_admin`** |
| 13 | Vince Berge | graynor@example.org | **`super_admin`** |
| 18 | Ebony Simonis | xgutmann@example.net | **`super_admin`** |
| 19 | Will Schulist | camille40@example.net | **`super_admin`** |
| 17 | Lupe Volkman | pwunsch@example.com | **`admin`** |
| 21 | Abdul Bayer | garland77@example.com | **`admin`** |
| 14–16 | PMx, PM, PM | `pm*@ebh.test` | coordinator |

**Seven Faker-generated accounts hold `super_admin` or `admin`.** They are leftovers from factory runs that persisted into the development database, with unknown passwords. They must not reach any shared host. Excluding them is not optional.

### A4. Role model, for reference

Six gates, ranked by seniority (`AppServiceProvider`): `write` (coordinator+) · `decide-approvals`, `manage-budget`, `manage-contract`, `manage-events` (manager+) · `manage-team` (admin+). `EventPolicy` additionally narrows below manager to actual event-team membership.

**Implication for the pilot:** a coordinator only sees events they are on the team for. Add the pilot event's team members explicitly, or coordinators will be locked out of the event they are meant to be running.

---

## B. Clean Pilot Database Plan

### B1. Authoritative — must carry across

| Data | Rows | Why it is authoritative | How to preserve |
|---|---:|---|---|
| **`company_profiles`** | 1 | Real address, logo, bank accounts, fee %, budget categories, ticket types. **Hand-entered, in no seeder.** | **Export to JSON before rebuild.** Fix the truncated email. |
| **Taxonomy customisations** | **17** | `taxonomy_terms` where `is_system = 0` — 16 added supplier categories, 1 added deal source. Real vocabulary decisions. | Export the 17 non-system rows |
| **`transport_service_types`** | 8 | Pickup & Drop-off, Airport↔Hotel, Hotel↔Venue, Full/Half Day at disposal, Intercity — genuine operational vocabulary | Keep |
| **`vehicle_types`** | 7 of 8 | Sedan, Van, Minibus, Midi Bus, Coach, VIP Sedan, Accessible Van | Keep — **drop "Test Van"** |
| **System taxonomy** | 191 | `is_system = 1` across 29 taxonomies — the platform's built-in vocabulary | Recreated by migration/seed |

### B2. Structure authoritative, values are not — re-enter before use

| Data | Rows | Evidence |
|---|---:|---|
| **`service_items`** (price list) | 19 | `PriceListSeeder`'s own docblock: *"Every price here is a placeholder: the units are the part worth keeping."* |

The **codes, categories and units** are good scaffolding (`ACC-DBL` / room_night, `TRN-SED` / vehicle_day, `TRN-BUS` / vehicle_trip…). **Every rate is invented.** Re-seed the structure, then enter real rates before raising a single invoice — this is the item most likely to cause real financial damage if it slips through.

### B3. Demo-seeded, but plausibly real-derived — your call

| Data | Rows | Source | Note |
|---|---:|---|---|
| `clients` | 8 | `DemoDataSeeder` | Names look real: Ernst & Young MENA, NDI, ICFT Global Committee, Qatar Tech Authority, German Jordanian University, World People Assembly, DeepRoot, Al Faisaliah. **No email or contact data on any of them.** |
| `venues` | 12 | `DemoDataSeeder` | Real venues: Royal Convention Centre Amman, Gulf Grand Ballroom Manama, Jumeirah Learning Hub Dubai, Doha Exhibition Center, GJU Main Campus Hall |
| `suppliers` | 44 | `DemoDataSeeder` + `FlagshipEventSeeder` | Not individually verified |

**Recommendation: exclude all three, and re-enter what the pilot event actually needs.** Eight clients with no contact details are not a client list — they are placeholders wearing real names, and carrying them over means the pilot starts with records nobody can trust or contact. Re-entering the two or three that the pilot event touches takes minutes and produces data you can rely on.

### B4. Exclude entirely

| Data | Rows |
|---|---:|
| `events` | 20 (all demo/faker) |
| `event_attendees` | 626 |
| `event_budget_categories` / `_items` | 90 / 62 |
| `event_agenda_sessions` / `_days` | 56 / 29 |
| `plan_items` / `plan_subtasks` / `plan_tracks` | 42 / 153 / 63 |
| `tasks` | 45 |
| `event_supplier` | 54 |
| `event_speakers` / `event_rooms` / `event_sponsors` | 26 / 22 / 13 |
| `invoices` / `invoice_lines` / `event_invoice_items` | 8 / 18 / 13 |
| `event_contracts` / `_payments` / `contract_signatories` | 8 / 8 / 11 |
| `event_risks` / `event_approvals` / `approval_steps` | 11 / 7 / 7 |
| `event_documents` | 8 |
| `registration_fields` | 57 (per-event, not reusable — the column is `event_id`) |
| `audit_logs` | 223 — **one distinct user, 17 Jul → 11 Aug: this is development activity, not business history** |
| Faker users | 10 (§A3) |

### B5. Registration templates — needs a look

3 templates exist, but `registration_fields` keys on `event_id`, not on a template. So the templates carry no fields of their own in this schema, and the 57 fields belong to demo events. **Confirm whether the 3 templates are worth keeping before deciding** — I could not establish this from the data alone.

### B6. Recommended build procedure

1. Snapshot again immediately before starting (`./scripts/db-backup.sh`).
2. **Export first, from the live DB:** `company_profiles` (1 row) · `taxonomy_terms WHERE is_system = 0` (17) · `transport_service_types` (8) · `vehicle_types` minus "Test Van" (7).
3. Build the pilot database **alongside** the current one — new file / new Postgres database. Do not `migrate:fresh` in place.
4. Migrate fresh → import the four exports → run `PriceListSeeder` for structure only → create the 6 real users with correct roles → correct `company_profiles.email`.
5. **Do not run `DemoDataSeeder`.** Ever, against the pilot.
6. Verify: 6 users, 0 events, 0 attendees, company profile intact with bank details, 19 price-list items, 208 taxonomy terms.
7. Switch the application over; keep the demo snapshot archived, not deleted.

---

## C. SMTP Options

### C1. Blocker: which domain sends mail?

Staff emails are `@elitebhub.com`. The company profile's website is `www.elitebusinesshub.com`. **These are two different domains.** SPF, DKIM and DMARC must be published on whichever domain the `From:` address uses, or mail will be rejected or junked. **Confirm the mail domain before configuring anything.**

### C2. Microsoft 365 — three routes

| Option | How | Best for | Caveats |
|---|---|---|---|
| **1. SMTP AUTH** (recommended) | `smtp.office365.com:587`, STARTTLS, licensed mailbox | This pilot | **SMTP AUTH is disabled by default on modern M365 tenants** — must be enabled per-mailbox in the admin centre. Requires a licensed mailbox. Blocked entirely if security defaults / MFA-only policy is on, unless an app password or an auth-policy exception is set. |
| **2. Microsoft Graph API** | OAuth2 app registration, `Mail.Send` | Long term | No SMTP AUTH needed, survives MFA policy, but needs a Laravel Graph transport — extra work, not warranted for a pilot |
| **3. High Volume Email (HVE)** | Dedicated M365 HVE endpoint | Internal bulk | Internal recipients only in most configurations — insufficient once attendees are external |

**Recommendation: Option 1 for the pilot, with Graph as the later path if MFA policy blocks it.**

### C3. Recommended sender

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls           # STARTTLS
MAIL_USERNAME=<licensed mailbox>
MAIL_PASSWORD=<app password / mailbox secret>   # never committed
MAIL_FROM_ADDRESS="no-reply@<confirmed mail domain>"
MAIL_FROM_NAME="Elite Business Hub"
```

Use a **dedicated shared mailbox** (`no-reply@` or `notifications@`) rather than a person's account — so replies do not land in someone's inbox, and rotating the secret does not lock a human out. A shared mailbox still needs a licence for SMTP AUTH.

Also correct `company_profiles.email` at the same time, so the in-app notification address and the SMTP sender agree.

### C4. Allow-list for the first two weeks

Four flows send mail: `ApprovalRequested`, `ApprovalDecided`, `RegistrationConfirmed`, `TeamInvite`. The first three can reach **attendees and external requesters**.

Two mechanisms, in order of safety:

1. **Laravel's own `Mail::alwaysTo()`** in a service provider, guarded to the pilot environment — every message, whatever its recipient, is redirected to one internal address. Strongest possible guarantee; nothing external can escape. Recommended for week 1.
2. **Explicit allow-list** — a config array of permitted recipients, checked before `notify()`. Needed from week 2 when real attendees must actually receive confirmations.

**Do not enable SMTP until §B's pilot database exists.** With the current database, 6 real colleagues are attached to 20 fabricated events; a single approval action would email them about work that does not exist.

---

## D. PostgreSQL Deployment

Context that changed this recommendation: **the `tests_pgsql` CI job was failing until today.** My roadmap claimed Postgres was "proven" — it was not. One real incompatibility was found and fixed (`767daba`: `wherePivot()` inside `withCount()` compiled to `where "pivot" = …`, which SQLite silently read as a false string comparison and Postgres correctly rejected). **CI is green on Postgres as of today** — but that class of bug is invisible on SQLite, so assume more may surface.

| Option | Pros | Cons | Verdict |
|---|---|---|---|
| **Local (Herd/Postgres.app on the laptop)** | Free, fast to stand up | Pilot data on one laptop; no backups off-device; laptop sleeps; not reachable by other pilot users | ❌ Not for a multi-user pilot |
| **Self-managed VM** (Hetzner / DigitalOcean droplet + Postgres) | Full control, cheap (~$10–20/mo), one box for app + DB | **You** own patching, backups, restore drills, disk monitoring. Real ops burden for a 6-person pilot with no ops engineer | ⚠️ Viable, adds work |
| **Managed Postgres** (DigitalOcean Managed / Supabase / Neon / RDS) | Automated daily backups + PITR, patching handled, restore is a button, connection limits and metrics included | ~$15–50/mo; slight latency if app and DB are in different regions | ✅ **Recommended** |

### D1. Recommendation: managed Postgres, app on a small VM, both in the same region

For a 6-person internal pilot, **the database is the only thing whose loss is unrecoverable.** Everything else — the app, the config — is in git and reproducible in minutes. Paying ~$15/month to make backup, restore and patching somebody else's job is the correct trade for a team with no dedicated ops.

Choose a region close to Amman — **Frankfurt** is the usual best latency for Jordan among the major providers.

### D2. Keep local development on SQLite

`docs/17` already says this and it is right. SQLite locally keeps the dev loop fast and needs no services running. The protection against divergence is the `tests_pgsql` CI job — **which must now be treated as a required gate, because it has already caught one real bug that SQLite hid.**

### D3. Cutover sequence

1. Provision managed Postgres; record credentials in a password manager, never in git.
2. Build the **pilot** database directly on Postgres (§B6) — do not copy the demo SQLite file across. This is the one chance to start clean.
3. `php artisan migrate` against Postgres, import the four exports, seed price-list structure, create the six users.
4. Smoke-test: log in, create an event, open its Hub, raise an invoice, run the Operations page (the page whose supplier-issue count was wrong).
5. Take a backup **and rehearse a restore** on Postgres before anyone relies on it — the drill proven in 2.2 was SQLite-specific; `scripts/db-backup.sh` supports pgsql but that path has never been exercised here.

---

## E. Exact Checklist to Close Phase 2

### Decisions only you can make

- [ ] **D1** — Confirm the 6 pilot users and each role (§A1)
- [ ] **D2** — Real `@elitebhub.com` addresses for Rahaf, Abdulla, Mohammad — or exclude them (§A2)
- [ ] **D3** — Confirm the mail domain: `elitebhub.com` or `elitebusinesshub.com` (§C1)
- [ ] **D4** — Confirm the correct value for the truncated `company_profiles.email`
- [ ] **D5** — Clients / venues / suppliers: exclude and re-enter (recommended), or carry over? (§B3)
- [ ] **D6** — Are the 3 registration templates worth keeping? (§B5)
- [ ] **D7** — Postgres provider and region (§D)
- [ ] **D8** — Provide M365 mailbox + confirm SMTP AUTH can be enabled (§C2)
- [ ] **D9** — Real price-list rates, or confirm they will be entered before the first invoice (§B2)

### Execution once decisions land

**2.3 — Clean pilot database**
- [ ] Fresh snapshot immediately before starting
- [ ] Export `company_profiles`, 17 non-system taxonomy terms, `transport_service_types`, `vehicle_types` minus "Test Van"
- [ ] Build alongside — never `migrate:fresh` in place
- [ ] Import exports · price-list structure · 6 users · corrected company email
- [ ] Verify: 6 users, 0 events, 0 attendees, bank details intact, 19 price items, 208 taxonomy terms
- [ ] Confirm all 10 Faker accounts are gone — **especially the 7 with admin rights**

**2.4 — SMTP**
- [ ] Only after 2.3 verifies
- [ ] Publish SPF/DKIM/DMARC on the confirmed mail domain
- [ ] Configure shared mailbox + app password
- [ ] Enable `Mail::alwaysTo()` redirect for week 1
- [ ] Test all four flows; confirm each arrives
- [ ] Confirm nothing reached an external address during testing

**2.5 — Postgres**
- [ ] Provision managed instance, same region as app
- [ ] Build the pilot DB directly on it
- [ ] Smoke-test the five flows in §D3.4
- [ ] Take a backup **and rehearse a restore on Postgres**
- [ ] Confirm local dev still runs on SQLite

**Phase 2 exit criteria**
- [ ] Pilot DB live on Postgres with real users and no demo data
- [ ] Mail sends, and provably cannot reach an unintended recipient
- [ ] Backup and restore proven on Postgres
- [ ] CI green on all three jobs (already true as of `767daba`)
- [ ] Demo snapshot archived, not deleted

**Then, and only then, Phase 3.**

---

## Estimated effort once decisions are made

| Task | Effort |
|---|---|
| 2.3 clean pilot database | 0.5–1 day |
| 2.4 SMTP (excl. waiting on M365 admin/DNS) | 0.5 day |
| 2.5 Postgres provision + cutover + restore drill | 1 day |
| **Total** | **2–2.5 days of work**, plus DNS propagation and any M365 policy change |

The critical path is not the work — it is **D1–D9**. Every one of them is a question only you can answer, and 2.3 cannot start until D1, D2, D5 and D9 are settled.

---

*Prepared 13 August 2026 against commit `767daba`. All counts measured directly. No data was modified, exported or deleted in producing this document. Bank details are referenced by existence only and are not reproduced here.*
