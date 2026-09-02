{{-- ══════════ DELIVERY SCOPE ══════════

     What this event commits to deliver, who is accountable for each part,
     and what "done" means. Grouped by workstream, because that is how a team
     is organised — not by module, which is how the software is organised.

     Every status on this page is READ, never stored. See App\Support\ScopeStatus:
     a scope people are held to is the worst possible place to keep a second
     copy of a truth another module already owns. --}}
<div class="cx-canvas">
    @php
        $tone = [
            \App\Support\ScopeStatus::MET => 'tone-ok',
            \App\Support\ScopeStatus::PARTIAL => 'tone-warn',
            \App\Support\ScopeStatus::OPEN => 'tone-risk',
            \App\Support\ScopeStatus::UNMEASURED => 'tone-muted',
        ];
        $stateWord = [
            \App\Support\ScopeStatus::MET => 'Met',
            \App\Support\ScopeStatus::PARTIAL => 'In progress',
            \App\Support\ScopeStatus::OPEN => 'Open',
            \App\Support\ScopeStatus::UNMEASURED => 'By hand',
        ];
    @endphp

    {{-- No stat strip here. The module header directly above already carries
         deliverables / met / no owner / overdue from the same figures — see
         HubModuleInspector's 'scope' entry — and printing them twice, one card
         apart, is the duplication this hub has had to be cleared of more than
         once. The tab body is the register itself. --}}

    {{-- ── Whose scope am I looking at ──
         The register answers "what are we delivering". This row answers "what
         is Omar answerable for", which is the question a person actually has. --}}
    <div class="mb-2 flex flex-wrap items-center gap-1.5">
        <span class="cx-eyebrow">Accountable</span>
        @if ($owners->isEmpty())
            <span class="text-[11px] text-muted">Nobody is named on this scope yet</span>
        @endif
            <button type="button" wire:click="filterOwner(null)"
                    class="cx-chip {{ $ownerFilter === null ? 'is-on' : '' }}">Everyone</button>
        @foreach ($owners as $o)
            <button type="button" wire:click="filterOwner({{ $o->id }})"
                    class="cx-chip {{ $ownerFilter === $o->id ? 'is-on' : '' }}">{{ $o->name }}</button>
        @endforeach

        @can('write')
            <button type="button" wire:click="newItem" class="cx-btn cx-btn-accent ms-auto" style="height:30px">＋ Deliverable</button>
        @endcan
    </div>

    {{-- ── The register ── --}}
    @forelse ($groups as $key => $group)
        <div class="cx-lcard">
            <div class="cx-lcard-head">
                <span class="cx-lt">
                    <span class="cx-hexdot" style="background: {{ \App\Models\EventScopeItem::WORKSTREAMS[$key][1] ?? 'var(--cx-muted)' }}"></span>
                    {{ $group['label'] }}
                </span>
                <span class="text-[10.5px] text-muted">{{ $group['rows']->count() }}</span>
            </div>

            @foreach ($group['rows'] as $row)
                @php $item = $row['model']; $st = $row['status']; @endphp
                <div wire:key="scope-{{ $item->id }}" class="group/s border-b border-line px-3.5 py-2.5 last:border-0">
                    <div class="flex items-start gap-2.5">
                        <span class="min-w-0 flex-1">
                            <span class="block text-[13px] font-semibold leading-snug text-ink">{{ $item->title }}</span>

                            @if ($item->definition_of_done)
                                {{-- The acceptance criteria, not a description. This is the
                                     sentence that ends the argument about whether it is done. --}}
                                <span class="mt-1 block text-[11.5px] leading-snug text-muted">
                                    <span class="font-semibold text-ink">Done when</span> · {{ $item->definition_of_done }}
                                </span>
                            @endif

                            @if ($item->out_of_scope)
                                <span class="mt-1 block rounded-lg px-2 py-1 text-[11px] leading-snug"
                                      style="background: var(--cx-warn-wash); color: var(--cx-warn-ink)">
                                    <span class="font-bold uppercase tracking-wide">Not in scope</span> · {{ $item->out_of_scope }}
                                </span>
                            @endif
                        </span>

                        <span class="flex shrink-0 flex-col items-end gap-1">
                            <span class="cx-tag {{ $tone[$st['state']] }}">{{ $stateWord[$st['state']] }}</span>
                            <span class="text-[10.5px] tabular-nums text-muted">{{ $st['note'] }}</span>
                        </span>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[10.5px] text-muted">
                        @if ($item->owner)
                            <span class="inline-flex items-center gap-1.5">
                                <x-user-avatar :user="$item->owner" size="h-4 w-4" />
                                <span class="font-semibold text-ink">{{ $item->owner->name }}</span>
                            </span>
                        @else
                            {{-- Named, not blank. A deliverable nobody owns is the
                                 thing this page exists to surface. --}}
                            <span class="font-semibold text-warning-ink">Nobody accountable</span>
                        @endif

                        <span class="tabular-nums {{ $item->isOverdue() ? 'font-bold text-danger-ink' : '' }}">
                            {{ $item->tMinus() }}@if ($item->dueOn()) · {{ $item->dueOn()->format('j M') }}@endif
                        </span>

                        @if ($item->source_type)
                            <span>via {{ $sources[$item->source_type] ?? $item->source_type }}</span>
                        @endif

                        @can('write')
                            <span class="ml-auto flex items-center gap-1 opacity-0 transition group-hover/s:opacity-100">
                                <button type="button" wire:click="edit({{ $item->id }})"
                                        class="rounded bg-page px-1.5 py-0.5 text-eyebrow font-bold text-muted hover:bg-line">✎</button>
                                <x-confirm title="Remove “{{ $item->title }}” from the scope?"
                                           body="It stops being something this event has committed to deliver."
                                           confirm="Remove"
                                           run="$wire.delete({{ $item->id }})"
                                           class="rounded bg-danger-soft px-1.5 py-0.5 text-eyebrow font-bold text-danger-ink hover:bg-danger-soft/70">✕</x-confirm>
                            </span>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <div class="cx-empty">
            <h3>No scope agreed yet</h3>
            <p>A deliverable is not a task. It names one accountable person, the date it is due, and the sentence that proves it finished — so a team can be handed its part and nothing falls between two people.</p>
            @can('write')
                <button type="button" wire:click="newItem" class="cx-btn cx-btn-accent">＋ First deliverable</button>
            @endcan
        </div>
    @endforelse

    {{-- ── Add / edit ── --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit deliverable' : 'Add deliverable'" max="lg" close="set('showForm', false)">
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Deliverable</label>
                    <input type="text" wire:model="title" class="eo-input" placeholder="Main stage built and handed over">
                    @error('title') <p class="mt-1 text-[11px] text-danger-ink">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Workstream</label>
                        <select wire:model="workstream" class="eo-select">
                            @foreach ($workstreams as $k => $label)
                                <option value="{{ $k }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Accountable</label>
                        <select wire:model="owner_id" class="eo-select">
                            <option value="">Nobody yet</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Done when</label>
                    <textarea wire:model="definition_of_done" rows="2" class="eo-textarea"
                              placeholder="The AV walkthrough passes and the venue signs the handover sheet."></textarea>
                </div>

                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Not in scope</label>
                    <textarea wire:model="out_of_scope" rows="2" class="eo-textarea"
                              placeholder="Stage graphics — client supplies artwork and printing."></textarea>
                    <p class="mt-1 text-[11px] text-muted">The exclusions are half of what a scope is for. This is the line that settles it later.</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Due, relative to event day</label>
                        <input type="number" wire:model="offset_days" class="eo-input" step="1">
                        <p class="mt-1 text-[11px] text-muted">Negative is before. −14 means T−14. Move the event and this moves with it.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Status comes from</label>
                        <select wire:model.live="source_type" class="eo-select">
                            <option value="">Nothing — track by hand</option>
                            @foreach ($sources as $k => $label)
                                <option value="{{ $k }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] text-muted">Read from the module that already knows. Never typed here.</p>
                    </div>
                </div>

                @if ($source_type === 'task')
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Task id</label>
                        <input type="number" wire:model="source_id" class="eo-input" placeholder="e.g. 214">
                    </div>
                @endif
            </div>

            <x-slot:footer>
                <button type="button" wire:click="set('showForm', false)" class="cx-btn cx-btn-ghost">Cancel</button>
                <button type="button" wire:click="save" class="cx-btn cx-btn-accent">{{ $editingId ? 'Save' : 'Add to scope' }}</button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
