@php $theme = $event->theme(); @endphp

<x-layouts.app :title="$event->name . ' — Event Hub'"
               :hide-title-row="true"
               :crumbs="[
                   ['label' => 'Command Center', 'href' => route('home')],
                   ['label' => 'Events', 'href' => route('events.index')],
                   ['label' => $event->name, 'href' => route('events.hub', $event)],
                   ['label' => \App\Models\Event::moduleLabel($tab)],
               ]">

    {{-- ══ The header ══
         Identity, scale, the one thing that needs a person, module-by-module
         progress, where to go, and what is true right now. See
         resources/views/components/event-header.blade.php for why it is these
         four blocks and not the one white bar it replaces. ══ --}}
    <x-event-header :event="$event" :header="$header" />

    {{-- ══ Module nav ══
         Twenty-three equal-weight pills wrapping to two rows was furniture —
         a seventh of a laptop screen before the work started. The strip is now
         a single row: the daily doors stay visible, everything else lives under
         More (grouped by the same families MODULE_COLORS already implies). The
         tab you are on is always promoted into the strip, so you never lose
         your place. Words still, not icon tiles — that lesson holds. Colour
         dots still name the family. ══ --}}
    @php
        $modules = \App\Models\Event::HUB_TABS;
        $attention = $header['attention'] ?? [];

        // Daily doors — the ones a coordinator opens every day the show is
        // running, per docs/19's workflow spine. Everything else, however
        // important, is a few-times-a-project door rather than a daily one,
        // and lives under More.
        $primaryKeys = ['overview', 'tasks', 'agenda', 'venue', 'transportation', 'budget'];

        $enabled = collect($modules)->keys()->filter(fn ($key) => $event->moduleEnabled($key))->values();

        $primary = collect($primaryKeys)->filter(fn ($key) => $enabled->contains($key))->values();
        if ($enabled->contains($tab) && ! $primary->contains($tab)) {
            $primary = $primary->push($tab);
        }

        $overflowKeys = $enabled->reject(fn ($key) => $primary->contains($key))->values();

        // Same family language as MODULE_COLORS — Plan blue, Programme teal,
        // Logistics amber, Exhibition violet, Sell green, Grow grey/gold.
        $families = [
            'Plan' => ['planning', 'pricing', 'approvals', 'brief', 'contract', 'risks'],
            'Programme' => ['speakers'],
            'Logistics' => ['suppliers', 'accommodation', 'catering'],
            'Partners' => ['exhibition', 'sponsors'],
            'Sell' => ['attendees'],
            'Library' => ['files', 'reports'],
            'System' => ['ai', 'settings'],
        ];

        $overflowByFamily = collect($families)->map(
            fn (array $keys) => collect($keys)->filter(fn ($key) => $overflowKeys->contains($key))->values()
        )->filter->isNotEmpty();

        // Anything enabled that isn't in a family bucket still shows (forward-safe).
        $bucketed = $overflowByFamily->flatten();
        $ungrouped = $overflowKeys->reject(fn ($key) => $bucketed->contains($key))->values();
        if ($ungrouped->isNotEmpty()) {
            $overflowByFamily = $overflowByFamily->put('More', $ungrouped);
        }

        $overflowAttention = $overflowKeys->sum(fn ($key) => (int) ($attention[$key]['count'] ?? 0));
        $overflowAlarm = $overflowKeys->contains(fn ($key) => ($attention[$key]['tone'] ?? null) === 'alarm');

        $tabLink = function (string $key, bool $inMenu = false) use ($modules, $tab, $attention): array {
            [$label, $note] = $modules[$key];
            $active = $tab === $key;
            $hex = \App\Models\Event::moduleColor($key);
            $n = $attention[$key] ?? null;

            return compact('label', 'note', 'active', 'hex', 'n', 'inMenu');
        };

        // Event Journey — concept-board stages. Each stage lands on an existing
        // hub tab (no route changes). The active stage follows the current tab.
        $journey = [
            ['key' => 'overview', 'label' => 'Overview', 'tabs' => ['overview']],
            ['key' => 'brief', 'label' => 'Brief', 'tabs' => ['brief', 'contract']],
            ['key' => 'planning', 'label' => 'Planning', 'tabs' => ['planning', 'tasks']],
            ['key' => 'programme', 'label' => 'Programme', 'tabs' => ['agenda', 'speakers']],
            ['key' => 'operations', 'label' => 'Operations', 'tabs' => ['venue', 'transportation', 'accommodation', 'catering', 'suppliers', 'exhibition', 'attendees']],
            ['key' => 'commercial', 'label' => 'Commercial', 'tabs' => ['budget', 'pricing', 'sponsors']],
            ['key' => 'control', 'label' => 'Control', 'tabs' => ['risks', 'approvals', 'reports', 'ai', 'files']],
            ['key' => 'closeout', 'label' => 'Closeout', 'tabs' => ['settings']],
        ];

        $activeJourney = collect($journey)->first(fn ($step) => in_array($tab, $step['tabs'], true))
            ?? $journey[0];
        $activeJourneyIndex = collect($journey)->search(fn ($step) => $step['key'] === $activeJourney['key']);
    @endphp

    {{-- Event Journey strip — progression chrome before module doors --}}
    <div class="mt-4">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2 px-1">
            <p class="eo-label">Event Journey</p>
            <span class="eo-journey-chip">{{ $activeJourney['label'] }} stage</span>
        </div>
        <nav class="eo-journey-strip" aria-label="Event journey">
            @foreach ($journey as $i => $step)
                @php
                    $door = collect($step['tabs'])->first(fn ($key) => $enabled->contains($key)) ?? $step['tabs'][0];
                    $isActive = $step['key'] === $activeJourney['key'];
                    $isComplete = $i < $activeJourneyIndex;
                @endphp
                <a
                    href="{{ route('events.hub', [$event, 'tab' => $door]) }}"
                    @class([
                        'eo-journey-step',
                        'is-active' => $isActive,
                        'is-complete' => $isComplete,
                    ])
                    @if ($isActive) aria-current="step" @endif
                >
                    <span class="eo-journey-step-index">0{{ $i + 1 }}</span>
                    <span class="eo-journey-step-label">{{ $step['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    <div class="sticky top-0 z-20 -mx-1 mt-3 rounded-2xl border border-eo-line bg-eo-workspace/95 px-2 backdrop-blur sm:-mx-2 sm:px-3"
         x-data="{ more: false }"
         @keydown.escape.window="more = false">
        <nav class="flex items-center gap-1 py-1.5" aria-label="Event modules">
            <div class="scrollbar-none flex min-w-0 flex-1 items-center gap-x-0.5 overflow-x-auto">
                @foreach ($primary as $key)
                    @php extract($tabLink($key)); @endphp
                    <a href="{{ route('events.hub', [$event, 'tab' => $key]) }}"
                       @if ($active) aria-current="page" @endif
                       title="{{ $note }}"
                       @class([
                           'group relative flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-[6px] text-[12px] font-bold transition',
                           'bg-gradient-to-b from-eo-teal-lit to-eo-teal-deep text-white shadow-eo-teal' => $active,
                           'font-semibold text-eo-muted hover:bg-white hover:text-eo-text' => ! $active,
                       ])>
                        <span class="h-[5px] w-[5px] shrink-0 rounded-full transition"
                              style="background: {{ $active ? 'rgba(255,255,255,.85)' : $hex }}; opacity: {{ $active ? 1 : 0.55 }}"
                              aria-hidden="true"></span>
                        {{ $label }}
                        @if ($n)
                            <span title="{{ $n['why'] }}" @class([
                                'grid h-[16px] min-w-[16px] shrink-0 place-items-center rounded-full px-1 text-[9.5px] font-black tabular-nums',
                                'bg-white/25 text-white' => $active,
                                'bg-eo-risk-soft text-eo-risk' => ! $active && $n['tone'] === 'alarm',
                                'bg-eo-warn-soft text-amber-800' => ! $active && $n['tone'] !== 'alarm',
                            ])>{{ $n['count'] > 99 ? '99+' : $n['count'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>

            @if ($overflowKeys->isNotEmpty())
                <div class="relative shrink-0 ps-1">
                    <button type="button"
                            @click="more = ! more"
                            :aria-expanded="more.toString()"
                            aria-haspopup="true"
                            aria-controls="hub-modules-more"
                            class="flex h-8 items-center gap-1.5 rounded-full px-2.5 text-[12px] font-bold transition"
                            :class="more ? 'bg-eo-navy text-white' : 'text-eo-muted hover:bg-white hover:text-eo-text'">
                        More
                        @if ($overflowAttention > 0)
                            <span @class([
                                'grid h-[16px] min-w-[16px] place-items-center rounded-full px-1 text-[9.5px] font-black tabular-nums',
                                'bg-eo-risk-soft text-eo-risk' => $overflowAlarm,
                                'bg-eo-warn-soft text-amber-800' => ! $overflowAlarm,
                            ])
                                  :class="more ? '!bg-white/25 !text-white' : ''">{{ $overflowAttention > 99 ? '99+' : $overflowAttention }}</span>
                        @endif
                        <svg class="h-3 w-3 opacity-60 transition" :class="more && 'rotate-180'" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                            <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    <div id="hub-modules-more"
                         x-show="more"
                         x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 translate-y-1"
                         @click.outside="more = false"
                         class="absolute end-0 top-full z-30 mt-1.5 w-[min(20rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-line bg-white shadow-[0_24px_50px_-28px_rgba(11,31,58,0.55)]">
                        <div class="max-h-[min(28rem,70vh)] overflow-y-auto p-2">
                            @foreach ($overflowByFamily as $family => $keys)
                                <p class="px-2.5 pb-1 pt-2 text-eyebrow font-bold uppercase tracking-[0.16em] text-navy-300 first:pt-1">{{ $family }}</p>
                                <div class="grid gap-0.5">
                                    @foreach ($keys as $key)
                                        @php extract($tabLink($key, true)); @endphp
                                        <a href="{{ route('events.hub', [$event, 'tab' => $key]) }}"
                                           @click="more = false"
                                           title="{{ $note }}"
                                           @class([
                                               'flex items-center gap-2.5 rounded-xl px-2.5 py-2 text-[12.5px] font-semibold transition',
                                               'bg-navy-50 text-navy-900' => $active,
                                               'text-navy-700 hover:bg-page' => ! $active,
                                           ])>
                                            <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $hex }}" aria-hidden="true"></span>
                                            <span class="min-w-0 flex-1 truncate">{{ $label }}</span>
                                            <span class="truncate text-eyebrow font-medium text-navy-300">{{ $note }}</span>
                                            @if ($n)
                                                <span title="{{ $n['why'] }}" @class([
                                                    'grid h-[16px] min-w-[16px] shrink-0 place-items-center rounded-full px-1 text-[9.5px] font-black tabular-nums',
                                                    'bg-risk/12 text-red-700' => $n['tone'] === 'alarm',
                                                    'bg-gold-100 text-gold-800' => $n['tone'] !== 'alarm',
                                                ])>{{ $n['count'] > 99 ? '99+' : $n['count'] }}</span>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </nav>
    </div>

    <div class="mt-5">
        @includeIf('events.hub.' . $tab, ['event' => $event, 'health' => $health, 'ai' => $ai, 'alerts' => $alerts, 'workload' => $workload])
    </div>
</x-layouts.app>
