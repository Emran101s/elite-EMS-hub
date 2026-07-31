@php
    use App\Support\NavPanel;

    $area = NavPanel::currentArea();
    $sections = NavPanel::sections($area);
    $tree = NavPanel::tree($area);
    $user = auth()->user();
@endphp

{{--
    The panel: what is inside the area you are in.

    Light, so the dark stays a 56px accent. Counts against the rows that have
    them, because a nav that does not say how much is in there makes you open
    each one to find out.
--}}
{{-- The context panel costs 286px. At lg — which is a landscape iPad — the
     rail, the panel and the gutters took 414 of 1024 and left 610 for the work
     itself. It now waits for xl, so a tablet gets the rail and a real column;
     the rail still reaches every area without it. --}}
<aside class="hidden w-[286px] shrink-0 flex-col overflow-hidden rounded-[22px] border border-line bg-white shadow-[0_20px_50px_-32px_rgba(11,31,58,0.45)] xl:flex">

    {{-- ── who you are ── --}}
    <details class="group/user relative shrink-0" data-menu>
        <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3.5 text-left transition hover:bg-page/60 [&::-webkit-details-marker]:hidden">
            <x-user-avatar :user="$user" size="h-9 w-9" />
            <span class="min-w-0 flex-1">
                <span class="flex items-center gap-1">
                    <span class="truncate text-[13px] font-bold text-navy-900">{{ $user?->name }}</span>
                    <x-icon name="chevron" class="h-3 w-3 shrink-0 text-navy-300 transition group-open/user:rotate-180" />
                </span>
                <span class="block truncate text-[10.5px] text-muted">{{ $user?->email }}</span>
            </span>
        </summary>

        <div class="absolute inset-x-2 z-40 mt-1 overflow-hidden rounded-xl border border-line bg-white py-1 shadow-lg">
            <a href="{{ route('settings.index') }}" class="block px-4 py-2 text-[12.5px] font-medium text-navy-700 transition hover:bg-navy-50">Settings</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="block w-full px-4 py-2 text-left text-[12.5px] font-medium text-navy-700 transition hover:bg-navy-50">Sign out</button>
            </form>
        </div>
    </details>

    <div class="scrollbar-none min-h-0 flex-1 overflow-y-auto px-2.5 pb-3">

        @foreach ($sections as $section)
            @if (! $loop->first)<div class="my-2.5 h-px bg-line"></div>@endif

            <p class="px-2 pb-1.5 pt-1 text-[10px] font-bold uppercase tracking-[0.16em] text-navy-300">{{ $section['label'] }}</p>

            @foreach ($section['items'] as $item)
                <a href="{{ $item['href'] }}"
                   @if ($item['active']) aria-current="page" @endif
                   @class([
                       'flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 transition',
                       // The selected row lifts off the panel rather than
                       // filling with colour, which keeps the panel calm.
                       'bg-page shadow-[0_1px_2px_rgba(11,31,58,0.06)] ring-1 ring-line' => $item['active'],
                       'hover:bg-page/60' => ! $item['active'],
                   ])>
                    <x-icon :name="$item['icon']" class="h-4 w-4 shrink-0 {{ $item['active'] ? 'text-gold-600' : 'text-navy-300' }}" />
                    <span class="min-w-0 flex-1 truncate text-[12.5px] {{ $item['active'] ? 'font-bold text-navy-900' : 'font-medium text-navy-700' }}">{{ $item['label'] }}</span>
                    @isset($item['count'])
                        <span class="shrink-0 text-[11px] font-semibold tabular-nums text-navy-300">{{ $item['count'] }}</span>
                    @endisset
                </a>
            @endforeach
        @endforeach

        {{-- ── the portfolio, where it is the thing you came for ── --}}
        @if ($tree->isNotEmpty())
            <div class="my-2.5 h-px bg-line"></div>

            <div class="flex items-center justify-between px-2 pb-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-navy-300">Portfolio</p>
                @if (\Illuminate\Support\Facades\Route::has('events.create'))
                    <a href="{{ route('events.create') }}" title="New event"
                       class="grid h-5 w-5 place-items-center rounded-full text-navy-300 transition hover:bg-gold-50 hover:text-gold-700">＋</a>
                @endif
            </div>

            <div class="rounded-2xl border border-line bg-page/40 p-1.5">
                @foreach ($tree as $group)
                    <details class="group/branch" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-2 py-1.5 transition hover:bg-white [&::-webkit-details-marker]:hidden">
                            <span class="text-[9px] text-navy-300 transition group-open/branch:rotate-90">▶</span>
                            <span class="h-2 w-2 shrink-0 rounded-[3px]" style="background: {{ $group['color'] }}"></span>
                            <span class="min-w-0 flex-1 truncate text-[12px] font-bold text-navy-800">{{ $group['label'] }}</span>
                            <span class="shrink-0 text-[10.5px] font-semibold tabular-nums text-navy-300">{{ $group['events']->count() }}</span>
                        </summary>

                        {{-- One hairline the children hang off, which is what
                             stops a tree reading as a flat list. --}}
                        <div class="ms-[13px] border-s border-line ps-2">
                            @foreach ($group['events'] as $event)
                                <a href="{{ route('events.hub', $event) }}"
                                   class="flex items-center gap-2 rounded-lg px-2 py-1.5 transition hover:bg-white">
                                    <x-icon name="folder" class="h-3.5 w-3.5 shrink-0 text-navy-300" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[12px] font-medium text-navy-700">{{ $event->name }}</span>
                                        <span class="block truncate text-[10px] text-navy-300">{{ $event->client?->name ?? 'No client' }}</span>
                                    </span>
                                    @if ($event->open_tasks)
                                        <span class="shrink-0 text-[10.5px] font-semibold tabular-nums text-navy-300">{{ $event->open_tasks }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>
        @endif
    </div>
</aside>
