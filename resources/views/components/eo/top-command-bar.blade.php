@props([
    'crumbs' => null,
    'title' => null,
])

@php
    use App\Support\NavPanel;

    $primary = NavPanel::primaryAction();
    $alertCount = \App\Models\EventApproval::where('status', 'pending')->count()
        + \App\Models\EventRisk::whereIn('status', ['open', 'escalated'])->count();
@endphp

{{-- Soft Command TopCommandBar — crumbs, command palette, teal primary action. --}}
<header {{ $attributes->class(['eo-top-command']) }}>
    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-3">
        {{-- Below 1280px the context panel is a drawer, and this is its only
             door. Hidden above that width, where the panel is always on screen. --}}
        <button type="button" class="eo-nav-trigger" @click="nav = true"
                :aria-expanded="nav ? 'true' : 'false'" aria-controls="eo-context-panel"
                aria-label="Open navigation">
            <x-icon name="grid" class="h-[18px] w-[18px]" />
        </button>

        <span class="eo-radar-mark shrink-0" aria-hidden="true"></span>

        <div class="min-w-0">
            @if ($crumbs)
                <nav class="flex flex-wrap items-center gap-1.5 text-[12px] text-eo-muted" aria-label="Breadcrumb">
                    @foreach ($crumbs as $i => $crumb)
                        @if ($i > 0)
                            <span class="opacity-40">/</span>
                        @endif
                        @if (is_array($crumb) && ! empty($crumb['href']) && ! $loop->last)
                            <a href="{{ $crumb['href'] }}" class="font-medium text-eo-muted hover:text-eo-teal-ink">{{ $crumb['label'] }}</a>
                        @else
                            <span class="font-semibold text-eo-text">{{ is_array($crumb) ? ($crumb['label'] ?? '') : $crumb }}</span>
                        @endif
                    @endforeach
                </nav>
            @elseif ($title)
                <p class="text-[13px] font-semibold text-eo-text">{{ $title }}</p>
            @else
                <p class="eo-label">Mission control</p>
            @endif
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <livewire:command-palette />

        @if ($primary)
            <x-eo.button :href="$primary['href']" size="sm">
                {{ $primary['label'] }}
            </x-eo.button>
        @endif

        <a href="{{ route('home') }}#live-alerts" title="Alerts"
           class="relative grid h-10 w-10 place-items-center rounded-2xl border border-eo-line bg-white text-eo-muted transition hover:text-eo-text">
            <x-icon name="bell" class="h-[18px] w-[18px]" />
            @if ($alertCount > 0)
                <span class="absolute -right-0.5 -top-0.5 grid h-[17px] min-w-[17px] place-items-center rounded-full bg-eo-risk px-1 text-[9px] font-bold text-white">{{ $alertCount }}</span>
            @endif
        </a>
    </div>
</header>
