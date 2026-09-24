<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventSpeaker;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * ⌘K / Ctrl-K from anywhere: find a record, go somewhere, or start something.
 *
 *   "icft budget"          → ICFT 2026 › Budget
 *   "new task on gala"     → EY Annual Gala › Tasks, with the add form open
 *   "overdue invoices"     → Invoices, filtered to overdue
 *   "approvals"            → the Approvals inbox
 *
 * Nothing here writes: every result is a link to a screen that already
 * exists and already checks permissions. The palette only decides which of
 * those links to offer — and never offers an event you could not open.
 */
class CommandPalette extends Component
{
    public string $q = '';

    /**
     * Words that name an event tab. Keys are Event::HUB_TABS keys; values are
     * what people actually type for them.
     */
    private const TAB_WORDS = [
        'overview' => ['overview', 'summary'],
        'brief' => ['brief', 'scope'],
        'contract' => ['contract', 'agreement'],
        'planning' => ['planning', 'plan'],
        'tasks' => ['tasks', 'task', 'todo'],
        'budget' => ['budget', 'costs', 'cost', 'money'],
        'pricing' => ['pricing', 'prices'],
        'risks' => ['risks', 'risk'],
        'approvals' => ['approvals', 'approval'],
        'agenda' => ['agenda', 'programme', 'program', 'sessions', 'session', 'schedule'],
        'speakers' => ['speakers', 'speaker'],
        'venue' => ['venue', 'rooms', 'room'],
        'suppliers' => ['suppliers', 'supplier', 'vendors'],
        'transportation' => ['transport', 'transportation', 'cars', 'drivers', 'movements'],
        'accommodation' => ['stay', 'hotel', 'hotels', 'rooming', 'accommodation'],
        'catering' => ['catering', 'food', 'menu', 'f&b'],
        'exhibition' => ['exhibition', 'booths', 'floor', 'stands'],
        'sponsors' => ['sponsors', 'sponsor', 'sponsorship'],
        'attendees' => ['attendees', 'registration', 'delegates', 'badges', 'guests'],
        'files' => ['files', 'documents', 'docs'],
        'settings' => ['settings'],
    ];

    /** "new X" → the tab whose ?action=add opens X's form. */
    private const CREATE_WORDS = [
        'task' => 'tasks', 'tasks' => 'tasks',
        'line' => 'budget', 'cost' => 'budget',
        'risk' => 'risks',
        'approval' => 'approvals', 'request' => 'approvals',
        'session' => 'agenda',
    ];

    private const VERBS = ['new', 'add', 'create'];

    private const FILLER = ['on', 'for', 'in', 'to', 'the', 'open', 'go'];

    /**
     * Places to go and things to start that need no record.
     *
     * @return list<array{label:string, sub:string, href:string, words:string, allowed:bool}>
     */
    private function catalogue(): array
    {
        $r = fn (string $name, array $params = []) => route($name, $params);

        return [
            ['label' => 'My work', 'sub' => 'Your open tasks and decisions', 'href' => $r('tasks.mine'), 'words' => 'my work mine tasks todo', 'allowed' => true],
            ['label' => 'Approvals', 'sub' => 'Decisions waiting on you', 'href' => $r('approvals.index'), 'words' => 'approvals decisions approve pending review', 'allowed' => true],
            ['label' => 'New event', 'sub' => 'Open Event Studio', 'href' => $r('events.create'), 'words' => 'new create add event studio', 'allowed' => Gate::allows('create', Event::class)],
            ['label' => 'Overdue tasks', 'sub' => 'Everyone\'s, across events', 'href' => $r('tasks.index', ['due' => 'overdue']), 'words' => 'overdue late tasks', 'allowed' => true],
            ['label' => 'My overdue tasks', 'sub' => 'Only yours', 'href' => $r('tasks.index', ['who' => 'me', 'due' => 'overdue']), 'words' => 'my overdue late tasks mine', 'allowed' => true],
            ['label' => 'Command Center', 'sub' => 'The dashboard', 'href' => $r('home'), 'words' => 'command center dashboard home', 'allowed' => true],
            ['label' => 'All tasks', 'sub' => 'Every task, filterable', 'href' => $r('tasks.index'), 'words' => 'all tasks everything', 'allowed' => true],
            ['label' => 'Events', 'sub' => 'The portfolio', 'href' => $r('events.index'), 'words' => 'events portfolio', 'allowed' => true],
            ['label' => 'Deal pipeline', 'sub' => 'Commercial', 'href' => $r('crm.index'), 'words' => 'deals pipeline crm commercial', 'allowed' => true],
            ['label' => 'Proposals', 'sub' => 'Commercial', 'href' => $r('proposals.index'), 'words' => 'proposals quotes offers', 'allowed' => true],
            ['label' => 'Contracts', 'sub' => 'Commercial', 'href' => $r('contracts.index'), 'words' => 'contracts agreements', 'allowed' => true],
            ['label' => 'Invoices', 'sub' => 'Finance', 'href' => $r('invoices.index'), 'words' => 'invoices billing', 'allowed' => true],
            ['label' => 'Overdue invoices', 'sub' => 'Past their due date', 'href' => $r('invoices.index', ['state' => 'overdue']), 'words' => 'overdue outstanding unpaid invoices', 'allowed' => true],
            ['label' => 'Overdue payments', 'sub' => 'Contract instalments past due', 'href' => $r('payments.index', ['status' => 'overdue']), 'words' => 'overdue outstanding payments instalments installments', 'allowed' => true],
            ['label' => 'Profit & loss', 'sub' => 'Finance', 'href' => $r('finance.index'), 'words' => 'finance profit loss p&l margin money', 'allowed' => true],
            ['label' => 'Reports', 'sub' => 'Intelligence', 'href' => $r('reports.index'), 'words' => 'reports analytics', 'allowed' => true],
            ['label' => 'Suppliers', 'sub' => 'Operations', 'href' => $r('suppliers.index'), 'words' => 'suppliers vendors', 'allowed' => true],
            ['label' => 'Venues', 'sub' => 'Operations', 'href' => $r('venues.index'), 'words' => 'venues', 'allowed' => true],
            ['label' => 'Team', 'sub' => 'People and roles', 'href' => $r('team.index'), 'words' => 'team people roles users', 'allowed' => true],
            ['label' => 'Settings', 'sub' => 'Workspace configuration', 'href' => $r('settings.index'), 'words' => 'settings configuration', 'allowed' => true],
        ];
    }

    /** What the empty palette suggests before you type. */
    private const SUGGESTED = ['My work', 'Approvals', 'New event', 'Overdue tasks'];

    /** @return list<string> */
    private function tokens(): array
    {
        return array_values(array_filter(preg_split('/\s+/', mb_strtolower(trim($this->q))) ?: []));
    }

    /** Every typed word starts some word of $haystack. */
    private static function matches(array $tokens, string $haystack): bool
    {
        $words = preg_split('/[\s&\-]+/', mb_strtolower($haystack));

        foreach ($tokens as $t) {
            if (! collect($words)->contains(fn ($w) => str_starts_with($w, $t))) {
                return false;
            }
        }

        return true;
    }

    /** Events this person may open, filtered by name — never one that would 403. */
    private function viewableEvents(array $nameTokens, int $limit): Collection
    {
        $user = auth()->user();

        return Event::whereNull('archived_at')
            ->where(function ($q) use ($nameTokens) {
                foreach ($nameTokens as $t) {
                    $q->where('name', 'like', '%'.$t.'%');
                }
            })
            ->orderBy('starts_at')->limit($limit * 3)->get()
            ->filter(fn (Event $e) => $user->can('view', $e))
            ->take($limit)->values();
    }

    /**
     * "icft budget", "gala transport", "new task on icft": an event plus a
     * tab (and maybe a verb) becomes a link straight into that tab.
     *
     * @return list<array{label:string, sub:string, href:string}>
     */
    private function jumps(array $tokens): array
    {
        $tab = null;
        $create = false;
        $rest = [];

        foreach ($tokens as $t) {
            if (in_array($t, self::VERBS, true)) {
                $create = true;
            } elseif ($create && $tab === null && isset(self::CREATE_WORDS[$t])) {
                $tab = self::CREATE_WORDS[$t];
            } elseif ($tab === null && ($key = $this->tabFor($t))) {
                $tab = $key;
            } elseif (! in_array($t, self::FILLER, true)) {
                $rest[] = $t;
            }
        }

        // Only a creatable tab can take ?action=add.
        if ($create && ! in_array($tab, array_unique(self::CREATE_WORDS), true)) {
            return [];
        }

        if ($tab === null || $rest === []) {
            return [];
        }

        [$tabLabel] = Event::HUB_TABS[$tab];

        return $this->viewableEvents($rest, 3)
            ->filter(fn (Event $e) => $e->moduleEnabled($tab))
            ->map(fn (Event $e) => [
                'label' => $e->name.' › '.$tabLabel,
                'sub' => $create ? 'Open '.$tabLabel.' with a new one started' : 'Open this tab',
                'href' => route('events.hub', array_filter([$e, 'tab' => $tab, 'action' => $create ? 'add' : null])),
            ])->all();
    }

    private function tabFor(string $token): ?string
    {
        foreach (self::TAB_WORDS as $key => $words) {
            if (in_array($token, $words, true)) {
                return $key;
            }
        }

        return null;
    }

    /** @return array<string, array<int, array{label:string, sub:?string, href:string}>> */
    private function results(): array
    {
        $catalogue = collect($this->catalogue())->where('allowed', true);
        $tokens = $this->tokens();

        if ($tokens === []) {
            return ['Suggested' => $catalogue->whereIn('label', self::SUGGESTED)->values()->all()];
        }

        $groups = [
            'Jump to' => collect($this->jumps($tokens)),
            'Go to' => $catalogue->filter(fn ($a) => self::matches($tokens, $a['label'].' '.$a['words']))->take(5)->values(),
        ];

        // Record search starts at two characters: one would match half the database.
        if (mb_strlen(trim($this->q)) >= 2) {
            $groups += $this->records(trim($this->q));
        }

        return collect($groups)->filter(fn ($g) => $g->isNotEmpty())->map->all()->all();
    }

    /** @return array<string, Collection> */
    private function records(string $q): array
    {
        $like = '%'.$q.'%';
        $user = auth()->user();
        $canOpen = fn (?Event $e) => $e && $user->can('view', $e);

        return [
            'Events' => $this->viewableEvents([$q], 5)
                ->map(fn ($e) => [
                    'label' => $e->name,
                    'sub' => trim(($e->city ?? '').' · '.($e->starts_at?->format('M Y') ?? ''), ' ·'),
                    'href' => route('events.hub', $e),
                ]),

            'Tasks' => Task::with('event')->where('title', 'like', $like)
                ->orderByRaw("status = 'done'")->limit(15)->get()
                ->filter(fn ($t) => $t->event === null || $canOpen($t->event))->take(5)
                ->map(fn ($t) => [
                    'label' => $t->title,
                    'sub' => ($t->event?->name ?? 'No event').' · '.$t->stageLabel(),
                    'href' => $t->event ? route('events.hub', [$t->event, 'tab' => 'tasks']) : route('tasks.index', ['q' => $t->title]),
                ])->values(),

            'Speakers' => EventSpeaker::with('event')->where('name', 'like', $like)->limit(12)->get()
                ->filter(fn ($s) => $canOpen($s->event))->take(4)
                ->map(fn ($s) => [
                    'label' => $s->name,
                    'sub' => $s->event?->name,
                    'href' => route('events.hub', [$s->event, 'tab' => 'speakers']),
                ])->values(),

            'Suppliers' => Supplier::where('name', 'like', $like)->limit(4)->get()
                ->map(fn ($s) => [
                    'label' => $s->name,
                    'sub' => str($s->category ?? '')->replace('_', ' ')->title()->toString() ?: null,
                    // Pre-filtered to this supplier, not the whole directory
                    // left for the user to search a second time.
                    'href' => route('suppliers.index', ['q' => $s->name]),
                ]),

            'Venues' => Venue::where('name', 'like', $like)->limit(4)->get()
                ->map(fn ($v) => ['label' => $v->name, 'sub' => $v->city, 'href' => route('venues.show', $v)]),

            'Team' => User::where('name', 'like', $like)->limit(4)->get()
                ->map(fn ($u) => ['label' => $u->name, 'sub' => $u->title ?? $u->roleLabel(), 'href' => route('team.index', ['q' => $u->name])]),
        ];
    }

    public function render()
    {
        return view('livewire.command-palette', ['groups' => $this->results()]);
    }
}
