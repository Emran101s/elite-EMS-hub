# ORBIT — Elite Event Hub design system

Light canvas. Gold accent. The Command Center disc anchoring an orbit of modules,
a dark instrument strip across the top, a floating dock at the bottom.

**Use only this folder.** Earlier folders in the outputs directory are superseded.

| File | What it is |
|---|---|
| `orbit-system.html` | The design system. Open it in a browser — live and interactive, theme toggle bottom-right. **Section 09 is your layout, fully built.** This is the source of truth Claude Code reads. |
| `setup-orbit.sh` | Bootstrap. Extracts the CSS out of the HTML and writes `CLAUDE.md`. Writes no Blade, PHP or JS. |
| `KICKOFF-PROMPT.txt` | The prompts. One per session, covering all 25 modules plus cross-cutting work, and corrective prompts for when the agent drifts. |
| `CLAUDE-CODE-BRIEF.md` | The full spec: component map, Tone enum, ThemePolicy, phases, acceptance criteria. |

## Do this now

```bash
# 1 — in your repo root
cp ~/Downloads/orbit-system.html .
cp ~/Downloads/setup-orbit.sh .
cp ~/Downloads/CLAUDE-CODE-BRIEF.md .
chmod +x setup-orbit.sh
git add -A && git commit -m "docs: ORBIT design system"

# 2 — extract the CSS and write the guardrails
./setup-orbit.sh --check     # look first
./setup-orbit.sh             # then write

# 3 — add these four lines to resources/css/app.css
#     @import './orbit-tokens.css';
#     @import 'tailwindcss';
#     @import './orbit-theme.css';
#     @import './orbit.css';

# 4 — fonts
npm i @fontsource/inter @fontsource/jetbrains-mono @fontsource/instrument-serif

git add -A && git commit -m "chore: ORBIT tokens and guardrails"
```

**5 —** open Claude Code and paste **Session 1** from `KICKOFF-PROMPT.txt`. Nothing else.

## What the script writes

```
resources/css/orbit-tokens.css   72 tokens, light default + dark theme   GENERATED
resources/css/orbit.css          140 component classes, 256 rules        GENERATED
resources/css/orbit-theme.css    Tailwind @theme wiring + print rules
CLAUDE.md                        guardrails the agent must follow
```

Build artefacts. Never edit them by hand — change the HTML and re-run the script.
`--force` overwrites; without it, existing files are skipped.

## The one rule that will get broken

Gold has **two values with two jobs.**

- `--gold` **#8A6209** — text, icons, labels. 5.48:1 on white.
- `--gold-lit` **#E8B84B** — fills, rings, active states, dock highlight. Carries
  dark text at 9.16:1.

`--gold-lit` as text on a light surface is **1.69:1** — invisible. Every signal
colour works the same way: `--vital` reads, `--vital-lit` fills.

The other easy mistake: the KPI strip, the dock and the Command Center disc use
`--chrome` and stay dark in **both** themes. They are furniture, not surfaces.

## Coverage

Sessions 1–4 change 100% of the platform on their own — every screen re-skins the
moment the tokens and components land. Sessions 5a–5n then recompose all 25 modules
by name; Session 6 covers auth, global chrome, empty states, error and loading
states, print/PDF, email templates and responsive.

Session **5d is the important one** — it rebuilds the Event Hub to match section 09
exactly. Every other screen inherits from that layout, so review it carefully before
letting the agent move on.

## Two rules for working with the agent

1. **Never say "redesign my app to look like this."** You'll get one beautiful screen
   and forty broken ones. Bottom-up: tokens → components → navigation → screens.
2. **One session at a time.** Review and merge before the next. The kickoff file is
   already split this way.
