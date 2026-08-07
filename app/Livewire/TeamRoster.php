<?php

namespace App\Livewire;

use App\Models\User;
use App\Notifications\TeamInvite;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app', ['title' => 'Team & Roles'])]
class TeamRoster extends Component
{
    use WithFileUploads;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $title = '';

    public string $role = 'coordinator';

    /** Uploaded profile photo (optional). */
    public $photo = null;

    public string $search = '';

    public function newItem(): void
    {
        $this->reset(['editingId', 'name', 'email', 'title', 'photo']);
        $this->role = 'coordinator';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $u = User::findOrFail($id);
        $this->editingId = $u->id;
        $this->name = $u->name;
        $this->email = $u->email;
        $this->title = $u->title ?? '';
        $this->role = $u->role ?? 'coordinator';
        $this->photo = null;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('manage-team');
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($this->editingId)],
            'title' => ['nullable', 'string', 'max:120'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $fields = [
            'name' => trim($this->name),
            'email' => trim($this->email),
            'title' => trim($this->title) ?: null,
            'role' => $this->role,
        ];

        if ($this->photo) {
            $fields['avatar_path'] = 'storage/'.$this->photo->store('avatars', 'public');
        }

        $isNewMember = ! $this->editingId;

        if ($this->editingId) {
            // Through the model, not the query builder: a mass update fires no
            // Eloquent events, so User's Auditable trait never ran and a role
            // change made here — the only place a human can make one — left no
            // record at all.
            $user = User::findOrFail($this->editingId);
            $user->update($fields);
        } else {
            // Nobody ever types this password — it exists only because the
            // column is required. The account becomes usable when TeamInvite's
            // link lets them set their own.
            $user = User::create($fields + ['password' => Hash::make(Str::password(24))]);
            $user->notify(new TeamInvite(Password::createToken($user)));
        }

        // What isAtLeast() actually reads (User::activeWorkspace()) is
        // workspace_user.role, not this row's role column. This is the one
        // place a human sets a role, so it is the one place that has to keep
        // both in sync — users.role stays a written, readable mirror; nothing
        // else in this screen or the audit trail needs to change to know that.
        if ($workspace = auth()->user()->activeWorkspace()) {
            $workspace->grant($user, $this->role);
        }

        $this->showForm = false;
        $this->reset(['editingId', 'photo']);
        session()->flash('status', $isNewMember
            ? 'Team member added — an email to set their password has been sent.'
            : 'Team member saved.');
    }

    public function delete(int $id): void
    {
        Gate::authorize('manage-team');
        if ($id === auth()->id()) {
            session()->flash('error', "You can't remove your own account.");

            return;
        }
        User::whereKey($id)->delete();
    }

    public function render()
    {
        $members = User::when($this->search !== '', fn ($q) => $q
            ->where(fn ($w) => $w->where('name', 'like', '%'.$this->search.'%')->orWhere('email', 'like', '%'.$this->search.'%')))
            ->orderBy('name')->get();

        return view('livewire.team-roster', [
            'members' => $members,
            'roles' => User::ROLES,
        ]);
    }
}
