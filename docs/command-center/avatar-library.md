# Event Avatar Library — System Plan

Premium visual identities for events. Every event carries an avatar from the library —
a "luxury miniature venue / digital twin template", never a generic icon.

## 1. UX flow (event creation)

```
Create New Event → Enter event info → Choose event type
    → system auto-recommends an avatar (gold "Recommended" badge, pre-selected)
    → user keeps it or picks another from the grid
    → Save → avatar appears everywhere the event appears
```

Rules:
- Changing the event type re-runs the recommendation *unless* the user already
  picked manually (manual choice is sticky — `avatarChosenManually` flag).
- The library grid inside the wizard is filterable by category and always shows
  the recommended card first.

## 2. Avatar Library page (`/events/avatars`)

- Header: title + "New Event" CTA.
- Filter chips: All · Conference · Gala · Exhibition · Workshop · VIP · Festival.
- Responsive card grid (3-up desktop / 2-up tablet / 1-up mobile).

## 3. Avatar card component

White card (`#FFFFFF`), soft border (`#E2E8F0`), rounded-2xl:
- Visual (SVG scene or uploaded image) in a 10:7 frame
- Name (navy bold) + subtitle (muted)
- "Best for" chips + palette dots (from `colors` JSON)
- Category tag; gold ring + badge when recommended/selected

## 4. Operations Hub

Island card = avatar thumbnail + event name/type/location + **status ring badge**
overlapping the avatar's corner (see §18). Orbit layout unchanged.

## 5. Event Control Room (future module)

Hero header: large avatar (lg size) left, event title/meta right, health ring
over the avatar corner. Same `<x-event-avatar>` component, `size="lg"`.

## 6–8. Data model

`event_avatars`
| column | type | notes |
|---|---|---|
| name / slug / subtitle | string | slug unique, drives built-in SVG component |
| category | string | conference · gala · exhibition · workshop · vip · festival |
| best_for | string | display copy |
| image_path / thumbnail_path | string nullable | uploaded renders (Phase later); SVG fallback when null |
| model_3d_path | string nullable | §17 — GLB/USDZ for 3D |
| supports_3d | bool | |
| colors | json | palette swatches |
| recommended_types | json | matches `Event::TYPES` |
| sort_order / is_active | int / bool | curation + soft retirement |

`events.avatar_id` → FK, nullable, nullOnDelete (deleting an avatar never breaks events).

Relationships: `EventAvatar hasMany Event`, `Event belongsTo EventAvatar`.

## 9. Seed data

Six launch avatars (see `EventAvatarSeeder`): International Conference,
Gala Dinner, Exhibition, Workshop, VIP Event, Festival / Outdoor.

## 10. API endpoints (when the API surface opens — Sanctum)

```
GET  /api/avatars?category=&type=      list (active only)
GET  /api/avatars/{slug}               show
GET  /api/avatars/recommend?type=gala  recommendation (same scope as web)
POST /api/avatars                      admin upload (multipart, policy-gated)
```

## 11. Component structure (Livewire + Blade — this platform's stack)

```
app/Livewire/EventCreate.php              wizard w/ live recommendation
resources/views/components/event-avatar.blade.php   universal renderer (+ring)
resources/views/components/avatars/*.blade.php      built-in SVG scenes (per slug)
resources/views/modules/avatar-library.blade.php    library page
```

`<x-event-avatar :event size ring>` is the ONE way avatars render anywhere
(hub, lists, control room, calendar, notifications, reports, mobile cards) —
one component to restyle, every surface follows.

## 12. Tailwind design tokens

Uses the existing theme: navy `#0B1F3A`, gold `#D4AF37`, page `#F8FAFC`-family,
line `#E2E8F0`-family, health colors `#22C55E` / `#F59E0B` / `#EF4444`, info `#3B82F6`.

## 13–14. Filtering & auto-suggestion

- Filter: `?category=` on the library page; category chips in the wizard grid.
- Suggest: `EventAvatar::recommendedFor($type)` = active + `recommended_types`
  JSON contains the type, ordered by `sort_order`. First hit is pre-selected.

## 15. Admin uploads (later phase)

Settings → Avatar Library (admin-gated): upload PNG/WebP (+auto thumbnail via
intervention/image), set name/category/colors/recommended types. Storage:
`storage/app/public/avatars/{slug}/`. `is_active` toggles availability.
Built-in SVGs stay as the zero-asset fallback.

## 16. Asset storage strategy

`image_path` null → render the built-in SVG Blade component for `slug`.
`image_path` set → `<img>` with `thumbnail_path` in small sizes. So art can be
upgraded avatar-by-avatar with zero code changes.

## 17. 3D-ready

`supports_3d` + `model_3d_path` (GLB). When the Operations Hub gets its 3D mode,
islands lazy-load `<model-viewer>`/three.js with the GLB and fall back to the
2D visual. No schema change needed later.

## 18. Status rings over avatars

`<x-event-avatar :ring="true">` overlays `<x-health-ring>` (progress % +
track/warn/risk color) on the avatar's bottom-right corner, white-backed.
The ring reads live event data — the avatar is identity, the ring is health.

## 19. Hub wiring

`CommandCenterService::islands()` eager-loads `avatar`; island cards render
`<x-event-avatar>`. Events without an avatar get a neutral placeholder frame.

## 20. Scale to 50+ avatars

- Library page: category chips + (later) search; grid paginates past 24.
- Wizard: recommended-first ordering keeps choice fast regardless of library size.
- `sort_order` curates; `is_active` retires without deleting history.
- Slug-driven rendering means new avatars = 1 DB row (+1 SVG file *or* 1 upload).
