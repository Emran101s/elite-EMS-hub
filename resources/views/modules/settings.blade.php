<x-layouts.app title="Settings" subtitle="Configuration only — your company, your lists, and the catalogues an event starts from.">
    @php
        // Grouped, because eleven equal cards make you read all eleven to find
        // one. Every label is the destination page's own title, so the link and
        // the page it opens agree.
        $groups = [
            [
                'title' => 'Workspace Configuration',
                'note' => 'Who you are, and what every new event inherits.',
                'items' => [
                    ['Company Profile', 'Brand, logo, address, default currency, timezone and management fee.', 'company.index', 'cog', null],
                    ['Types & Lists', 'Event types, session types, supplier and risk categories, deal sources — the lists every dropdown draws from.', 'taxonomies.index', 'grid', 'terms'],
                    ['Statuses & Colours', 'What the platform calls each step — event stages, task columns, pipeline lanes — and the colour it wears.', 'workflows.index', 'chart', 'states'],
                    ['Defaults & Templates', 'The budget categories, ticket types and fee a new event starts with.', 'defaults.index', 'clipboard', 'categories'],
                ],
            ],
            [
                'title' => 'Catalogues & Templates',
                'note' => 'Priced things you offer or hire, ready to drop into an event.',
                'items' => [
                    ['Sponsorship Packages', 'Default tiers, prices, slots and benefits.', 'sponsor-packages.index', 'star', 'tiers'],
                    ['Transport Catalogue', 'Vehicles, capacities, drivers and the services you offer.', 'transport-settings.index', 'truck', 'vehicles & drivers'],
                    ['Price List', 'What the company sells, what one of each costs, and how it is counted.', 'catalogue.index', 'currency', 'items'],
                    ['Registration Templates', 'Question sets an event starts its registration form from.', 'registration-templates.index', 'document', 'templates'],
                ],
            ],
        ];
    @endphp

    <div class="space-y-6">
        @foreach ($groups as $group)
            <section>
                <div class="mb-3">
                    <h2 class="eo-title">{{ $group['title'] }}</h2>
                    <p class="mt-0.5 text-xs text-eo-muted">{{ $group['note'] }}</p>
                </div>

                <div class="eo-soft-card divide-y divide-eo-line overflow-hidden">
                    @foreach ($group['items'] as [$title, $desc, $route, $icon, $unit])
                        @continue (! \Illuminate\Support\Facades\Route::has($route))
                        @php $n = $counts[$route] ?? null; @endphp
                        <a href="{{ route($route) }}" class="group flex items-center gap-4 px-4 py-3.5 transition hover:bg-eo-workspace">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-eo-bg text-eo-muted transition group-hover:bg-eo-teal-soft group-hover:text-eo-teal-ink">
                                <x-icon :name="$icon" class="h-4 w-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[13.5px] font-bold text-eo-text">{{ $title }}</span>
                                <span class="block truncate text-[11.5px] text-eo-muted">{{ $desc }}</span>
                            </span>
                            {{-- What is actually in there. A library index that
                                 makes you open each one to find out is a menu. --}}
                            @isset ($n)
                                <span class="hidden shrink-0 text-right sm:block">
                                    <span class="block text-[15px] font-bold tabular-nums leading-none {{ $n ? 'text-eo-text' : 'text-eo-muted' }}">{{ number_format($n) }}</span>
                                    <span class="mt-0.5 block text-[10px] text-eo-muted">{{ $unit }}</span>
                                </span>
                            @endisset
                            <span class="shrink-0 text-eo-muted transition group-hover:translate-x-0.5 group-hover:text-eo-teal-ink">→</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.app>
