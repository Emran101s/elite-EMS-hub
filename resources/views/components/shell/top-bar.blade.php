@props([
    'crumbs' => null,
    'title' => null,
])

@php
    $primary = \App\Support\NavPanel::primaryAction();
    $alertCount = \App\Models\EventApproval::where('status', 'pending')->count()
        + \App\Models\EventRisk::whereIn('status', ['open', 'escalated'])->count();
@endphp

{{-- The floating header — rounded, shadowed, inset from the canvas edge. --}}
<header class="flex flex-wrap items-center justify-between gap-3 rounded-[22px] border border-line bg-white px-4 py-3 shadow-[0_20px_50px_-32px_rgba(11,31,58,0.45)]">
    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-3">
        {{-- Below xl (1280px) the nav is a drawer; this is its only door. --}}
        <button type="button" class="grid h-9 w-9 shrink-0 place-items-center rounded-xl text-navy-400 hover:bg-page hover:text-navy-900 xl:hidden"
                @click="nav = true" :aria-expanded="nav ? 'true' : 'false'" aria-label="Open navigation">
            <x-icon name="grid" class="h-[18px] w-[18px]" />
        </button>

        <div class="min-w-0">
            @if ($crumbs)
                <nav class="flex flex-wrap items-center gap-1.5 text-[12px] text-muted" aria-label="Breadcrumb">
                    @foreach ($crumbs as $i => $crumb)
                        @if ($i > 0)
                            <span class="opacity-40">/</span>
                        @endif
                        @if (is_array($crumb) && ! empty($crumb['href']) && ! $loop->last)
                            <a href="{{ $crumb['href'] }}" class="font-medium text-muted hover:text-navy-700">{{ $crumb['label'] }}</a>
                        @else
                            <span class="font-semibold text-navy-900">{{ is_array($crumb) ? ($crumb['label'] ?? '') : $crumb }}</span>
                        @endif
                    @endforeach
                </nav>
            @elseif ($title)
                <p class="text-[13px] font-semibold text-navy-900">{{ $title }}</p>
            @else
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-navy-300">Mission control</p>
            @endif
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <livewire:command-palette />

        @if ($primary)
            <a href="{{ $primary['href'] }}"
               class="flex h-10 items-center rounded-full bg-gold-500 px-4 text-[12.5px] font-bold text-navy-900 shadow-[0_10px_24px_-10px_rgba(212,175,55,0.6)] transition hover:bg-gold-400">
                {{ $primary['label'] }}
            </a>
        @endif

        <a href="{{ route('home') }}#live-alerts" title="Alerts"
           class="relative grid h-10 w-10 place-items-center rounded-2xl border border-line bg-white text-muted transition hover:text-navy-900">
            <x-icon name="bell" class="h-[18px] w-[18px]" />
            @if ($alertCount > 0)
                <span class="absolute -right-0.5 -top-0.5 grid h-[17px] min-w-[17px] place-items-center rounded-full bg-danger px-1 text-[9px] font-bold text-white">{{ $alertCount }}</span>
            @endif
        </a>
    </div>
</header>
