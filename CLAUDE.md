# Elite Business Hub (new platform)

Standalone events-operations platform. **Not** the emranspace repo — this is a fresh build;
never modify `~/Herd/emranspace` from here.

- Stack: Laravel 13, Livewire 4, Vite + Tailwind v4, SQLite (local)
- Served by Herd at http://elitehub.test · dev server 127.0.0.1:8912
- Homepage = Command Center dashboard (portfolio level), then per-event modules
- Run the suite with `php artisan test`; build with `npm run build`

> The navy `#0B1F3A` / gold `#D4AF37` palette is the **outgoing** system.
> ORBIT replaces it. See below.

---

## Design system: ORBIT

The visual specification is `orbit-system.html` in the repo root.
Open it in a browser — it is live and interactive — and read the CSS in
its `<style>` block. That file is the source of truth for every colour,
size, radius, shadow and class name. Section 09 is the target layout.

`resources/css/orbit-tokens.css` and `resources/css/orbit.css` are
**generated** from that HTML by `setup-orbit.sh`. Never edit them by hand.
If a token genuinely needs to change, change the HTML and re-run the script.

### The five laws

1. **One light source.** Gold marks the action, the position, the one number.
2. **Distance is relevance.** Modules orbit the event core.
3. **Light is home.** Dark is a mode you enter for show days.
4. **Numbers are typography.** Every figure in the data face.
5. **Stillness is a feature.** Motion means state changed.

### The two golds — the rule most likely to be broken

- `--gold` **#8A6209** — text, icons, labels. Passes AA on light surfaces.
- `--gold-lit` **#E8B84B** — fills, rings, active states, dock highlight.
  Carries dark text at 9.16:1. As text on a light surface it is 1.69:1.
  **Never use --gold-lit as a text colour.**

Every signal colour follows the same pattern: `--vital` reads,
`--vital-lit` fills. Same for ion, plasma, flare, critical.

### Hard rules

- Never introduce a colour, radius, shadow, font-size, spacing value or
  transition duration that is not already a token.
- Gold appears on at most **three** elements per viewport.
- Signal colours mean state. Never use one decoratively.
- Exactly one `data-gravity="hero"` card per screen.
- Every number is wrapped in `.o-num` or a metric class. Money and counts
  are right-aligned in tables.
- Status colour comes from `App\Support\Tone`. No colour literals in Blade.
- `.o-ring` is for single values only. Splits use `.o-meter`.
  Never add a donut chart or a chart library to this product.
- Empty states teach the concept. "No data" is not an acceptable string.
- Any new component must be added to the `/design` gallery in the same PR.
- Respect `prefers-reduced-motion`. Only two animations loop in the whole
  system: the overdue pulse and the live-phase breathe.

### Chrome is constant

The KPI strip, the bottom dock and the Command Center disc stay dark in
**both** themes. They use `--chrome`, not `--hull`. Fixed furniture that
changes colour when the theme switches makes the product feel unstable.

### Theme

**Light is the default.** Dark is for show days and live operations.
`App\Support\ThemePolicy` decides per module and prints the result into
`<html data-theme="...">`. Never hard-code a theme in a view. The ratio is
a single constant in that class — change it there, nowhere else.

### Order of work

Bottom-up, always: tokens -> components -> navigation -> screens.
Never rebuild a screen before the components it needs exist.
One phase per PR, behind a feature flag, with before/after screenshots.

See `CLAUDE-CODE-BRIEF.md` for the full phase plan and module checklist.
