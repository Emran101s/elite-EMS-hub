{{--
    Command Center — the portfolio workspace (session 5a).

    Recomposed on ORBIT. Two rules shape it:

    - It opens with a sentence someone can act on, not a bare number. The old
      dark "Operations Room" strip is gone: the shell's KPI ribbon already says
      how many events there are and how many are at risk, and repeating it
      taught nobody anything.
    - Exactly one card carries data-gravity="hero" — the greeting and its
      lenses, because deciding what to look at is why anyone opens this page.
--}}
@php
    use App\Support\Tone;

    $firstName = \Illuminate\Support\Str::of(auth()->user()?->name ?? '')->explode(' ')->first();
    $critical = $signals->where('severity', 'critical')->count();

    // The opening sentence changes with the state of the portfolio, so it is
    // never the same empty greeting two days running.
    $headline = match (true) {
        $signalTotal === 0 => 'Nothing needs you right now.',
        $critical > 0 => $critical.' '.str('thing')->plural($critical).' '.($critical === 1 ? 'needs' : 'need').' you today.',
        default => $signalTotal.' open '.str('signal')->plural($signalTotal).', none critical.',
    };

    $lensTone = ['overdue' => 'critical', 'approvals' => 'flare', 'blocked' => 'plasma', 'money' => 'gold', 'risks' => 'flare'];
@endphp

<div style="display:grid;gap:var(--o-4)">

    {{-- ══ HERO — what needs you, and the fastest way to act on it ══ --}}
    <x-orbit.card gravity="hero">
        <x-orbit.greeting :heading="'Good '.(now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening')).', '.$firstName">
            <x-slot:summary>
                <b>{{ $headline }}</b>
                @if ($focused)
                    Focused on <b>{{ $focused->name }}</b> —
                    <button type="button" wire:click="focusOn" style="color:var(--gold);font-weight:600">show everything</button>.
                @endif
            </x-slot:summary>
            <x-slot:aside>
                <x-orbit.btn variant="gold" :href="route('events.create')">Create event</x-orbit.btn>
            </x-slot:aside>
        </x-orbit.greeting>

        {{-- Lenses — the same filters as before, as chips. --}}
        <div class="o-chips" style="margin-top:var(--o-5)">
            <button type="button" wire:click="setLens('all')" class="o-badge" @if ($lens === 'all') data-tone="gold" @endif>
                All {{ $signals->count() }}
            </button>
            @foreach (\App\Livewire\CommandCenter::LENSES as $key => [$label, $hex])
                @continue (($lensCounts[$key] ?? 0) === 0)
                <button type="button" wire:click="setLens('{{ $key }}')" class="o-badge"
                        @if ($lens === $key) data-tone="{{ $lensTone[$key] ?? 'ion' }}" @endif>
                    {{ $label }} {{ $lensCounts[$key] }}
                </button>
            @endforeach
        </div>
    </x-orbit.card>

    {{-- ══ ACTION STREAM ══ --}}
    <x-orbit.card :pad="false">
        <x-slot:head>
            <h3 class="o-card__title">What needs you</h3>
            <span class="o-mute">{{ $signalTotal }} {{ str('signal')->plural($signalTotal) }}</span>
        </x-slot:head>

        @forelse ($signals as $s)
            @php $tone = $s['severity'] === 'critical' ? 'critical' : ($lensTone[$s['lens']] ?? 'ion'); @endphp
            <a href="{{ $s['link'] }}" class="o-row" wire:key="{{ $s['key'] }}">
                <span class="o-row__icon" style="color:{{ Tone::from($tone)->var() }}">
                    <x-orbit.icon :name="$s['severity'] === 'critical' ? 'warn' : 'clock'" :size="17" />
                </span>
                <div class="min-w-0">
                    <div class="o-row__name">{{ $s['title'] }}</div>
                    <div class="o-row__meta"><span>{{ $s['event_name'] }}</span><span>{{ $s['detail'] }}</span></div>
                </div>
                <x-orbit.badge :tone="$tone" :pulse="$s['severity'] === 'critical'">
                    {{ \App\Livewire\CommandCenter::LENSES[$s['lens']][0] ?? $s['lens'] }}
                </x-orbit.badge>
                <span></span>
            </a>
        @empty
            <div style="padding:var(--o-5)">
                <x-orbit.empty icon="spark" title="Nothing is waiting on you"
                               body="Signals land here when a task runs late, an approval sits unanswered, a payment slips or a risk escalates. An empty stream means every event is on plan." />
            </div>
        @endforelse
    </x-orbit.card>

    {{-- ══ NEXT 14 DAYS · MONEY ══ --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:var(--o-4)">
        <x-orbit.card title="Next 14 days" :pad="false">
            @forelse ($week as $e)
                @php $days = (int) round(now()->startOfDay()->diffInDays($e->starts_at->copy()->startOfDay(), false)); @endphp
                <x-orbit.row :name="$e->name" icon="cal"
                             :meta="array_filter([$e->city, $e->starts_at->format('D j M')])"
                             :href="route('events.hub', $e)">
                    <x-orbit.badge :tone="$days <= 2 ? 'critical' : ($days <= 7 ? 'flare' : 'ion')">
                        {{ $days <= 0 ? 'Live' : 'in '.$days.'d' }}
                    </x-orbit.badge>
                </x-orbit.row>
            @empty
                <p class="o-mute" style="margin:0;padding:var(--o-5)">
                    No event starts in the next fortnight.
                    @if ($next = $events->whereNotNull('starts_at')->sortBy('starts_at')->first())
                        The nearest is {{ $next->name }}, {{ $next->starts_at->diffForHumans() }}.
                    @endif
                </p>
            @endforelse
        </x-orbit.card>

        <x-orbit.card title="Money">
            @php
                $cur = $w['currency'] ?? 'JD';
                $budget = (int) ($w['budget'] ?? 0);
                $spent = (int) ($w['spent'] ?? 0);
                $outstanding = (int) ($w['outstanding'] ?? 0);
                $remaining = max(0, $budget - $spent);
            @endphp
            <div class="o-metric">{{ $cur }} {{ number_format($budget / 100) }}</div>
            <p class="o-mute" style="margin:5px 0 var(--o-4)">committed across every active event</p>

            @if ($budget > 0)
                <x-orbit.meter tall legend :total="$budget" :segments="[
                    ['value' => $spent, 'tone' => 'plasma', 'label' => 'Spent', 'display' => $cur.' '.number_format($spent / 100)],
                    ['value' => $remaining, 'tone' => 'vital', 'label' => 'Remaining', 'display' => $cur.' '.number_format($remaining / 100)],
                ]" />
            @else
                <p class="o-mute" style="margin:0">No budgets set yet — add one on an event to see spend against plan here.</p>
            @endif

            @if ($outstanding > 0)
                <p class="o-mute" style="margin-top:var(--o-4)">
                    <b style="color:var(--flare)">{{ $cur }} {{ number_format($outstanding / 100) }}</b> still outstanding.
                </p>
            @endif
        </x-orbit.card>
    </div>

    {{-- ══ TEAM · TASKS ══ --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:var(--o-4)">
        <x-orbit.card title="Team & assignments">
            <div style="display:flex;align-items:baseline;gap:8px">
                <span class="o-metric">{{ $w['teamSize'] ?? 0 }}</span>
                <span class="o-mute">people · <b style="color:var(--ink)">{{ $w['unassigned'] ?? 0 }}</b> unassigned</span>
            </div>
            <ul class="o-pulse" style="margin-top:var(--o-4)">
                @foreach (collect($w['people'] ?? [])->take(6) as $p)
                    <li>
                        <x-orbit.avatar :name="$p['user']->name ?? ''" size="sm" />
                        {{ $p['user']->name ?? '—' }}
                        <b>{{ $p['open'] ?? 0 }}</b>
                    </li>
                @endforeach
            </ul>
        </x-orbit.card>

        <x-orbit.card title="Task overview">
            @php
                $done = (int) ($w['taskDone'] ?? 0);
                $total = (int) ($w['taskTotal'] ?? 0);
                $pct = $total > 0 ? (int) round($done / $total * 100) : 0;
            @endphp
            <div style="display:flex;align-items:center;gap:var(--o-5)">
                <x-orbit.ring :value="$pct" label="Done" />
                <ul class="o-pulse" style="flex:1">
                    <li><i style="background:var(--vital-lit)"></i>Completed<b>{{ $done }}</b></li>
                    <li><i style="background:var(--ion-lit)"></i>Open<b>{{ max(0, $total - $done) }}</b></li>
                    @if ($w['overdue'] ?? 0)<li><i style="background:var(--critical-lit)"></i>Overdue<b>{{ $w['overdue'] }}</b></li>@endif
                    @if ($w['approvals'] ?? 0)<li><i style="background:var(--flare-lit)"></i>Approvals<b>{{ $w['approvals'] }}</b></li>@endif
                </ul>
            </div>
        </x-orbit.card>
    </div>

    {{-- ══ PORTFOLIO ══ --}}
    <x-orbit.card :pad="false">
        <x-slot:head>
            <h3 class="o-card__title">Portfolio</h3>
            <a href="{{ route('events.index') }}" class="o-btn o-btn--quiet o-btn--sm">All events →</a>
        </x-slot:head>
        @foreach ($events as $e)
            @php
                $score = (int) ($health[$e->id]['score'] ?? 0);
                $days = $e->starts_at ? (int) round(now()->startOfDay()->diffInDays($e->starts_at->copy()->startOfDay(), false)) : null;
                $open = $byEvent[$e->id] ?? 0;
            @endphp
            <div class="o-row" wire:key="pf-{{ $e->id }}">
                <span class="o-row__icon"><x-orbit.icon name="grid" :size="17" /></span>
                <div class="min-w-0">
                    <a href="{{ route('events.hub', $e) }}" class="o-row__name">{{ $e->name }}</a>
                    <div class="o-row__meta">
                        <span>{{ $e->client?->name ?? str($e->type)->replace('_', ' ')->title() }}</span>
                        @if ($days !== null)<span>{{ $days > 0 ? $days.' days out' : 'live' }}</span>@endif
                        @if ($open)<span style="color:var(--critical)">{{ $open }} {{ str('signal')->plural($open) }}</span>@endif
                    </div>
                </div>
                <button type="button" wire:click="focusOn({{ $e->id }})" class="o-btn o-btn--ghost o-btn--sm">Focus</button>
                <x-orbit.ring :value="$score" :size="44" :stroke="4" :label="null" :show-num="false" />
            </div>
        @endforeach
    </x-orbit.card>
</div>
