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

    {{-- ══════════ COMMAND HEADER ══════════ --}}
    <div class="mb-4 flex flex-wrap items-stretch gap-3">
        <div class="strip-dark relative flex min-w-0 flex-1 flex-wrap items-center gap-x-8 gap-y-4 overflow-hidden px-5 py-4">
            <div class="pointer-events-none absolute -right-8 -top-12 h-40 w-40 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.25),transparent_70%)]"></div>
            @php $ring = 2 * M_PI * 26; @endphp
            <div class="relative flex shrink-0 items-center gap-3">
                <div class="relative">
                    <svg class="h-[70px] w-[70px] -rotate-90" viewBox="0 0 60 60">
                        <circle cx="30" cy="30" r="26" fill="none" stroke="rgba(255,255,255,.12)" stroke-width="6"/>
                        <circle cx="30" cy="30" r="26" fill="none" stroke="var(--color-gold-500)" stroke-width="6" stroke-linecap="round" stroke-dasharray="{{ $ring }}" stroke-dashoffset="{{ $ring - ($ring * $stats['pct'] / 100) }}"/>
                    </svg>
                    <span class="absolute inset-0 flex items-center justify-center text-sm font-black text-white">{{ $stats['pct'] }}%</span>
                </div>
                <div>
                    <p class="text-eyebrow font-bold uppercase tracking-[0.2em] text-gold-300">Tasks Studio</p>
                    <p class="pf text-2xl font-black leading-none text-white">{{ $stats['done'] }}<span class="text-base text-white/40">/{{ $stats['total'] }}</span></p>
                    <p class="mt-0.5 text-eyebrow font-semibold text-white/55">tasks done</p>
                </div>
            </div>

            <div class="relative flex min-w-0 flex-1 items-center gap-1.5">
                @foreach (\App\Models\Task::STAGES as $sv => [$slabel, $shex])
                    <div class="flex min-w-0 flex-1 flex-col items-center gap-1">
                        <div class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-white/[0.06] py-1.5 ring-1 ring-white/10">
                            <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $shex }}"></span>
                            <span class="text-sm font-black text-white">{{ $gateCounts[$sv] ?? 0 }}</span>
                        </div>
                        <span class="w-full truncate text-center text-eyebrow font-bold uppercase tracking-wide text-white/45">{{ $slabel }}</span>
                    </div>
                    @if (! $loop->last)<span class="shrink-0 text-white/20">→</span>@endif
                @endforeach
            </div>

            <div class="relative flex shrink-0 gap-2">
                <div class="flex h-[62px] w-[4.4rem] flex-col justify-center rounded-xl bg-black/20 px-2 text-center ring-1 ring-white/10">
                    <span class="text-xl font-black leading-none" style="color: {{ $stats['needApproval'] > 0 ? 'var(--color-warning)' : 'rgba(255,255,255,.4)' }}">{{ $stats['needApproval'] }}</span>
                    <span class="mt-1 text-eyebrow font-bold uppercase leading-tight tracking-wider text-white/50">Need approval</span>
                </div>
                <div class="flex h-[62px] w-[4.4rem] flex-col justify-center rounded-xl bg-black/20 px-2 text-center ring-1 ring-white/10">
                    <span class="text-xl font-black leading-none" style="color: {{ $stats['overdue'] > 0 ? 'var(--color-danger)' : 'rgba(255,255,255,.4)' }}">{{ $stats['overdue'] }}</span>
                    <span class="mt-1 text-eyebrow font-bold uppercase leading-tight tracking-wider text-white/50">Overdue</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════ TOOLBAR ══════════ --}}
    <div class="mb-2 flex flex-wrap items-center gap-2.5">
        <div class="flex rounded-xl border border-line bg-white p-0.5 shadow-sm">
            @foreach ($views as $vk => [$vlabel, $vicon])
                <button type="button" wire:click="setView('{{ $vk }}')" @class(['flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-micro font-bold transition', 'bg-navy-900 text-white' => $view === $vk, 'text-navy-400 hover:text-navy-700' => $view !== $vk])>
                    <x-icon :name="$vicon" class="h-3.5 w-3.5" /> {{ $vlabel }}
                </button>
            @endforeach
        </div>

        <div class="relative">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search tasks…" class="input h-9 w-40 pl-8 text-xs">
            <x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-navy-300" />
        </div>

        <div class="flex rounded-xl border border-line bg-white p-0.5 shadow-sm">
            @foreach (['all' => 'All', 'mine' => 'Mine', 'overdue' => 'Overdue'] as $fk => $flabel)
                <button type="button" wire:click="setFocus('{{ $fk }}')" @class(['rounded-lg px-2.5 py-1.5 text-micro font-bold transition', 'bg-navy-900 text-white' => $focus === $fk, 'text-navy-400 hover:text-navy-700' => $focus !== $fk])>{{ $flabel }}</button>
            @endforeach
        </div>

        <a href="{{ route('events.hub', [$event, 'tab' => 'planning']) }}" class="flex h-9 items-center gap-1.5 rounded-xl border border-line bg-white px-3 text-micro font-bold text-navy-500 shadow-sm transition hover:text-navy-900" title="Open the plan"><x-icon name="list" class="h-3.5 w-3.5" /> Plan</a>

        <button type="button" wire:click="addTask" class="ml-auto flex h-9 items-center gap-1.5 rounded-xl bg-gradient-to-r from-gold-400 to-gold-500 px-4 text-xs font-bold text-navy-950 shadow-sm transition hover:brightness-105">＋ New task</button>
    </div>

    {{-- module filter --}}
    <div class="mb-3 flex flex-wrap items-center gap-1">
        <span class="mr-1 w-12 shrink-0 text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Module</span>
        <button type="button" wire:click="filterByModule('')" @class(['h-7 rounded-lg px-2.5 text-micro font-bold transition', 'bg-navy-900 text-white' => $filterArea === '', 'bg-white text-navy-500 ring-1 ring-line hover:text-navy-800' => $filterArea !== ''])>All</button>
        @foreach ($moduleCounts as $slug => $count)
            @php [$mlabel, $mhex] = \App\Models\Task::MODULES[$slug] ?? [ucfirst($slug), 'var(--color-neutral)']; @endphp
            <button type="button" wire:click="filterByModule('{{ $slug }}')" @class(['flex h-7 items-center gap-1.5 rounded-lg px-2.5 text-micro font-bold transition', 'text-white' => $filterArea === $slug, 'bg-white text-navy-600 ring-1 ring-line hover:text-navy-900' => $filterArea !== $slug]) @style(['background: '.$mhex => $filterArea === $slug])>
                <span class="h-2 w-2 rounded-full" style="background: {{ $filterArea === $slug ? '#fff' : $mhex }}"></span>{{ $mlabel }} <span class="opacity-60">{{ $count }}</span>
            </button>
        @endforeach
    </div>

    {{-- ══════════ ACTIVE VIEW ══════════ --}}
    <div wire:key="tview-{{ $view }}">
        @includeIf('livewire.hub.partials.tasks-studio.' . $view)
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
