@php
    $statusMeta = [
        'invited' => ['Invited', 'bg-page text-muted'],
        'confirmed' => ['Confirmed', 'bg-success-soft text-success-ink'],
        'declined' => ['Declined', 'bg-danger-soft text-danger-ink'],
        'cancelled' => ['Cancelled', 'bg-page text-muted'],
    ];
    $spacesDefault = $speakers->isNotEmpty() && $speakers->count() <= 6 ? 'cards' : 'list';
@endphp
<div>
    {{-- Speakers / Confirmed / Keynotes / Speaker fees are the same figures
         the Universal Module Header already shows above this component, and
         "＋ Add Speaker" already lives in the toolbar below and the empty
         state — a right-side control rail here would have carried nothing
         the list itself doesn't already show. --}}
    <div x-data="{
                 mode: (() => {
                     const saved = localStorage.getItem('elitehub.speakers.mode');
                     return saved === 'list' || saved === 'cards' ? saved : @js($spacesDefault);
                 })(),
                 setMode(m) { this.mode = m; localStorage.setItem('elitehub.speakers.mode', m); }
             }">
            @if ($speakers->isEmpty())
                <x-empty icon="sparkles" title="No speakers yet"
                         hint="Add keynotes, panellists and moderators — track invitations, confirmations and fees.">
                    <x-slot:actions>
                        <button type="button" wire:click="newItem" class="h-10 rounded-full bg-gold-500 px-5 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">＋ Add the first speaker</button>
                    </x-slot:actions>
                </x-empty>
            @else
                <div class="mb-2.5 flex flex-wrap items-center justify-between gap-2">
                    <x-bulk-bar :count="$this->selectedCount()" noun="speaker" />
                    <div class="ms-auto flex items-center gap-2">
                        <span role="group" aria-label="Speaker layout" class="inline-flex items-center rounded-full border border-line bg-white p-0.5">
                            <button type="button" @click="setMode('list')" :aria-pressed="mode === 'list'"
                                    :class="mode === 'list' ? 'bg-navy-900 text-white' : 'text-muted hover:text-ink'"
                                    class="rounded-full px-2.5 py-1.5 text-eyebrow font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gold-400 focus-visible:ring-offset-1">List</button>
                            <button type="button" @click="setMode('cards')" :aria-pressed="mode === 'cards'"
                                    :class="mode === 'cards' ? 'bg-navy-900 text-white' : 'text-muted hover:text-ink'"
                                    class="rounded-full px-2.5 py-1.5 text-eyebrow font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gold-400 focus-visible:ring-offset-1">Cards</button>
                        </span>
                        <button type="button" wire:click="newItem" class="h-8 rounded-full bg-gold-500 px-3 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">＋ Add Speaker</button>
                    </div>
                </div>

                <div x-show="mode === 'list'" x-cloak class="overflow-hidden rounded-lg border border-line bg-white">
                    <ul class="divide-y divide-line">
                        @foreach ($speakers as $s)
                            @php [$stLabel, $stClass] = $statusMeta[$s->status] ?? $statusMeta['invited']; @endphp
                            <li wire:key="sp-list-{{ $s->id }}" class="group flex flex-wrap items-center gap-x-2.5 gap-y-1.5 px-3.5 py-2 transition hover:bg-page {{ $this->isSelected($s->id) ? 'bg-page' : '' }}">
                                <button type="button" wire:click="toggleSelect({{ $s->id }})" class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-eyebrow {{ $this->isSelected($s->id) ? 'border-navy-900 bg-navy-900 text-white' : 'border-line text-transparent hover:border-muted' }}" title="Select">✓</button>
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-navy-900 text-eyebrow font-bold text-gold-400">{{ $s->initials() }}</span>
                                <div class="order-last w-full min-w-0 sm:order-none sm:w-auto sm:flex-1">
                                    <p class="truncate text-[13px] font-bold text-ink">
                                        {{ $s->name }}
                                        @if ($s->is_keynote)<span class="ms-1 rounded bg-gold-50 px-1 text-eyebrow font-bold uppercase text-gold-700">Keynote</span>@endif
                                    </p>
                                    <p class="truncate text-eyebrow text-muted">{{ $s->title }}@if ($s->title && $s->organization) · @endif{{ $s->organization }}@if ($s->topic) · "{{ \Illuminate\Support\Str::limit($s->topic, 40) }}"@endif</p>
                                    @if ($s->sessions->isNotEmpty())
                                        <p class="truncate text-eyebrow text-muted">On agenda: {{ $s->sessions->pluck('title')->implode(', ') }}</p>
                                    @endif
                                </div>
                                <span class="hidden shrink-0 rounded-full px-2 py-0.5 text-eyebrow font-bold sm:inline-flex {{ $stClass }}">{{ $stLabel }}</span>
                                <span class="hidden w-20 shrink-0 text-right text-eyebrow font-bold tabular-nums text-ink sm:block">{{ $s->fee_cents ? $event->money($s->fee_cents) : '—' }}</span>
                                <span class="flex gap-0.5 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100">
                                    @if ($s->status !== 'confirmed')
                                        <button type="button" wire:click="setStatus({{ $s->id }}, 'confirmed')" class="rounded-md bg-success-soft px-1.5 py-0.5 text-eyebrow font-bold text-success-ink hover:bg-success-soft/70">✓</button>
                                    @endif
                                    <button type="button" wire:click="edit({{ $s->id }})" class="rounded-md bg-page px-1.5 py-0.5 text-eyebrow font-bold text-muted hover:bg-line">✎</button>
                                    <x-confirm title="Remove {{ $s->name }}?" confirm="Remove" run="$wire.delete({{ $s->id }})"
                                               class="rounded-md bg-danger-soft px-1.5 py-0.5 text-eyebrow font-bold text-danger-ink hover:bg-danger-soft/70">✕</x-confirm>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div x-show="mode === 'cards'" x-cloak class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($speakers as $s)
                        @php [$stLabel, $stClass] = $statusMeta[$s->status] ?? $statusMeta['invited']; @endphp
                        <div wire:key="sp-card-{{ $s->id }}" class="group/sp flex flex-col overflow-hidden rounded-lg border border-line bg-white {{ $this->isSelected($s->id) ? '!border-navy-900 ring-2 ring-navy-900' : '' }}">
                            <div class="flex flex-1 flex-col p-3.5">
                                <div class="flex items-start justify-between gap-2.5">
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        <button type="button" wire:click="toggleSelect({{ $s->id }})" class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-eyebrow {{ $this->isSelected($s->id) ? 'border-navy-900 bg-navy-900 text-white' : 'border-line text-transparent hover:border-muted' }}" title="Select">✓</button>
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-navy-900 text-sm font-bold text-gold-400">{{ $s->initials() }}</span>
                                        <div class="min-w-0">
                                            <p class="flex flex-wrap items-center gap-1.5 text-sm font-bold text-ink">{{ $s->name }}
                                                @if ($s->is_keynote)<span class="rounded-full bg-gold-50 px-2 py-0.5 text-eyebrow font-bold text-gold-700">Keynote</span>@endif
                                            </p>
                                            <p class="truncate text-[10.5px] text-muted">{{ $s->title }}@if ($s->title && $s->organization) · @endif{{ $s->organization }}</p>
                                        </div>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-eyebrow font-bold {{ $stClass }}">{{ $stLabel }}</span>
                                </div>
                                @if ($s->topic)<p class="mt-2.5 rounded-lg bg-page px-2.5 py-1.5 text-xs italic text-ink">"{{ $s->topic }}"</p>@endif
                                @if ($s->sessions->isNotEmpty())
                                    <p class="mt-2 truncate text-eyebrow text-muted">On agenda: {{ $s->sessions->pluck('title')->implode(', ') }}</p>
                                @endif
                            </div>
                            <div class="mt-auto flex items-center gap-2 border-t border-line bg-page px-3.5 py-2">
                                <span class="truncate text-eyebrow font-semibold text-ink">{{ $s->fee_cents ? $event->money($s->fee_cents).' fee' : 'No fee' }}</span>
                                <div class="ms-auto flex items-center gap-1 opacity-100 sm:opacity-0 sm:transition sm:group-hover/sp:opacity-100">
                                    @if ($s->status !== 'confirmed')
                                        <button type="button" wire:click="setStatus({{ $s->id }}, 'confirmed')" class="rounded-md bg-success-soft px-2 py-0.5 text-eyebrow font-bold text-success-ink hover:bg-success-soft/70">✓ Confirm</button>
                                    @endif
                                    <button type="button" wire:click="edit({{ $s->id }})" class="rounded-md bg-white px-1.5 py-0.5 text-eyebrow font-bold text-muted ring-1 ring-line hover:ring-navy-300">✎</button>
                                    <x-confirm title="Remove {{ $s->name }}?" confirm="Remove" run="$wire.delete({{ $s->id }})"
                                               class="rounded-md bg-danger-soft px-1.5 py-0.5 text-eyebrow font-bold text-danger-ink hover:bg-danger-soft/70">✕</x-confirm>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
    </div>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit speaker' : 'New speaker'" max="xl" close="set('showForm', false)">
                <form wire:submit="save" class="grid gap-3.5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Full name</label>
                        <input type="text" wire:model="name" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Dr. Layla Haddad">
                        @error('name')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Job title</label>
                        <input type="text" wire:model="title" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Minister of Economy">
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Organization</label>
                        <input type="text" wire:model="organization" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Government of Jordan">
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Talk / topic</label>
                        <input type="text" wire:model="topic" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="The Future of the Arab Economy">
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Email</label>
                        <input type="email" wire:model="email" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="—">
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Phone</label>
                        <input type="text" wire:model="phone" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="—">
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Status</label>
                        <select wire:model="status" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                            @foreach (\App\Models\EventSpeaker::STATUSES as $st)<option value="{{ $st }}">{{ ucfirst($st) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Fee ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="fee" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="0">
                    </div>
                    <label class="flex items-center gap-2 sm:col-span-2">
                        <input type="checkbox" wire:model="is_keynote" class="h-4 w-4 rounded border-line text-gold-700">
                        <span class="text-xs font-semibold text-ink">Keynote speaker</span>
                    </label>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Bio</label>
                        <textarea wire:model="bio" rows="2" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none text-sm" placeholder="Short biography…"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-muted hover:text-ink">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="h-10 rounded-full bg-gold-500 px-6 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update' : 'Add speaker' }}</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
        </x-modal>
    @endif
</div>
