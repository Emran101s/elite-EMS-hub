<?php

namespace App\Support;

use App\Models\Deal;
use App\Models\Event;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * The two-tier navigation: which area you are in, and what is inside it.
 *
 * The rail carries the areas; the panel carries the contents of the one you
 * are in. That split is the whole idea — a row of pills could only ever show
 * the top level, which is why five modules ended up behind a More menu that
 * turned out to be invisible.
 *
 * Areas are declared here rather than derived from config/modules.php because
 * they are not the same list: an area is a place you work, and Suppliers,
 * Venues and Equipment are three doors into one place.
 */
class NavPanel
{
    /**
     * The rail, top to bottom.
     *
     *   match  route-name patterns that mean "you are in this area"
     *
     * @var array<string,array{label:string,icon:string,route:string,match:array<int,string>}>
     */
    public const AREAS = [
        'workspace' => [
            'label' => 'Command Center', 'icon' => 'home', 'route' => 'home',
            'match' => ['home', 'operations-room', 'concept.*'],  // the room redirects here
        ],
        'events' => [
            'label' => 'Events', 'icon' => 'calendar', 'route' => 'events.index',
            'match' => ['events.*'],
        ],
        'tasks' => [
            'label' => 'Tasks', 'icon' => 'clipboard', 'route' => 'tasks.index',
            'match' => ['tasks.*'],
        ],
        'crm' => [
            'label' => 'CRM', 'icon' => 'identification', 'route' => 'crm.index',
            'match' => ['crm.*'],
        ],
        'finance' => [
            'label' => 'Finance', 'icon' => 'currency', 'route' => 'finance.index',
            'match' => ['finance.*'],
        ],
        'library' => [
            'label' => 'Library', 'icon' => 'archive', 'route' => 'suppliers.index',
            'match' => ['suppliers.*', 'venues.*', 'requirements.*', 'projects.*', 'team.*', 'sponsors.*', 'ai.*', 'reports.*'],
        ],
    ];

    /**
     * The panel, exactly as the Elite Orbit OS reference draws it.
     *
     * Fixed rather than derived from the area: the reference shows the whole
     * platform at once, so the panel is the map and the rail is the pin on it.
     *
     * The map only draws what exists. Rows for unbuilt pages used to be drawn
     * greyed out to teach the shape of the product; in practice new staff read
     * them as broken software and clicked them anyway, so docs/18 called them
     * in and the audit's first fix was to take them out. A route that is not
     * registered drops its row, and a section left with no rows drops too —
     * the shape of the nav follows the shape of the build, with nothing to
     * hand-tidy the day a module lands.
     *
     * @var array<int,array{0:string,1:array<int,array{0:string,1:string,2:string}>}>
     */
    public const PANEL = [
        ['Events', [
            ['Dashboard', 'home', 'home'],
            ['All events', 'events.index', 'calendar'],
            ['New event', 'events.create', 'sparkles'],
        ]],
        ['Planning', [
            ['Planning board', 'planning.index', 'grid'],
            ['Tasks', 'tasks.index', 'clipboard'],
        ]],
        ['Business', [
            ['Proposals', 'proposals.index', 'document'],
            ['Contracts', 'contracts.index', 'document'],
        ]],
        ['Finance', [
            ['Invoices', 'invoices.index', 'currency'],
            ['Payments', 'payments.index', 'card'],
        ]],
    ];

    /**
     * Areas the fixed panel already covers.
     *
     * Everywhere else — the CRM, the library, Settings — still appends its own
     * sections underneath, or Venues, Team and Reports would have no door left.
     */
    public const PANEL_COVERS = ['workspace', 'events', 'tasks'];

    /**
     * @return Collection<int,array{label:string,items:Collection}>
     */
    public static function panel(): Collection
    {
        return collect(self::PANEL)
            ->map(fn (array $section) => [
                'label' => $section[0],
                'items' => collect($section[1])
                    ->filter(fn (array $item) => Route::has($item[1]))
                    ->map(fn (array $item) => [
                        'label' => $item[0],
                        'href' => route($item[1]),
                        'icon' => $item[2],
                        'count' => null,
                        'active' => request()->routeIs($item[1]),
                    ])->values(),
            ])
            ->reject(fn (array $section) => $section['items']->isEmpty())
            ->values();
    }

    /** Settings sits apart on the rail, as it does in most tools. */
    public const SETTINGS = [
        'label' => 'Settings', 'icon' => 'cog', 'route' => 'settings.index',
        'match' => ['settings.*', 'company.*', 'taxonomies.*', 'workflows.*', 'defaults.*',
            'clients.*', 'transport-settings.*', 'sponsor-packages.*'],
    ];

    /**
     * The one gold button in the tools bar.
     *
     * It is contextual because that is what the references draw: Create Event
     * where you are looking at events, Ask AI where you are looking at one. A
     * primary action that means the same thing everywhere means very little
     * anywhere.
     *
     * @return array{label:string,icon:string,href:string}|null
     */
    public static function primaryAction(): ?array
    {
        $candidates = match (self::currentArea()) {
            'events' => [['＋ Create Event', 'sparkles', 'events.create']],
            'crm' => [['＋ New Client', 'identification', 'clients.index']],
            'library' => [['＋ Add Venue', 'building', 'venues.index']],
            default => [],
        };

        // Ask AI is the fallback everywhere else, and the last resort if the
        // area's own action points at a route that does not exist yet.
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
     * The panel's sections for an area.
     *
     * A section is [label, items]; an item is [label, route, icon, count?].
     * Routes that do not exist are dropped rather than crashing the chrome —
     * a module can be half-built without taking the navigation with it.
     *
     * @return Collection<int,array{label:string,items:array}>
     */
    public static function sections(string $area): Collection
    {
        return collect(match ($area) {
            'events' => [
                ['Events', [
                    ['All events', 'events.index', 'calendar', null],
                    ['New event', 'events.create', 'sparkles', null],
                ]],
            ],
            'tasks' => [
                ['Tasks', [
                    ['Everything', 'tasks.index', 'clipboard', self::openTasks()],
                ]],
            ],
            'crm' => [
                ['Pipeline', [
                    ['Deal board', 'crm.index', 'columns', Deal::whereIn('stage', Deal::OPEN)->count()],
                    ['Clients', 'clients.index', 'identification', null],
                ]],
            ],
            'finance' => [
                ['Money', [
                    ['Profit & loss', 'finance.index', 'chart', null],
                ]],
            ],
            'library' => [
                ['Directories', [
                    ['Suppliers', 'suppliers.index', 'truck', null],
                    ['Venues', 'venues.index', 'building', null],
                    ['Projects', 'projects.index', 'folder', null],
                    ['Team', 'team.index', 'users', null],
                ]],
                ['Catalogues', [
                    ['Equipment', 'requirements.index', 'archive', null],
                    ['Sponsorships', 'sponsors.index', 'star', null],
                ]],
                ['Grow', [
                    ['Reports', 'reports.index', 'chart', null],
                    ['AI Assistant', 'ai.index', 'sparkles', null],
                ]],
            ],
            'settings' => [
                ['Workspace', [
                    ['Company Profile', 'company.index', 'cog', null],
                    ['Types & Lists', 'taxonomies.index', 'grid', null],
                    ['Statuses & Colours', 'workflows.index', 'chart', null],
                    ['Defaults', 'defaults.index', 'clipboard', null],
                    ['Team & Roles', 'team.index', 'users', null],
                ]],
                ['Directories', [
                    ['Clients', 'clients.index', 'identification', null],
                    ['Suppliers', 'suppliers.index', 'truck', null],
                    ['Venues', 'venues.index', 'building', null],
                ]],
                ['Catalogues', [
                    ['Equipment', 'requirements.index', 'archive', null],
                    ['Sponsorship packages', 'sponsor-packages.index', 'star', null],
                    ['Transport', 'transport-settings.index', 'truck', null],
                ]],
            ],
            default => [
                ['Workspace', [
                    ['Dashboard', 'home', 'home', null],
                    ['Events', 'events.index', 'calendar', self::liveEvents()],
                    ['Tasks', 'tasks.index', 'clipboard', self::openTasks()],
                ]],
            ],
        })
            ->map(fn (array $section) => [
                'label' => $section[0],
                'items' => collect($section[1])
                    ->filter(fn (array $item) => Route::has($item[1]))
                    ->map(fn (array $item) => [
                        'label' => $item[0],
                        'href' => route($item[1]),
                        'icon' => $item[2],
                        'count' => $item[3],
                        'active' => request()->routeIs($item[1]),
                    ])->values(),
            ])
            ->reject(fn (array $section) => $section['items']->isEmpty())
            ->values();
    }

    /**
     * The portfolio, as a tree of stages with the events inside them.
     *
     * Only where it is the thing you came for — a tree of events on the
     * Finance screen is furniture. Empty stages are not drawn: a folder with
     * nothing in it is a row you have to read to dismiss.
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

    private static function openTasks(): int
    {
        return Task::whereNotIn('status', ['done', 'cancelled'])->count();
    }

    private static function liveEvents(): int
    {
        return Event::whereNull('archived_at')->count();
    }
}
