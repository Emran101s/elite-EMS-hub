@php
    $statusMeta = [
        'invited' => ['Invited', 'bg-navy-100 text-navy-600'],
        'confirmed' => ['Confirmed', 'bg-emerald-100 text-emerald-700'],
        'declined' => ['Declined', 'bg-red-100 text-red-700'],
        'cancelled' => ['Cancelled', 'bg-navy-100 text-navy-400'],
    ];
@endphp
<div>
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
        <div class="min-w-0">
    @if ($speakers->isEmpty())
        <div class="card px-6 py-16 text-center">
            <p class="text-sm font-semibold text-navy-900">No speakers yet</p>
            <p class="mt-1 text-xs text-muted">Add keynotes, panellists and moderators — track invitations, confirmations and fees.</p>
            <button type="button" wire:click="newItem" class="btn-gold mt-4 h-10 px-5 text-xs">＋ Add the first speaker</button>
        </div>
    @else
        <x-bulk-bar :count="$this->selectedCount()" noun="speaker" />
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($speakers as $s)
                @php [$stLabel, $stClass] = $statusMeta[$s->status] ?? $statusMeta['invited']; @endphp
                <div wire:key="sp-{{ $s->id }}" class="group/sp op-card {{ $this->isSelected($s->id) ? '!border-navy-900 ring-2 ring-navy-900' : '' }}">
                    <div class="flex flex-1 flex-col p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <button type="button" wire:click="toggleSelect({{ $s->id }})" class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-eyebrow {{ $this->isSelected($s->id) ? 'border-navy-900 bg-navy-900 text-white' : 'border-navy-200 text-transparent hover:border-navy-400' }}" title="Select">✓</button>
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-navy-900 text-sm font-bold text-gold-300">{{ $s->initials() }}</span>
                                <div class="min-w-0">
                                    <p class="pf flex items-center gap-1.5 text-sm font-bold text-navy-900">{{ $s->name }}
                                        @if ($s->is_keynote)<span class="pill bg-gold-500/20 text-gold-700">Keynote</span>@endif
                                    </p>
                                    <p class="truncate text-micro text-muted">{{ $s->title }}@if ($s->title && $s->organization) · @endif{{ $s->organization }}</p>
                                </div>
                            </div>
                            <span class="pill shrink-0 {{ $stClass }}">{{ $stLabel }}</span>
                        </div>

                        @if ($s->topic)<p class="mt-3 rounded-xl bg-page/60 px-3 py-2 text-xs italic text-navy-700">“{{ $s->topic }}”</p>@endif
                    </div>

                    {{-- dark navy footer: fee + hover actions --}}
                    <div class="op-card-foot">
                        <span class="truncate text-eyebrow font-semibold text-white/80">{{ $s->fee_cents ? $event->money($s->fee_cents).' fee' : 'No fee' }}</span>
                        <div class="ml-auto flex items-center gap-1 opacity-0 transition group-hover/sp:opacity-100">
                            @if ($s->status !== 'confirmed')
                                <button type="button" wire:click="setStatus({{ $s->id }}, 'confirmed')" class="rounded-lg bg-emerald-400/20 px-2 py-1 text-eyebrow font-bold text-emerald-300 hover:bg-emerald-400/30">✓ Confirm</button>
                            @endif
                            <button type="button" wire:click="edit({{ $s->id }})" class="rounded-lg bg-white/10 px-1.5 py-1 text-eyebrow font-bold text-white/80 hover:bg-white/20">✎</button>
                            <button type="button" wire:click="delete({{ $s->id }})" wire:confirm="Remove {{ $s->name }}?" class="rounded-lg bg-red-400/15 px-1.5 py-1 text-eyebrow font-bold text-red-300 hover:bg-red-400/25">✕</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
        </div>

        {{-- ══ Speakers Control Center ══ --}}
        <div class="xl:sticky xl:top-4 xl:h-fit">
            <div class="cc-panel">
                <div class="cc-head">
                    <div class="pointer-events-none absolute -right-6 -top-10 h-28 w-28 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.28),transparent_70%)]"></div>
                    <x-icon name="sparkles" class="relative h-4 w-4 text-gold-400" />
                    <span class="relative text-2xs font-bold uppercase tracking-[0.18em] text-white">Speakers Control Center</span>
                </div>
                <div class="border-b border-line p-4">
                    <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-navy-300"></span> Summary</p>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Speakers</span><span class="font-bold text-navy-900">{{ $speakers->count() }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Confirmed</span><span class="font-bold text-emerald-700">{{ $confirmed }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Keynotes</span><span class="font-bold text-navy-900">{{ $keynotes }}</span></div>
                        <div class="flex justify-between border-t border-line pt-1.5"><span class="text-muted">Speaker fees</span><span class="font-bold text-navy-900">{{ $event->money($feeTotal) }}</span></div>
                    </div>
                </div>
                <div class="p-4">
                    <button type="button" wire:click="newItem" class="btn-gold h-10 w-full text-xs">＋ Add Speaker</button>
                </div>
            </div>
        </div>
    </div>

    {{-- modal --}}
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
                        <input type="checkbox" wire:model="is_keynote" class="h-4 w-4 rounded border-navy-300 text-gold-500">
                        <span class="text-xs font-semibold text-navy-800">Keynote speaker</span>
                    </label>
                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-eyebrow">Bio</label>
                        <textarea wire:model="bio" rows="2" class="input text-sm" placeholder="Short biography…"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                        <button type="submit" class="btn-navy h-10 px-6 text-xs">{{ $editingId ? 'Update' : 'Add speaker' }}</button>
                    </div>
                </form>
        </x-modal>
    @endif
</div>
