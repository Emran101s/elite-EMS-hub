<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Company Command navigation — rail areas + panel map.
 *
 * The rail pins which Company Command area you are in. The panel draws the
 * full operating map (or Settings when you are configuring). Rows whose
 * routes are not registered are dropped — no dead links.
 */
class NavPanel
{
    /**
     * Rail areas (Company Command modes). Settings stays in the dock.
     *
     * @var array<string,array{label:string,icon:string,route:string,match:array<int,string>}>
     */
    public const AREAS = [
        'workspace' => [
            'label' => 'Command Center', 'icon' => 'home', 'route' => 'home',
            'match' => ['home', 'operations-room', 'concept.*'],
        ],
        'sales' => [
            'label' => 'Sales & CRM', 'icon' => 'identification', 'route' => 'crm.index',
            'match' => ['crm.*', 'clients.*'],
        ],
        'events' => [
            'label' => 'Events', 'icon' => 'calendar', 'route' => 'events.index',
            'match' => ['events.*'],
        ],
        'proposals' => [
            'label' => 'Proposals', 'icon' => 'document', 'route' => 'proposals.index',
            'match' => ['proposals.*'],
        ],
        'contracts' => [
            'label' => 'Contracts', 'icon' => 'document', 'route' => 'contracts.index',
            'match' => ['contracts.*'],
        ],
        'planning' => [
            'label' => 'Planning', 'icon' => 'clipboard', 'route' => 'planning.index',
            'match' => ['planning.*', 'tasks.*'],
        ],
        'operations' => [
            'label' => 'Operations', 'icon' => 'truck', 'route' => 'venues.index',
            'match' => ['venues.*'],
        ],
        'finance' => [
            'label' => 'Finance', 'icon' => 'currency', 'route' => 'finance.index',
            'match' => ['finance.*', 'invoices.*', 'payments.*'],
        ],
        'partners' => [
            'label' => 'Partners', 'icon' => 'archive', 'route' => 'suppliers.index',
            'match' => ['suppliers.*', 'requirements.*', 'projects.*', 'sponsors.*'],
        ],
        'intelligence' => [
            'label' => 'Intelligence', 'icon' => 'chart', 'route' => 'reports.index',
            'match' => ['reports.*', 'ai.*'],
        ],
        'team' => [
            'label' => 'Team', 'icon' => 'users', 'route' => 'team.index',
            'match' => ['team.*'],
        ],
    ];

    /**
     * Company Command panel map — section label => rows.
     *
     * Row shape: [label, route, icon, count?, query?]
     * query is an optional array of query-string params for filtered deep links.
     *
     * @var array<int,array{0:string,1:array<int,array{0:string,1:string,2:string,3?:int|null,4?:array<string,string>}>}>
     */
    public const PANEL = [
        ['Command Center', [
            ['Executive Overview', 'home', 'home'],
            ['Live Alerts', 'home', 'bell', null, ['_hash' => 'live-alerts']],
            ['Upcoming Events', 'events.index', 'calendar'],
            ['Financial Signals', 'finance.index', 'currency'],
            ['My Priorities', 'tasks.index', 'clipboard'],
        ]],
        ['Sales & CRM', [
            ['Deal Pipeline', 'crm.index', 'columns'],
            ['Clients', 'clients.index', 'identification'],
            ['New Deal', 'crm.index', 'sparkles'],
        ]],
        ['Event Portfolio', [
            ['All Events', 'events.index', 'calendar'],
            ['New Event', 'events.create', 'sparkles'],
            ['Live Events', 'events.index', 'calendar', null, ['stage' => 'live']],
            ['Completed Events', 'events.index', 'calendar', null, ['stage' => 'completed']],
            ['Projects', 'projects.index', 'folder'],
        ]],
        ['Proposals', [
            ['Proposals', 'proposals.index', 'document'],
            ['Draft Proposals', 'proposals.index', 'document', null, ['state' => 'draft']],
            ['Sent Proposals', 'proposals.index', 'document', null, ['state' => 'sent']],
            ['Accepted Proposals', 'proposals.index', 'document', null, ['state' => 'accepted']],
            ['Declined Proposals', 'proposals.index', 'document', null, ['state' => 'declined']],
        ]],
        ['Contracts', [
            ['Contracts', 'contracts.index', 'document'],
            ['Client Contracts', 'contracts.index', 'document', null, ['type' => 'client']],
            ['Vendor Contracts', 'contracts.index', 'document', null, ['type' => 'vendor']],
            ['Speaker Contracts', 'contracts.index', 'document', null, ['type' => 'speaker']],
            ['Sponsorship Contracts', 'contracts.index', 'document', null, ['type' => 'sponsorship']],
            ['Pending Signatures', 'contracts.index', 'bell', null, ['status' => 'sent']],
            ['Payment Schedules', 'payments.index', 'card'],
        ]],
        ['Planning & Tasks', [
            ['Planning Board', 'planning.index', 'grid'],
            ['My Tasks', 'tasks.index', 'clipboard'],
            ['Team Tasks', 'tasks.index', 'users'],
        ]],
        ['Operations Control', [
            ['Venue & Layout Overview', 'venues.index', 'building'],
        ]],
        ['Finance', [
            ['Financial Dashboard', 'finance.index', 'chart'],
            ['Invoices', 'invoices.index', 'currency'],
            ['Payments', 'payments.index', 'card'],
        ]],
        ['Suppliers & Venues', [
            ['Suppliers', 'suppliers.index', 'truck'],
            ['Venues', 'venues.index', 'building'],
            ['Equipment & Requirements', 'requirements.index', 'archive'],
            ['Sponsorships', 'sponsors.index', 'star'],
        ]],
        ['Reports & Intelligence', [
            ['Reports Overview', 'reports.index', 'chart'],
            ['AI Assistant', 'ai.index', 'sparkles'],
        ]],
        ['Team & Access', [
            ['Team Members', 'team.index', 'users'],
        ]],
    ];

    /**
     * Settings panel — configuration only (no daily-work directories).
     *
     * @var array<int,array{0:string,1:array<int,array{0:string,1:string,2:string,3?:int|null,4?:array<string,string>}>}>
     */
    public const SETTINGS_PANEL = [
        ['Settings', [
            ['Settings Hub', 'settings.index', 'cog'],
            ['Company Profile', 'company.index', 'cog'],
            ['Event Types', 'taxonomies.index', 'grid'],
            ['Statuses & Colours', 'workflows.index', 'chart'],
            ['Defaults', 'defaults.index', 'clipboard'],
            ['Price List', 'catalogue.index', 'archive'],
            ['Sponsor Packages', 'sponsor-packages.index', 'star'],
            ['Transport Catalogues', 'transport-settings.index', 'truck'],
            ['Registration Templates', 'registration-templates.index', 'clipboard'],
        ]],
    ];

    /** @deprecated Kept for callers; the Company Command map always covers the rail. */
    public const PANEL_COVERS = [
        'workspace', 'sales', 'events', 'proposals', 'contracts',
        'planning', 'operations', 'finance', 'partners', 'intelligence', 'team',
    ];

    /** Settings sits apart on the rail, in the panel dock. */
    public const SETTINGS = [
        'label' => 'Settings', 'icon' => 'cog', 'route' => 'settings.index',
        'match' => [
            'settings.*', 'company.*', 'taxonomies.*', 'workflows.*', 'defaults.*',
            'transport-settings.*', 'sponsor-packages.*', 'catalogue.*',
            'registration-templates.*',
        ],
    ];

    /**
     * @return Collection<int,array{label:string,items:Collection}>
     */
    public static function panel(): Collection
    {
        $source = self::currentArea() === 'settings'
            ? self::SETTINGS_PANEL
            : self::PANEL;

        return self::mapSections($source);
    }

    /**
     * @param  array<int,array{0:string,1:array<int,mixed>}>  $sections
     * @return Collection<int,array{label:string,items:Collection}>
     */
    private static function mapSections(array $sections): Collection
    {
        return collect($sections)
            ->map(fn (array $section) => [
                'label' => $section[0],
                'items' => collect($section[1])
                    ->filter(fn (array $item) => Route::has($item[1]))
                    ->map(fn (array $item) => self::mapItem($item))
                    ->values(),
            ])
            ->reject(fn (array $section) => $section['items']->isEmpty())
            ->values();
    }

    /**
     * @param  array{0:string,1:string,2:string,3?:int|null,4?:array<string,mixed>}  $item
     * @return array{label:string,href:string,icon:string,count:int|null,active:bool}
     */
    private static function mapItem(array $item): array
    {
        $meta = $item[4] ?? [];
        $hash = $meta['_hash'] ?? null;
        $params = collect($meta)->except('_hash')->all();

        $href = route($item[1], $params);
        if (is_string($hash) && $hash !== '') {
            $href .= '#'.$hash;
        }

        $active = request()->routeIs($item[1]);
        if ($hash !== null) {
            // Fragment targets cannot be resolved server-side.
            $active = false;
        } elseif ($active && $params !== []) {
            $active = collect($params)->every(
                fn ($value, $key) => (string) request()->query($key, '') === (string) $value
            );
        } elseif ($active && $params === []) {
            // Bare route is active only when no list filter is set, so
            // "All Events" and "Live Events" do not both light up.
            $active = collect(['stage', 'state', 'status', 'type'])
                ->every(fn ($key) => blank(request()->query($key)));
        }

        return [
            'label' => $item[0],
            'href' => $href,
            'icon' => $item[2],
            'count' => $item[3] ?? null,
            'active' => $active,
        ];
    }

    /**
     * The one gold button in the tools bar.
     *
     * @return array{label:string,icon:string,href:string}|null
     */
    public static function primaryAction(): ?array
    {
        $candidates = match (self::currentArea()) {
            'events' => [['＋ Create Event', 'sparkles', 'events.create']],
            'sales' => [['＋ New Client', 'identification', 'clients.index']],
            'proposals' => [['＋ Proposals', 'document', 'proposals.index']],
            'contracts' => [['＋ Contracts', 'document', 'contracts.index']],
            'partners', 'operations' => [['＋ Add Venue', 'building', 'venues.index']],
            'finance' => [['＋ Invoices', 'currency', 'invoices.index']],
            'team' => [['＋ Team', 'users', 'team.index']],
            default => [],
        };

        $candidates[] = ['✦ Ask AI', 'sparkles', 'ai.index'];

        foreach ($candidates as [$label, $icon, $route]) {
            if (Route::has($route)) {
                return ['label' => $label, 'icon' => $icon, 'href' => route($route)];
            }
        }

        return null;
    }

    /** Which area the current request is in. Falls back to the workspace. */
    public static function currentArea(): string
    {
        foreach (self::AREAS as $key => $area) {
            if (request()->routeIs(...$area['match'])) {
                return $key;
            }
        }

        return request()->routeIs(...self::SETTINGS['match']) ? 'settings' : 'workspace';
    }

    public static function areaLabel(string $area): string
    {
        return $area === 'settings' ? self::SETTINGS['label'] : (self::AREAS[$area]['label'] ?? 'Workspace');
    }

    /**
     * Area-focused sections (legacy helpers / deep focus). Prefer panel().
     *
     * @return Collection<int,array{label:string,items:Collection}>
     */
    public static function sections(string $area): Collection
    {
        if ($area === 'settings') {
            return self::mapSections(self::SETTINGS_PANEL);
        }

        // Company Command map already covers every rail area.
        return collect();
    }

    /**
     * The portfolio tree of stages with events inside them.
     */
    public static function tree(string $area): Collection
    {
        if (! in_array($area, ['workspace', 'events'], true)) {
            return collect();
        }

        $events = Event::whereNull('archived_at')
            ->withCount(['tasks as open_tasks' => fn ($q) => $q->whereNotIn('status', ['done', 'cancelled'])])
            ->with('client')
            ->orderByDesc('starts_at')
            ->get();

        return collect(Workflow::SETS['event_stage']['states'])
            ->map(fn ($_, string $stage) => [
                'key' => $stage,
                'label' => Workflow::label('event_stage', $stage),
                'color' => Workflow::color('event_stage', $stage),
                'events' => $events->where('stage', $stage)->values(),
            ])
            ->filter(fn (array $group) => $group['events']->isNotEmpty())
            ->values();
    }

}
