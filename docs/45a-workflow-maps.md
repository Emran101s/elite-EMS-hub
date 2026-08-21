# Elite EMS — Workflow Maps (companion to docs/45)

Full detail for the 11 major workflows named in the platform inventory. Source: `routes/web.php`, `App\Models\Event::HUB_TABS`, `App\Support\Workflow::SETS` (10 state machines), `App\Support\NavPanel`, and direct component docblocks.

## 1. Event Creation
Start → `events.create` (`EventCreate`, "Event Studio") → wizard builds event on one canvas with live preview → event created at `event_stage = draft` (`draft → proposal → confirmed → planning → production → live → completed → closed`, with `cancelled`/`on_hold` as side-states) → lands on Event Hub (`events.hub`), Overview tab.

Related pages: `events.create`, `events.hub`.
Note: an event can also originate from winning a CRM deal (`deal_stage`: `enquiry → qualified → proposal → negotiation → won`/`lost`) — winning opens the event, per `NavPanel`'s own comment ("Winning a deal creates the event, so the set itself is fixed").

## 2. Registration
Start → Coordinator opens Event Hub → Attendees tab → generates a token-based public link → shares `/register/{token}` (`PublicRegistration`, fully public/unauthenticated, guest-eo layout) → visitor fills the event's own dynamic registration form (fields defined by `RegistrationTemplate`, applied per-event) → `RegistrationConfirmed` notification sent → attendee row created at `attendee_status = registered` → (optional) staff confirms → `confirmed` → walks in → `checked_in` (via Check-In workflow, below) or `cancelled`.

Related pages: `settings/registration-templates` (the reusable form library), Event Hub → Attendees tab, `/register/{token}` (public), `events.attendees.template` (XLSX import for manual/offline registrations).

## 3. Check-In
Start → badge QR scanned → `/checkin/{token}/{reference}` (`CheckInScan`, fully public/unauthenticated, guest-eo layout) → marks attendee `checked_in`; can only do this one thing (cannot list attendees or reveal who else is coming, per its own doc comment).

Fallback path → **Arrivals Desk** (`events.arrivals`, `ArrivalsDesk`, authenticated) → for the person without a badge, misspelled name, or unregistered walk-in — "not an edge case, it is most of the first hour" per its own docblock. Deliberately not the attendee list with a filter — answers "is this person on the list" rather than "who is coming."

Related pages: `events.badges.pdf` (badge sheet, printed ahead of time), `/checkin/{token}/{reference}` (public), `events.arrivals`.
End: attendee status = `checked_in`.

## 4. Speaker Management
Start → Event Hub → Speakers tab (`SpeakersTab`) → add speaker, set `is_keynote`, assign to agenda session(s) → session status progresses independently (`session_status`: `draft → waiting_speaker → needs_review → confirmed → final`) → speaker fee tracked (`fee_cents`) → billing feeds Budget/Pricing.

Related pages: Event Hub → Speakers tab, Event Hub → Agenda tab (session assignment), command palette (speakers are directly searchable).
End: session reaches `confirmed`/`final`, fee reconciled in Budget.

## 5. Sponsorship
Start → Event Hub → Sponsors tab → package assigned (from `settings/sponsor-packages`, `SponsorPackagesSettings`) → sponsor status tracked (pending/partial/paid pattern, same shape as `payment_status`) → `SponsorshipController::show` renders the sponsorship page, `::pdf` exports the packages-sold document (dompdf).

Related pages: Event Hub → Sponsors tab, Event Hub → Exhibition tab (a sponsor often also holds a booth), `/sponsors` (cross-event sponsorship view, inline closure route), `settings/sponsor-packages`, `events.sponsorship.pdf`.
End: package paid in full, reflected in event revenue.

## 6. Finance
Start → Proposal (`proposals.index`/`.edit`) — priced offer before an event exists → accepted → wins the deal → **Contract** (Event Hub → Contract tab; cross-event register at `contracts.index`) drafted, sent, signed (`contract_status`: `draft → sent → partially_signed → signed`, or `void`) → **Budget** (Event Hub → Budget tab) tracks cost lines against categories → **Pricing/Invoice Items** (Event Hub → Pricing tab) sets what's billed → **Invoice** generated (`invoices.edit`, three-decimal JOD precision) → **Payments** recorded against contract installments (`payments.index`, cross-event ledger) or invoice lines (`payment_status`: `pending → partial → paid`) → **Finance Overview** (`finance.index`) rolls the whole book up above any one event's budget.

Related pages: `proposals.index`, `proposals.{proposal}`, Event Hub → Contract, `contracts.index`, Event Hub → Budget, Event Hub → Pricing, `invoices.index`, `invoices.{invoice}`, `payments.index`, `finance.index`.
End: invoice fully paid, contract signed, budget reconciled.

## 7. Procurement (Suppliers)
Start → `suppliers.index` (cross-event directory) → supplier attached to an event (via Budget line, Transport movement, or Exhibition/Catering order) → order status tracked per-context (transport uses `transport_status`: `planned → ordered → confirmed → in_progress → completed`, or `issue`/`cancelled`) → **Supplier Order** PDF (vendor-facing, no client price) sent to vendor for quoting/confirmation.

Related pages: `suppliers.index`, Event Hub → Suppliers tab, Event Hub → Budget (cost commitment), various per-module supplier-order exports.
End: order confirmed/completed, cost reconciled to Budget.

## 8. Transportation
Start → Event Hub → Transport tab (`TransportationTab`) → movements planned per guest/leg → **Transport Live** (phone-first, "its own route so it can be opened directly on a phone without walking the hub") for event-day tracking → **Transport Dispatch** (lanes against a time axis, desktop/tablet-only) for the ops-room view → movement status (`transport_status`) progresses `planned → ordered → confirmed → in_progress → completed`.

Document suite (each with exactly one reader, per its own route comments): Daily Movement Schedule (ops clipboard), Driver Trip Sheets (per-driver), VIP Transfer Sheets (per-VIP-guest), Transport Master Plan (client-facing summary), Supplier Order (vendor-facing), Transport Manifest (full chronological + fleet summary).

Related pages: Event Hub → Transport tab, `events.transport.live`, `events.transport.dispatch`, `settings/transport` (vehicle/driver master data), six PDF exports.
End: movement `completed`.

## 9. Accommodation
Start → Event Hub → Stay tab (`AccommodationTab`) → room block created → **Rooming List** import (XLSX, dynamic columns) or manual entry → block cost computed from nights × rooms × rate → **Rooming List PDF** (hotel-facing, deliberately money-free) exported to the hotel.

Related pages: Event Hub → Stay tab, `events.rooming.template`, `events.rooming.pdf`.
End: rooming list finalized and sent to hotel.

## 10. Exhibition
Start → Event Hub → Exhibition tab → **Exhibition Floor Plan builder** (`ExhibitionFloorPlan` — an interactive canvas builder, not just a list) → halls/booths laid out and priced → booth sold/reserved against an exhibitor or sponsor → **Exhibition Floor PDF** (dompdf) exports the plan + sales summary.

Related pages: Event Hub → Exhibition tab, `events.exhibition-floor` (builder), `events.exhibition-floor.pdf`.
End: floor sold out or event day arrives.

## 11. Reporting
Start → any module's own tab (each has its own internal metrics via the Universal Module Header / Inspector) → cross-event roll-up at `/reports` ("the book read across, rather than one event at a time") → AI-assisted synthesis at `/ai-assistant` ("what needs a person today, and where to find it") → Command Center dashboard (`home`) surfaces the same signals as the platform's front door, always-on.

Related pages: `reports.index`, `ai.index`, `home` (Command Center), every module's own Inspector panel, PDF/XLSX exports as documented evidence.
End: ongoing — reporting is continuous, not a terminal state.

---
**Cross-cutting note:** 10 named workflow-state machines exist in `App\Support\Workflow::SETS` — `event_stage`, `deal_stage`, `task_stage`, `task_priority`, `plan_status`, `session_status`, `attendee_status`, `transport_status`, `contract_status`, `payment_status`. These are the actual state vocabularies driving every workflow above; all are user-editable at Settings → Statuses & Colours (`WorkflowSettings`), labels/colours only — keys are fixed because code reasons about them directly (e.g. `won` opens an event, `done` closes a task count).
