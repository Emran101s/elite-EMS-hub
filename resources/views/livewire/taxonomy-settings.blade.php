<div class="grid gap-4 lg:grid-cols-[240px_minmax(0,1fr)]">

    {{-- ══════════ Which list ══════════ --}}
    <aside class="self-start lg:sticky lg:top-4">
        <div class="eo-soft-card overflow-hidden">
            @foreach ($groups as $groupName => $lists)
                <div class="border-b border-eo-line px-4 py-2 {{ $loop->first ? '' : 'border-t' }}">
                    <p class="eo-label">{{ $groupName }}</p>
                </div>
                <div class="p-1.5">
                    @foreach ($lists as $list)
                        <button type="button" wire:click="pick('{{ $list['key'] }}')"
                                @class([
                                    'flex w-full items-center gap-2 rounded-xl px-3 py-1.5 text-left transition',
                                    'bg-eo-navy text-white' => $taxonomy === $list['key'],
                                    'text-eo-muted hover:bg-eo-bg hover:text-eo-text' => $taxonomy !== $list['key'],
                                ])>
                            <span class="min-w-0 flex-1 truncate text-[12.5px] font-semibold">{{ $list['label'] }}</span>
                            <span @class([
                                'shrink-0 rounded-full px-1.5 text-[10px] font-bold tabular-nums',
                                'bg-white/15 text-white' => $taxonomy === $list['key'],
                                'bg-eo-bg text-eo-muted' => $taxonomy !== $list['key'],
                            ])>{{ $list['count'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endforeach
        </div>
    </aside>

    {{-- ══════════ The terms ══════════ --}}
    <section class="eo-soft-card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-eo-line bg-eo-workspace px-4 py-3">
            <div class="min-w-0">
                <h2 class="text-[16px] font-bold text-eo-text">{{ $listLabel }}</h2>
                <p class="mt-0.5 text-[11.5px] text-eo-muted">{{ $listDescription }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-3">
                {{-- What this list is carrying, before you change any of it. --}}
                <div class="hidden text-right sm:block">
                    <p class="text-[15px] font-bold tabular-nums leading-none text-eo-text">{{ number_format($totals['records']) }}</p>
                    <p class="mt-0.5 text-[10px] text-eo-muted">on {{ $totals['inUse'] }} of {{ $totals['terms'] }} terms</p>
                </div>
                <x-eo.button size="sm" wire:click="newTerm">＋ Add</x-eo.button>
            </div>
        </div>

        <div class="flex items-center gap-3 border-b border-eo-line px-4 py-1.5">
            <span class="w-4 shrink-0"></span>
            @if ($usesColor)<span class="w-3.5 shrink-0"></span>@endif
            <span class="eo-label min-w-0 flex-1">Term</span>
            <span class="eo-label w-12 shrink-0 text-right" title="Records currently on this term">In use</span>
            <span class="w-[141px] shrink-0"></span>
        </div>

        <div class="divide-y divide-eo-line" data-terms>
            @forelse ($terms as $term)
                <div wire:key="root-{{ $term->id }}" data-term="{{ $term->id }}">
                    <x-taxonomy-row :term="$term" :used="$usage[$term->key] ?? 0"
                                    :uses-color="$usesColor" :stores-label="$storesLabel" />

                    {{-- Children sit under their parent and drag among themselves. --}}
                    @if ($term->children->isNotEmpty())
                        <div class="divide-y divide-eo-line border-t border-eo-line" data-kids="{{ $term->id }}">
                            @foreach ($term->children as $child)
                                <div wire:key="kid-{{ $child->id }}" data-term="{{ $child->id }}">
                                    <x-taxonomy-row :term="$child" :used="$usage[$child->key] ?? 0"
                                                    :uses-color="$usesColor" :stores-label="$storesLabel" child />
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <p class="px-4 py-8 text-center text-[12px] text-eo-muted">Nothing in this list yet.</p>
            @endforelse
        </div>

        <p class="border-t border-eo-line bg-eo-workspace px-4 py-2.5 text-[11px] text-eo-muted">
            @if ($storesLabel)
                This list is saved onto records word for word, so renaming a term changes what is offered from
                now on — records saved earlier keep the wording they were saved with.
            @else
                Renaming is safe here: records store the key, not the label, so every existing one follows.
            @endif
            Hiding a term keeps every record that already uses it and only stops offering it on new ones;
            hiding a parent hides what sits under it.
        </p>
    </section>

    {{-- ══════════ Add / edit ══════════ --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit term' : 'Add to '.$listLabel" close="$set('showForm', false)" max="md">
            <div class="space-y-4">
                <label class="block">
                    <span class="eo-label">Label</span>
                    <input type="text" wire:model="label" placeholder="e.g. Board Retreat" class="eo-input">
                    @error('label')<p class="mt-1 text-[11px] text-eo-risk">{{ $message }}</p>@enderror
                    @error('key')<p class="mt-1 text-[11px] text-eo-risk">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="eo-label">Sits under</span>
                    <select wire:model="parent_id" class="eo-select">
                        <option value="">Nothing — this is a top-level term</option>
                        @foreach ($parents as $p)
                            <option value="{{ $p->id }}">{{ $p->label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[10.5px] text-eo-muted">One level only. A term with its own sub-terms stays at the top.</p>
                </label>

                @if ($editingId && ! $storesLabel)
                    <div>
                        <span class="eo-label">Key</span>
                        <p class="eo-input flex items-center bg-eo-workspace font-mono text-[12px] text-eo-muted">{{ $key }}</p>
                        <p class="mt-1 text-[10.5px] text-eo-muted">Fixed — every record using this term stores it.</p>
                    </div>
                @endif

                @if ($usesColor)
                    <label class="block">
                        <span class="eo-label">Colour</span>
                        <input type="color" wire:model="color" class="eo-input h-10 w-full p-1">
                    </label>
                @endif

                <label class="block">
                    <span class="eo-label">Note</span>
                    <input type="text" wire:model="note" placeholder="Optional — what this is for." class="eo-input">
                </label>
            </div>

            <x-slot:footer>
                <button type="button" wire:click="$set('showForm', false)" class="eo-btn-ghost eo-btn-sm">Cancel</button>
                <x-eo.button size="sm" wire:click="save">{{ $editingId ? 'Save' : 'Add' }}</x-eo.button>
            </x-slot:footer>
        </x-modal>
    @endif

    @script
    <script>
        /* Order is what people see in every dropdown, so it is draggable.
           Each level sorts among itself: roots with roots, children with their
           own siblings. Dragging across levels would change what a term means,
           which is what the parent field is for.
           Re-initialised after each morph, the same way budget categories are. */
        const initTermSort = () => {
            document.querySelectorAll('[data-terms], [data-kids]').forEach((el) => {
                if (el._sortable) return;
                el._sortable = window.Sortable.create(el, {
                    handle: '.cat-drag',
                    draggable: '[data-term]',
                    animation: 160,
                    ghostClass: 'opacity-40',
                    onEnd: () => {
                        const ids = [...el.children].filter((n) => n.dataset.term).map((n) => n.dataset.term);
                        $wire.reorder(ids);
                    },
                });
            });
        };
        initTermSort();
        Livewire.hook('morph.updated', () => initTermSort());
    </script>
    @endscript
</div>
