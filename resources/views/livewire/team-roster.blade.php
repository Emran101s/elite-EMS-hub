<div>
    @php
        // Five roles, five distinct eo-pill tones — no off-palette blue for
        // "manager": teal (eo-pill-live) is the closest informational tone
        // the eo palette actually has.
        $roleTone = [
            'super_admin' => 'eo-pill-premium',
            'admin' => 'eo-pill bg-eo-navy text-white',
            'manager' => 'eo-pill-live',
            'coordinator' => 'eo-pill-ok',
            'viewer' => 'eo-pill-pending',
        ];
    @endphp

    <x-eo.team-header title="Team & Roles" subtitle="Everyone in your workspace — their role and profile photo.">
        <x-slot:actions>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search team…" class="eo-input h-10 w-52 text-sm">
            <x-eo.button size="sm" wire:click="newItem" class="h-10">＋ Add member</x-eo.button>
        </x-slot:actions>
    </x-eo.team-header>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-eo-ok/30 bg-eo-ok-soft px-4 py-2 text-xs font-semibold text-eo-ok">{{ session('status') }}</div>
    @endif

    {{-- user cards --}}
    <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
        @forelse ($members as $m)
            <div wire:key="m-{{ $m->id }}" class="group eo-soft-card flex flex-col overflow-hidden transition hover:-translate-y-0.5">
                <button type="button" wire:click="edit({{ $m->id }})" class="flex flex-1 flex-col p-5 text-left">
                    <div class="flex items-start gap-3">
                        <x-user-avatar :user="$m" size="h-12 w-12" text="text-sm" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-eo-text">{{ $m->name }}@if ($m->id === auth()->id())<span class="ml-1 text-[10px] font-semibold text-eo-muted">(you)</span>@endif</p>
                            <p class="truncate text-micro text-eo-muted">{{ $m->title ?: '—' }}</p>
                        </div>
                        <span class="{{ $roleTone[$m->role] ?? 'eo-pill-pending' }} shrink-0">{{ $m->roleLabel() }}</span>
                    </div>
                    @if ($m->email)
                        <p class="mt-3 flex items-center gap-1.5 truncate text-micro text-eo-muted"><x-icon name="identification" class="h-3 w-3 shrink-0 text-eo-muted" />{{ $m->email }}</p>
                    @endif
                </button>

                {{-- light footer --}}
                <div class="mt-auto flex items-center gap-2 border-t border-eo-line bg-eo-workspace px-3.5 py-2 text-eo-text">
                    <x-icon name="users" class="h-3 w-3 shrink-0 text-eo-gold" />
                    <span class="truncate text-[11px] font-semibold text-eo-muted">{{ $m->roleLabel() }}</span>
                    <div class="ml-auto flex items-center gap-1">
                        <button type="button" wire:click="edit({{ $m->id }})" class="rounded-lg bg-eo-bg px-1.5 py-1 text-[10px] font-bold text-eo-muted opacity-0 transition hover:bg-eo-line group-hover:opacity-100" title="Edit">✎</button>
                        @unless ($m->id === auth()->id())
                            <x-confirm title="Remove {{ $m->name }} from the team?" confirm="Remove" run="$wire.delete({{ $m->id }})" class="rounded-lg bg-eo-risk/10 px-1.5 py-1 text-[10px] font-bold text-eo-risk opacity-0 transition hover:bg-eo-risk/20 group-hover:opacity-100">✕</x-confirm>
                        @endunless
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full eo-soft-card px-6 py-16 text-center">
                <p class="text-sm font-semibold text-eo-text">No team members yet</p>
                <x-eo.button size="sm" wire:click="newItem" class="mt-4">＋ Add your first member</x-eo.button>
            </div>
        @endforelse
    </div>
    <p class="mt-3 text-center text-[11px] text-eo-muted">{{ $members->count() }} {{ str('member')->plural($members->count()) }}</p>

    {{-- modal --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit member' : 'Add member'" max="md" close="set('showForm', false)">

                <form wire:submit="save" class="grid gap-4">
                    <div>
                        <label class="eo-label mb-1">Profile photo (optional)</label>
                        <div class="flex items-center gap-3">
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-eo-navy text-sm font-bold text-eo-gold ring-2 ring-eo-line">
                                @if ($photo)
                                    <img src="{{ $photo->temporaryUrl() }}" class="h-full w-full object-cover" alt="preview">
                                @elseif ($editingId && ($u = \App\Models\User::find($editingId)) && $u->avatar_path)
                                    <img src="{{ asset($u->avatar_path) }}" class="h-full w-full object-cover" alt="current">
                                @else
                                    {{ $name ? \Illuminate\Support\Str::of($name)->explode(' ')->filter()->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('') : '—' }}
                                @endif
                            </span>
                            <div class="flex-1">
                                <input type="file" wire:model="photo" accept="image/png,image/jpeg,image/webp"
                                       class="block w-full text-xs text-eo-muted file:mr-3 file:rounded-lg file:border-0 file:bg-eo-navy file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-eo-navy-deep">
                                <div wire:loading wire:target="photo" class="mt-1 text-[11px] font-semibold text-eo-teal-ink">Uploading…</div>
                                @error('photo')<p class="mt-1 text-xs text-eo-risk">{{ $message }}</p>@enderror
                                <p class="mt-1 text-[11px] text-eo-muted">Leave blank to keep initials.</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="eo-label mb-1">Full name</label>
                        <input type="text" wire:model="name" class="eo-input h-10 text-sm" placeholder="e.g. Layla Haddad">
                        @error('name')<p class="mt-1 text-xs text-eo-risk">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="eo-label mb-1">Email</label>
                            <input type="email" wire:model="email" class="eo-input h-10 text-sm" placeholder="name@company.com">
                            @error('email')<p class="mt-1 text-xs text-eo-risk">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="eo-label mb-1">Role</label>
                            <select wire:model="role" class="eo-select h-10 text-sm">
                                @foreach ($roles as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="eo-label mb-1">Job title (optional)</label>
                        <input type="text" wire:model="title" class="eo-input h-10 text-sm" placeholder="e.g. Event Coordinator">
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showForm', false)" class="eo-btn-ghost eo-btn-sm">Cancel</button>
                        <x-eo.button type="submit" size="sm" wire:loading.attr="disabled" wire:target="save,photo">{{ $editingId ? 'Update' : 'Add member' }}</x-eo.button>
                    </div>
                </form>
        </x-modal>
    @endif
</div>
