# Elite Orbit Soft Command — Phase 1 Review Package

**Branch:** `cursor/soft-command-phase1`  
**Status:** Ready for design review — **STOP** (no App Shell / page redesign yet)  
**Gallery URL (local):** `/design/soft-command` → `http://elitehub.test/design/soft-command`

---

## What shipped

Design System Foundation only:

1. Theme tokens (`resources/css/eo-soft-command.css`)
2. Typography system (Plus Jakarta Sans + scale utilities)
3. Color system (navy / teal / soft gray / gold brand / status)
4. Button system (`.eo-btn-*` + `x-eo.button`)
5. Form system (`.eo-input`, `.eo-select`, `.eo-textarea`, `.eo-field-label`)
6. Table system (`.eo-table` + `x-eo.smart-table`)
7. Status system (`x-eo.status-pill` tones)
8. Card system (`x-eo.soft-card`, selected dark, readiness, module, alert, empty)
9. Shared component library under `resources/views/components/eo/`

### Components

| Component | Purpose |
|-----------|---------|
| `x-eo.soft-card` | Light workspace card |
| `x-eo.selected-dark-card` | Active queue item (navy) |
| `x-eo.page-header` | Page title + actions |
| `x-eo.status-pill` | Status chips |
| `x-eo.metric-pill` | Compact KPI |
| `x-eo.queue-list` | Master column |
| `x-eo.detail-panel` | Detail column |
| `x-eo.action-panel` | Action column |
| `x-eo.smart-table` | Soft data table |
| `x-eo.filter-bar` | Search + filters |
| `x-eo.readiness-card` | Progress readiness |
| `x-eo.module-card` | Module entry card |
| `x-eo.empty-state` | Empty state |
| `x-eo.alert-card` | Inline alert |
| `x-eo.button` | Button variants |

### Demo page

- Route: `GET /design/soft-command` (`design.soft-command`)
- Layout: `x-layouts.eo-gallery` (standalone Soft Command chrome — **does not** replace the live app shell)

---

## Explicitly out of scope (Phase 1)

- App Shell / navigation redesign
- Any product page redesign
- PR #44 merge
- Routes beyond the gallery demo route
- Permissions / EventPolicy / database / migrations / MySQL cutover

---

## Token reference

| Token | Value | Role |
|-------|-------|------|
| Deep Navy | `#0B1322` | Structure / rail later |
| Orbit Navy | `#101A29` | Selected dark surfaces |
| Teal | `#1EACAC` | Operational actions |
| Soft Gray | `#E6E9EE` | Workspace canvas |
| Soft White | `#F8F9FA` | Secondary surfaces |
| Card | `#FFFFFF` | Soft cards |
| Gold | `#D6AE34` / `#F4D76B` | Brand highlights only |
| Risk / Warn / OK | `#F45B5B` / `#F5A94E` / `#2CC36B` | Status |

Typography: **Plus Jakarta Sans** (gallery layout loads Bunny Fonts).

---

## Review checklist

- [ ] Palette matches Soft Command screenshots (soft gray, navy selection, teal CTAs)
- [ ] Gold is brand-only (not primary buttons)
- [ ] Cards feel soft (large radius, soft shadow, no harsh borders)
- [ ] Master → Detail → Action composition reads clearly
- [ ] Type hierarchy feels premium / calm
- [ ] Approve to proceed to **Phase 2: App Shell**

---

## After approval

Phase 2 starts App Shell only (slim dark icon rail + context sidebar + Soft Command workspace chrome). No full-platform page rewrite until later phases.
