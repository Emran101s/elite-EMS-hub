<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('settings.index') }}" class="text-xs font-semibold text-muted hover:text-navy-900">← Settings</a>
            <h1 class="mt-1 text-lg font-bold text-navy-900">Clients</h1>
            <p class="text-xs text-muted">The organizations you run events for — each event attaches to one.</p>
        </div>
        <div class="flex items-center gap-2">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search clients…" class="input h-10 w-52 text-sm">
            <button type="button" wire:click="newItem" class="btn-gold h-10 px-4 text-xs">＋ Add client</button>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50/60 px-4 py-2 text-xs font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[640px]">
            <thead>
                <tr class="border-b border-line bg-page/40 text-left text-eyebrow font-bold uppercase tracking-wide text-muted">
                    <th class="px-5 py-2.5">Client</th>
                    <th class="px-3 py-2.5">Primary contact</th>
                    <th class="px-3 py-2.5 text-center">Events</th>
                    <th class="px-3 py-2.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clients as $c)
                    <tr wire:key="c-{{ $c->id }}" wire:click="edit({{ $c->id }})" class="group cursor-pointer border-b border-line last:border-0 hover:bg-page/30">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                @if ($c->logo_path)
                                    <img src="{{ asset($c->logo_path) }}" class="h-10 w-10 shrink-0 rounded-xl bg-white object-contain ring-1 ring-line" alt="{{ $c->name }}">
                                @else
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-navy-900 text-xs font-bold text-gold-400 ring-1 ring-line">{{ $c->initials() }}</span>
                                @endif
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-navy-900">{{ $c->name }}</p>
                                    <p class="truncate text-micro text-muted">{{ $c->organization ?: '—' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3">
                            <p class="text-xs font-semibold text-navy-700">{{ $c->primaryContact?->name ?: '—' }}</p>
                            <p class="text-micro text-muted">{{ $c->email ?: $c->phone ?: '' }}</p>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <span class="rounded-full bg-navy-50 px-2.5 py-0.5 text-eyebrow font-bold text-navy-600">{{ $c->events_count }}</span>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex items-center justify-end gap-1">
                                {{-- The record is where people, deals and history live;
                                     this form only edits the organisation itself. --}}
                                <a href="{{ route('crm.client', $c) }}" wire:click.stop
                                   class="rounded-lg bg-navy-50 px-2 py-1 text-eyebrow font-bold text-navy-600 transition hover:bg-navy-900 hover:text-white">Record →</a>
                                <span class="text-eyebrow font-semibold text-navy-400 opacity-0 transition group-hover:opacity-100">Edit ✎</span>
                                <x-confirm title="Delete {{ $c->name }}?"
                                           :body="$c->events_count ? $c->events_count.' event(s) will be unlinked.' : null"
                                           confirm="Delete" run="$wire.delete({{ $c->id }})"
                                           class="rounded-lg bg-risk/10 px-2 py-1 text-eyebrow font-bold text-red-700 opacity-0 transition hover:bg-risk/20 group-hover:opacity-100">✕</x-confirm>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-16 text-center">
                        <p class="text-sm font-semibold text-navy-900">No clients yet</p>
                        <button type="button" wire:click="newItem" class="btn-gold mt-4 h-10 px-5 text-xs">＋ Add your first client</button>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-center text-eyebrow text-muted">{{ $clients->count() }} {{ str('client')->plural($clients->count()) }}</p>

    {{-- modal --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit client' : 'Add client'" max="lg" close="set('showForm', false)">

                <form wire:submit="save" class="grid gap-4">
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Logo (optional)</label>
                        <div class="flex items-center gap-3">
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-navy-900 text-sm font-bold text-gold-400 ring-1 ring-line">
                                @if ($logo)
                                    <img src="{{ $logo->temporaryUrl() }}" class="h-full w-full object-contain" alt="preview">
                                @elseif ($editingId && ($c = \App\Models\Client::find($editingId)) && $c->logo_path)
                                    <img src="{{ asset($c->logo_path) }}" class="h-full w-full object-contain" alt="current">
                                @else
                                    {{ $name ? \Illuminate\Support\Str::of($name)->explode(' ')->filter()->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('') : '—' }}
                                @endif
                            </span>
                            <div class="flex-1">
                                <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                       class="block w-full text-xs text-navy-600 file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-navy-800">
                                <div wire:loading wire:target="logo" class="mt-1 text-eyebrow font-semibold text-gold-700">Uploading…</div>
                                @error('logo')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Client name</label>
                            <input type="text" wire:model="name" class="input h-10 text-sm" placeholder="e.g. Qatar Tech Authority">
                            @error('name')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Industry / sector</label>
                            <input type="text" wire:model="organization" class="input h-10 text-sm" placeholder="e.g. Government">
                        </div>
                    </div>

                    {{-- People live on the client record now, where a client can
                         have several of them. This form is the organisation only. --}}
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Website</label>
                        <input type="text" wire:model="website" class="input h-10 text-sm" placeholder="e.g. qta.gov.qa">
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Email</label>
                            <input type="email" wire:model="email" class="input h-10 text-sm" placeholder="name@client.com">
                            @error('email')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Phone</label>
                            <input type="text" wire:model="phone" class="input h-10 text-sm" placeholder="+974 …">
                        </div>
                    </div>

                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Notes (optional)</label>
                        <textarea wire:model="notes" rows="2" class="input text-sm" placeholder="Relationship notes, billing details, etc."></textarea>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save,logo" class="btn-navy h-10 px-6 text-xs">{{ $editingId ? 'Update' : 'Add client' }}</button>
                    </div>
                </form>
        </x-modal>
    @endif
</div>
