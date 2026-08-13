@php
    use App\Support\NavPanel;

    $area = NavPanel::currentArea();
    $sections = NavPanel::sections($area);

    $subtitle = match ($area) {
        'workspace' => 'Summits · Forums · Exhibitions · VIP',
        'events' => 'Summits · Forums · Exhibitions · VIP',
        'crm' => 'Commercial Command · Deals · Offers · Contracts',
        'finance' => 'Finance Command · Collection · Reconciliation',
        'tasks' => 'Everything open, across every mission',
        'operations' => 'Suppliers · Venues · Equipment',
        'intelligence' => 'Reports · Command Briefing',
        'team' => 'Everyone working the book',
        'settings' => 'Company profile · Types · Roles',
        default => null,
    };
@endphp

{{-- Soft Command ContextSidebar — core links for the area you're in.
     Smart views, filters and view-mode switches live in the content pages
     they filter now, not here — see docs on the Phase 1 nav diet. --}}
<aside id="eo-context-panel" {{ $attributes->class(['eo-context-sidebar']) }} aria-label="Context"
       x-bind:class="{ 'is-open': nav }">
    <div class="border-b border-white/8 px-5 py-5">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-eo-gold-soft/90">Elite Orbit</p>
                <p class="mt-1 text-[17px] font-semibold tracking-tight text-white">{{ NavPanel::areaLabel($area) }}</p>
            </div>
            {{-- Drawer-only: a way back out that is not the scrim. --}}
            <button type="button" class="eo-nav-close" @click="nav = false" aria-label="Close navigation">
                <x-icon name="chevron" class="h-4 w-4 rotate-90" />
            </button>
        </div>
        @if ($subtitle)
            <p class="mt-1 text-[12px] text-white/45">{{ $subtitle }}</p>
        @endif
    </div>

    {{-- Drawer-only. The rail carries the domains at every width, but it
         identifies them by hover tooltip — and there is no hover on a tablet,
         so on touch they are six unlabelled glyphs. These are the same six
         areas, named. Hidden above 1280px, where the rail's tooltips work. --}}
    <div class="eo-context-domains">
        <p class="mb-2 px-2 text-[10px] font-bold uppercase tracking-[0.16em] text-white/35">Areas</p>
        <div class="grid grid-cols-2 gap-1">
            @foreach (NavPanel::AREAS as $key => $domain)
                @continue (! \Illuminate\Support\Facades\Route::has($domain['route']))
                <a href="{{ route($domain['route']) }}"
                   @class(['eo-context-link', 'is-active' => $area === $key])>
                    <x-icon :name="$domain['icon']" class="h-4 w-4 shrink-0" />
                    <span class="truncate">{{ $domain['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Any destination closes the drawer: on a phone the panel covers the
         thing you just asked for. --}}
    <div class="flex-1 space-y-5 overflow-y-auto px-3 py-4" @click="nav = false">
        @foreach ($sections as $section)
            <div>
                <p class="mb-2 px-2 text-[10px] font-bold uppercase tracking-[0.16em] text-white/35">{{ $section['label'] }}</p>
                <div class="space-y-1">
                    @foreach ($section['items'] as $item)
                        <a href="{{ $item['href'] }}" @class(['eo-context-link', 'is-active' => $item['active']])>
                            <x-icon :name="$item['icon']" class="h-4 w-4 shrink-0 opacity-80" />
                            <span class="truncate">{{ $item['label'] }}</span>
                            @if (! empty($item['count']))
                                <span class="ml-auto rounded-full bg-white/10 px-1.5 py-0.5 text-[10px] font-bold text-white/70">{{ $item['count'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="border-t border-white/8 px-4 py-4">
        @if (\Illuminate\Support\Facades\Route::has('settings.index'))
            <a href="{{ route('settings.index') }}" @class(['eo-context-link', 'is-active' => $area === 'settings'])>
                <x-icon name="cog" class="h-4 w-4" />
                <span>Settings</span>
            </a>
        @endif
    </div>
</aside>
