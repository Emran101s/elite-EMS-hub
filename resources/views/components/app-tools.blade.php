@php
    $alertCount = \App\Models\EventApproval::where('status', 'pending')->count()
        + \App\Models\EventRisk::whereIn('status', ['open', 'escalated'])->count();
@endphp

{{--
    The tools, rehomed above the work.

    These used to ride the right end of the pill row. The rail carries areas
    and the panel carries contents, so neither is the place for a search box —
    these belong with the thing you are looking at.
--}}
<div class="mb-4 flex items-center gap-2">

    {{-- On a narrow screen the panel is gone, so the areas come back as a
         scrollable row rather than disappearing with it. --}}
    <nav class="scrollbar-none -mx-1 flex min-w-0 flex-1 items-center gap-1 overflow-x-auto px-1 lg:hidden" aria-label="Areas">
        @foreach (\App\Support\NavPanel::AREAS as $key => $area)
            @continue (! \Illuminate\Support\Facades\Route::has($area['route']))
            @php $active = \App\Support\NavPanel::currentArea() === $key; @endphp
            <a href="{{ route($area['route']) }}"
               @class([
                   'inline-flex h-9 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full px-3 text-[12.5px] transition',
                   'bg-navy-900 font-semibold text-white' => $active,
                   'bg-white font-medium text-navy-600' => ! $active,
               ])>
                <x-icon :name="$area['icon']" class="h-3.5 w-3.5" />{{ $area['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="ml-auto flex shrink-0 items-center gap-2">
        <livewire:command-palette />

        <x-event-radar />

        @if (\Illuminate\Support\Facades\Route::has('ai.index'))
            <a href="{{ route('ai.index') }}"
               class="hidden h-10 items-center gap-1.5 rounded-full bg-gold-500 px-3.5 text-[12.5px] font-bold text-navy-950 shadow-[0_6px_16px_-8px_rgba(212,175,55,0.9)] transition hover:bg-gold-400 sm:inline-flex">
                ✦<span class="hidden xl:inline">Ask AI</span>
            </a>
        @endif

        <a href="{{ route('home') }}#live-alerts" title="Alerts"
           class="relative grid h-10 w-10 place-items-center rounded-full bg-white text-navy-600 shadow-[0_2px_10px_-4px_rgba(11,31,58,0.25)] transition hover:text-navy-900">
            <x-icon name="bell" class="h-[18px] w-[18px]" />
            @if ($alertCount > 0)
                <span class="absolute -right-0.5 -top-0.5 grid h-[17px] min-w-[17px] place-items-center rounded-full bg-risk px-1 text-[9px] font-bold text-white ring-2 ring-page">{{ $alertCount }}</span>
            @endif
        </a>

        {{-- The avatar lives in the panel now, so this is only the way back to
             an account on a screen too narrow to show the panel. --}}
        <details class="group relative lg:hidden" data-menu>
            <summary class="flex h-10 cursor-pointer list-none items-center rounded-full bg-white p-1 shadow-[0_2px_10px_-4px_rgba(11,31,58,0.25)] [&::-webkit-details-marker]:hidden">
                <x-user-avatar :user="auth()->user()" size="h-8 w-8" />
            </summary>
            <div class="absolute right-0 z-40 mt-2 w-52 overflow-hidden rounded-2xl border border-line bg-white shadow-lg">
                <p class="border-b border-line px-4 py-2.5">
                    <span class="block text-[13px] font-bold text-navy-900">{{ auth()->user()?->name }}</span>
                    <span class="block text-[11px] text-muted">{{ auth()->user()?->roleLabel() }}</span>
                </p>
                <a href="{{ route('settings.index') }}" class="block px-4 py-2.5 text-sm text-navy-700 hover:bg-navy-50">Settings</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2 border-t border-line px-4 py-2.5 text-left text-sm text-risk hover:bg-navy-50">
                        <x-icon name="logout" class="h-4 w-4" /> Log out
                    </button>
                </form>
            </div>
        </details>
    </div>
</div>
