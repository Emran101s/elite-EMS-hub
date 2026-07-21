<div>
    @php
        $roleTone = [
            'super_admin' => 'bg-gold-50 text-gold-700 ring-gold-200',
            'admin' => 'bg-navy-900 text-white ring-navy-900',
            'manager' => 'bg-blue-50 text-blue-700 ring-blue-200',
            'coordinator' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'viewer' => 'bg-navy-50 text-navy-500 ring-line',
        ];
    @endphp

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('settings.index') }}" class="text-xs font-semibold text-muted hover:text-navy-900">← Settings</a>
            <h1 class="mt-1 text-lg font-bold text-navy-900">Team &amp; Roles</h1>
            <p class="text-xs text-muted">Everyone in your workspace — their role and profile photo.</p>
        </div>
        <div class="flex items-center gap-2">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search team…" class="input h-10 w-52 text-sm">
            <button type="button" wire:click="newItem" class="btn-gold h-10 px-4 text-xs">＋ Add member</button>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50/60 px-4 py-2 text-xs font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[620px]">
            <thead>
                <tr class="border-b border-line bg-page/40 text-left text-eyebrow font-bold uppercase tracking-wide text-muted">
                    <th class="px-5 py-2.5">Member</th>
                    <th class="px-3 py-2.5">Email</th>
                    <th class="px-3 py-2.5">Role</th>
                    <th class="px-3 py-2.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($members as $m)
                    <tr wire:key="m-{{ $m->id }}" wire:click="edit({{ $m->id }})" class="group cursor-pointer border-b border-line last:border-0 hover:bg-page/30">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <x-user-avatar :user="$m" size="h-10 w-10" text="text-xs" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-navy-900">{{ $m->name }}
                                        @if ($m->id === auth()->id())<span class="ml-1 text-eyebrow font-semibold text-muted">(you)</span>@endif
                                    </p>
                                    <p class="truncate text-micro text-muted">{{ $m->title ?: '—' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-xs text-navy-600">{{ $m->email }}</td>
                        <td class="px-3 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-eyebrow font-bold uppercase tracking-wide ring-1 {{ $roleTone[$m->role] ?? 'bg-navy-50 text-navy-500 ring-line' }}">{{ $m->roleLabel() }}</span>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <span class="text-eyebrow font-semibold text-navy-400 opacity-0 transition group-hover:opacity-100">Edit ✎</span>
                                @unless ($m->id === auth()->id())
                                    <button type="button" wire:click.stop="delete({{ $m->id }})" wire:confirm="Remove {{ $m->name }} from the team?" class="rounded-lg bg-risk/10 px-2 py-1 text-eyebrow font-bold text-red-700 opacity-0 transition hover:bg-risk/20 group-hover:opacity-100">✕</button>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-16 text-center">
                        <p class="text-sm font-semibold text-navy-900">No team members yet</p>
                        <button type="button" wire:click="newItem" class="btn-gold mt-4 h-10 px-5 text-xs">＋ Add your first member</button>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-center text-eyebrow text-muted">{{ $members->count() }} {{ str('member')->plural($members->count()) }}</p>

    {{-- modal --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit member' : 'Add member'" max="md" close="set('showForm', false)">

                <form wire:submit="save" class="grid gap-4">
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Profile photo (optional)</label>
                        <div class="flex items-center gap-3">
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-navy-900 text-sm font-bold text-gold-400 ring-2 ring-line">
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
                                       class="block w-full text-xs text-navy-600 file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-navy-800">
                                <div wire:loading wire:target="photo" class="mt-1 text-eyebrow font-semibold text-gold-700">Uploading…</div>
                                @error('photo')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                                <p class="mt-1 text-eyebrow text-muted">Leave blank to keep initials.</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Full name</label>
                        <input type="text" wire:model="name" class="input h-10 text-sm" placeholder="e.g. Layla Haddad">
                        @error('name')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Email</label>
                            <input type="email" wire:model="email" class="input h-10 text-sm" placeholder="name@company.com">
                            @error('email')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Role</label>
                            <select wire:model="role" class="input h-10 text-sm">
                                @foreach ($roles as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Job title (optional)</label>
                        <input type="text" wire:model="title" class="input h-10 text-sm" placeholder="e.g. Event Coordinator">
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save,photo" class="btn-navy h-10 px-6 text-xs">{{ $editingId ? 'Update' : 'Add member' }}</button>
                    </div>
                </form>
        </x-modal>
    @endif
</div>
