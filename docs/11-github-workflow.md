# GitHub Workflow

## Repository

- Remote: `origin` → `https://github.com/Emran101s/elite-EMS-hub.git`
- `main` — the baseline/release branch. Recent `main` commits use a ticket-style prefix
  (`EBH-47 Event modules build-out — ...`).
- Long-running feature branches exist off `main` for sustained work (e.g.
  `ops-modules-and-design-system`, `phase-0-foundation`). Within those, commit messages
  drop the ticket prefix in favour of a plain, specific, present-tense description of what
  changed and — in the body — **why**, not just what.

## Commit message convention actually in use here

Subject line: short, specific, imperative or descriptive — not generic
("fix bug", "update file"). Examples from this repo's own history:

```
One equipment list per venue; the Equipment PDF stops printing blank
Venues priced by the day, and a floor plan that shows the layout
"From budget" reads the budget, not one field of it that is usually empty
```

Body (for anything non-trivial): explains the reasoning — what was wrong, why it mattered,
what was deliberately excluded and why. Multi-paragraph bodies are normal and expected for
substantial changes; see the two most recent commits
(`Stage 1: gate every mutating Livewire method...`, `Stage 2: one definition for money,
dates, and status colour`) for the target level of detail.

Co-authorship trailer is used when an AI assistant contributed:
```
Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
```
Cursor sessions should use the equivalent trailer for whichever model actually did the work,
for the same reason: an honest record of provenance.

## Hard rules observed in this repo (keep following them in Cursor)

- **Never** `git push --force` to `main` or to a shared branch without explicit, per-instance
  approval from the project owner.
- **Never** `git reset --hard`, `git clean -f`, or delete a branch without the owner's
  explicit go-ahead — check `git status` before anything that could discard uncommitted
  work.
- **Never** skip hooks (`--no-verify`) or bypass signing.
- Prefer new commits over `--amend` once something has been pushed.
- Stage specific files by name; avoid blanket `git add -A`/`git add .` without reviewing
  `git status` afterward, so nothing unintended (a stray local file, a credential) rides
  along.
- **Run the full test suite (`php artisan test`) before every commit that touches
  application code.** This repo's own recent history shows this discipline consistently —
  every substantial commit was preceded by a full green run, and the count is typically
  quoted in the commit body.

## Branch used for this handover

This handover's docs and any `CLAUDE.md` correction are committed on the current branch,
`ops-modules-and-design-system`, which is already the active feature branch containing the
Stage 1/2 work described in [10-current-codebase-assessment.md](10-current-codebase-assessment.md).
No new branch was created unless the project owner asked for one at handover time — see
[12-cursor-handover.md](12-cursor-handover.md) for the exact branch/commit this handover
produced.
