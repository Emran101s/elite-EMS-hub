<div>
    @php
        // Five roles, five distinct semantic tones.
        $roleTone = [
            'super_admin' => 'bg-gold-50 text-gold-700',
            'admin' => 'bg-navy-900 text-white',
            'manager' => 'bg-info-soft text-info-ink',
            'coordinator' => 'bg-success-soft text-success-ink',
            'viewer' => 'bg-page text-muted',
        ];
    @endphp

    <x-cc.header eyebrow="Team Command" title="Team & Roles" subtitle="Everyone in your workspace — their role and profile photo.">
        <x-slot:actions>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search team…"
                   class="h-10 w-52 rounded-full border border-line bg-white px-3.5 text-[12.5px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
            <button type="button" wire:click="newItem" class="rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:-translate-y-0.5 hover:bg-gold-400">＋ Add member</button>
        </x-slot:actions>
    </x-cc.header>

    @if (session('status'))
        <div class="mb-4 mt-4 rounded-lg border border-success-soft bg-success-soft px-4 py-2.5 text-[12.5px] font-semibold text-success-ink">{{ session('status') }}</div>
    @endif

    {{-- user cards --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
        @forelse ($members as $m)
            <div wire:key="m-{{ $m->id }}" class="group flex flex-col overflow-hidden rounded-lg border border-line bg-white transition hover:-translate-y-0.5 hover:shadow-float">
                <button type="button" wire:click="edit({{ $m->id }})" class="flex flex-1 flex-col p-4 text-left">
                    <div class="flex items-start gap-3">
                        <x-user-avatar :user="$m" size="h-12 w-12" text="text-sm" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13.5px] font-bold text-ink">{{ $m->name }}@if ($m->id === auth()->id())<span class="ml-1 text-[10px] font-semibold text-muted">(you)</span>@endif</p>
                            <p class="truncate text-[11px] text-muted">{{ $m->title ?: '—' }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $roleTone[$m->role] ?? 'bg-page text-muted' }}">{{ $m->roleLabel() }}</span>
                    </div>
                    @if ($m->email)
                        <p class="mt-3 flex items-center gap-1.5 truncate text-[11px] text-muted"><x-icon name="identification" class="h-3 w-3 shrink-0 text-muted" />{{ $m->email }}</p>
                    @endif
                </button>

                {{-- light footer --}}
                {{-- The role pill is already on the card, directly above this
                     line — printing roleLabel() again here told you nothing
                     twice. This is the question a roster exists to answer:
                     what is this person carrying, and are they behind. --}}
                <div class="mt-auto flex items-center gap-2 border-t border-line bg-page px-3.5 py-2">
                    <x-icon name="clipboard" class="h-3 w-3 shrink-0 text-gold-600" />
                    @if ($m->open_tasks_count)
                        <span class="truncate text-[11px] font-semibold text-ink">
                            {{ $m->open_tasks_count }} open {{ str('task')->plural($m->open_tasks_count) }}
                        </span>
                        @if ($m->overdue_tasks_count)
                            <span class="shrink-0 rounded-full bg-danger-soft px-1.5 py-0.5 text-[9.5px] font-bold uppercase text-danger-ink">
                                {{ $m->overdue_tasks_count }} overdue
                            </span>
                        @endif
                    @else
                        <span class="truncate text-[11px] font-semibold text-muted">Nothing assigned</span>
                    @endif
                    <div class="ml-auto flex items-center gap-1">
                        <button type="button" wire:click="edit({{ $m->id }})" class="rounded-md bg-page px-1.5 py-1 text-[10px] font-bold text-muted opacity-0 transition hover:bg-line group-hover:opacity-100" title="Edit">✎</button>
                        @unless ($m->id === auth()->id())
                            <x-confirm title="Remove {{ $m->name }} from the team?" confirm="Remove" run="$wire.delete({{ $m->id }})" class="rounded-md bg-danger-soft px-1.5 py-1 text-[10px] font-bold text-danger-ink opacity-0 transition hover:bg-danger-soft/70 group-hover:opacity-100">✕</x-confirm>
                        @endunless
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-lg border border-line bg-white px-6 py-16 text-center">
                <p class="text-[13.5px] font-semibold text-ink">No team members yet</p>
                <button type="button" wire:click="newItem" class="mt-4 rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:-translate-y-0.5 hover:bg-gold-400">＋ Add your first member</button>
            </div>
        @endforelse
    </div>
    <p class="mt-3 text-center text-[11px] text-muted">{{ $members->count() }} {{ str('member')->plural($members->count()) }}</p>

    {{-- modal --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit member' : 'Add member'" max="md" close="set('showForm', false)">

                <form wire:submit="save" class="grid gap-4">
                    <div>
                        <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Profile photo (optional)</label>
                        <div class="flex items-center gap-3">
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-navy-900 text-sm font-bold text-gold-600 ring-2 ring-line">
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
                                       class="block w-full text-xs text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-navy-950">
                                <div wire:loading wire:target="photo" class="mt-1 text-[11px] font-semibold text-gold-700">Uploading…</div>
                                @error('photo')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                                <p class="mt-1 text-[11px] text-muted">Leave blank to keep initials.</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Full name</label>
                        <input type="text" wire:model="name" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="e.g. Layla Haddad">
                        @error('name')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Email</label>
                            <input type="email" wire:model="email" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="name@company.com">
                            @error('email')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Role</label>
                            <select wire:model="role" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink focus:border-navy-300 focus:outline-none h-10 text-sm">
                                @foreach ($roles as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Job title (optional)</label>
                        <input type="text" wire:model="title" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="e.g. Event Coordinator">
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showForm', false)" class="btn-sm rounded-full border border-line font-semibold text-ink transition hover:border-gold-300">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save,photo" class="rounded-full bg-gold-500 px-4 py-2 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400 disabled:opacity-60">{{ $editingId ? 'Update' : 'Add member' }}</button>
                    </div>
                </form>
        </x-modal>
    @endif
</div>
