<div class="grid gap-4 lg:grid-cols-[240px_minmax(0,1fr)]">

    {{-- ══════════ Which list ══════════ --}}
    <aside class="self-start lg:sticky lg:top-[92px]">
        <div class="card overflow-hidden">
            <div class="border-b border-line px-4 py-3"><p class="eyebrow">Lists</p></div>
            <div class="p-1.5">
                @foreach ($lists as $list)
                    <button type="button" wire:click="pick('{{ $list['key'] }}')"
                            @class([
                                'flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left transition',
                                'bg-navy-900 text-white' => $taxonomy === $list['key'],
                                'text-navy-600 hover:bg-navy-50 hover:text-navy-900' => $taxonomy !== $list['key'],
                            ])>
                        <span class="min-w-0 flex-1 truncate text-[12.5px] font-semibold">{{ $list['label'] }}</span>
                        <span @class([
                            'shrink-0 rounded-full px-1.5 text-[10px] font-bold tabular-nums',
                            'bg-white/15 text-white' => $taxonomy === $list['key'],
                            'bg-navy-50 text-navy-500' => $taxonomy !== $list['key'],
                        ])>{{ $list['count'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </aside>

    {{-- ══════════ The terms ══════════ --}}
    @php
        $inUse = collect($terms)->filter(fn ($t) => ($usage[$t->key] ?? 0) > 0)->count();
        $records = collect($terms)->sum(fn ($t) => $usage[$t->key] ?? 0);
    @endphp
    <section class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-page/40 px-4 py-3">
            <div class="min-w-0">
                <h2 class="pf text-[16px] font-bold text-navy-900">{{ $listLabel }}</h2>
                <p class="mt-0.5 text-[11.5px] text-muted">{{ $listDescription }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-3">
                {{-- What this list is carrying, before you change any of it. --}}
                <div class="hidden text-right sm:block">
                    <p class="text-[15px] font-bold tabular-nums leading-none text-navy-900">{{ number_format($records) }}</p>
                    <p class="mt-0.5 text-[10px] text-muted">on {{ $inUse }} of {{ count($terms) }} terms</p>
                </div>
                <button type="button" wire:click="newTerm" class="btn-gold btn-sm">＋ Add</button>
            </div>
        </div>

        <div class="flex items-center gap-3 border-b border-line px-4 py-1.5">
            <span class="w-4 shrink-0"></span>
            @if ($usesColor)<span class="w-3.5 shrink-0"></span>@endif
            <span class="min-w-0 flex-1 eyebrow">Term</span>
            <span class="w-12 shrink-0 text-right eyebrow" title="Records currently on this term">In use</span>
            <span class="w-[109px] shrink-0"></span>
        </div>

        <div class="divide-y divide-line" data-terms>
            @forelse ($terms as $term)
                @php $used = $usage[$term->key] ?? 0; @endphp
                <div wire:key="term-{{ $term->id }}" data-term="{{ $term->id }}"
                     class="group/row flex items-center gap-3 px-4 py-2.5 transition hover:bg-page/40 {{ $term->is_active ? '' : 'bg-page/50' }}">
                    <span class="cat-drag grid h-6 w-4 shrink-0 cursor-grab place-items-center text-navy-200 transition group-hover/row:text-navy-400" title="Drag to reorder">⋮⋮</span>

                    @if ($usesColor)
                        <span class="h-3.5 w-3.5 shrink-0 rounded-full ring-1 ring-line"
                              style="background: {{ $term->color ?: 'var(--color-navy-100)' }}"></span>
                    @endif

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="truncate text-[12.5px] font-bold {{ $term->is_active ? 'text-navy-900' : 'text-navy-400 line-through' }}">{{ $term->label }}</span>
                            @unless ($term->is_active)<span class="chip">Hidden</span>@endunless
                        </div>
                        <div class="flex items-center gap-2 text-[10.5px] text-muted">
                            {{-- The key is what records store, so it is shown and never edited.
                                 On a list that stores the words themselves it would just
                                 repeat the label, so it is left out there. --}}
                            @unless ($storesLabel)
                                <code class="rounded bg-page px-1 py-px font-mono text-[10px] text-navy-400">{{ $term->key }}</code>
                            @endunless
                            @if ($term->note)<span class="truncate">{{ $term->note }}</span>@endif
                        </div>
                    </div>

                    {{-- The count is the whole point of the row: it says what
                         you are about to hide or remove before you do it. --}}
                    <span class="w-12 shrink-0 text-right text-[11px] tabular-nums {{ $used ? 'font-bold text-navy-600' : 'text-navy-200' }}">
                        {{ $used ? number_format($used) : '—' }}
                    </span>

                    <div class="flex shrink-0 items-center gap-1">
                        <button type="button" wire:click="toggleActive({{ $term->id }})"
                                title="{{ $term->is_active ? 'Stop offering this on new records' : 'Offer this again' }}"
                                class="grid h-7 w-7 place-items-center rounded-lg text-[13px] text-navy-300 transition hover:bg-navy-50 hover:text-navy-900">
                            {{ $term->is_active ? '◉' : '○' }}
                        </button>
                        <button type="button" wire:click="edit({{ $term->id }})" class="btn-ghost btn-xs">Edit</button>
                        <button type="button" wire:click="delete({{ $term->id }})"
                                wire:confirm="{{ match (true) {
                                    $used > 0 => number_format($used).' record'.($used === 1 ? '' : 's').' still use “'.$term->label.'”, so it will be hidden rather than deleted and they keep their label. Continue?',
                                    $term->is_system => 'The platform names “'.$term->label.'” in its own code, so it will be hidden rather than deleted. Continue?',
                                    default => 'Delete “'.$term->label.'”? Nothing is using it.',
                                } }}"
                                class="grid h-7 w-7 place-items-center rounded-lg text-navy-300 transition hover:bg-risk/10 hover:text-risk">✕</button>
                    </div>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-[12px] text-muted">Nothing in this list yet.</p>
            @endforelse
        </div>

        <p class="border-t border-line bg-page/50 px-4 py-2.5 text-[11px] text-muted">
            @if ($storesLabel)
                This list is saved onto records word for word, so renaming a term changes what is offered from
                now on — records saved earlier keep the wording they were saved with.
            @else
                Renaming is safe here: records store the key, not the label, so every existing one follows.
            @endif
            Hiding a term keeps every record that already uses it and only stops offering it on new ones.
        </p>
    </section>

    {{-- ══════════ Add / edit ══════════ --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit term' : 'Add to '.$listLabel" close="$set('showForm', false)" max="md">
            <div class="space-y-4">
                <label class="block">
                    <span class="field-label">Label</span>
                    <input type="text" wire:model="label" placeholder="e.g. Board Retreat" class="input">
                    @error('label')<p class="mt-1 text-[11px] text-risk">{{ $message }}</p>@enderror
                    @error('key')<p class="mt-1 text-[11px] text-risk">{{ $message }}</p>@enderror
                </label>

                @if ($editingId && ! $storesLabel)
                    <div>
                        <span class="field-label">Key</span>
                        <p class="input flex items-center bg-page/70 font-mono text-[12px] text-navy-400">{{ $key }}</p>
                        <p class="mt-1 text-[10.5px] text-muted">Fixed — every record using this term stores it.</p>
                    </div>
                @endif

                @if ($usesColor)
                    <label class="block">
                        <span class="field-label">Colour</span>
                        <input type="color" wire:model="color" class="input h-10 w-full p-1">
                    </label>
                @endif

                <label class="block">
                    <span class="field-label">Note</span>
                    <input type="text" wire:model="note" placeholder="Optional — what this is for." class="input">
                </label>
            </div>

            <x-slot:footer>
                <button type="button" wire:click="$set('showForm', false)" class="btn-ghost btn-sm">Cancel</button>
                <button type="button" wire:click="save" class="btn-gold btn-sm">{{ $editingId ? 'Save' : 'Add' }}</button>
            </x-slot:footer>
        </x-modal>
    @endif

    @script
    <script>
        /* Order is what people see in every dropdown, so it is draggable.
           Re-initialised after each morph, the same way the budget categories
           are: switching lists replaces this container. */
        const initTermSort = () => {
            const el = document.querySelector('[data-terms]');
            if (! el || el._sortable) return;
            el._sortable = window.Sortable.create(el, {
                handle: '.cat-drag',
                draggable: '[data-term]',
                animation: 160,
                ghostClass: 'opacity-40',
                onEnd: () => {
                    const ids = [...el.querySelectorAll('[data-term]')].map((n) => n.dataset.term);
                    $wire.reorder(ids);
                },
            });
        };
        initTermSort();
        Livewire.hook('morph.updated', () => initTermSort());
    </script>
    @endscript
</div>
