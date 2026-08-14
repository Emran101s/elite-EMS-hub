# Phase 2 — Execution Package

**Date:** 14 August 2026 · **Commit:** pending · **Status:** Phase 2 open — 3 items left before 2.3/2.4/2.5 can run

---

## Settled — no further input needed

| # | Decision |
|---|---|
| D1 | Pilot users: Emran, Sara, Omar, Khalid, Layla, Dana |
| D2 | Rahaf, Abdulla, Mohammad excluded until real addresses + roles confirmed |
| D3 | Mail domain: `elitebhub.com` |
| D6 | Keep all 3 registration templates |
| D7 | DigitalOcean Managed PostgreSQL · Frankfurt · v16 · smallest tier |
| D8 | `notifications@elitebhub.com` · M365 shared mailbox |
| D9 | No invoice may be issued until rates are manually verified |

---

## Still open — 3 one-line answers

**1. D4 — does the notification address match D8?**
`company_profiles.email` is currently truncated (`notification@el`). Given D3 and D8, the obvious value is `notifications@elitebhub.com` — same mailbox for both. **Confirm yes, or give a different address.**
→ Unblocks [docs/38 §3.1](38-phase-2-execution-prep.md), step 6.

**2. D8 — mailbox and tenant status**
- Does `notifications@elitebhub.com` exist and hold a licence yet, or does it need creating?
- Can SMTP AUTH be enabled for it — has anyone checked whether Security Defaults or a Conditional Access MFA policy would block it?
→ Unblocks [docs/38 §3.2](38-phase-2-execution-prep.md), steps 1–3.

**3. D7 — who provisions the DigitalOcean instance?**
I have no billing access and can't create the resource myself. Do you have a DigitalOcean account/API token ready, or do you want the exact console click-path spelled out?
→ Unblocks [docs/38 §3.3](38-phase-2-execution-prep.md), step 1.

Nothing else is required to start. §3.1 steps 1–5 and 7 (snapshot, export, migrate, import, create users) need only D4.

---

## Standing action — not a decision, a to-do

**Move the pilot-reference export off the laptop.** Steps and a hash to verify against are in [docs/38 §2.3–2.4](38-phase-2-execution-prep.md). Tell me once it's done and I'll re-hash the local copy so you can confirm the upload matches — **required before Phase 2 can close**, independent of D1–D9.

---

## What fires the moment each answer lands

| Answer supplied | Action begins |
|---|---|
| D4 confirmed | 2.3 build starts: snapshot → export → migrate → import → create 6 users ([docs/38 §3.1](38-phase-2-execution-prep.md)) |
| D8 sub-answers + DNS published | 2.4 starts: SPF/DKIM/DMARC verified → SMTP AUTH enabled → `.env.pilot` mail block set → four flows tested ([docs/38 §3.2](38-phase-2-execution-prep.md)) |
| D7 provisioning path confirmed | 2.5 starts: instance provisioned → network-restricted → 2.3's build runs against it directly ([docs/38 §3.3](38-phase-2-execution-prep.md)) |

No further planning is required for any of the three. The commands are already written; each one runs as soon as its one-line answer arrives.

Phase 3 not started. No new plans created. No feature work touched.
