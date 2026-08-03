# Elite Business Hub — Product Vision

## The thesis

Most event software helps you *plan* an event. Elite Business Hub is built to **run** one.

The distinction matters everywhere: planning software is used at a desk, in advance, calmly.
Running software is used at 06:40 on show day, standing in a loading bay, one-handed, when a
driver hasn't arrived and 40 delegates are waiting. The same product has to do both.

An event in this platform is a container that holds a budget, a programme, a venue, a guest
list, a fleet, a supplier roster, a set of contracts and a paper trail — and a health score
that watches all of it and tells you what is slipping.

## Who uses it

| Role | What they do |
|---|---|
| **Super admin** | Owns the company account, settings, catalogues, users |
| **Admin** | Manages team and access company-wide |
| **Manager** | Owns events end to end. Approves money, signs contracts, answers to the client |
| **Coordinator** | Does the work. Chases suppliers, builds the agenda, assigns transport |
| **Viewer** | Reads. Usually a client contact or an internal stakeholder |

See [07-user-roles-and-permissions.md](07-user-roles-and-permissions.md) for the exact
rank hierarchy and permission gates.

## Current priority — internal Event Operations Platform first

Multi-tenant SaaS, billing, tenant management and white-label features are a **later**
phase. The current build is not designed around them yet, and nothing should be built
toward them until this phase is explicitly re-opened.

Priority order, as it stands today:

1. Company Command Center
2. Event Command Center
3. Event life cycle
4. Approval workflow engine
5. Supplier portal
6. Client portal
7. Contracts, budget, invoices, payments
8. Documents, reports, notifications
9. Templates and knowledge base
10. Multi-tenant SaaS

Items 1–4 and most of 7 already have working implementations (see
[10-current-codebase-assessment.md](10-current-codebase-assessment.md)). Items 5, 6, 9 and
10 are not yet built — see [06-portal-strategy.md](06-portal-strategy.md) and
[09-development-roadmap.md](09-development-roadmap.md) for what exists today versus what's
planned.

## One sentence

An operations platform where a small team runs large, formal, often government-facing
events — planning them at a desk for months, then running them from a phone for five days —
where half the output is documents that go to the client with the company's name on them.
