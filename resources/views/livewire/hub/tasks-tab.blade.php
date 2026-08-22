<div x-data="{ toasts: [], push(e) { const id = Date.now() + Math.random(); this.toasts.push({ id, message: e.detail.message, tone: e.detail.tone || 'ok' }); setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), 2600); } }" @task-toast.window="push($event)">
    @php $views = ['board' => ['Board', 'grid'], 'timeline' => ['Timeline', 'chart'], 'list' => ['List', 'list'], 'gallery' => ['Gallery', 'archive']]; @endphp

    {{-- toast host --}}
    <div class="pointer-events-none fixed bottom-6 right-6 z-[60] flex flex-col gap-2">
        <template x-for="t in toasts" :key="t.id">
            <div class="pointer-events-auto flex items-center gap-2 rounded-lg bg-navy-900 px-4 py-2.5 text-xs font-semibold text-white shadow-float" x-transition>
                <span class="h-1.5 w-1.5 rounded-full" :class="t.tone === 'ok' ? 'bg-success' : 'bg-warning'"></span><span x-text="t.message"></span>
            </div>
        </template>
    </div>

    {{-- ══════════ THE WORKSPACE ══════════
         The board always has the whole width. The Control Center used to take
         440px of it permanently, which is why five columns could not fit and
         Done sat past the edge — so it is now a panel that slides over the
         board when you ask for it, and takes nothing when you do not. --}}
    <div x-data="{ cc: $persist(false).as('ebh-task-cc') }">

        {{-- ── toolbar + active view ── --}}
        <div class="min-w-0">
            {{-- lead chips: scan numbers before the board. "Overdue" is dropped
                 here — the Universal Module Header already shows that exact
                 count; the "Overdue" filter button below is unaffected. --}}
            @php $openChip = max($stats['total'] - $stats['done'], 0); @endphp
            <div class="mb-3 flex flex-wrap items-center gap-1.5">
                @foreach ([
                    ['Open', $openChip, null],
                    ['Mine', $stats['mine'], null],
                    ['Done', $stats['pct'].'%', null],
                ] as [$chipLabel, $chipVal, $chipTone])
                    <span @class([
                        'inline-flex h-7 items-center gap-1.5 rounded-full px-2.5 text-eyebrow font-bold ring-1',
                        $chipTone ?: 'bg-white text-ink ring-line',
                    ])>
                        <span class="text-muted">{{ $chipLabel }}</span>
                        <span class="tabular-nums text-ink">{{ $chipVal }}</span>
                    </span>
                @endforeach
            </div>

            {{-- slim toolbar --}}
            <div class="mb-3 flex flex-wrap items-center gap-2.5">
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search tasks…" class="h-9 w-48 rounded-full border border-line bg-white pl-8 pr-3 text-xs text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
                    <x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted" />
                </div>

                <div class="flex rounded-full border border-line bg-white p-0.5 shadow-sm">
                    @foreach (['all' => 'All', 'mine' => 'Mine', 'overdue' => 'Overdue'] as $fk => $flabel)
                        <button type="button" wire:click="setFocus('{{ $fk }}')" @class(['rounded-full px-2.5 py-1.5 text-micro font-bold transition', 'bg-navy-900 text-white' => $focus === $fk, 'text-muted hover:text-ink' => $focus !== $fk])>{{ $flabel }}</button>
                    @endforeach
                </div>

                <button type="button" @click="cc = ! cc"
                        :class="cc ? 'border-navy-900 bg-navy-900 text-white' : 'border-line bg-white text-muted hover:border-navy-300 hover:text-gold-700'"
                        class="ml-auto flex h-9 items-center gap-1.5 rounded-full border px-3 text-xs font-bold shadow-sm transition">
                    <x-icon name="chart" class="h-3.5 w-3.5" />
                    <span x-text="cc ? 'Hide panel' : 'Control Center'"></span>
                </button>

                <button type="button" wire:click="addTask" class="rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">＋ New task</button>
            </div>

            {{-- module filter --}}
            <div class="mb-4 flex flex-wrap items-center gap-1">
                <span class="mr-1 text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Module</span>
                <button type="button" wire:click="filterByModule('')" @class(['h-7 rounded-full px-2.5 text-micro font-bold transition', 'bg-navy-900 text-white' => $filterArea === '', 'bg-white text-muted ring-1 ring-line hover:text-ink' => $filterArea !== ''])>All</button>
                @foreach ($moduleCounts as $slug => $count)
                    @php [$mlabel, $mhex] = \App\Models\Task::MODULES[$slug] ?? [ucfirst($slug), 'var(--color-neutral)']; @endphp
                    <button type="button" wire:click="filterByModule('{{ $slug }}')" @class(['flex h-7 items-center gap-1.5 rounded-full px-2.5 text-micro font-bold transition', 'text-white' => $filterArea === $slug, 'bg-white text-muted ring-1 ring-line hover:text-ink' => $filterArea !== $slug]) @style(['background: '.$mhex => $filterArea === $slug])>
                        <span class="h-2 w-2 rounded-full" style="background: {{ $filterArea === $slug ? '#fff' : $mhex }}"></span>{{ $mlabel }} <span class="opacity-60">{{ $count }}</span>
                    </button>
                @endforeach
            </div>

            {{-- active view --}}
            <div wire:key="tview-{{ $view }}">
                @includeIf('livewire.hub.partials.tasks-studio.' . $view)
            </div>
        </div>

        {{-- ── the Control Center, over the board rather than beside it ──
             No dimming backdrop: this is a panel you glance at, and the board
             behind it should stay readable while you do. It closes on its own
             button or on Escape — not on any click outside, which would fire
             every time you reached for a card. --}}
        <div x-show="cc" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
             x-on:keydown.escape.window="cc = false"
             class="fixed inset-y-0 right-0 z-30 w-[min(440px,92vw)] overflow-y-auto bg-page/95 p-4 shadow-float backdrop-blur">
            <div class="mb-3 flex items-center justify-end">
                <button type="button" @click="cc = false"
                        class="flex h-8 items-center gap-1.5 rounded-lg bg-white px-2.5 text-eyebrow font-bold text-muted ring-1 ring-line transition hover:text-ink">
                    Close ✕
                </button>
            </div>
            @include('livewire.hub.partials.tasks-studio.control-center')
        </div>
    </div>

    {{-- ══════════ DRAWER ══════════ --}}
    @if ($detail)
        @include('livewire.hub.partials.tasks-studio.drawer')
    @endif

    @script
    <script>
        if (! window.__taskStudioBound) {
            window.__taskStudioBound = true;
            const bind = () => {
                if (typeof window.Sortable === 'undefined') return;
                document.querySelectorAll('[data-task-col]').forEach(col => {
                    if (col._sortable) return;
                    col._sortable = window.Sortable.create(col, {
                        group: 'taskgates', animation: 150, ghostClass: 'ts-ghost', dragClass: 'ts-drag',
                        draggable: '[data-card]', filter: '[data-block-drag]', preventOnFilter: false,
                        onEnd: (e) => {
                            const to = e.to.getAttribute('data-task-col');
                            const id = +e.item.getAttribute('data-item-id');
                            const ordered = Array.from(e.to.querySelectorAll('[data-card]')).map(el => +el.getAttribute('data-item-id'));
                            window.__tsWire.moveToStatus(id, to, ordered);
                        },
                    });
                });
            };
            window.__tsWire = $wire;
            bind();
            Livewire.hook('morph.updated', () => { window.__tsWire = $wire; setTimeout(bind, 30); });
        } else {
            window.__tsWire = $wire;
        }
    </script>
    @endscript
</div>
