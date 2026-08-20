<div>
    @php $views = ['board' => ['Board', 'grid'], 'timeline' => ['Timeline', 'chart'], 'list' => ['List', 'list'], 'gallery' => ['Gallery', 'archive']]; @endphp

    {{-- ══════════ COUNTDOWN TO EVENT DAY ══════════
         The plan's headline is how long is left, not how many gates exist. --}}
    @php
        $pct = $stats['total'] ? (int) round($stats['done'] / $stats['total'] * 100) : 0;
        $daysLeft = $event->starts_at ? (int) now()->startOfDay()->diffInDays($event->starts_at->startOfDay(), false) : null;
    @endphp
    {{-- The old "Countdown to Event Day" headline strip (ring + lead figures,
         in a dark x-module-head) is retired as this page's PRIMARY header —
         the Event Hub's new Universal Module Header now carries that role
         above this component. The countdown itself is real information the
         header doesn't show, so it stays, just visually subordinate: a
         planning pulse beneath the header, not a second competing one. Its
         own "＋ Add task" action is dropped as redundant — every view below
         (Board/List/Gallery) already has its own add-item entry point.
         Export PDF stays, since nothing else on this page offers it. --}}
    <div class="hubx-countdown-panel">
        <div class="hubx-countdown-lead">
            <span class="hubx-countdown-days">{{ $daysLeft !== null ? max($daysLeft, 0) : '—' }}</span>
            <div class="hubx-countdown-meta">
                <span class="hubx-countdown-label">Countdown to Event Day</span>
                <span class="hubx-countdown-note">{{ $daysLeft !== null && $daysLeft < 0 ? 'days ago' : 'days to go' }} · {{ $event->starts_at?->format('l, j F Y') ?? 'No date set' }}</span>
            </div>
        </div>

        <div class="hubx-countdown-stats">
            <div class="hubx-countdown-stat">
                <span class="hubx-countdown-stat-value">{{ $pct }}%</span>
                <span class="hubx-countdown-stat-label">Plan progress</span>
            </div>
            <div class="hubx-countdown-stat">
                <span class="hubx-countdown-stat-value">{{ $stats['done'] }} / {{ $stats['total'] }}</span>
                <span class="hubx-countdown-stat-label">Done</span>
            </div>
            <div class="hubx-countdown-stat">
                <span class="hubx-countdown-stat-value" style="color: {{ $stats['overdue'] > 0 ? 'var(--color-eo-risk)' : 'var(--color-eo-text)' }}">
                    {{ $stats['overdue'] > 0 ? $stats['overdue'] : $stats['needApproval'] }}
                </span>
                <span class="hubx-countdown-stat-label">{{ $stats['overdue'] > 0 ? 'Overdue' : 'Awaiting sign-off' }}</span>
            </div>
        </div>

        <a href="{{ route('events.planning.pdf', $event) }}" target="_blank" class="hubx-countdown-export">
            <x-icon name="chart" class="h-3.5 w-3.5" /> Export PDF
        </a>
    </div>

    {{-- ══════════ OWNER FILTER ══════════ --}}
    <div class="mb-3 flex flex-wrap items-center gap-1.5">
        <span class="eyebrow mr-1">Owner</span>
        <button type="button" wire:click="filterByOwner" @class(['h-8 rounded-lg px-3 text-micro font-bold transition', 'bg-eo-navy text-white' => ! $filterOwner, 'bg-white text-eo-muted ring-1 ring-eo-line hover:text-eo-text' => $filterOwner])>Everyone</button>
        @foreach ($owners as $owner)
            <button type="button" wire:click="filterByOwner({{ $owner['id'] }})"
                    @class(['flex h-8 items-center gap-1.5 rounded-lg pl-1 pr-2.5 text-micro font-bold transition', 'bg-eo-navy text-white' => $filterOwner === $owner['id'], 'bg-white text-eo-text ring-1 ring-eo-line hover:text-eo-text' => $filterOwner !== $owner['id']])>
                <x-user-avatar :user="$owner['user']" size="h-6 w-6" />
                {{ str($owner['user']->name)->before(' ') }}
                <span @class(['text-eo-muted' => $filterOwner !== $owner['id'], 'text-white/50' => $filterOwner === $owner['id']])>{{ $owner['count'] }}</span>
            </button>
        @endforeach
    </div>

    {{-- ══════════ LEGEND ══════════ --}}
    <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1.5">
        @foreach (\App\Models\PlanItem::STATUSES as $sv => [$slabel, $shex])
            <span class="flex items-center gap-1.5 text-micro font-semibold text-eo-muted">
                <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $shex }}"></span>{{ $slabel }}
                <span class="text-eo-muted">{{ $gateCounts[$sv] ?? 0 }}</span>
            </span>
        @endforeach
        <span class="ml-auto text-micro text-eo-muted">Click a task to edit · ▸ expand for subtasks</span>
    </div>

    {{-- ══════════ TOOLBAR: view switcher + filters + add ══════════ --}}
    <div class="mb-3 flex flex-wrap items-center gap-2.5">
        <div class="flex rounded-xl border border-eo-line bg-white p-0.5 shadow-sm">
            @foreach ($views as $vk => [$vlabel, $vicon])
                <button type="button" wire:click="setView('{{ $vk }}')" @class(['flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-micro font-bold transition', 'bg-eo-navy text-white' => $view === $vk, 'text-eo-muted hover:text-eo-text' => $view !== $vk])>
                    <x-icon :name="$vicon" class="h-3.5 w-3.5" /> {{ $vlabel }}
                </button>
            @endforeach
        </div>

        <div class="relative">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search items…" class="eo-input h-9 w-44 pl-8 text-xs">
            <x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-eo-muted" />
        </div>

        {{-- track filter --}}
        <div class="flex flex-wrap items-center gap-1">
            <button type="button" wire:click="filterByTrack" @class(['h-8 rounded-lg px-2.5 text-micro font-bold transition', 'bg-eo-navy text-white' => ! $filterTrack, 'bg-white text-eo-muted ring-1 ring-eo-line hover:text-eo-text' => $filterTrack])>All tracks</button>
            @foreach ($tracks as $t)
                <button type="button" wire:click="filterByTrack({{ $t->id }})" @class(['flex h-8 items-center gap-1.5 rounded-lg px-2.5 text-micro font-bold transition', 'text-white' => $filterTrack === $t->id, 'bg-white text-eo-text ring-1 ring-eo-line hover:text-eo-text' => $filterTrack !== $t->id]) @style(['background: '.$t->color => $filterTrack === $t->id])>
                    <span class="h-2 w-2 rounded-full" style="background: {{ $filterTrack === $t->id ? '#ffffff' : $t->color }}"></span>{{ \Illuminate\Support\Str::limit($t->name, 16) }}
                </button>
            @endforeach
        </div>

        <div class="ml-auto flex items-center gap-2">
            <button type="button" wire:click="$set('showTracks', true)" class="flex h-9 items-center gap-1.5 rounded-xl border border-eo-line bg-white px-3 text-micro font-bold text-eo-text shadow-sm transition hover:text-eo-text" title="Manage tracks and goals">
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
