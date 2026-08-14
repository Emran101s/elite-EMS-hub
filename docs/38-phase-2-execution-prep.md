# Phase 2 — Execution Preparation

**Date:** 14 August 2026 · **Commit:** `485e28c` · **Status:** Phase 2 open — preparation only, nothing executed
**Purpose:** so 2.3, 2.4 and 2.5 can start the moment D1–D9 land, with no re-thinking mid-execution

Nothing in this document changes application behaviour. No file outside `docs/` was touched producing it.

---

## Part 1 — Exact values needed for D4, D7, D8

These three decisions need a *specific value*, not a direction — the completion report named the question, this names the exact answer shape so nothing gets improvised at execution time.

### D4 — Notification sender address

**The problem, precisely:** `company_profiles.email` currently reads `notification@el` — 15 characters, cut off mid-domain. I can't infer the rest; `el` fits both `elitebhub.com` and `elitebusinesshub.com` (§D3's ambiguity), and `notification@` could equally have been `notifications@`.

**What I need back, verbatim:**
```
company_profiles.email = "________________________"
```
One complete, valid, deliverable email address. If it's meant to be the same mailbox as D8's SMTP sender, say so explicitly and I'll use one value for both — an app that notifies from one address but sends SMTP from another looks broken to a recipient checking headers.

### D7 — Postgres provider and region

**What I need back:**

| Field | Example answer | Why execution needs it |
|---|---|---|
| Provider | "DigitalOcean Managed Databases" | Determines the provisioning API/console I use in §3 below |
| Region | "Frankfurt (fra1)" | Sets latency to Amman; must match the app VM's region |
| Postgres version | "16" (recommended — matches CI) | CI already runs `pgsql` on 16; a mismatch is a needless variable |
| Plan / size | e.g. "smallest managed tier, 1 vCPU / 1GB" | A 6-person pilot needs very little; I'd default to the smallest tier unless told otherwise |
| Who provisions it | You, or should I walk you through the console? | I cannot create billing-linked cloud resources myself — see §2 |

If you don't have a preference on provider, say so and I'll pick DigitalOcean Managed Postgres (cheapest managed tier with PITR, Frankfurt available) as the default rather than asking again.

### D8 — SMTP mailbox

**What I need back:**

| Field | Example answer |
|---|---|
| Mailbox address | `notifications@elitebhub.com` |
| Is it licensed already, or does one need creating? | |
| Can you (or your M365 admin) enable SMTP AUTH for it? | Needs `Set-CASMailbox -SmtpClientAuthenticationDisabled $false`, run by a Global/Exchange admin |
| Is Security Defaults or a Conditional Access MFA policy active on the tenant? | If yes, SMTP AUTH may be refused regardless of the setting above — the fallback is Microsoft Graph, which is more work and not scoped for the pilot |
| The app password / mailbox secret, once created | **Do not send this in chat.** Put it directly into the `.env` on the pilot host, or hand it to me only as a value I paste straight into that file — never as chat text that lands in this transcript |

---

## Part 2 — Secure off-laptop storage of the pilot export

**What I can and can't do here, plainly:** I have no authenticated connection to OneDrive, a password manager, or any cloud storage account — SharePoint/OneDrive tools are listed as requiring authorization I don't have in this session, and reaching for credentials on your behalf isn't something I do regardless. I can get the file into a state where moving it takes you one click, and verify it lands correctly — the click itself is yours.

### 2.1 The file as it stands

```
storage/backups/pilot-reference-20260814-075700.json
28,589 bytes · mode 600 · sha256 f9835b62ebea724500b5533b53e54fe3ed70b1d59489dd4c215cef27f68ad227
```

Contains: company profile (address, logo path, management fee, budget categories, ticket types), **two live bank accounts with full IBAN and SWIFT**, 17 custom taxonomy terms, transport vocabulary, price-list structure. Confirmed gitignored; confirmed absent from `git status`.

### 2.2 What "secure secondary location" should mean for this file

- **Encrypted at rest** on the destination, or the destination guarantees it (OneDrive for Business, a password manager's file-attachment feature — both do)
- **Access limited** to people who should see live bank details — not a shared team drive with broad read access
- **Not email** — attachments sit unencrypted in mail stores and get forwarded
- **Not Slack/Teams** — same problem, plus it's searchable by anyone who later joins the channel

### 2.3 Recommended path — copy, don't retype

1. On the laptop, reveal the file: `open storage/backups/` (Finder) — or I can copy it to your Desktop if that's easier to drag from.
2. Drop it into **OneDrive for Business** (if your M365 tenant is already the SMTP answer for D8, this keeps everything in one trust boundary) or your **password manager's secure attachment** feature (1Password, Bitwarden — both support file attachments on a vault item).
3. Rename the *uploaded copy* to something identifying without being descriptive of contents in a filename that might get searched — e.g. `ebh-pilot-ref-2026-08-14.json` is fine; avoid a name containing "bank" or "IBAN".
4. **Delete the copy from Desktop** if you made one in step 1 — the version in `storage/backups/` stays, since it's the working copy the `pilot:import` command will read from at execution time.

### 2.4 Verifying the upload didn't corrupt anything

After it's uploaded, tell me and I'll re-hash the local copy so you can compare — a byte-for-byte match confirms nothing was altered in transit:

```bash
shasum -a 256 storage/backups/pilot-reference-20260814-075700.json
```
Expected: `f9835b62ebea724500b5533b53e54fe3ed70b1d59489dd4c215cef27f68ad227`

Most cloud UIs don't expose a hash directly — downloading the uploaded copy back down and re-hashing it locally is the reliable check if you want one.

### 2.5 Going forward

Re-run `php artisan pilot:export` after any change to the company profile, and repeat this upload. The command is idempotent and cheap (29KB, sub-second) — there's no reason to let this copy go stale once real pilot data replaces the demo set.

---

## Part 3 — Execution checklists

Ready to run the moment the relevant decisions land. Each is a literal runbook — commands as I'd actually type them, not a description of the idea.

### 3.1 — 2.3 Clean Pilot Database

**Waits on:** D1, D2, D4, D6, D9 (rates can start at zero and be filled in per §3.1 step 8 without blocking the rest)

```bash
# 1. Snapshot immediately before touching anything
cd /Users/emranalitan/Herd/elitehub
OUT=storage/backups/elitehub-PRE-PILOT-$(date +%Y%m%d-%H%M%S).sqlite ./scripts/db-backup.sh

# 2. Fresh reference export (in case anything changed since Aug 14)
php artisan pilot:export

# 3. Point at the pilot target — .env.pilot, not .env, so local dev is never at risk
cp .env .env.pilot
#   edit .env.pilot: DB_CONNECTION=pgsql, DB_HOST/PORT/DATABASE/USERNAME/PASSWORD from D7's provisioned instance
#   APP_ENV=production, APP_DEBUG=false

# 4. Migrate the pilot database
php artisan migrate --force --env=pilot

# 5. Import the reference set — zero-rates until D9's verified sheet exists
php artisan pilot:import storage/backups/pilot-reference-<latest>.json --zero-rates --env=pilot

# 6. Correct the notification address (D4)
php artisan tinker --env=pilot --execute="
  \DB::table('company_profiles')->update(['email' => '<D4 value>']);
"

# 7. Create the confirmed pilot users (D1, D2) — repeat per user
php artisan tinker --env=pilot --execute="
  \App\Models\User::create([
    'name' => '<name>', 'email' => '<real address>',
    'password' => Hash::make(Str::random(24)),   // send a reset link, never a generated password
    'role' => '<role>',
  ]);
"
#   then trigger Laravel's password-reset flow per user rather than transmitting a password

# 8. Rates (D9) — once the verified sheet exists
php artisan pilot:import <verified-rates-file>.json --env=pilot   # without --zero-rates

# 9. Verify — every line must match
php artisan tinker --env=pilot --execute="
  echo 'users: '.\App\Models\User::count().PHP_EOL;
  echo 'admin-tier users: '.\App\Models\User::whereIn('role',['admin','super_admin'])->count().PHP_EOL;
  echo 'events: '.\App\Models\Event::count().PHP_EOL;
  echo 'clients: '.\App\Models\Client::count().PHP_EOL;
  echo 'suppliers: '.\App\Models\Supplier::count().PHP_EOL;
  echo 'venues: '.\App\Models\Venue::count().PHP_EOL;
  echo 'company profile rows: '.\DB::table('company_profiles')->count().PHP_EOL;
  echo 'bank_accounts present: '.(\DB::table('company_profiles')->value('bank_accounts') ? 'yes' : 'NO').PHP_EOL;
  echo 'taxonomy_terms: '.\DB::table('taxonomy_terms')->count().PHP_EOL;
  echo 'service_items with rate=0: '.\App\Models\ServiceItem::where('unit_price_cents',0)->count().' of '.\App\Models\ServiceItem::count().PHP_EOL;
"
```

**Exit checklist for this step:**
- [ ] `users` matches the confirmed roster exactly, zero `@example.*` / `@test.com` / `@ebh.test`
- [ ] `admin-tier users` = only the confirmed admins/super_admins from D1
- [ ] `events` = 0, `clients` = 0, `suppliers` = 0, `venues` = 0
- [ ] `company profile rows` = 1, `bank_accounts present` = yes
- [ ] `taxonomy_terms` = 208
- [ ] `service_items with rate=0` = 0 of 19 **before** the pilot opens (all verified)

---

### 3.2 — 2.4 SMTP Setup

**Waits on:** D3 (mail domain), D8 (mailbox + M365 admin answers) · **Hard gate: do not start until 3.1's exit checklist is fully green**

```bash
# 1. DNS — on whichever domain D3 confirms
#    SPF (merge with any existing record — one SPF record only):
#      TXT @ "v=spf1 include:spf.protection.outlook.com -all"
#    DKIM — get the two CNAME targets from:
#      M365 admin centre → Exchange → mail flow → DKIM (per domain)
#      then publish:
#      CNAME selector1._domainkey → selector1-<tenant>._domainkey.<tenant>.onmicrosoft.com
#      CNAME selector2._domainkey → selector2-<tenant>._domainkey.<tenant>.onmicrosoft.com
#    DMARC (start permissive, tighten after two weeks of clean reports):
#      TXT _dmarc "v=DMARC1; p=none; rua=mailto:<D4 or an ops address>"

# 2. Confirm DNS propagated before touching app config
dig +short TXT <domain>
dig +short CNAME selector1._domainkey.<domain>
dig +short TXT _dmarc.<domain>

# 3. Enable SMTP AUTH on the mailbox (M365 admin, PowerShell)
#    Set-CASMailbox -Identity "<D8 mailbox>" -SmtpClientAuthenticationDisabled $false
#    If Security Defaults / Conditional Access blocks this — stop, report back, do not
#    improvise a workaround (app passwords under a blocking CA policy silently fail).

# 4. .env.pilot — mail block
MAIL_MAILER=smtp
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=<D8 mailbox>
MAIL_PASSWORD=<app password — set directly in .env.pilot on the host, never in chat>
MAIL_FROM_ADDRESS=<D8 mailbox, matching D4>
MAIL_FROM_NAME="Elite Business Hub"

# 5. Pilot-only redirect guard — every message, whatever the recipient, goes to one inbox
#    In AppServiceProvider::boot(), guarded to the pilot env:
if (app()->environment('production') && config('mail.pilot_redirect')) {
    Mail::alwaysTo(config('mail.pilot_redirect'));
}
#    .env.pilot: MAIL_PILOT_REDIRECT=<your own inbox>

# 6. Send a real test through each of the four flows and confirm arrival + headers
php artisan tinker --env=pilot --execute="
  // trigger ApprovalRequested, ApprovalDecided, RegistrationConfirmed, TeamInvite
  // against a throwaway record, one at a time; check the mailbox after each
"

# 7. Check SPF/DKIM/DMARC actually pass on a delivered message
#    Open the arrived email → view source/headers → look for:
#      Authentication-Results: spf=pass dkim=pass dmarc=pass
```

**Exit checklist:**
- [ ] SPF, DKIM, DMARC all resolve and a delivered message shows `spf=pass dkim=pass dmarc=pass`
- [ ] All four flows tested individually and arrived
- [ ] `Mail::alwaysTo()` confirmed active — send to an arbitrary address, confirm it still lands in the redirect inbox
- [ ] No message reached any address outside the redirect inbox during testing

---

### 3.3 — 2.5 Postgres Deployment

**Waits on:** D7 · **Feeds into 3.1 step 3** (the pilot database is built *on* this instance, not migrated to it afterward)

```bash
# 1. Provision (console, per D7's provider — example shown for DigitalOcean)
#    doctl databases create ebh-pilot --engine pg --version 16 --region fra1 --size db-s-1vcpu-1gb
#    Or via the web console if you'd rather click through it — either way, record:
#      host, port, database name, username, password → straight into a password manager

# 2. Restrict network access to just the app VM's IP (trusted sources / firewall rule)
#    Never leave a managed Postgres instance open to 0.0.0.0/0

# 3. Confirm reachability from the app host before anything else
psql "postgresql://<user>:<pass>@<host>:<port>/<db>?sslmode=require" -c "select version();"

# 4. Hand off to 3.1 — migrate + import runs against this instance

# 5. Confirm the provider's automated backup + PITR is actually enabled
#    (most managed tiers default this on, but check explicitly — do not assume)

# 6. Application-level backup — has never been run against pgsql in this project
OUT=storage/backups/ebh-pilot-pg-$(date +%Y%m%d-%H%M%S).dump \
  DB_CONNECTION=pgsql DB_HOST=<host> DB_DATABASE=<db> ./scripts/db-backup.sh
#    Check scripts/db-backup.sh's pgsql branch works as expected — this is the first
#    real exercise of that code path; read it before running against real data.

# 7. Restore drill — non-destructive, into a throwaway database, never in place
#    createdb ebh-pilot-restore-drill
#    pg_restore -d ebh-pilot-restore-drill storage/backups/ebh-pilot-pg-<stamp>.dump
#    Verify row counts against the source, same discipline as the 2.2 SQLite drill.

# 8. Smoke test through the app once pointed at Postgres
#    - log in as a real pilot user
#    - create an event
#    - open its Hub
#    - raise an invoice
#    - open /suppliers specifically — this is the page fixed in 767daba;
#      confirm "Open supplier issues" shows a real number, not 0 and not a 500

# 9. Confirm local dev is unaffected
DB_CONNECTION=sqlite php artisan test
#    Should still be 1082/1082 — pilot deployment must not touch local .env
```

**Exit checklist:**
- [ ] Instance reachable only from the app host's IP
- [ ] Provider PITR confirmed on
- [ ] `db-backup.sh`'s pgsql path run successfully at least once
- [ ] A restore rehearsed into a throwaway database, row counts matched
- [ ] All five smoke-test steps pass, including the Operations page
- [ ] Local suite still 1082/1082 on SQLite — nothing about pilot deployment touched local `.env`

---

## What this preparation does not do

- Does not create any cloud account, spend any money, or touch billing
- Does not upload the export anywhere — §2.3 is a set of steps for you to run
- Does not send SMTP credentials anywhere — §3.2 step 4 says explicitly to set them directly on the host
- Does not start 2.3, 2.4 or 2.5 — every command above is written but not run
- Does not touch performance work, Hub Overview, Operations Control, Layout Builder, Exhibition Builder, or any new feature

---

*Prepared 14 August 2026 against commit `485e28c`. No application file outside `docs/` was modified producing this document. No command in Part 3 has been executed.*
