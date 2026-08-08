# Cleanup Report — Master IA Rebuild

**Date:** 2026-08-08  
**Rule followed:** Do not delete working source code, migrations, models, routes, or Livewire components.

## Archived (safe)

| Path | Reason |
|---|---|
| `docs/archive/orbit-ia-brief.md` | Superseded IA brief |
| `docs/archive/orbit-migration-plan.md` | Superseded migration plan |
| `docs/archive/README.md` | Explains archive |

## Left in place (unsure / still useful)

| Path | Why kept |
|---|---|
| `docs/18-phase1-platform-audit.md` | Historical audit evidence |
| `docs/19-internal-improvement-plan.md` | Historical P0 tracking |
| `docs/17-postgres-cutover-plan.md` | Technique reference; strategy now MySQL (`docs/22`) |
| `/concept/flow`, `/concept/nav` | Prototype routes still registered; not in primary nav |
| `resources/views/modules/*.blade.php` | Some still serve live routes (projects, tasks, settings, sponsors) |

## Not removed (production code)

- All migrations, models, Livewire components, PDF controllers, gates, EventPolicy.

## Recommended future cleanup (do not rush)

1. Confirm orphan `modules/team.blade.php` if still unused → archive view only.  
2. Retire concept routes once Orbit language is fully retired from UI chrome.  
3. Revisit `config/modules.php` labels to match Company Command naming (reachability test depends on routes, not labels).
