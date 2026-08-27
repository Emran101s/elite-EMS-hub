@php $sel = $selected; @endphp

<div class="space-y-5">

    <x-cc.header eyebrow="Commercial Command" title="Clients" subtitle="Queue → Client → Action Panel. Organizations you run summits, forums, and exhibitions for.">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-2.5 py-1 text-[10.5px] font-bold uppercase tracking-wide text-gold-700">
                <span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Accounts
            </span>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search clients…"
                       class="h-10 w-52 rounded-full border border-line bg-white pl-9 pr-3 text-[12.5px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
            </div>
            <button type="button" wire:click="newItem" class="rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:-translate-y-0.5 hover:bg-gold-400">＋ Add client</button>
        </x-slot:actions>
    </x-cc.header>

    @if (session('status'))
        <div class="flex items-center gap-2 rounded-lg border border-success-soft bg-success-soft px-4 py-2.5 text-[12.5px] font-semibold text-success-ink">{{ session('status') }}</div>
    @endif

    @if ($clients->isEmpty())
        <x-eo.empty-state title="No clients yet" hint="Add the organizations you run events for." icon="identification">
            <x-slot:actions>
                <button type="button" wire:click="newItem" class="rounded-full bg-gold-500 px-4 py-2 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">＋ Add your first client</button>
            </x-slot:actions>
        </x-eo.empty-state>
    @else
        <div class="grid gap-4 xl:grid-cols-12">
            <div class="xl:col-span-4">
                <x-billing.queue title="Client queue">
                    @foreach ($clients as $c)
                        @php $active = $sel?->id === $c->id; @endphp
                        <button type="button" wire:click="select({{ $c->id }})" wire:key="c-{{ $c->id }}" class="w-full text-start">
                            @if ($active)
                                <div class="rounded-lg bg-navy-900 p-4">
                                    <div class="flex items-center gap-3">
                                        @if ($c->logo_path)
                                            <img src="{{ asset($c->logo_path) }}" class="h-10 w-10 rounded-lg object-contain ring-1 ring-white/20" alt="">
                                        @else
                                            <span class="grid h-10 w-10 place-items-center rounded-lg bg-white/10 text-[11px] font-bold text-gold-400">{{ $c->initials() }}</span>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="truncate text-[14px] font-semibold text-white">{{ $c->name }}</p>
                                            <p class="truncate text-[12px] text-white/50">{{ $c->organization ?: '—' }}</p>
                                        </div>
                                    </div>
                                    <p class="mt-3 text-[12px] text-white/60">{{ $c->events_count }} {{ str('event')->plural($c->events_count) }}</p>
                                </div>
                            @else
                                <div class="flex items-center gap-3 rounded-lg border border-line bg-white px-4 py-3 transition hover:border-navy-300 hover:shadow-float">
                                    @if ($c->logo_path)
                                        <img src="{{ asset($c->logo_path) }}" class="h-9 w-9 rounded-lg object-contain ring-1 ring-line" alt="">
                                    @else
                                        <span class="grid h-9 w-9 place-items-center rounded-lg bg-navy-900 text-[10px] font-bold text-gold-400">{{ $c->initials() }}</span>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-[13px] font-bold text-ink">{{ $c->name }}</p>
                                        <p class="truncate text-[11px] text-muted">{{ $c->primaryContact?->name ?: 'No primary contact' }}</p>
                                    </div>
                                    <span class="text-[11px] font-bold text-muted">{{ $c->events_count }}</span>
                                </div>
                            @endif
                        </button>
                    @endforeach
                </x-billing.queue>
            </div>

            <div class="xl:col-span-5">
                @if ($sel)
                    <x-cc.briefing-panel :title="$sel->name" :subtitle="$sel->organization ?: 'Client account'">
                        <div class="mb-4 flex items-center gap-3">
                            @if ($sel->logo_path)
                                <img src="{{ asset($sel->logo_path) }}" class="h-14 w-14 rounded-lg object-contain ring-1 ring-line" alt="">
                            @else
                                <span class="grid h-14 w-14 place-items-center rounded-lg bg-navy-900 text-sm font-bold text-gold-400">{{ $sel->initials() }}</span>
                            @endif
                            <span class="inline-flex items-center rounded-full bg-info-soft px-2.5 py-1 text-[11px] font-bold text-info-ink">{{ $sel->events_count }} events</span>
                        </div>
                        <div class="space-y-2 text-[13px]">
                            @foreach ([
                                ['Primary contact', $sel->primaryContact?->name ?: '—'],
                                ['Email', $sel->email ?: '—'],
                                ['Phone', $sel->phone ?: '—'],
                                ['Website', $sel->website ?: '—'],
                            ] as [$k, $v])
                                <div class="flex justify-between gap-3 border-b border-line/70 pb-2">
                                    <span class="text-muted">{{ $k }}</span>
                                    <span class="truncate font-semibold text-ink">{{ $v }}</span>
                                </div>
                            @endforeach
                        </div>
                        @if ($sel->notes)
                            <p class="mt-4 rounded-lg bg-page px-3 py-2 text-[12px] text-muted">{{ $sel->notes }}</p>
                        @endif
                    </x-cc.briefing-panel>
                @endif
            </div>

            <div class="xl:col-span-3">
                <x-billing.action-panel title="Account actions">
                    @if ($sel)
                        <a href="{{ route('crm.client', $sel) }}" class="flex w-full items-center justify-center rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">Open record →</a>
                        <button type="button" wire:click="edit({{ $sel->id }})" class="flex w-full items-center justify-center rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:border-navy-300">Edit organisation</button>
                        <x-confirm title="Delete {{ $sel->name }}?"
                                   :body="$sel->events_count ? $sel->events_count.' event(s) will be unlinked.' : null"
                                   confirm="Delete" run="$wire.delete({{ $sel->id }})"
                                   class="flex w-full items-center justify-center rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-danger-ink transition hover:border-navy-300">Delete</x-confirm>
                    @else
                        <p class="text-[12px] text-muted">Select a client from the queue.</p>
                    @endif
                </x-billing.action-panel>
            </div>
        </div>
    @endif

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit client' : 'Add client'" max="lg" close="set('showForm', false)">
            <form wire:submit="save" class="grid gap-4">
                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Logo (optional)</label>
                    <div class="flex items-center gap-3">
                        <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-navy-900 text-sm font-bold text-gold-600 ring-1 ring-line">
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
                                   class="block w-full text-xs text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white">
                            <div wire:loading wire:target="logo" class="mt-1 text-[11px] font-semibold text-gold-700">Uploading…</div>
                            @error('logo')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Client name</label>
                        <input type="text" wire:model="name" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none" placeholder="e.g. Qatar Tech Authority">
                        @error('name')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Industry / sector</label>
                        <input type="text" wire:model="organization" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none" placeholder="e.g. Government">
                    </div>
                </div>
                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Website</label>
                    <input type="text" wire:model="website" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none" placeholder="e.g. qta.gov.qa">
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Email</label>
                        <input type="email" wire:model="email" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
                        @error('email')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Phone</label>
                        <input type="text" wire:model="phone" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
                    </div>
                </div>
                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Notes</label>
                    <textarea wire:model="notes" rows="2" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('showForm', false)" class="btn-sm rounded-full border border-line font-semibold text-muted transition hover:text-ink">Cancel</button>
                    <button type="submit" class="rounded-full bg-gold-500 px-4 py-2 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">{{ $editingId ? 'Update' : 'Add client' }}</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
