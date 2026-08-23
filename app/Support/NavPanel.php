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
            // projects.* lived under the old Library area; Projects is an
            // Events core link (added in the Sidebar Diet), so this is where
            // "you are on the Projects page" should now resolve to.
            'match' => ['events.*', 'projects.*'],
        ],
        'tasks' => [
            'label' => 'Tasks', 'icon' => 'clipboard', 'route' => 'tasks.index',
            'match' => ['tasks.*'],
        ],
        // Key stays 'crm' — only the label changes. Renaming the key would
        // touch every route-independent place that keys off it (primaryAction,
        // sections, the drift-prone stuff); the route names (crm.index,
        // clients.index…) are the actual product surface and are untouched.
        'crm' => [
            'label' => 'Commercial', 'icon' => 'identification', 'route' => 'crm.index',
            'match' => ['crm.*', 'clients.*', 'proposals.*', 'contracts.*', 'sponsors.*'],
        ],
        'finance' => [
            'label' => 'Finance', 'icon' => 'currency', 'route' => 'finance.index',
            'match' => ['finance.*', 'invoices.*', 'payments.*'],
        ],
        // Library, dissolved: its eight links were three different jobs
        // wearing one label. Suppliers/Venues/Equipment run events (Operations),
        // Reports/AI make sense of them (Intelligence), Team was never really
        // a "directory" at all.
        'operations' => [
            'label' => 'Operations', 'icon' => 'truck', 'route' => 'suppliers.index',
            'match' => ['suppliers.*', 'venues.*', 'requirements.*'],
        ],
        'intelligence' => [
            'label' => 'Intelligence', 'icon' => 'sparkles', 'route' => 'reports.index',
            'match' => ['reports.*', 'ai.*'],
        ],
        'team' => [
            'label' => 'Team', 'icon' => 'users', 'route' => 'team.index',
            'match' => ['team.*'],
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

    /**
     * Settings sits apart on the rail, as it does in most tools.
     *
     * 'clients.*' does not belong here: Clients lives in Commercial's own
     * match list too, and AREAS is checked first in currentArea(), so a
     * shared pattern always resolved to Commercial — Settings could never
     * highlight while looking at a route it no longer even links to.
     * 'catalogue.*' and 'registration-templates.*' are added because those
     * two routes are only reachable from Settings and matched nowhere else.
     */
    public const SETTINGS = [
        'label' => 'Settings', 'icon' => 'cog', 'route' => 'settings.index',
        'match' => ['settings.*', 'company.*', 'taxonomies.*', 'workflows.*', 'defaults.*',
            'transport-settings.*', 'sponsor-packages.*', 'catalogue.*', 'registration-templates.*'],
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
            'operations' => [['＋ Add Venue', 'building', 'venues.index']],
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

    /**
     * A one-line "what's live in this area right now" line for the panel's
     * header block — the number you'd want before you even click a row.
     *
     * One cheap count per area at most, and only the current area's arm of
     * the match runs per request (the nav renders on every page), so this
     * costs one extra query on the pages that show it and none elsewhere.
     * An area with no single honest headline number returns null and the
     * header falls back to a plain subtitle.
     *
     * @return array{value:string,label:string,tone:string}|null
     */
    public static function areaMeta(string $area): ?array
    {
        return match ($area) {
            'workspace', 'events' => self::meta(self::liveEvents(), 'live event', 'ok'),
            'tasks' => self::meta(self::openTasks(), 'open task', 'warn'),
            'crm' => self::meta(Deal::whereIn('stage', Deal::OPEN)->count(), 'deal', 'info', 'in play'),
            'operations' => self::meta(\App\Models\Supplier::count(), 'supplier', 'neutral', 'on file'),
            'team' => self::meta(\App\Models\User::count(), 'person', 'neutral'),
            default => null,
        };
    }

    /**
     * Builds the header stat, pluralising the noun to the count so it never
     * reads "1 deals". $noun is singular; $trail is any words that follow it.
     *
     * @return array{value:string,label:string,tone:string}|null
     */
    private static function meta(int $count, string $noun, string $tone, string $trail = ''): ?array
    {
        if ($count === 0) {
            return null;
        }

        $label = str($noun)->plural($count)->toString();

        return ['value' => (string) $count, 'label' => trim($label.' '.$trail), 'tone' => $tone];
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
            // The dashboard's own anchors, not new pages — Command Center is
            // one screen, so its core links are the things on it worth
            // jumping straight to. Pre-built items (not [label, route, icon,
            // count] tuples) because a fragment isn't a route.
            'workspace' => [
                ['Command Center', [
                    ['label' => 'Dashboard', 'href' => route('home'), 'icon' => 'home', 'count' => null, 'active' => request()->routeIs('home')],
                    ['label' => 'Live Alerts', 'href' => route('home').'#live-alerts', 'icon' => 'bell', 'count' => null, 'active' => false],
                ]],
            ],
            'events' => [
                ['Events', [
                    ['label' => 'Event Portfolio', 'href' => route('events.index'), 'icon' => 'calendar', 'count' => self::liveEvents(), 'active' => request()->routeIs('events.index') && ! request()->boolean('archived')],
                    ['Event Studio', 'events.create', 'sparkles', null],
                    ['Projects', 'projects.index', 'folder', null],
                    ['label' => 'Archived Events', 'href' => route('events.index', ['archived' => 1]), 'icon' => 'archive', 'count' => null, 'active' => request()->routeIs('events.index') && request()->boolean('archived')],
                ]],
            ],
            'tasks' => [
                ['Tasks', [
                    ['Everything', 'tasks.index', 'clipboard', self::openTasks()],
                ]],
            ],
            'crm' => [
                ['Commercial Command', [
                    ['Deal board', 'crm.index', 'columns', Deal::whereIn('stage', Deal::OPEN)->count()],
                    ['Clients', 'clients.index', 'identification', null],
                    ['Proposals', 'proposals.index', 'document', null],
                    ['Contracts', 'contracts.index', 'document', null],
                    // Sponsorships stays inside Commercial rather than
                    // becoming its own rail domain — it's a commercial deal
                    // like any other, just one that funds a booth instead of
                    // buying one.
                    ['Sponsorships', 'sponsors.index', 'star', null],
                ]],
            ],
            'finance' => [
                ['Finance Command', [
                    ['Profit & loss', 'finance.index', 'chart', null],
                    ['Invoices', 'invoices.index', 'currency', null],
                    ['Payments', 'payments.index', 'card', null],
                ]],
            ],
            'operations' => [
                ['Operations', [
                    ['Suppliers', 'suppliers.index', 'truck', null],
                    ['Venues', 'venues.index', 'building', null],
                    ['Equipment', 'requirements.index', 'archive', null],
                ]],
            ],
            'intelligence' => [
                ['Intelligence', [
                    ['Reports', 'reports.index', 'chart', null],
                    ['Command Briefing', 'ai.index', 'sparkles', null],
                ]],
            ],
            'team' => [
                ['Team', [
                    ['Team roster', 'team.index', 'users', null],
                ]],
            ],
            // Two groups, not three: configuration you set once, and the
            // priced/reusable things an event starts from. Clients,
            // Suppliers, Venues, Equipment and Team & Roles used to be
            // listed here too — they are daily-work destinations that
            // already have a home in their own domain, so this panel no
            // longer carries a second door to any of them.
            'settings' => [
                ['Workspace Configuration', [
                    ['Company Profile', 'company.index', 'cog', null],
                    ['Types & Lists', 'taxonomies.index', 'grid', null],
                    ['Statuses & Colours', 'workflows.index', 'chart', null],
                    ['Defaults', 'defaults.index', 'clipboard', null],
                ]],
                ['Catalogues & Templates', [
                    ['Sponsorship packages', 'sponsor-packages.index', 'star', null],
                    ['Transport', 'transport-settings.index', 'truck', null],
                    ['Price list', 'catalogue.index', 'currency', null],
                    ['Registration templates', 'registration-templates.index', 'document', null],
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
                    // A pre-built item (Command Center's anchors, the archive
                    // link) carries its own href already — a fragment or a
                    // query string was never a route name to look up.
                    ->filter(fn (array $item) => isset($item['href']) || Route::has($item[1]))
                    ->map(fn (array $item) => isset($item['href']) ? $item : [
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
    private static function openTasks(): int
    {
        // Memoised: areaMeta() and sections() can both ask for it in the same
        // render, and it's the same number either way.
        static $count = null;

        return $count ??= Task::whereNotIn('status', ['done', 'cancelled'])->count();
    }

    private static function liveEvents(): int
    {
        static $count = null;

        return $count ??= Event::whereNull('archived_at')->count();
    }
}
