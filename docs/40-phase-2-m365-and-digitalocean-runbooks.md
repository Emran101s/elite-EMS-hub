# Phase 2 — M365 Mailbox Runbook & DigitalOcean Console Runbook

**Date:** 14 August 2026 · **Status:** Phase 2 open — documentation only, nothing executed
**Confirms:** D4 = `notifications@elitebhub.com` (same mailbox as D8) · D7 = DigitalOcean Managed PostgreSQL, Frankfurt, v16, smallest tier
**Explicitly not done here:** SMTP not enabled · no DigitalOcean resource created · no billing touched · no database written

---

## Part 1 — Microsoft 365: `notifications@elitebhub.com`

Two ways this mailbox can end up able to send SMTP, because Microsoft's newer tenants disable SMTP AUTH by default and a shared mailbox usually can't authenticate on its own credentials without a license attached to it.

**Path A — license the shared mailbox directly.** Simplest, one mailbox, one cost line.
**Path B — a licensed service account with Send As on the shared mailbox.** More setup, but the credential that authenticates isn't the sender the recipient sees, which some tenants prefer.

Pick one before starting step 3. Path A is recommended for a 6-person pilot — less to manage.

### 1. Create or confirm the mailbox

1. Sign in to [admin.microsoft.com](https://admin.microsoft.com) as Global Admin or Exchange Admin.
2. **Teams & groups → Shared mailboxes.** Search "notifications" — check whether it already exists.
3. If it doesn't: **Add a shared mailbox** → name "Notifications" → email `notifications` → confirm the domain dropdown shows `elitebhub.com` → **Add**.
4. Note the mailbox's object ID or just its address — needed for the PowerShell steps below.

### 2. License it if required

**Path A:** Users → Active users → find `notifications@elitebhub.com` → **Licenses and Apps** → assign the cheapest SKU that includes Exchange Online (Business Basic, or Exchange Online Plan 1 if you don't want Teams/SharePoint bundled in). Shared mailboxes under 50GB don't strictly need a license to *receive* mail, but SMTP AUTH client submission needs an authenticatable, licensed identity — that's this step.

**Path B:** Create a separate licensed account (e.g. `svc-notifications@elitebhub.com`), license it the same way, then grant it **Send As** on the shared mailbox: Shared mailboxes → `notifications@elitebhub.com` → **Delegation** → **Edit Send as permissions** → add the service account. SMTP then authenticates as the service account; `MAIL_FROM_ADDRESS` stays `notifications@elitebhub.com`.

### 3. Enable SMTP AUTH — per-mailbox, not tenant-wide

Leaving SMTP AUTH off everywhere except this one mailbox is the smaller attack surface. Needs Exchange Online PowerShell:

```powershell
Install-Module -Name ExchangeOnlineManagement -Scope CurrentUser   # if not already installed
Connect-ExchangeOnline

# Path A — the shared mailbox itself:
Set-CASMailbox -Identity "notifications@elitebhub.com" -SmtpClientAuthenticationDisabled $false

# Path B — the service account instead:
Set-CASMailbox -Identity "svc-notifications@elitebhub.com" -SmtpClientAuthenticationDisabled $false

# Verify — should read False:
Get-CASMailbox -Identity "notifications@elitebhub.com" | Select-Object SmtpClientAuthenticationDisabled
```

If the tenant's **org-wide** SMTP AUTH default is off (the modern default), this per-mailbox override is normally enough on its own — *unless* Security Defaults or Conditional Access blocks legacy auth outright, which is step 4.

### 4. Check whether Security Defaults or Conditional Access blocks it

Two separate settings, either can block SMTP AUTH regardless of step 3:

- **Security Defaults** — [entra.microsoft.com](https://entra.microsoft.com) → Identity → Overview → **Properties** → **Manage Security defaults**. If **Enabled**, it blocks all legacy/basic-auth protocols tenant-wide, including SMTP AUTH, and the per-mailbox setting in step 3 has no effect. Turning Security Defaults off tenant-wide is usually not the right trade for one mailbox — if it's on, go to the fallback in step 5 instead of disabling it.
- **Conditional Access** — Entra admin center → Protection → Conditional Access → **Policies**. Look for any policy targeting "Legacy authentication clients" or requiring MFA for all cloud apps — SMTP AUTH can't satisfy an MFA challenge, so a blanket MFA-for-everything policy blocks it the same way Security Defaults does.

**The reliable way to know for certain** is to actually attempt it once credentials exist:

```bash
openssl s_client -starttls smtp -crlf -connect smtp.office365.com:587
# then, at the resulting prompt:
EHLO test
AUTH LOGIN
# base64 the mailbox address, then the password, when prompted
```

An authentication failure citing basic auth being disabled confirms the block, independent of what the admin-centre toggles say they're set to.

### 5. Fallback if SMTP AUTH is blocked — Microsoft Graph

If Security Defaults or Conditional Access blocks legacy auth, don't disable tenant security to work around it. Use the Graph API instead — OAuth2 client-credentials, not basic auth, so it isn't affected by either setting.

1. **Entra admin center → App registrations → New registration.** Name it something identifiable ("EliteHub Notifications").
2. **API permissions** → add `Mail.Send` (**Application**, not Delegated) → **Grant admin consent**.
3. **Certificates & secrets** → new client secret → record it straight into the pilot host's `.env`, never into chat.
4. **Restrict the app to only this mailbox** — by default an app with `Mail.Send` (Application) can send as *any* mailbox in the tenant, which is far more than this needs. Scope it down with an `ApplicationAccessPolicy` in Exchange Online PowerShell:
   ```powershell
   New-ApplicationAccessPolicy -AppId "<app client id>" `
     -PolicyScopeGroupId "notifications@elitebhub.com" `
     -AccessRight RestrictAccess -Description "Notifications mailbox only"
   ```
5. Application code side: replace the SMTP transport with a Graph-based Laravel mail transport (a custom Symfony Mailer transport calling `POST /users/{id}/sendMail`, or an existing community package). **This is implementation work, not configuration** — flagging it here as the fallback path, not doing it now. Only build it if step 4's actual test confirms SMTP AUTH is blocked.

---

## Part 2 — DigitalOcean: Managed PostgreSQL console runbook

Console only, per your instruction — no `doctl`, no API token, nothing that could touch billing without you clicking it yourself.

### Configuration checklist (what you're aiming for)

- [ ] Engine: **PostgreSQL 16**
- [ ] Region: **Frankfurt (FRA1)**
- [ ] Tier: smallest/cheapest listed (currently the **Basic** shared-CPU tier — exact RAM/disk numbers shift over time, so pick whatever the console lists as smallest rather than trusting a figure here)
- [ ] Standby nodes: **0** — a pilot doesn't need HA, and DO's automated backups cover recovery
- [ ] A dedicated database and user — not the `defaultdb`/`doadmin` defaults
- [ ] Public access restricted to the app host's IP only
- [ ] Automated backups / PITR confirmed on

### Click-path

1. Sign in at [cloud.digitalocean.com](https://cloud.digitalocean.com).
2. If this is the first resource for the pilot: top of the sidebar, create or select a **Project** — e.g. "Elite Business Hub Pilot" — so it's not mixed into an unrelated project.
3. Left sidebar → **Databases** → **Create Database Cluster** (or the green **Create** button, top right → Databases).
4. **Choose a database engine:** PostgreSQL → version **16**.
5. **Choose a datacenter region:** **Frankfurt**.
6. **Choose a cluster configuration:** the **Basic** plan tab, then the smallest node size on that tab.
7. **Cluster name:** something identifiable, e.g. `ebh-pilot-pg`.
8. **Create Database Cluster.** Provisioning takes a few minutes.
9. Once ready, open the cluster → **Settings** tab → **Trusted Sources** → add only the app VM's IP address. Do not leave this on the "allow all" default.
10. **Users & Databases** tab → **Add database** → name it something like `elitehub_pilot` (not `defaultdb`). Optionally add a dedicated user here too, rather than using the cluster's default admin user for the application.
11. **Overview** or **Connection Details** panel → copy: host, port, database name, username, password, and the connection string. DigitalOcean managed Postgres requires `sslmode=require` and provides a CA certificate for it — download that too. **Put all of this straight into a password manager — don't paste it into chat.**
12. **Settings** tab → confirm **Backups** shows automated daily backups with point-in-time recovery enabled. This is on by default for managed clusters, but confirm rather than assume — it's the entire reason for choosing managed over self-hosted.
13. Optional but recommended for a pilot with no dedicated ops: **Insights** tab → add an alert on CPU or connection count, so something notices before the smallest tier gets overwhelmed.

Once you have the values from step 11, they go into `.env.pilot` per [docs/38 §3.3](38-phase-2-execution-prep.md) — that step is still pending on your provisioning, not on anything further from me.

---

## Status after this document

| Item | State |
|---|---|
| D4 value | Confirmed: `notifications@elitebhub.com`. Not yet written anywhere — it lands in the pilot database at [docs/38 §3.1](38-phase-2-execution-prep.md) step 6, during 2.3, not before. |
| SMTP | **Still fully disabled.** Nothing in Part 1 has been run — it's a runbook for you or your M365 admin to execute, gated behind 2.3 completing per your explicit ordering rule. |
| Postgres | **No resource created.** Part 2 is a console guide only — you provision it manually as you said you would. |
| Off-host export backup | Unchanged — still waiting on you to upload `storage/backups/pilot-reference-20260814-075700.json` per [docs/38 §2.3](38-phase-2-execution-prep.md). Tell me once it's done and I'll re-hash the local copy against `f9835b62eb…` for you to compare. |

Nothing else is blocking documentation. What's left is you clicking through Parts 1 and 2, and confirming the off-host upload.

---

*Prepared 14 August 2026. No application file, database, or cloud resource was touched producing this document.*
