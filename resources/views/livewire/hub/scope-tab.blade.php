{{-- ══════════ SCOPE OF WORK ══════════

     What the client has asked us to deliver, written here and nowhere else.
     The Event Brief renders this rather than holding a copy — a scope typed
     into two places disagrees with itself the first time one is revised.

     Reads as a document: the work grouped by area, then the exclusions
     gathered at the end where a scope of work puts them. --}}
<div class="cx-canvas">

    @if ($total === 0)
        <div class="cx-empty">
            <h3>No scope written yet</h3>
            <p>Write what the client has asked us to deliver, area by area. The Event Brief reads this scope directly, so it only has to be written once — and revising it here revises it there.</p>
            @can('write')
                <button type="button" wire:click="newItem" class="cx-btn cx-btn-accent">＋ Write the first line</button>
            @endcan
        </div>
    @else
        <div class="mb-2 flex flex-wrap items-center gap-1.5">
            <span class="cx-eyebrow">Scope of work</span>
            <span class="text-[11px] text-muted">
                {{ $groups->sum(fn ($g) => $g['rows']->count()) }} in scope
                @if ($exclusions->isNotEmpty())
                    · {{ $exclusions->count() }} excluded
                @endif
            </span>
            @can('write')
                <button type="button" wire:click="newItem" class="cx-btn cx-btn-accent ms-auto" style="height:30px">＋ Scope line</button>
                <button type="button" wire:click="newItem(true)" class="cx-btn cx-btn-ghost" style="height:30px">＋ Exclusion</button>
            @endcan
        </div>

        {{-- ── What we are delivering ── --}}
        @foreach ($groups as $key => $group)
            <div class="cx-lcard">
                <div class="cx-lcard-head">
                    <span class="cx-lt">
                        <span class="cx-hexdot" style="background: {{ \App\Models\EventScopeItem::AREAS[$key][1] ?? 'var(--cx-muted)' }}"></span>
                        {{ $group['label'] }}
                    </span>
                    <span class="text-[10.5px] text-muted">{{ $group['rows']->count() }}</span>
                </div>

                @foreach ($group['rows'] as $item)
                    <div wire:key="s-{{ $item->id }}" class="group/s flex items-start gap-2.5 border-b border-line px-3.5 py-2.5 last:border-0">
                        <span class="min-w-0 flex-1">
                            <span class="block text-[13px] font-semibold leading-snug text-ink">{{ $item->title }}</span>
                            @if ($item->body)
                                <span class="mt-1 block whitespace-pre-line text-[12px] leading-relaxed text-muted">{{ $item->body }}</span>
                            @endif
                        </span>

                        @can('write')
                            <span class="flex shrink-0 items-center gap-1 opacity-0 transition group-hover/s:opacity-100">
                                <button type="button" wire:click="edit({{ $item->id }})"
                                        class="rounded bg-page px-1.5 py-0.5 text-eyebrow font-bold text-muted hover:bg-line">✎</button>
                                <x-confirm title="Remove this line from the scope?"
                                           body="It stops being something we have said we will deliver."
                                           confirm="Remove"
                                           run="$wire.delete({{ $item->id }})"
                                           class="rounded bg-danger-soft px-1.5 py-0.5 text-eyebrow font-bold text-danger-ink hover:bg-danger-soft/70">✕</x-confirm>
                            </span>
                        @endcan
                    </div>
                @endforeach
            </div>
        @endforeach

        {{-- ── What we are not doing ──
             Kept together and last. The exclusions are the half of a scope
             that settles the argument three weeks out, so they are stated
             plainly rather than buried among the inclusions. --}}
        @if ($exclusions->isNotEmpty())
            <div class="cx-lcard">
                <div class="cx-lcard-head" style="background: var(--cx-warn-wash)">
                    <span class="cx-lt" style="color: var(--cx-warn-ink)">Not included in this scope</span>
                    <span class="text-[10.5px]" style="color: var(--cx-warn-ink)">{{ $exclusions->count() }}</span>
                </div>

                @foreach ($exclusions as $item)
                    <div wire:key="x-{{ $item->id }}" class="group/x flex items-start gap-2.5 border-b border-line px-3.5 py-2.5 last:border-0">
                        <span class="min-w-0 flex-1">
                            <span class="block text-[13px] font-semibold leading-snug text-ink">{{ $item->title }}</span>
                            @if ($item->body)
                                <span class="mt-1 block whitespace-pre-line text-[12px] leading-relaxed text-muted">{{ $item->body }}</span>
                            @endif
                        </span>
                        @can('write')
                            <span class="flex shrink-0 items-center gap-1 opacity-0 transition group-hover/x:opacity-100">
                                <button type="button" wire:click="edit({{ $item->id }})"
                                        class="rounded bg-page px-1.5 py-0.5 text-eyebrow font-bold text-muted hover:bg-line">✎</button>
                                <x-confirm title="Remove this exclusion?"
                                           confirm="Remove"
                                           run="$wire.delete({{ $item->id }})"
                                           class="rounded bg-danger-soft px-1.5 py-0.5 text-eyebrow font-bold text-danger-ink hover:bg-danger-soft/70">✕</x-confirm>
                            </span>
                        @endcan
                    </div>
                @endforeach
            </div>
        @endif

        <p class="mt-2 text-[11px] text-muted">
            The Event Brief reads this scope directly — revise it here and the Brief follows.
        </p>
    @endif

    {{-- ── Write / revise ── --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Revise this line' : ($is_exclusion ? 'Add an exclusion' : 'Add to the scope')"
                 max="lg" close="set('showForm', false)">
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">
                        {{ $is_exclusion ? 'What is not included' : 'What we will deliver' }}
                    </label>
                    <input type="text" wire:model="title" class="eo-input"
                           placeholder="{{ $is_exclusion ? 'Simultaneous interpretation' : 'Full event management and on-site supervision' }}">
                    @error('title') <p class="mt-1 text-[11px] text-danger-ink">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Detail (optional)</label>
                    <textarea wire:model="body" rows="4" class="eo-textarea"
                              placeholder="{{ $is_exclusion ? 'The client contracts interpreters directly and supplies the booths.' : 'Planning, supplier coordination, run-of-show and an on-site team for the full event period.' }}"></textarea>
                </div>

                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Area</label>
                    <select wire:model="area" class="eo-select">
                        @foreach ($areas as $k => $label)
                            <option value="{{ $k }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <label class="flex items-center gap-2 text-[12.5px] text-ink">
                    <input type="checkbox" wire:model="is_exclusion" class="rounded border-line">
                    This line states what is <span class="font-semibold">not</span> included
                </label>
            </div>

            <x-slot:footer>
                <button type="button" wire:click="set('showForm', false)" class="cx-btn cx-btn-ghost">Cancel</button>
                <button type="button" wire:click="save" class="cx-btn cx-btn-accent">{{ $editingId ? 'Save' : 'Add' }}</button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
