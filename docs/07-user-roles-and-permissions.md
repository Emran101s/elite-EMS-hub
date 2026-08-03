# User Roles and Permissions

This is a direct reference to `app/Models/User.php` and the gate definitions in
`app/Providers/AppServiceProvider.php` — ground truth, not a paraphrase. If code and this
doc ever disagree, the code is right and this doc is stale.

## Roles and rank (`User::ROLES`, `User::ROLE_RANK`)

| Rank | Role | Label |
|---|---|---|
| 4 | `super_admin` | Super Admin |
| 3 | `admin` | Admin |
| 2 | `manager` | Manager |
| 1 | `coordinator` | Coordinator |
| 0 | `viewer` | Viewer |

`$user->isAtLeast('manager')` returns true for `manager`, `admin` and `super_admin` — rank
is a floor, not an exact match.

## Gates (`Gate::define`, in `AppServiceProvider::boot()`)

| Gate | Minimum role | Used for |
|---|---|---|
| `write` | coordinator | The default gate for routine, additive Livewire actions — creating an event, adding a budget line, saving a form. This is the floor: almost everything a coordinator does day to day. |
| `decide-approvals` | manager | Approving/rejecting an `EventApproval`. |
| `manage-budget` | manager | Approving, rejecting or revising a budget version — **not** routine budget-building (adding a line, editing an estimate), which stays at `write`. |
| `manage-contract` | manager | Contract-level decisions. |
| `manage-events` | manager | Higher-stakes event actions — duplicate/archive/delete an existing event (creating a **new** event is `write`, since that's routine and additive). |
| `manage-team` | admin | Managing other users' roles and access. |

## The mechanical guard

`tests/Unit/AuthorizationGuardTest.php` uses PHP reflection to scan every public,
non-lifecycle method on every `app/Livewire/**` class and fails the build if a method that
mutates the database has no `Gate::authorize()` / `$this->authorize()` call and isn't on an
explicit, commented allowlist. This exists because the platform previously had real,
undetected authorization gaps (see [10-current-codebase-assessment.md](10-current-codebase-assessment.md))
— **any new Livewire method that writes to the database must call the right gate, or the
test suite will catch it and fail.** This is enforced, not just documented.

## Practical rule for new features

Default new mutating actions to `write` (coordinator) unless the action is specifically a
financial decision, a contract decision, an approval decision, or a team/access change —
those get their own named gate at `manager` or `admin`. Don't invent a new gate name for
something that already fits one of the six above.
