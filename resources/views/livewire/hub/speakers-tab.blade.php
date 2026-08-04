@php
    $statusMeta = [
        'invited' => ['Invited', 'bg-navy-100 text-navy-600'],
        'confirmed' => ['Confirmed', 'bg-emerald-100 text-emerald-700'],
        'declined' => ['Declined', 'bg-red-100 text-red-700'],
        'cancelled' => ['Cancelled', 'bg-navy-100 text-navy-400'],
    ];
    $moduleHex = \App\Models\Event::moduleColor('speakers');
    $spacesDefault = $speakers->isNotEmpty() && $speakers->count() <= 6 ? 'cards' : 'list';
@endphp
<div>
    <x-stat-strip class="mb-4" :stats="[
        ['Speakers', $speakers->count(), 'sparkles', null, null, $keynotes ? $keynotes.' keynote' : null],
        ['Confirmed', $confirmed, 'check', $speakers->count() ? round($confirmed / max($speakers->count(), 1) * 100) : null, 'bg-emerald-500', null, 'text-emerald-600'],
        ['Keynotes', $keynotes, 'star', null, null, null, 'text-gold-600'],
        ['Speaker fees', $event->money($feeTotal), 'currency', null, null, null, 'text-navy-900'],
    ]" />

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
        <div class="min-w-0" x-data="{
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
                        <button type="button" wire:click="newItem" class="btn-gold h-10 px-5 text-xs">＋ Add the first speaker</button>
                    </x-slot:actions>
                </x-empty>
            @else
                <div class="mb-2.5 flex flex-wrap items-center justify-between gap-2">
                    <x-bulk-bar :count="$this->selectedCount()" noun="speaker" />
                    <div class="ms-auto flex items-center gap-2">
                        <span class="inline-flex items-center rounded-xl border border-line bg-white p-0.5">
                            <button type="button" @click="setMode('list')"
                                    :class="mode === 'list' ? 'bg-navy-950 text-white' : 'text-navy-500 hover:text-navy-900'"
                                    class="rounded-lg px-2.5 py-1.5 text-eyebrow font-bold transition">List</button>
                            <button type="button" @click="setMode('cards')"
                                    :class="mode === 'cards' ? 'bg-navy-950 text-white' : 'text-navy-500 hover:text-navy-900'"
                                    class="rounded-lg px-2.5 py-1.5 text-eyebrow font-bold transition">Cards</button>
                        </span>
                        <button type="button" wire:click="newItem" class="btn-gold h-8 px-3 text-xs">＋ Add Speaker</button>
                    </div>
                </div>

                <div x-show="mode === 'list'" x-cloak class="card overflow-hidden">
                    <ul class="divide-y divide-line">
                        @foreach ($speakers as $s)
                            @php [$stLabel, $stClass] = $statusMeta[$s->status] ?? $statusMeta['invited']; @endphp
                            <li wire:key="sp-list-{{ $s->id }}" class="group flex items-center gap-2.5 px-3.5 py-2 transition hover:bg-page/40 {{ $this->isSelected($s->id) ? 'bg-navy-50/60' : '' }}">
                                <button type="button" wire:click="toggleSelect({{ $s->id }})" class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-eyebrow {{ $this->isSelected($s->id) ? 'border-navy-900 bg-navy-900 text-white' : 'border-navy-200 text-transparent hover:border-navy-400' }}" title="Select">✓</button>
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-navy-900 text-eyebrow font-bold text-gold-300">{{ $s->initials() }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[13px] font-bold text-navy-900">
                                        {{ $s->name }}
                                        @if ($s->is_keynote)<span class="ms-1 rounded bg-gold-100 px-1 text-eyebrow font-bold uppercase text-gold-700">Keynote</span>@endif
                                    </p>
                                    <p class="truncate text-eyebrow text-muted">{{ $s->title }}@if ($s->title && $s->organization) · @endif{{ $s->organization }}@if ($s->topic) · “{{ \Illuminate\Support\Str::limit($s->topic, 40) }}”@endif</p>
                                </div>
                                <span class="pill shrink-0 {{ $stClass }}">{{ $stLabel }}</span>
                                <span class="hidden w-20 shrink-0 text-right text-eyebrow font-bold tabular-nums text-navy-900 sm:block">{{ $s->fee_cents ? $event->money($s->fee_cents) : '—' }}</span>
                                <span class="flex gap-0.5 opacity-0 transition group-hover:opacity-100">
                                    @if ($s->status !== 'confirmed')
                                        <button type="button" wire:click="setStatus({{ $s->id }}, 'confirmed')" class="rounded-md bg-emerald-50 px-1.5 py-0.5 text-eyebrow font-bold text-emerald-700 hover:bg-emerald-100">✓</button>
                                    @endif
                                    <button type="button" wire:click="edit({{ $s->id }})" class="rounded-md bg-navy-50 px-1.5 py-0.5 text-eyebrow font-bold text-navy-600 hover:bg-navy-100">✎</button>
                                    <button type="button" wire:click="delete({{ $s->id }})" wire:confirm="Remove {{ $s->name }}?" class="rounded-md bg-risk/10 px-1.5 py-0.5 text-eyebrow font-bold text-red-700 hover:bg-risk/20">✕</button>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div x-show="mode === 'cards'" x-cloak class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($speakers as $s)
                        @php [$stLabel, $stClass] = $statusMeta[$s->status] ?? $statusMeta['invited']; @endphp
                        <div wire:key="sp-card-{{ $s->id }}" class="group/sp op-card {{ $this->isSelected($s->id) ? '!border-navy-900 ring-2 ring-navy-900' : '' }}">
                            <div class="flex flex-1 flex-col p-3.5">
                                <div class="flex items-start justify-between gap-2.5">
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        <button type="button" wire:click="toggleSelect({{ $s->id }})" class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-eyebrow {{ $this->isSelected($s->id) ? 'border-navy-900 bg-navy-900 text-white' : 'border-navy-200 text-transparent hover:border-navy-400' }}" title="Select">✓</button>
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-navy-900 text-sm font-bold text-gold-300">{{ $s->initials() }}</span>
                                        <div class="min-w-0">
                                            <p class="pf flex flex-wrap items-center gap-1.5 text-sm font-bold text-navy-900">{{ $s->name }}
                                                @if ($s->is_keynote)<span class="pill bg-gold-500/20 text-gold-700">Keynote</span>@endif
                                            </p>
                                            <p class="truncate text-micro text-muted">{{ $s->title }}@if ($s->title && $s->organization) · @endif{{ $s->organization }}</p>
                                        </div>
                                    </div>
                                    <span class="pill shrink-0 {{ $stClass }}">{{ $stLabel }}</span>
                                </div>
                                @if ($s->topic)<p class="mt-2.5 rounded-lg bg-page/60 px-2.5 py-1.5 text-xs italic text-navy-700">“{{ $s->topic }}”</p>@endif
                            </div>
                            <div class="op-card-foot">
                                <span class="truncate text-eyebrow font-semibold text-navy-700">{{ $s->fee_cents ? $event->money($s->fee_cents).' fee' : 'No fee' }}</span>
                                <div class="ms-auto flex items-center gap-1 opacity-100 sm:opacity-0 sm:transition sm:group-hover/sp:opacity-100">
                                    @if ($s->status !== 'confirmed')
                                        <button type="button" wire:click="setStatus({{ $s->id }}, 'confirmed')" class="rounded-md bg-emerald-50 px-2 py-0.5 text-eyebrow font-bold text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-100">✓ Confirm</button>
                                    @endif
                                    <button type="button" wire:click="edit({{ $s->id }})" class="rounded-md bg-white px-1.5 py-0.5 text-eyebrow font-bold text-navy-600 ring-1 ring-line hover:ring-gold-300">✎</button>
                                    <button type="button" wire:click="delete({{ $s->id }})" wire:confirm="Remove {{ $s->name }}?" class="rounded-md bg-risk/10 px-1.5 py-0.5 text-eyebrow font-bold text-red-700 hover:bg-risk/20">✕</button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="xl:sticky xl:top-4 xl:h-fit">
            <div class="cc-panel">
                <div class="cc-head">
                    <span class="relative flex h-7 w-7 items-center justify-center rounded-lg text-white shadow-sm" style="background: {{ $moduleHex }}">
                        <x-icon name="sparkles" class="h-3.5 w-3.5" />
                    </span>
                    <span class="relative text-2xs font-bold uppercase tracking-[0.18em] text-navy-900">Speakers Control</span>
                </div>
                <div class="border-b border-line p-3">
                    <p class="field-label !mb-1.5 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-navy-300"></span> Summary</p>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Speakers</span><span class="font-bold text-navy-900">{{ $speakers->count() }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Confirmed</span><span class="font-bold text-emerald-700">{{ $confirmed }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Keynotes</span><span class="font-bold text-navy-900">{{ $keynotes }}</span></div>
                        <div class="flex justify-between border-t border-line pt-1.5"><span class="text-muted">Speaker fees</span><span class="font-bold text-navy-900">{{ $event->money($feeTotal) }}</span></div>
                    </div>
                </div>
                <div class="p-3">
                    <button type="button" wire:click="newItem" class="btn-gold h-9 w-full text-xs">＋ Add Speaker</button>
                </div>
            </div>
        </div>
    </div>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit speaker' : 'New speaker'" max="xl" close="set('showForm', false)">
                <form wire:submit="save" class="grid gap-3.5 sm:grid-cols-2">
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Full name</label>
                        <input type="text" wire:model="name" class="input h-10 text-sm" placeholder="Dr. Layla Haddad">
                        @error('name')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Job title</label>
                        <input type="text" wire:model="title" class="input h-10 text-sm" placeholder="Minister of Economy">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Organization</label>
                        <input type="text" wire:model="organization" class="input h-10 text-sm" placeholder="Government of Jordan">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Talk / topic</label>
                        <input type="text" wire:model="topic" class="input h-10 text-sm" placeholder="The Future of the Arab Economy">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Email</label>
                        <input type="email" wire:model="email" class="input h-10 text-sm" placeholder="—">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Phone</label>
                        <input type="text" wire:model="phone" class="input h-10 text-sm" placeholder="—">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Status</label>
                        <select wire:model="status" class="input h-10 text-sm">
                            @foreach (\App\Models\EventSpeaker::STATUSES as $st)<option value="{{ $st }}">{{ ucfirst($st) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Fee ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="fee" class="input h-10 text-sm" placeholder="0">
                    </div>
                    <label class="flex items-center gap-2 sm:col-span-2">
                        <input type="checkbox" wire:model="is_keynote" class="h-4 w-4 rounded border-navy-300 text-gold-700">
                        <span class="text-xs font-semibold text-navy-800">Keynote speaker</span>
                    </label>
                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-eyebrow">Bio</label>
                        <textarea wire:model="bio" rows="2" class="input text-sm" placeholder="Short biography…"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn-navy h-10 px-6 text-xs">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update' : 'Add speaker' }}</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
        </x-modal>
    @endif
</div>
