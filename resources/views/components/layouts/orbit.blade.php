@props([
    'event' => null,
    'module' => 'overview',
    'kpis' => [],        // the dark ribbon — see App\Support\OrbitShell::kpis()
    'title' => null,
    'heading' => null,   // portfolio pages: the page's own name
    'subtitle' => null,
    'crumbs' => null,    // [['label'=>..,'href'=>..], ..] — overrides the default trail
])
{{--
    The ORBIT shell. Structure per docs/orbit-ia-brief.md:

      command ribbon   identity + search + Ask AI + create + alerts + profile
      KPI ribbon       dark, real-time snapshot (chrome — dark in both themes)
      orbit            12 modules around the COMMAND CENTER core, on the left
      workspace        adaptive — each module renders its own operational view
      rails            Event Pulse + AI Event Director, both contextual
      dock             nine slots, floating, always visible

    Only the workspace and the rails change between modules; the chrome is
    constant so the product feels like one instrument, not eighteen screens.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ \App\Support\ThemePolicy::for($module, $event) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? ($event?->name ?? config('app.name')) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="o-shell">
<x-orbit.sprite />

{{-- ══ 1. COMMAND RIBBON ══ --}}
<header class="o-topbar o-shell__ribbon">
    <a href="{{ route('home') }}" class="o-brandmark" aria-label="Elite Event Hub">
        <x-orbit.icon name="orbit" :size="19" />
    </a>

    @if ($event)
        <a href="{{ route('events.index') }}" class="o-btn o-btn--quiet o-btn--sm">
            <x-orbit.icon name="chevL" :size="14" /> Events
        </a>
        <x-orbit.event-chip :lines="[\Illuminate\Support\Str::of($event->name)->explode(' ')->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(4)->implode(''), $event->starts_at?->format('y')]" />
        <div class="min-w-0">
            <h1 class="o-shell__evt">{{ $event->name }}</h1>
            <p class="o-shell__evtmeta">
                {{ $event->starts_at?->format('M j') }} – {{ ($event->ends_at ?? $event->starts_at)?->format('M j, Y') }}
                @if ($event->venue?->name) · {{ $event->venue->name }} @elseif ($event->city) · {{ $event->city }} @endif
                @if ($event->client?->name) · {{ $event->client->name }} @endif
            </p>
        </div>
    @endif

    <div class="o-shell__ribbonend">
        {{-- Who owns this event. Ops staff ask "who do I chase" constantly, so the
             manager stays on the ribbon rather than buried in a settings tab. --}}
        @if ($event?->projectManager)
            <span class="o-shell__owner" title="Project manager: {{ $event->projectManager->name }}">
                <x-orbit.avatar :name="$event->projectManager->name" size="sm" />
                <span>{{ $event->projectManager->name }}</span>
            </span>
        @endif
        {{-- The real ⌘K palette, not a decorative search box: it owns the
             keybinding and the results view. Its own trigger is hidden here so
             the ribbon shows the ORBIT search affordance instead. --}}
        <div class="o-shell__palette">
            <x-orbit.search @click="$dispatch('open-palette')" />
            <livewire:command-palette />
        </div>
        <x-orbit.btn variant="ghost" size="sm"><x-orbit.icon name="spark" :size="15" /> Ask AI</x-orbit.btn>
        <x-orbit.btn variant="gold" icon aria-label="Create"><x-orbit.icon name="plus" :size="17" /></x-orbit.btn>
        <button type="button" class="o-btn o-btn--quiet o-btn--icon" aria-label="Notifications"><x-orbit.icon name="bell" :size="17" /></button>
        <button type="button" class="o-btn o-btn--quiet o-btn--icon" aria-label="Messages"><x-orbit.icon name="chat" :size="17" /></button>
        <x-orbit.avatar :name="auth()->user()?->name" />
    </div>
</header>

{{-- ══ 1b. BREADCRUMB — where you are, as a landmark screen readers can jump to ══ --}}
<nav aria-label="Breadcrumb" class="o-shell__crumbs">
    @if ($event)
        <a href="{{ route('home') }}">Command Canvas</a>
        <span aria-hidden="true">›</span>
        <a href="{{ route('events.index') }}">Events</a>
        <span aria-hidden="true">›</span>
        <a href="{{ route('events.hub', $event) }}">{{ $event->name }}</a>
        <span aria-hidden="true">›</span>
        <span aria-current="page">{{ \App\Models\Event::moduleLabel($module) }}</span>
    @elseif ($crumbs)
        @foreach ($crumbs as $i => $crumb)
            @if ($i > 0)<span aria-hidden="true">›</span>@endif
            @if (! empty($crumb['href']) && ! $loop->last)
                <a href="{{ $crumb['href'] }}">{{ $crumb['label'] }}</a>
            @else
                <span @if ($loop->last) aria-current="page" @endif>{{ $crumb['label'] }}</span>
            @endif
        @endforeach
    @else
        <span aria-current="page">Command Canvas</span>
    @endif
</nav>

{{-- ══ 2. KPI RIBBON — the real-time snapshot ══ --}}
@if ($kpis)
    <div class="o-shell__kpis">
        <x-orbit.kpi-strip :items="$kpis">
            @if ($event)
                <x-slot:cta>
                    <x-orbit.btn variant="solid" size="sm" :href="route('events.hub', [$event, 'tab' => 'reports'])">
                        <x-orbit.icon name="pulse" :size="15" /> Event Pulse
                    </x-orbit.btn>
                </x-slot:cta>
            @endif
        </x-orbit.kpi-strip>
    </div>
@endif

{{-- ══ 3. ORBIT · WORKSPACE · RAILS ══ --}}
<div class="o-shell__body">
    @if ($event)
        @php $rings = \App\Models\Event::orbitRings(); @endphp
        <x-orbit.nav-arc
            :current="$module"
            :inner="collect($rings['inner'])->map(fn ($m) => $m + ['href' => route('events.hub', [$event, 'tab' => $m['key']])])->all()"
            :outer="collect($rings['outer'])->map(fn ($m) => $m + ['href' => route('events.hub', [$event, 'tab' => $m['key']])])->all()"
            :inner-span="120" :outer-span="120" :inner-radius="180" :outer-radius="280">
            <x-slot:core>
                <span class="o-arc__coretitle">Command<br>Center</span>
                <span class="o-arc__coresub">{{ \App\Models\Event::moduleLabel($module) }}</span>
            </x-slot:core>
        </x-orbit.nav-arc>
    @endif

    <main class="o-shell__work">
        @if ($heading)
            <div class="o-shell__pagehead">
                <h2 class="o-display-sm">{{ $heading }}</h2>
                @if ($subtitle)<p class="o-mute" style="margin:4px 0 0">{{ $subtitle }}</p>@endif
            </div>
        @endif
        {{ $slot }}
    </main>

    @isset($rails)
        <aside class="o-shell__rails">{{ $rails }}</aside>
    @endisset
</div>

{{-- ══ 4. COMMAND DOCK ══ --}}
<div class="o-shell__dock">
    @if ($event)
        <x-orbit.dock :current="$module" :items="collect(\App\Models\Event::orbitDock())->map(fn ($m) => $m + ['href' => route('events.hub', [$event, 'tab' => $m['key']])])->all()" />
    @else
        <x-orbit.dock :current="$module" :items="\App\Support\OrbitShell::portfolioDock()" />
    @endif
</div>

@livewireScripts
</body>
</html>
