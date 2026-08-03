# Event Life Cycle

## The actual stages (`Event::STAGES`)

```
draft → proposal → confirmed → planning → production → live → completed → closed
                                                              (+ cancelled, on_hold)
```

**What the product needs to be changes at each stage.** A design or feature that only
works at "production" density fails for most of an event's life:

- **draft / proposal** — almost no data. A name, a date, a client. Every module is empty.
  This is where a design most often looks broken if empty states aren't treated as a first
  impression.
- **confirmed / planning** — the build-out. Budget lines, agenda, supplier outreach. Data
  arrives unevenly (e.g. 41 sessions but zero attendees yet).
- **production** — everything at once. Peak density, peak stress, the heaviest month(s).
- **live** — show days. Effectively a different product: real-time, glanceable, mobile,
  two-tap actions (see Transport's Live Operations view).
- **completed / closed** — retrospective. Reports, final P&L, archive.
- **cancelled / on_hold** — off the main line, not a stage to design around but a state
  every module needs to render sensibly for.

## Mapping to the target seven-phase model

The requested framing (Initiate → Plan → Prepare → Execute → Monitor → Close → Evaluate) is
a **conceptual** model, useful for describing the product's rhythm — it isn't a proposed
change to `Event::STAGES`, which stays the actual state machine in code.

| Conceptual phase | Actual `Event::STAGES` | Notes |
|---|---|---|
| **Initiate** | `draft`, `proposal` | Client and shape are still forming. |
| **Plan** | `confirmed`, `planning` | The build-out described above. |
| **Prepare** | `production` | Final supplier/logistics lock-in before doors open. |
| **Execute** | `live` | Show days — a different interaction mode. |
| **Monitor** | *cross-cutting, not a stage* | The Health Engine, risk register and approvals run continuously through Prepare and Execute — not a discrete state. |
| **Close** | `completed` | Wrap-up: final invoices, outstanding payments, documents filed. |
| **Evaluate** | `closed` | Retrospective: reports, lessons learned (not yet built — see [09-development-roadmap.md](09-development-roadmap.md)). |

## Why this matters for Cursor

Any new screen or feature should be checked against at least the empty (`draft`/`proposal`)
and the dense (`production`/`live`) ends of this range before being called done. A view
that only looks right with 40 tasks and 600 attendees, and a view that only looks right with
zero of everything, are both incomplete.
