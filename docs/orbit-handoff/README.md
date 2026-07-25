# ORBIT v2 — handoff package

Four files. Drop all four in your Laravel repo root.

| File | What it is |
|---|---|
| `orbit-v2-hub-system.html` | The design system. Open it in a browser — it's live and interactive. This is the visual source of truth Claude Code reads. |
| `setup-orbit.sh` | Bootstrap. Extracts the CSS out of the HTML and writes `CLAUDE.md`. Writes no Blade, PHP or JS. |
| `KICKOFF-PROMPT.txt` | The prompts. One per session, plus corrective prompts for when the agent drifts. |
| `CLAUDE-CODE-BRIEF.md` | The full spec Claude Code reads: component map, data contracts, phases, acceptance criteria. |

## Run order

```bash
# in your repo root
cp ~/Downloads/orbit-v2-hub-system.html .
cp ~/Downloads/setup-orbit.sh .
cp ~/Downloads/CLAUDE-CODE-BRIEF.md .
chmod +x setup-orbit.sh

git add -A && git commit -m "docs: ORBIT v2 design system"

./setup-orbit.sh --check     # look before you leap
./setup-orbit.sh             # writes the CSS + CLAUDE.md
```

Then add the four `@import` lines it prints to `resources/css/app.css`, install
the fonts, commit, and paste **Session 1** from `KICKOFF-PROMPT.txt` into Claude Code.

## What the script writes

```
resources/css/orbit-tokens.css   64 design tokens × 2 themes   GENERATED
resources/css/orbit.css          141 component classes, 253 rules  GENERATED
resources/css/orbit-theme.css    Tailwind @theme wiring + print
CLAUDE.md                        guardrails the agent must follow
```

The three CSS files are build artefacts. Never edit them by hand — change the
HTML and re-run the script. `setup-orbit.sh --force` overwrites; without it,
existing files are skipped.

## Why a script instead of letting the agent copy the CSS

64 tokens across two themes and 141 component classes is mechanical work with no judgement in it. An agent
retyping that will introduce silent errors, and every wrong hex quietly breaks a
contrast guarantee that was measured, not assumed. So the script does the
mechanical part exactly, and Claude Code does everything that needs thinking:
the Blade components, the screens, the data model, the migration.

## The two rules that matter most

1. **Never say "redesign my app to look like this."** You'll get one beautiful
   screen and forty broken ones. Work bottom-up: tokens → components →
   navigation → screens.
2. **One session per phase.** Review and merge before starting the next. The
   kickoff file is already split this way.
