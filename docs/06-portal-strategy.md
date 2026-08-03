# Portal Strategy

## What exists today: two public, unauthenticated surfaces

The platform already has portal-shaped surfaces, but only for the attendee side, and only
as single-purpose token routes — not a full authenticated portal with its own login/session:

- **`/register/{token}`** — a public registration form. The token identifies the event's
  registration template; submitting it creates an attendee record. No login.
- **`/checkin/{token}/{reference}`** — QR check-in. Scanning a printed badge hits this route
  and marks the attendee checked in. No login.
- **Badge/prospectus PDFs** (`badges.pdf`, sponsorship prospectus, etc.) are generated
  server-side and handed out as files, not viewed through a portal session.

These are deliberately narrow: a token grants exactly one action, there's no dashboard
behind it, and there's no account system for external parties yet.

## What's planned: Supplier Portal and Client Portal

Both are **not yet built**. They are priority items 5 and 6 in the roadmap (see
[01-product-vision.md](01-product-vision.md)), after the Company/Event Command Centers,
event life cycle, and approval engine are solid.

**Supplier Portal** (future) — where a supplier would see their own orders/requests
(currently generated as one-way PDFs — see [05-approval-workflow-engine.md](05-approval-workflow-engine.md)
for the RFQ/Quotation gap), confirm availability, and upload their own documents, instead
of everything flowing through the internal team as email + PDF.

**Client Portal** (future) — where a client would see their event's brief, budget summary,
agenda and contracts directly, instead of receiving them as PDFs. The Event Brief module
(a living document that already assembles itself from the event's own data into a
WYSIWYG PDF) is the natural seed for this — the portal would essentially be that document
rendered as a live page instead of an export.

## Design implication for whoever builds these later

Both portals should almost certainly reuse the existing token-route pattern
(`register/{token}`, `checkin/{token}/{reference}`) for scoped, no-login access rather than
introducing a full external-user authentication system — that's a much bigger decision
(user table for external parties, password resets, session management for people outside
the company) and shouldn't be taken on incidentally while building a portal feature.
