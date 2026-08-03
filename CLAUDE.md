# Elite Business Hub (new platform)

Standalone events-operations platform. **Not** the emranspace repo — this is a fresh build;
never modify `~/Herd/emranspace` from here.

- Stack: Laravel 13, Livewire 4, Vite + Tailwind v4, SQLite (local)
- Served by Herd at http://elitehub.test · dev server 127.0.0.1:8912
- Homepage = Command Center dashboard (portfolio level), then per-event modules
- Run the suite with `php artisan test`; build with `npm run build`

See `/docs` for the full product, architecture and design-system reference — start with
`docs/09-development-roadmap.md` and `docs/10-current-codebase-assessment.md`.

## Design system: Command Center (navy / gold / Playfair)

The navy `#0B1F3A` / gold `#D4AF37` palette on a light canvas, with Playfair Display for
titles, is the **current, live** system. An earlier attempt to replace it with a system
called "ORBIT" (`App\Support\Tone`, `orbit-tokens.css`) was built and then **reverted** —
that class and those files no longer exist in the codebase. `orbit-system.html` and
`setup-orbit.sh` at the repo root are inert leftovers from that attempt; nothing generates
from them anymore.

Full detail — status-colour conventions, the money/date consolidation, the one deliberate
exception (Plan Studio keeps its own visual identity) — is in `docs/08-design-system-rules.md`.

## Hard rules

- **Do not redesign the sidebar. Do not redesign the navigation. Do not redesign existing
  screens.** This is a standing instruction, not a "not yet gotten to."
- Money formatting goes through `App\Support\Money` (`forScreen`/`forDocument`/`abbreviated`).
- Dates render through `<x-date :value="..." style="short|document|long|withTime">`.
- Status colour comes from the owning model's own `statusMeta()`-style method, or the
  shared `<x-status-badge>` for genuinely generic statuses — never a new local
  formatter/colour map hand-rolled in a view. See `docs/10-current-codebase-assessment.md`
  for why this is enforced (two real bugs traced back to exactly this pattern).
- Every database-mutating Livewire method must call an authorization gate
  (`Gate::authorize`/`$this->authorize`) — `tests/Unit/AuthorizationGuardTest.php`
  reflects over every `app/Livewire/**` method and fails the build otherwise. See
  `docs/07-user-roles-and-permissions.md` for the six gates and their role thresholds.
- Multi-tenant SaaS is a deliberately deferred later phase — don't build toward it
  incidentally. See `docs/01-product-vision.md`.
