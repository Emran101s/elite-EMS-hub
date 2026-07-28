<div class="grid gap-4 lg:grid-cols-[240px_minmax(0,1fr)]">

    {{-- ══════════ Which set ══════════ --}}
    <aside class="self-start lg:sticky lg:top-4">
        <div class="card overflow-hidden">
            <div class="border-b border-line px-4 py-3"><p class="eyebrow">Workflows</p></div>
            <div class="p-1.5">
                @foreach ($sets as $s)
                    <button type="button" wire:click="pick('{{ $s['key'] }}')"
                            @class([
                                'flex w-full items-center gap-2 rounded-xl px-3 py-1.5 text-left transition',
                                'bg-navy-900 text-white' => $set === $s['key'],
                                'text-navy-600 hover:bg-navy-50 hover:text-navy-900' => $set !== $s['key'],
                            ])>
                        <span class="min-w-0 flex-1 truncate text-[12.5px] font-semibold">{{ $s['label'] }}</span>
                        <span @class([
                            'shrink-0 rounded-full px-1.5 text-[10px] font-bold tabular-nums',
                            'bg-white/15 text-white' => $set === $s['key'],
                            'bg-navy-50 text-navy-500' => $set !== $s['key'],
                        ])>{{ $s['count'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <p class="mt-3 px-1 text-[11px] leading-relaxed text-muted">
            Adding a step or removing one would change how the platform works, so these sets are fixed.
            Looking for something you can add to?
            <a href="{{ route('taxonomies.index') }}" class="font-semibold text-gold-700 hover:underline">Types &amp; Lists</a>.
        </p>
    </aside>

    {{-- ══════════ The states ══════════ --}}
    <section class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-page/40 px-4 py-3">
            <div class="min-w-0">
                <h2 class="pf text-[16px] font-bold text-navy-900">{{ $setLabel }}</h2>
                <p class="mt-0.5 text-[11.5px] text-muted">{{ $setNote }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                @if ($changed)
                    <button type="button" wire:click="restore"
                            wire:confirm="Put “{{ $setLabel }}” back to the wording and colours it shipped with?"
                            class="btn-ghost btn-sm">Restore defaults</button>
                @endif
                <button type="button" wire:click="save" class="btn-gold btn-sm">Save</button>
            </div>
        </div>

        @if (session('status'))
            <p class="border-b border-line bg-emerald-50/60 px-4 py-2 text-[11.5px] font-semibold text-emerald-800">
                {{ session('status') }}
            </p>
        @endif

        <div class="flex items-center gap-3 border-b border-line px-4 py-1.5">
            <span class="w-4 shrink-0"></span>
            <span class="w-9 shrink-0 eyebrow">Colour</span>
            <span class="min-w-0 flex-1 eyebrow">What you call it</span>
            <span class="hidden w-40 shrink-0 eyebrow sm:block">Key &amp; shipped wording</span>
        </div>

        <div class="divide-y divide-line" data-states>
            @foreach ($rows as $row)
                <div wire:key="state-{{ $row['key'] }}" data-state="{{ $row['key'] }}"
                     class="group/row flex items-center gap-3 px-4 py-2 transition hover:bg-page/40">
                    <span class="cat-drag grid h-6 w-4 shrink-0 cursor-grab place-items-center text-navy-200 transition group-hover/row:text-navy-400"
                          title="Drag to reorder">⋮⋮</span>

                    {{-- The swatch is the input: the colour is the thing you are picking. --}}
                    <label class="relative h-7 w-9 shrink-0 cursor-pointer overflow-hidden rounded-lg ring-1 ring-line"
                           style="background: {{ $row['color'] }}" title="{{ $row['color'] }}">
                        <input type="color" wire:model.live="colors.{{ $row['key'] }}"
                               class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                    </label>

                    <div class="min-w-0 flex-1">
                        {{-- value is set explicitly so the wording is in the
                             server-rendered HTML, not only after hydration. --}}
                        <input type="text" wire:model.blur="labels.{{ $row['key'] }}"
                               value="{{ $labels[$row['key']] ?? $row['label'] }}" maxlength="40"
                               class="w-full rounded-lg border border-transparent bg-transparent px-2 py-1 text-[12.5px] font-bold text-navy-900 transition hover:border-line focus:border-gold-400 focus:bg-white focus:outline-none">
                        @error('labels.'.$row['key'])<p class="px-2 text-[10.5px] text-risk">{{ $message }}</p>@enderror
                        @error('colors.'.$row['key'])<p class="px-2 text-[10.5px] text-risk">{{ $message }}</p>@enderror
                    </div>

                    {{-- The key is what every record and every line of code holds,
                         so it is shown and never editable. --}}
                    <div class="hidden w-40 shrink-0 text-[10.5px] leading-tight sm:block">
                        <code class="rounded bg-page px-1 py-px font-mono text-[10px] text-navy-400">{{ $row['key'] }}</code>
                        @if ($row['label'] !== $row['default_label'])
                            <span class="mt-0.5 block truncate text-muted">was “{{ $row['default_label'] }}”</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <p class="border-t border-line bg-page/50 px-4 py-2.5 text-[11px] text-muted">
            Renaming is always safe: records store the key on the right, never the wording. Order is the order
            these appear in every dropdown and on every board.
        </p>
    </section>

    @script
    <script>
        /* Order is the column order on the boards, so it is draggable. */
        const initStateSort = () => {
            const el = document.querySelector('[data-states]');
            if (! el || el._sortable) return;
            el._sortable = window.Sortable.create(el, {
                handle: '.cat-drag',
                draggable: '[data-state]',
                animation: 160,
                ghostClass: 'opacity-40',
                onEnd: () => {
                    const keys = [...el.querySelectorAll('[data-state]')].map((n) => n.dataset.state);
                    $wire.reorder(keys);
                },
            });
        };
        initStateSort();
        Livewire.hook('morph.updated', () => initStateSort());
    </script>
    @endscript
</div>
