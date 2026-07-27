<x-layouts.app title="Settings" subtitle="Everything the platform draws from — your company, your libraries, your lists.">
    @php
        // Grouped, because eleven equal cards make you read all eleven to find
        // one. Every label is the destination page's own title, so the link and
        // the page it opens agree.
        $groups = [
            [
                'title' => 'Workspace',
                'note' => 'Who you are, and what every new event inherits.',
                'items' => [
                    ['Company Profile', 'Brand, logo, address, default currency, timezone and management fee.', 'company.index', 'cog', null],
                    ['Types & Lists', 'Event types, session types, supplier and risk categories, deal sources — the lists every dropdown draws from.', 'taxonomies.index', 'grid', 'terms'],
                    ['Defaults & Templates', 'The budget categories, ticket types and fee a new event starts with.', 'defaults.index', 'clipboard', 'categories'],
                    ['Team & Roles', 'Members, their roles and profile photos.', 'team.index', 'users', 'members'],
                ],
            ],
            [
                'title' => 'Directories',
                'note' => 'The libraries events pull from instead of retyping.',
                'items' => [
                    ['Clients', 'Every client, their people, and the record of what you have done together.', 'clients.index', 'identification', 'clients'],
                    ['Suppliers', 'The supplier directory shared across all events.', 'suppliers.index', 'truck', 'suppliers'],
                    ['Venues & Locations', 'Hotels, halls and sites — reused by every event.', 'venues.index', 'building', 'venues'],
                ],
            ],
            [
                'title' => 'Catalogues',
                'note' => 'Priced things you offer or hire, ready to drop into an event.',
                'items' => [
                    ['Equipment Catalog', 'Reusable equipment and prices for venues and events.', 'requirements.index', 'archive', 'items'],
                    ['Sponsorship Packages', 'Default tiers, prices, slots and benefits.', 'sponsor-packages.index', 'star', 'tiers'],
                    ['Transport Catalogue', 'Vehicles, capacities, drivers and the services you offer.', 'transport-settings.index', 'truck', 'vehicles & drivers'],
                ],
            ],
        ];
    @endphp

    <div class="space-y-6">
        @foreach ($groups as $group)
            <section>
                <div class="mb-3">
                    <h2 class="pf text-h1 font-bold text-navy-900">{{ $group['title'] }}</h2>
                    <p class="mt-0.5 text-xs text-muted">{{ $group['note'] }}</p>
                </div>

                <div class="card divide-y divide-line overflow-hidden">
                    @foreach ($group['items'] as [$title, $desc, $route, $icon, $unit])
                        @continue (! \Illuminate\Support\Facades\Route::has($route))
                        @php $n = $counts[$route] ?? null; @endphp
                        <a href="{{ route($route) }}" class="group flex items-center gap-4 px-4 py-3.5 transition hover:bg-page/50">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-navy-50 text-navy-500 transition group-hover:bg-gold-50 group-hover:text-gold-700">
                                <x-icon :name="$icon" class="h-4 w-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[13.5px] font-bold text-navy-900">{{ $title }}</span>
                                <span class="block truncate text-[11.5px] text-muted">{{ $desc }}</span>
                            </span>
                            {{-- What is actually in there. A library index that
                                 makes you open each one to find out is a menu. --}}
                            @isset ($n)
                                <span class="hidden shrink-0 text-right sm:block">
                                    <span class="block text-[15px] font-bold tabular-nums leading-none {{ $n ? 'text-navy-900' : 'text-navy-300' }}">{{ number_format($n) }}</span>
                                    <span class="mt-0.5 block text-[10px] text-muted">{{ $unit }}</span>
                                </span>
                            @endisset
                            <span class="shrink-0 text-navy-200 transition group-hover:translate-x-0.5 group-hover:text-gold-600">→</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.app>
