# Decision Record — Internal-First

**Date:** 9 August 2026
**Decided by:** Emran Itan
**Supersedes:** the SaaS-oriented assumptions in `docs/26` §5, and the Option B path in `docs/30-executive-audit.md` §9
**Status:** ACTIVE — this governs scope until explicitly reversed

---

## The decision

**Elite Business Hub is an internal event operations platform for our own company.**

We are not launching SaaS now. SaaS remains on the roadmap, **after** the internal product is
*stable, tested, and used daily by our team*.

That sentence is the success definition. Not a feature count — daily use by real staff on real events.

---

## Why this is the right call

The independent audit (`docs/30`) scored the platform **~35% complete as a commercial SaaS** but
**~70% complete as an internal tool**. The difference is almost entirely commercialisation work —
billing, onboarding, tenancy proof, API — none of which serves a single internal team.

The platform's genuine strength (transport dispatch, rooming, contracts, budget depth) is *already*
the thing our own operations need. Internal-first ships that value in weeks instead of quarters.

---

## Out of scope — do not build

Stop work on, and do not start:

- Payment gateway integration — we take JOD bank transfers; manual payment recording is correct for us
- Multi-tenant hardening (the "Slice 4" fail-closed work)
- Public API / webhooks / integration marketplace
- SSO and 2FA
- Billing, subscription plans, entitlement enforcement
- Self-serve tenant onboarding and signup
- Per-tenant branding / white-label
- GDPR self-service tooling

**If you are an agent or contributor picking up work in this repo: none of the above is on the
critical path. Check with Emran before spending time on any of it.**

---

## In scope — the internal critical path

Ordered by how much it blocks daily use:

1. **Transactional email.** `MAIL_MAILER=log` today, zero `Mail::` usage. Approvals are silent,
   invoices cannot reach clients, attendees get no confirmation. This is still the #1 blocker.
2. **SQLite → Postgres.** ~18 concurrent users against SQLite's single writer produces
   "database is locked" in normal daily use. Plan already exists: `docs/17-postgres-cutover-plan.md`.
3. **Fix the Event Hub Overview.** Journey nav renders as run-on text; Mission Radar cards clip
   their container. The team opens this screen every day.
4. **Green suite + merge gate.** `v1-soft-command-baseline` carries 10 failing tests. Also explain
   the 109 tests that never execute.
5. **Resolve the design seam.** 12 of 56 page views are converted to Soft Command. Either finish the
   Event Hub interior or roll back — but stop shipping two visual languages to daily users.
6. **On-site reality: offline check-in and mobile.** Our staff work from phones at venues with
   unreliable Wi-Fi. Check-in, arrivals and dispatch must survive that.
7. **Pagination** on attendee and task lists — lower urgency at current volumes, real at 1,000+ pax.

---

## The rule that protects the SaaS option

**Keep the tenancy spine in place, dormant. Do not extend it. Do not remove it.**

`tenant_id` exists on all 62 tables, indexed, guarded by `TenancyGuardTest`. That retrofit is already
paid for. Removing it now and re-doing it when SaaS returns would cost far more than leaving it idle.
Keep `Tenant`, `Workspace`, `BelongsToTenant` and `ResolveTenant` exactly as they are — just stop
investing in them.

**Known and accepted while single-tenant:** `Tenancy::id()` fails *open* — an unbound query returns
all rows rather than none. This is safe today because HTTP is the only entry point and `ResolveTenant`
is global middleware. **It becomes a real defect the moment we add a queue job, console command or
webhook.** If async work is introduced, this must be closed at the same time.

---

## The milestone that ends this phase

Not a feature. **The next real Elite event, run end-to-end in the system** — brief, budget, suppliers,
agenda, transport, attendees, check-in, invoice — with no parallel spreadsheet.

The database currently holds 18 demo/faker events. Replacing those with live operational data is the
actual proof that "used daily" has been achieved.
