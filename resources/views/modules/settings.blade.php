<x-layouts.app title="Settings" subtitle="Workspace master data & platform configuration — the libraries every event draws from.">
    @php
        $sections = [
            ['glyph' => '🎛', 'title' => 'Equipment Catalog', 'desc' => 'Reusable equipment & prices for venues and events.', 'route' => 'requirements.index', 'live' => true],
            ['glyph' => '📍', 'title' => 'Venues & Locations', 'desc' => 'Your library of hotels, halls and sites — reused by every event.', 'route' => 'venues.index', 'live' => true],
            ['glyph' => '🚚', 'title' => 'Suppliers', 'desc' => 'Supplier directory shared across all events.', 'route' => 'suppliers.index', 'live' => true],
            ['glyph' => '👥', 'title' => 'Team & Roles', 'desc' => 'Members, roles and profile photos.', 'route' => 'team.index', 'live' => true],
            ['glyph' => '🤝', 'title' => 'Clients', 'desc' => 'Your client directory — events attach to a client.', 'route' => 'clients.index', 'live' => true],
            ['glyph' => '🏆', 'title' => 'Sponsorship Packages', 'desc' => 'Default sponsorship tiers, prices, slots & benefits.', 'route' => 'sponsor-packages.index', 'live' => true],
            ['glyph' => '🏢', 'title' => 'Company Profile', 'desc' => 'Brand, logo, default currency & timezone.', 'route' => 'company.index', 'live' => true],
            ['glyph' => '⚙️', 'title' => 'Defaults & Templates', 'desc' => 'Default budget categories & management fee for new events.', 'route' => 'defaults.index', 'live' => true],
            ['glyph' => '🚐', 'title' => 'Transport Types', 'desc' => 'Vehicles and their capacity, plus the services you offer.', 'route' => 'transport-settings.index', 'live' => true],
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($sections as $s)
            @php $href = $s['route'] ? route($s['route']) : null; @endphp
            <a @if ($href) href="{{ $href }}" @endif
                class="group flex items-start gap-4 rounded-2xl border border-line bg-white p-5 shadow-sm transition duration-200 {{ $href ? 'cursor-pointer hover:-translate-y-1 hover:border-gold-200 hover:shadow-[0_22px_46px_-20px_rgba(11,31,58,0.5)]' : 'cursor-default opacity-70' }}">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-navy-50 text-xl transition group-hover:bg-gold-50">{{ $s['glyph'] }}</span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h2 class="pf text-base font-bold text-navy-900">{{ $s['title'] }}</h2>
                        @if ($s['live'])
                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[0.55rem] font-bold uppercase tracking-wide text-emerald-700 ring-1 ring-emerald-200">Live</span>
                        @else
                            <span class="rounded-full bg-navy-50 px-2 py-0.5 text-[0.55rem] font-bold uppercase tracking-wide text-navy-400">Soon</span>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-muted">{{ $s['desc'] }}</p>
                    @if ($href)
                        <span class="mt-2 inline-block text-[0.68rem] font-semibold text-gold-700 transition group-hover:text-gold-600">Open →</span>
                    @endif
                </div>
            </a>
        @endforeach
    </div>
</x-layouts.app>
