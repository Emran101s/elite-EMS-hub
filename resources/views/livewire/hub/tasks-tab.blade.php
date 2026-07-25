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
    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_440px]">

        {{-- ── left: toolbar + active view ── --}}
        <div class="min-w-0">
            {{-- slim toolbar --}}
            <div class="mb-3 flex flex-wrap items-center gap-2.5">
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search tasks…" class="input h-9 w-48 pl-8 text-xs">
                    <x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-navy-300" />
                </div>

                <div class="flex rounded-xl border border-line bg-white p-0.5 shadow-sm">
                    @foreach (['all' => 'All', 'mine' => 'Mine', 'overdue' => 'Overdue'] as $fk => $flabel)
                        <button type="button" wire:click="setFocus('{{ $fk }}')" @class(['rounded-lg px-2.5 py-1.5 text-micro font-bold transition', 'bg-navy-900 text-white' => $focus === $fk, 'text-navy-400 hover:text-navy-700' => $focus !== $fk])>{{ $flabel }}</button>
                    @endforeach
                </div>

                <button type="button" wire:click="addTask" class="ml-auto flex h-9 items-center gap-1.5 rounded-xl bg-gradient-to-r from-gold-400 to-gold-500 px-4 text-xs font-bold text-navy-950 shadow-[0_6px_18px_-6px_rgba(212,175,55,0.8)] transition hover:brightness-105">＋ New task</button>
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

        {{-- ── right: Task Control Center ── --}}
        <div class="xl:sticky xl:top-4">
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
