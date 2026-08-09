# Soft Command — Phase 2 Shell Review

**Status:** Ready for review — **STOP** (no product page redesign yet)  
**Branch:** `cursor/soft-command-phase1`  
**Shell preview:** http://elitehub.test/design/soft-command-shell  
**Component gallery:** http://elitehub.test/design/soft-command  

---

## Design language refinements (pre–Phase 2)

Incorporated into Soft Command without redesigning Phase 1:

1. **Event DNA** — journey chips, event atmosphere, summit/forum/exhibition language  
2. **Mission Radar** — signature `x-eo.mission-radar` (+ rail mark)  
3. **Domain cards** — Mission / Readiness / Operations / Commercial / Event Health  
4. **Teal depth** — soft gradients, glow, elevated active states (executive, not cyberpunk)

---

## Phase 2 shell components

| Component | Role |
|-----------|------|
| `x-eo.app-shell` | Full Soft Command chrome |
| `x-eo.mini-rail` | Slim dark icon rail (teal active) |
| `x-eo.context-sidebar` | Domain context map |
| `x-eo.top-command-bar` | Search + crumbs + teal CTA |
| `x-eo.workspace-shell` | Soft gray workspace canvas |

Demo layout: `x-layouts.eo-shell`  
**Live product layout (`x-layouts.app`) is unchanged** until shell approval.

---

## Explicitly out of scope

- Product page redesigns  
- PR #44 merge  
- Permissions / EventPolicy / DB / migrations  
- Replacing live chrome globally  

---

## After approval

Phase 3 candidates: Command Center + Event Portfolio adoption of Soft Command shell + Mission Radar.
