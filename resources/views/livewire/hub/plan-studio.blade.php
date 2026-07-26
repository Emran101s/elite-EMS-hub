<div>
    @php $views = ['board' => ['Board', 'grid'], 'timeline' => ['Timeline', 'chart'], 'list' => ['List', 'list'], 'gallery' => ['Gallery', 'archive']]; @endphp

    {{-- ══════════ COUNTDOWN TO EVENT DAY ══════════
         The plan's headline is how long is left, not how many gates exist. --}}
    @php
        $pct = $stats['total'] ? (int) round($stats['done'] / $stats['total'] * 100) : 0;
        $daysLeft = $event->starts_at ? (int) now()->startOfDay()->diffInDays($event->starts_at->startOfDay(), false) : null;
    @endphp
    <div class="mb-4 flex flex-wrap items-stretch gap-3">
        <div class="strip-dark relative flex min-w-0 flex-1 flex-wrap items-center gap-x-10 gap-y-4 overflow-hidden px-5 py-4">
            <div class="pointer-events-none absolute -right-8 -top-12 h-40 w-40 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.25),transparent_70%)]"></div>

            <div class="relative shrink-0">
                <p class="eyebrow-gold">Countdown to Event Day</p>
                <p class="mt-1 flex items-baseline gap-2">
                    <span class="pf text-[42px] font-black leading-none text-gold-400">{{ $daysLeft !== null ? max($daysLeft, 0) : '—' }}</span>
                    <span class="text-sm font-semibold text-white/70">{{ $daysLeft !== null && $daysLeft < 0 ? 'days ago' : 'days to go' }}</span>
                </p>
                <p class="mt-1 text-micro text-white/45">{{ $event->starts_at?->format('l, j F Y') ?? 'No date set' }}</p>
            </div>

            <div class="relative min-w-[220px] flex-1">
                <p class="text-micro font-semibold text-white/70">{{ $stats['done'] }} / {{ $stats['total'] }} done</p>
                <div class="mt-1.5 flex items-center gap-3">
                    <span class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-white/10">
                        <span class="block h-full rounded-full bg-gradient-to-r from-gold-400 to-gold-500" style="width: {{ $pct }}%"></span>
                    </span>
                    <span class="shrink-0 text-xs font-bold text-gold-300">{{ $pct }}%</span>
                </div>
                @if ($stats['overdue'] > 0)
                    <p class="mt-1.5 text-micro font-semibold text-red-300">⚠ {{ $stats['overdue'] }} overdue {{ str('task')->plural($stats['overdue']) }}</p>
                @elseif ($stats['needApproval'] > 0)
                    <p class="mt-1.5 text-micro font-semibold text-amber-300">{{ $stats['needApproval'] }} awaiting sign-off</p>
                @else
                    <p class="mt-1.5 text-micro font-semibold text-emerald-300">Nothing overdue</p>
                @endif
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <a href="{{ route('events.planning.pdf', $event) }}" target="_blank" class="btn-ghost h-11">
                <x-icon name="chart" class="h-4 w-4" /> Export PDF
            </a>
            <button type="button" wire:click="addItem" class="btn-gold h-11">＋ Add task</button>
        </div>
    </div>

    {{-- ══════════ OWNER FILTER ══════════ --}}
    <div class="mb-3 flex flex-wrap items-center gap-1.5">
        <span class="eyebrow mr-1">Owner</span>
        <button type="button" wire:click="filterByOwner" @class(['h-8 rounded-lg px-3 text-micro font-bold transition', 'bg-navy-900 text-white' => ! $filterOwner, 'bg-white text-navy-500 ring-1 ring-line hover:text-navy-800' => $filterOwner])>Everyone</button>
        @foreach ($owners as $owner)
            <button type="button" wire:click="filterByOwner({{ $owner['id'] }})"
                    @class(['flex h-8 items-center gap-1.5 rounded-lg pl-1 pr-2.5 text-micro font-bold transition', 'bg-navy-900 text-white' => $filterOwner === $owner['id'], 'bg-white text-navy-600 ring-1 ring-line hover:text-navy-900' => $filterOwner !== $owner['id']])>
                <x-user-avatar :user="$owner['user']" size="h-6 w-6" />
                {{ str($owner['user']->name)->before(' ') }}
                <span @class(['text-navy-300' => $filterOwner !== $owner['id'], 'text-white/50' => $filterOwner === $owner['id']])>{{ $owner['count'] }}</span>
            </button>
        @endforeach
    </div>

    {{-- ══════════ LEGEND ══════════ --}}
    <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1.5">
        @foreach (\App\Models\PlanItem::STATUSES as $sv => [$slabel, $shex])
            <span class="flex items-center gap-1.5 text-micro font-semibold text-navy-500">
                <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $shex }}"></span>{{ $slabel }}
                <span class="text-navy-300">{{ $gateCounts[$sv] ?? 0 }}</span>
            </span>
        @endforeach
        <span class="ml-auto text-micro text-navy-300">Click a task to edit · ▸ expand for subtasks</span>
    </div>

    {{-- ══════════ TOOLBAR: view switcher + filters + add ══════════ --}}
    <div class="mb-3 flex flex-wrap items-center gap-2.5">
        <div class="flex rounded-xl border border-line bg-white p-0.5 shadow-sm">
            @foreach ($views as $vk => [$vlabel, $vicon])
                <button type="button" wire:click="setView('{{ $vk }}')" @class(['flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-micro font-bold transition', 'bg-navy-900 text-white' => $view === $vk, 'text-navy-400 hover:text-navy-700' => $view !== $vk])>
                    <x-icon :name="$vicon" class="h-3.5 w-3.5" /> {{ $vlabel }}
                </button>
            @endforeach
        </div>

        <div class="relative">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search items…" class="input h-9 w-44 pl-8 text-xs">
            <x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-navy-300" />
        </div>

        {{-- track filter --}}
        <div class="flex flex-wrap items-center gap-1">
            <button type="button" wire:click="filterByTrack" @class(['h-8 rounded-lg px-2.5 text-micro font-bold transition', 'bg-navy-900 text-white' => ! $filterTrack, 'bg-white text-navy-500 ring-1 ring-line hover:text-navy-800' => $filterTrack])>All tracks</button>
            @foreach ($tracks as $t)
                <button type="button" wire:click="filterByTrack({{ $t->id }})" @class(['flex h-8 items-center gap-1.5 rounded-lg px-2.5 text-micro font-bold transition', 'text-white' => $filterTrack === $t->id, 'bg-white text-navy-600 ring-1 ring-line hover:text-navy-900' => $filterTrack !== $t->id]) @style(['background: '.$t->color => $filterTrack === $t->id])>
                    <span class="h-2 w-2 rounded-full" style="background: {{ $filterTrack === $t->id ? '#ffffff' : $t->color }}"></span>{{ \Illuminate\Support\Str::limit($t->name, 16) }}
                </button>
            @endforeach
        </div>

        <div class="ml-auto flex items-center gap-2">
            <button type="button" wire:click="$set('showTracks', true)" class="flex h-9 items-center gap-1.5 rounded-xl border border-line bg-white px-3 text-micro font-bold text-navy-600 shadow-sm transition hover:text-navy-900" title="Manage tracks and goals">
                <x-icon name="columns" class="h-3.5 w-3.5" /> Tracks
            </button>
        </div>
    </div>

    {{-- ══════════ ACTIVE VIEW ══════════ --}}
    <div wire:key="view-{{ $view }}">
        @includeIf('livewire.hub.partials.plan-studio.' . $view)
    </div>

    {{-- ══════════ DETAIL DRAWER ══════════ --}}
    @if ($selected)
        @include('livewire.hub.partials.plan-studio.drawer')
    @endif

    {{-- ══════════ TRACKS MANAGER ══════════ --}}
    @if ($showTracks)
        @include('livewire.hub.partials.plan-studio.tracks')
    @endif

    @script
    <script>
        if (! window.__planStudioBound) {
            window.__planStudioBound = true;
            const bind = () => {
                if (typeof window.Sortable === 'undefined') return;
                document.querySelectorAll('[data-gate-col]').forEach(col => {
                    if (col._sortable) return;
                    col._sortable = window.Sortable.create(col, {
                        group: 'gates', animation: 150, ghostClass: 'ps-ghost', dragClass: 'ps-drag',
                        draggable: '[data-card]', filter: '[data-block-drag]', preventOnFilter: false,
                        onEnd: (e) => {
                            const to = e.to.getAttribute('data-gate-col');
                            const id = +e.item.getAttribute('data-item-id');
                            const ordered = Array.from(e.to.querySelectorAll('[data-card]')).map(el => +el.getAttribute('data-item-id'));
                            window.__psWire.moveToStatus(id, to, ordered);
                        },
                    });
                });
            };
            window.__psWire = $wire;
            bind();
            Livewire.hook('morph.updated', () => { window.__psWire = $wire; setTimeout(bind, 30); });
        } else {
            window.__psWire = $wire;
        }
    </script>
    @endscript
</div>
