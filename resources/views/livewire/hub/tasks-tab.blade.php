<div x-data="{ toasts: [], push(e) { const id = Date.now() + Math.random(); this.toasts.push({ id, message: e.detail.message, tone: e.detail.tone || 'ok' }); setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), 2600); } }" @task-toast.window="push($event)">
    @php $views = ['board' => ['Board', 'grid'], 'timeline' => ['Timeline', 'chart'], 'list' => ['List', 'list'], 'gallery' => ['Gallery', 'archive']]; @endphp

    {{-- toast host --}}
    <div class="pointer-events-none fixed bottom-6 right-6 z-[60] flex flex-col gap-2">
        <template x-for="t in toasts" :key="t.id">
            <div class="pointer-events-auto flex items-center gap-2 rounded-xl bg-navy-900 px-4 py-2.5 text-xs font-semibold text-white shadow-2xl" x-transition>
                <span class="h-1.5 w-1.5 rounded-full" :class="t.tone === 'ok' ? 'bg-emerald-400' : 'bg-amber-400'"></span><span x-text="t.message"></span>
            </div>
        </template>
    </div>

    {{-- ══════════ WORKSPACE + CONTROL CENTER ══════════ --}}
    {{-- The ORBIT shell owns the right edge (Event Pulse + AI Director), so the
         Control Center runs full-width beneath the board rather than competing
         with the rails for the same 440px. --}}
    <div style="display:grid;gap:var(--o-4)">

        <div class="min-w-0">
            {{-- toolbar: what am I looking at, and how --}}
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:var(--o-3);margin-bottom:var(--o-4)">
                <input type="text" class="o-input" style="width:210px"
                       wire:model.live.debounce.300ms="search" placeholder="Search tasks…">

                {{-- The brief's filter tabs. Overdue is the only one that ever pulses. --}}
                <div class="o-tabs" role="tablist">
                    @foreach (['all' => 'All', 'mine' => 'Mine', 'overdue' => 'Overdue', 'today' => 'Today', 'week' => 'This week'] as $fk => $flabel)
                        <button type="button" role="tab" wire:click="setFocus('{{ $fk }}')"
                                aria-selected="{{ $focus === $fk ? 'true' : 'false' }}">{{ $flabel }}</button>
                    @endforeach
                </div>

                <div class="o-seg" role="tablist" style="margin-left:auto">
                    @foreach ($views as $vk => [$vlabel, $vicon])
                        <button type="button" role="tab" wire:click="setView('{{ $vk }}')"
                                aria-selected="{{ $view === $vk ? 'true' : 'false' }}">{{ $vlabel }}</button>
                    @endforeach
                </div>

                <x-orbit.btn variant="gold" wire:click="addTask">New task</x-orbit.btn>
            </div>

            {{-- module filter --}}
            <div class="mb-4 flex flex-wrap items-center gap-1">
                <span class="mr-1 text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Module</span>
                <button type="button" wire:click="filterByModule('')" @class(['h-7 rounded-lg px-2.5 text-micro font-bold transition', 'bg-navy-900 text-white' => $filterArea === '', 'bg-white text-navy-500 ring-1 ring-line hover:text-navy-800' => $filterArea !== ''])>All</button>
                @foreach ($moduleCounts as $slug => $count)
                    @php [$mlabel, $mhex] = \App\Models\Task::MODULES[$slug] ?? [ucfirst($slug), 'var(--color-neutral)']; @endphp
                    <button type="button" wire:click="filterByModule('{{ $slug }}')" @class(['flex h-7 items-center gap-1.5 rounded-lg px-2.5 text-micro font-bold transition', 'text-white' => $filterArea === $slug, 'bg-white text-navy-600 ring-1 ring-line hover:text-navy-900' => $filterArea !== $slug]) @style(['background: '.$mhex => $filterArea === $slug])>
                        <span class="h-2 w-2 rounded-full" style="background: {{ $filterArea === $slug ? 'var(--chrome-ink)' : $mhex }}"></span>{{ $mlabel }} <span class="opacity-60">{{ $count }}</span>
                    </button>
                @endforeach
            </div>

            {{-- active view --}}
            <div wire:key="tview-{{ $view }}">
                @includeIf('livewire.hub.partials.tasks-studio.' . $view)
            </div>
        </div>

        @include('livewire.hub.partials.tasks-studio.control-center')
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
