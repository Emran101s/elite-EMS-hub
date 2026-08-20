@php $sel = $selected; @endphp

<div class="eo-event-atmosphere space-y-5 rounded-[24px]">

    <x-eo.page-header
        eyebrow="Commercial Command"
        title="Clients"
        subtitle="Queue → Client → Action Panel. Organizations you run summits, forums, and exhibitions for."
    >
        <x-slot:actions>
            <span class="eo-journey-chip">Accounts</span>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-eo-muted" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search clients…"
                       class="eo-input h-10 w-52 !py-0 !ps-9 text-xs">
            </div>
            <x-eo.button size="sm" wire:click="newItem">＋ Add client</x-eo.button>
        </x-slot:actions>
    </x-eo.page-header>

    @if (session('status'))
        <x-eo.alert-card tone="ok" title="{{ session('status') }}" />
    @endif

    @if ($clients->isEmpty())
        <x-eo.empty-state title="No clients yet" hint="Add the organizations you run events for." icon="identification">
            <x-slot:actions>
                <x-eo.button wire:click="newItem">＋ Add your first client</x-eo.button>
            </x-slot:actions>
        </x-eo.empty-state>
    @else
        <div class="grid gap-4 xl:grid-cols-12">
            <div class="xl:col-span-4">
                <x-eo.queue-list title="Client queue">
                    <x-slot:header>
                        <span class="text-[11px] font-bold text-eo-muted">{{ $clients->count() }}</span>
                    </x-slot:header>
                    @foreach ($clients as $c)
                        @php $active = $sel?->id === $c->id; @endphp
                        <button type="button" wire:click="select({{ $c->id }})" wire:key="c-{{ $c->id }}" class="w-full text-start">
                            @if ($active)
                                <x-eo.selected-dark-card>
                                    <div class="flex items-center gap-3">
                                        @if ($c->logo_path)
                                            <img src="{{ asset($c->logo_path) }}" class="h-10 w-10 rounded-xl object-contain ring-1 ring-white/20" alt="">
                                        @else
                                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-eo-teal/20 text-[11px] font-bold text-eo-teal-lit">{{ $c->initials() }}</span>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="truncate text-[14px] font-semibold text-white">{{ $c->name }}</p>
                                            <p class="truncate text-[12px] text-white/50">{{ $c->organization ?: '—' }}</p>
                                        </div>
                                    </div>
                                    <p class="mt-3 text-[12px] text-white/60">{{ $c->events_count }} {{ str('event')->plural($c->events_count) }}</p>
                                </x-eo.selected-dark-card>
                            @else
                                <div class="flex items-center gap-3 rounded-2xl border border-eo-line bg-white px-4 py-3 transition hover:border-eo-teal/30 hover:shadow-eo">
                                    @if ($c->logo_path)
                                        <img src="{{ asset($c->logo_path) }}" class="h-9 w-9 rounded-xl object-contain ring-1 ring-eo-line" alt="">
                                    @else
                                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-eo-navy text-[10px] font-bold text-eo-gold">{{ $c->initials() }}</span>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-[13px] font-bold text-eo-text">{{ $c->name }}</p>
                                        <p class="truncate text-[11px] text-eo-muted">{{ $c->primaryContact?->name ?: 'No primary contact' }}</p>
                                    </div>
                                    <span class="text-[11px] font-bold text-eo-muted">{{ $c->events_count }}</span>
                                </div>
                            @endif
                        </button>
                    @endforeach
                </x-eo.queue-list>
            </div>

            <div class="xl:col-span-5">
                @if ($sel)
                    <x-eo.detail-panel title="{{ $sel->name }}" subtitle="{{ $sel->organization ?: 'Client account' }}">
                        <div class="mb-4 flex items-center gap-3">
                            @if ($sel->logo_path)
                                <img src="{{ asset($sel->logo_path) }}" class="h-14 w-14 rounded-2xl object-contain ring-1 ring-eo-line" alt="">
                            @else
                                <span class="grid h-14 w-14 place-items-center rounded-2xl bg-eo-navy text-sm font-bold text-eo-gold">{{ $sel->initials() }}</span>
                            @endif
                            <div>
                                <x-eo.status-pill tone="live">{{ $sel->events_count }} events</x-eo.status-pill>
                            </div>
                        </div>
                        <div class="space-y-2 text-[13px]">
                            @foreach ([
                                ['Primary contact', $sel->primaryContact?->name ?: '—'],
                                ['Email', $sel->email ?: '—'],
                                ['Phone', $sel->phone ?: '—'],
                                ['Website', $sel->website ?: '—'],
                            ] as [$k, $v])
                                <div class="flex justify-between gap-3 border-b border-eo-line/70 pb-2">
                                    <span class="text-eo-muted">{{ $k }}</span>
                                    <span class="truncate font-semibold text-eo-text">{{ $v }}</span>
                                </div>
                            @endforeach
                        </div>
                        @if ($sel->notes)
                            <p class="mt-4 rounded-xl bg-eo-workspace px-3 py-2 text-[12px] text-eo-muted">{{ $sel->notes }}</p>
                        @endif
                    </x-eo.detail-panel>
                @endif
            </div>

            <div class="xl:col-span-3">
                <x-eo.action-panel title="Account actions">
                    @if ($sel)
                        <x-eo.button href="{{ route('crm.client', $sel) }}" class="w-full justify-center" size="sm">Open record →</x-eo.button>
                        <x-eo.button variant="ghost" wire:click="edit({{ $sel->id }})" class="w-full justify-center" size="sm">Edit organisation</x-eo.button>
                        <x-confirm title="Delete {{ $sel->name }}?"
                                   :body="$sel->events_count ? $sel->events_count.' event(s) will be unlinked.' : null"
                                   confirm="Delete" run="$wire.delete({{ $sel->id }})"
                                   class="eo-btn-ghost eo-btn-sm w-full justify-center text-eo-risk-ink">Delete</x-confirm>
                    @else
                        <p class="text-[12px] text-eo-muted">Select a client from the queue.</p>
                    @endif
                </x-eo.action-panel>
            </div>
        </div>
    @endif

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit client' : 'Add client'" max="lg" close="set('showForm', false)">
            <form wire:submit="save" class="grid gap-4">
                <div>
                    <label class="eo-label mb-1">Logo (optional)</label>
                    <div class="flex items-center gap-3">
                        <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-eo-navy text-sm font-bold text-eo-gold ring-1 ring-eo-line">
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
                                   class="block w-full text-xs text-eo-muted file:mr-3 file:rounded-lg file:border-0 file:bg-eo-navy file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white">
                            <div wire:loading wire:target="logo" class="mt-1 text-[11px] font-semibold text-eo-teal-ink">Uploading…</div>
                            @error('logo')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="eo-label mb-1">Client name</label>
                        <input type="text" wire:model="name" class="eo-input" placeholder="e.g. Qatar Tech Authority">
                        @error('name')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="eo-label mb-1">Industry / sector</label>
                        <input type="text" wire:model="organization" class="eo-input" placeholder="e.g. Government">
                    </div>
                </div>
                <div>
                    <label class="eo-label mb-1">Website</label>
                    <input type="text" wire:model="website" class="eo-input" placeholder="e.g. qta.gov.qa">
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="eo-label mb-1">Email</label>
                        <input type="email" wire:model="email" class="eo-input">
                        @error('email')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="eo-label mb-1">Phone</label>
                        <input type="text" wire:model="phone" class="eo-input">
                    </div>
                </div>
                <div>
                    <label class="eo-label mb-1">Notes</label>
                    <textarea wire:model="notes" rows="2" class="eo-input"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('showForm', false)" class="eo-btn-ghost eo-btn-sm">Cancel</button>
                    <x-eo.button type="submit" size="sm">{{ $editingId ? 'Update' : 'Add client' }}</x-eo.button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
