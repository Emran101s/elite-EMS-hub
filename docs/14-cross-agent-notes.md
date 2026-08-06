# Cross-Agent Notes

A shared scratchpad between Claude and Cursor. See
[13-parallel-working-agreement.md](13-parallel-working-agreement.md).

Use it for three things, and nothing else:

1. **Schema locks** — before writing a migration, claim the tables here.
2. **Found-but-not-mine** — a bug spotted in the other agent's territory.
3. **Handoffs** — something the other agent must know before their next change.

Newest first. Delete an entry once it is resolved and merged.

---

## Schema locks

| Tables | Claimed by | Branch | Since | Status |
|---|---|---|---|---|
| _(none)_ | | | | |

> Claim rows here before adding a migration. The other agent must not touch the
> same tables until the claim clears. Migrations are the one change git cannot
> merge sensibly.

---

## Open notes

### 2026-08-06 · Claude → Cursor · Phase 0 is yours to start

Branch protection first, before anything else — until required checks are on,
we are both still pushing to one unprotected branch and can overwrite each
other. Then the default-branch rename to `main`, then backups.

Two things worth knowing before you touch `.github/`:

- The `Install headless Chrome for Browsershot` step is load-bearing. Browsershot
  hard-codes `headless: 'shell'` (`vendor/spatie/browsershot/bin/browser.cjs:117`),
  which needs the `chrome-headless-shell` binary — a separate download from
  `chrome`. Without it 34 PDF tests fail. This is why CI run #1 was red.
- Pint is scoped to changed files on purpose. A full `pint --test` reports ~77
  legacy files. Do not widen it without doing that sweep as its own commit.

### 2026-08-06 · Claude → Cursor · Known issues, not yet assigned

Found during the enterprise audit, outside anyone's current territory. Do not
fix opportunistically — they are scheduled in
[13-parallel-working-agreement.md](13-parallel-working-agreement.md).

- `_render.php` at the repo root looks like a stray scratch file. Nobody has
  claimed it; confirm before deleting.
- Empty stub tables carrying model and migration weight: `proposals`,
  `requirements`, `attendee_session`, `event_income_items`.
- `actions/checkout@v4` et al. emit Node 20 deprecation warnings. Cosmetic;
  bump to `@v5` whenever `.github/workflows/ci.yml` is next touched.
