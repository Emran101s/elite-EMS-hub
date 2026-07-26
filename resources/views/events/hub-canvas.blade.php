@php
    use App\Support\CommandCanvasData as D;
    use App\Support\EventCanvasData as E;

    $band = D::band((int) ($health['score'] ?? 0));
    $modules = E::dock($event, $tab);
    $moduleLabel = $tab === 'overview' ? 'Overview' : (\App\Models\Event::HUB_MODULES[$tab][0] ?? str($tab)->title());
    $insights = collect($ai['attention'] ?? [])->take(4)->values();
@endphp
<x-layouts.canvas :title="$event->name.' — Event Hub'">

    {{-- ══ COMMAND RIBBON — the event's identity ══ --}}
    <header class="flex flex-wrap items-center gap-4 bg-white px-4 py-3 2xl:flex-nowrap 2xl:px-6">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3 rounded-2xl bg-cc-navy px-4 py-3 cc-lift-2">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-cc-gold">
                <span class="block h-4 w-4 rotate-45 rounded-[3px] border-2 border-cc-navy"></span>
            </span>
            <span class="leading-none">
                <span class="block text-[15px] font-extrabold tracking-[0.22em] text-white">ELITE</span>
                <span class="mt-1 block text-[8px] font-semibold tracking-[0.3em] text-cc-gold">BUSINESS HUB</span>
            </span>
        </a>

        <a href="{{ route('events.index') }}" class="flex shrink-0 items-center gap-1.5 rounded-xl border border-cc-line px-3 py-2 text-[12px] font-bold text-cc-ink-2 transition hover:border-cc-gold hover:text-cc-navy">
            <x-canvas.icon name="chev" :size="14" class="rotate-90" /> Events
        </a>

        <div class="min-w-0 basis-full xl:basis-auto xl:flex-1">
            <p class="text-[10px] font-bold uppercase tracking-[0.24em] text-cc-ink-3">{{ $moduleLabel }}</p>
            <h1 class="mt-0.5 truncate text-xl font-extrabold tracking-tight text-cc-navy xl:text-[24px]">{{ $event->name }}</h1>
            <p class="mt-0.5 truncate text-[12px] text-cc-ink-2">
                {{ $event->starts_at?->format('M j') }} – {{ ($event->ends_at ?? $event->starts_at)?->format('M j, Y') }}
                @if ($event->venue?->name) · {{ $event->venue->name }} @elseif ($event->city) · {{ $event->city }} @endif
                @if ($event->client?->name) · {{ $event->client->name }} @endif
            </p>
        </div>

        <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
            <x-canvas.health-badge :score="(int) ($health['score'] ?? 0)" size="sm" class="shrink-0" />
            @if ($event->projectManager)
                <span class="hidden items-center gap-2 rounded-xl border border-cc-line px-2.5 py-1.5 lg:flex" title="Project manager: {{ $event->projectManager->name }}">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-cc-navy text-[10px] font-bold text-cc-gold">
                        {{ \Illuminate\Support\Str::of($event->projectManager->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                    </span>
                    <span class="text-[11.5px] font-semibold text-cc-ink-2">{{ $event->projectManager->name }}</span>
                </span>
            @endif
            <a href="{{ route('events.hub', [$event, 'tab' => 'ai']) }}" class="flex h-11 items-center gap-2 rounded-xl bg-cc-navy px-4 text-[13px] font-bold text-white transition hover:bg-cc-navy-2 cc-lift-2">
                <x-canvas.icon name="ai" :size="16" class="text-cc-gold" /> AI Director
            </a>
        </div>
    </header>

    <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-2 px-4 pt-3 text-[12px] text-cc-ink-3 xl:px-6">
        <a href="{{ route('home') }}" class="transition hover:text-cc-navy">Command Canvas</a>
        <span aria-hidden="true">›</span>
        <a href="{{ route('events.index') }}" class="transition hover:text-cc-navy">Events</a>
        <span aria-hidden="true">›</span>
        <a href="{{ route('events.hub', $event) }}" class="transition hover:text-cc-navy">{{ $event->name }}</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page" class="font-semibold text-cc-navy">{{ $moduleLabel }}</span>
    </nav>

    <div class="px-4 pb-10 pt-4 xl:px-6">

        {{-- ══ EVENT PULSE RIBBON ══ --}}
        <x-canvas.company-pulse-strip :items="E::kpis($event, $health)"
                                      :health="D::band((int) ($health['score'] ?? 0))" />

        <div class="mt-5 flex flex-col gap-5 2xl:flex-row 2xl:items-start">

            {{-- module dock — only the modules this event has switched on --}}
            <nav aria-label="Modules" class="sticky top-4 z-20 flex shrink-0 flex-row gap-1 overflow-x-auto rounded-[22px] border border-cc-line bg-white p-2 cc-lift-2 2xl:flex-col 2xl:overflow-visible">
                @foreach ($modules as $m)
                    @php $active = $m['key'] === $tab; @endphp
                    <a href="{{ $m['href'] }}" @if ($active) aria-current="page" @endif
                       class="group grid w-[62px] shrink-0 place-items-center gap-1 rounded-2xl px-1 py-2.5 transition
                              {{ $active ? 'bg-cc-gold/15 text-cc-navy' : 'text-cc-ink-3 hover:bg-cc-mist hover:text-cc-navy' }}">
                        <span class="cc-hex-flat grid h-9 w-9 place-items-center transition
                                     {{ $active ? 'bg-cc-gold text-cc-navy' : 'bg-cc-mist text-cc-ink-2 group-hover:bg-white' }}">
                            <x-canvas.icon :name="$m['icon']" :size="17" />
                        </span>
                        <span class="w-full truncate text-center text-[9.5px] font-bold">{{ $m['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            {{-- the module's own workspace, unchanged --}}
            <main class="min-w-0 flex-1">
                @includeIf('events.hub.'.$tab, [
                    'event' => $event, 'health' => $health, 'ai' => $ai,
                    'alerts' => $alerts, 'workload' => $workload,
                ])
            </main>

            {{-- rails --}}
            <aside class="grid w-full shrink-0 grid-cols-1 gap-5 md:grid-cols-2 2xl:w-[336px] 2xl:grid-cols-1">
                <section class="rounded-[22px] border border-cc-line bg-white p-5 cc-lift-2">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-[12px] font-extrabold uppercase tracking-[0.13em] text-cc-navy">Event Pulse</h2>
                            <p class="mt-0.5 text-[11px] text-cc-ink-3">Health by component</p>
                        </div>
                        <x-canvas.health-badge :score="(int) ($health['score'] ?? 0)" size="sm" />
                    </div>
                    <ul class="mt-4 space-y-2.5">
                        @forelse (E::pulse($event, $health) as $row)
                            @php $c = ['ok' => 'bg-cc-ok', 'info' => 'bg-cc-info', 'warn' => 'bg-cc-warn', 'risk' => 'bg-cc-risk'][$row['tone']]; @endphp
                            <li class="flex items-center gap-2.5 text-[12px] text-cc-ink-2">
                                <span class="h-2 w-2 shrink-0 rounded-full {{ $c }}"></span>
                                <span class="min-w-0 flex-1 truncate">{{ $row['label'] }}</span>
                                <b class="shrink-0 font-extrabold tabular-nums text-cc-navy">{{ $row['value'] }}</b>
                            </li>
                        @empty
                            <li class="text-[12px] text-cc-ink-3">Nothing is being tracked on this event yet.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="rounded-[22px] border border-cc-line bg-white p-5 cc-lift-2">
                    <div class="flex items-start gap-2.5">
                        <span class="cc-hex-flat grid h-8 w-8 shrink-0 place-items-center bg-cc-navy text-cc-gold"><x-canvas.icon name="ai" :size="16" /></span>
                        <div class="min-w-0">
                            <h2 class="text-[12px] font-extrabold uppercase tracking-[0.13em] text-cc-navy">AI Event Director</h2>
                            <p class="mt-0.5 text-[11px] text-cc-ink-3">What needs a decision</p>
                        </div>
                    </div>
                    <ol class="mt-4 space-y-2">
                        @forelse ($insights as $i => $line)
                            @php
                                $tone = str_contains($line, 'Risk') || str_contains($line, 'overdue') || str_contains($line, 'issue') ? 'risk'
                                    : (str_contains($line, 'approval') || str_contains($line, 'unassigned') ? 'warn' : 'info');
                                $chip = ['risk' => 'text-cc-risk bg-cc-risk/10', 'warn' => 'text-cc-warn bg-cc-warn/10', 'info' => 'text-cc-info bg-cc-info/10'][$tone];
                            @endphp
                            <li class="flex items-start gap-3 rounded-xl border border-cc-line p-2.5">
                                <span class="cc-hex-flat grid h-7 w-7 shrink-0 place-items-center text-[10.5px] font-extrabold {{ $chip }}">{{ $i + 1 }}</span>
                                <span class="min-w-0 text-[12px] leading-snug text-cc-ink">{{ $line }}</span>
                            </li>
                        @empty
                            <li class="text-[12px] text-cc-ink-3">Nothing needs a decision on this event right now.</li>
                        @endforelse
                    </ol>
                </section>

                @if ($alerts->isNotEmpty())
                    <section class="rounded-[22px] border border-cc-line bg-white p-5 cc-lift-2">
                        <h2 class="text-[12px] font-extrabold uppercase tracking-[0.13em] text-cc-navy">Live Signals</h2>
                        <ul class="mt-3 divide-y divide-cc-line">
                            @foreach ($alerts as $alert)
                                @php $t = $alert['tone'] === 'risk' ? 'text-cc-risk bg-cc-risk/10' : ($alert['tone'] === 'warn' ? 'text-cc-warn bg-cc-warn/10' : 'text-cc-info bg-cc-info/10'); @endphp
                                <li class="flex items-start gap-3 py-2.5">
                                    <span class="cc-hex-flat mt-0.5 grid h-7 w-7 shrink-0 place-items-center {{ $t }}"><x-canvas.icon name="risk" :size="13" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[12.5px] font-bold text-cc-navy">{{ $alert['title'] }}</span>
                                        <span class="block truncate text-[11px] text-cc-ink-3">{{ $alert['sub'] }}</span>
                                    </span>
                                    <span class="shrink-0 text-[10.5px] text-cc-ink-3">{{ $alert['when']?->diffForHumans(short: true) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </aside>
        </div>
    </div>
</x-layouts.canvas>
