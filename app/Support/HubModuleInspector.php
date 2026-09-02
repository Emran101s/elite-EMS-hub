<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventAgendaDay;
use App\Models\EventAgendaSession;
use App\Models\EventApproval;
use App\Models\EventAttendee;
use App\Models\EventBrief;
use App\Models\EventBudgetItem;
use App\Models\EventBudgetVersion;
use App\Models\EventCateringItem;
use App\Models\EventContract;
use App\Models\EventDocument;
use App\Models\EventExhibitor;
use App\Models\EventInvoiceItem;
use App\Models\EventRisk;
use App\Models\EventRoom;
use App\Models\EventRoomBlock;
use App\Models\EventScopeItem;
use App\Models\EventSpeaker;
use App\Models\EventSponsor;
use App\Models\EventTransport;
use App\Models\PlanItem;
use App\Models\Task;

/**
 * One per-module view-model, read by both the Universal Module Header
 * (shown inside a module's own tab content) and the Universal Right
 * Inspector (shown in the Event Hub's side panel) — a single place computing
 * "what does this module's readiness/status/metrics/owner/next-action say",
 * so the two surfaces can never quietly disagree about the same module.
 *
 * The two surfaces have different jobs, though: the Header owns identity,
 * readiness, metrics and the next action; the Inspector owns ownership
 * detail, actions, recent activity and related modules — it reads this same
 * array but never repeats the Header's own metric rows. Overview has no
 * single module to inspect, so its Inspector reads overview() instead —
 * event-level readiness gates and cross-module attention, not one module's
 * numbers standing in for the whole event.
 *
 * Every figure here reads an already-loaded Event relation or
 * EventCommandHeader's own numbers (meters()/critical()) — nothing is
 * computed fresh that isn't already computed elsewhere.
 */
class HubModuleInspector
{
    /**
     * What each module's headline percentage measures.
     *
     * Only where "Progress" would be wrong or vague — a budget is not
     * "85% progressed", it is 85% of its cap.
     */
    private const PCT_LABELS = [
        'budget' => 'Of cap',
        'transportation' => 'Readiness',
        'venue' => 'Readiness',
        'suppliers' => 'Readiness',
    ];

    private const PURPOSES = [
        'scope' => 'What we are delivering, who owns it, and what done means.',
        'agenda' => 'Programme planning, sessions, speakers and timeline control.',
        'budget' => 'Costs, forecast, margin and commercial control.',
        'transportation' => 'Movements, vehicles, drivers and VIP transfer readiness.',
        'approvals' => 'Decisions, sign-offs and pending confirmations.',
        'files' => 'Documents, templates, uploads and event references.',
        'accommodation' => 'Room blocks, rooming lists and hotel readiness.',
        'planning' => 'Milestones, task gates and overall plan progress toward event day.',
        'tasks' => 'Work items, ownership and progress toward event day.',
        'risks' => 'What could go wrong, tracked against severity and mitigation.',
        'speakers' => 'Line-up, confirmations and speaker fees.',
        'venue' => 'Rooms, capacity and layouts for this event.',
        'suppliers' => 'Vendors, orders and delivery readiness.',
        'catering' => 'Menus, breaks, meals and catering costs.',
        'exhibition' => 'Floor plan, stands and exhibitor revenue.',
        'sponsors' => 'Partnerships, packages and sponsorship revenue.',
        'attendees' => 'Registrations, check-in and badges.',
        'pricing' => 'What this event is invoiced for, and what it costs us.',
        'brief' => 'Scope, objectives and delegate journey for this event.',
        'contract' => 'Terms, signatures and payment schedule.',
    ];

    /** Modules whose Livewire component honours ?action=add on mount. */
    private const SUPPORTS_ADD_ACTION = ['agenda', 'budget', 'approvals', 'tasks', 'risks'];

    /** What that ?action=add button actually adds, for modules where "Add" alone is ambiguous. */
    private const ADD_LABELS = [
        'agenda' => 'Add Session',
        'budget' => 'Add Line Item',
        'approvals' => 'Add Approval',
        'tasks' => 'Add Task',
        'risks' => 'Add Risk',
    ];

    /**
     * Overview's own Inspector — event-level, not a per-module readout.
     * Deliberately doesn't repeat Health/Readiness/Days Out/Budget/Risk
     * (Event Pulse already says those) or near-term items (Mission Timeline
     * already says those): this adds the readiness gate detail behind the
     * bare %, which modules need a person right now, and the owner/activity/
     * doors a module's own Inspector would otherwise offer.
     */
    public static function overview(Event $event, array $header): array
    {
        $pct = $header['readiness']['pct'] ?? null;
        $statusWord = match (true) {
            $pct === null || $pct === 0 => 'Not started',
            $pct >= 60 => 'On Track',
            default => 'At Risk',
        };

        $attention = collect($header['attention'] ?? [])
            ->map(function (array $signal, string $key) {
                [$label, , $icon] = Event::HUB_TABS[$key] ?? [ucfirst($key), '', 'archive'];

                return ['tab' => $key, 'label' => $label, 'icon' => $icon, 'why' => $signal['why'], 'tone' => $signal['tone']];
            })
            ->sortBy(fn (array $s) => $s['tone'] === 'alarm' ? 0 : 1)
            ->take(4)
            ->values();

        $recent = AuditLog::where('event_id', $event->id)->with('user')->latest()->take(3)->get();

        return [
            'tab' => 'overview',
            'label' => 'Event Inspector',
            'icon' => 'home',
            'color' => '#0B1F3A',
            'pct' => $pct,
            'statusWord' => $statusWord,
            'gates' => $header['readiness']['gates'] ?? [],
            'owner' => $event->projectManager,
            'attention' => $attention,
            'recent' => $recent,
            'quickLinks' => [
                ['label' => 'Agenda', 'tab' => 'agenda', 'icon' => 'calendar'],
                ['label' => 'Budget', 'tab' => 'budget', 'icon' => 'currency'],
                ['label' => 'Tasks', 'tab' => 'tasks', 'icon' => 'clipboard'],
            ],
        ];
    }

    /**
     * Is there anything in the Inspector worth a 280px column?
     *
     * On a module tab the panel carries only what the Universal Module Header
     * above it does not: recent activity, a next action, and links to
     * neighbouring modules. When the first two are empty the panel is a
     * heading that says "No recent activity" and two links the module nav bar
     * already offers — which is not worth the width it takes from the tab's
     * own content. Overview always keeps it: its readiness gates and
     * attention list have nothing above them.
     */
    public static function hasContent(Event $event, array $header, string $tab): bool
    {
        if ($tab === 'overview') {
            return true;
        }

        $m = self::data($event, $header, $tab);

        return $m['recent']->isNotEmpty()
            || $m['nextAction'] !== null
            || ! empty($m['supportsAdd']);
    }

    public static function data(Event $event, array $header, string $tab): array
    {
        $modules = Event::HUB_TABS;
        $inspectTab = $tab;
        [$label,, $icon] = $modules[$inspectTab] ?? [ucfirst($inspectTab), '', 'archive'];
        $color = Event::moduleColor($inspectTab);

        $metersByKey = collect($header['meters'] ?? [])->keyBy('key');
        $meterAlias = ['transportation' => 'logistics', 'venue' => 'logistics', 'suppliers' => 'logistics'];
        $pct = $metersByKey->get($meterAlias[$inspectTab] ?? $inspectTab)['pct'] ?? null;

        // Planning has no EventCommandHeader meter of its own — its
        // completion percentage (done ÷ total plan items) is real, already
        // shown on its own page's countdown panel, just not a number
        // meters() computes centrally like it does for budget/agenda.
        if ($pct === null && $inspectTab === 'planning') {
            $planItems = $event->planItems;
            $pct = $planItems->isNotEmpty() ? (int) round($planItems->where('status', 'done')->count() / $planItems->count() * 100) : null;
        }

        // The rest of these have no EventCommandHeader meter either — each
        // fallback is the same figure that module's own stat strip already
        // shows and trusts, not a new computation invented for the panel.
        // Scope: met over total, the same figure its own summary strip shows.
        // Without this the module header announced "not started" above a body
        // listing seven deliverables — the exact self-contradiction this file
        // has had to be corrected for twice already.
        if ($pct === null && $inspectTab === 'scope') {
            $scope = $event->scopeItems;
            $pct = $scope->isNotEmpty()
                ? (int) round($scope->filter(fn ($i) => ScopeStatus::isMet($i))->count() / $scope->count() * 100)
                : null;
        }
        if ($pct === null && $inspectTab === 'risks') {
            $risks = $event->risks;
            $resolved = $risks->whereIn('status', ['mitigated', 'closed'])->count();
            $pct = $risks->isNotEmpty() ? (int) round($resolved / $risks->count() * 100) : null;
        }
        if ($pct === null && $inspectTab === 'catering') {
            $items = $event->cateringItems->reject(fn ($c) => $c->status === 'cancelled');
            $pct = $items->isNotEmpty() ? (int) round($items->where('status', 'confirmed')->count() / $items->count() * 100) : null;
        }
        if ($pct === null && $inspectTab === 'exhibition') {
            $exhibitors = $event->exhibitors;
            $confirmed = $exhibitors->whereIn('status', ['confirmed', 'paid'])->count();
            $pct = $exhibitors->isNotEmpty() ? (int) round($confirmed / $exhibitors->count() * 100) : null;
        }
        if ($pct === null && $inspectTab === 'attendees') {
            $capacity = (int) ($event->expected_participants ?? 0);
            $registered = $event->attendees->where('status', '!=', 'cancelled')->count();
            $pct = $capacity > 0 ? min(100, (int) round($registered / $capacity * 100)) : null;
        }
        if ($pct === null && $inspectTab === 'pricing') {
            $items = $event->invoiceItems;
            $pct = $items->isNotEmpty() ? (int) round($items->filter(fn ($i) => $i->cost_cents > 0)->count() / $items->count() * 100) : null;
        }

        $statusWord = match (true) {
            $pct === null || $pct === 0 => 'Not started',
            $pct >= 60 => 'On Track',
            default => 'At Risk',
        };

        $critical = $header['critical'];
        // A module's own Next Action, when EventCommandHeader's single
        // event-wide critical() item happens to belong to this tab — never
        // a fabricated per-module action.
        $nextAction = ($critical && ($critical['tab'] ?? null) === $inspectTab) ? $critical : null;

        [$metrics, $quickLinks, $auditTypes] = match ($inspectTab) {
            'agenda' => [
                [
                    ['icon' => 'clipboard', 'value' => $event->agendaSessions->count(), 'label' => 'Sessions'],
                    ['icon' => 'bell', 'value' => $event->agendaSessions->reject->isSettled()->count(), 'label' => 'Not settled'],
                    ['icon' => 'users', 'value' => $event->attendees->count(), 'label' => 'Attendees'],
                    ['icon' => 'sparkles', 'value' => $event->speakers->where('status', 'confirmed')->count().'/'.$event->speakers->count(), 'label' => 'Speakers confirmed'],
                ],
                [['label' => 'Sessions', 'tab' => 'agenda', 'icon' => 'calendar'], ['label' => 'Speakers', 'tab' => 'speakers', 'icon' => 'sparkles'], ['label' => 'Documents', 'tab' => 'files', 'icon' => 'archive']],
                [EventAgendaSession::class, EventAgendaDay::class],
            ],
            'budget' => [
                (function () use ($event) {
                    $cost = $event->costForecast();
                    $committed = (int) $event->budgetItems->sum('actual_cents');

                    return [
                        ['icon' => 'currency', 'value' => $event->money($cost['forecast']), 'label' => 'Forecast'],
                        ['icon' => 'currency', 'value' => $event->money($committed), 'label' => 'Committed'],
                        ['icon' => 'currency', 'value' => $cost['over'] > 0 ? $event->money($cost['over']) : $event->money(0), 'label' => 'Over cap'],
                    ];
                })(),
                [['label' => 'Budget', 'tab' => 'budget', 'icon' => 'currency'], ['label' => 'Pricing', 'tab' => 'pricing', 'icon' => 'archive'], ['label' => 'Reports', 'tab' => 'reports', 'icon' => 'chart']],
                [EventBudgetItem::class, EventBudgetVersion::class],
            ],
            'transportation' => [
                [
                    ['icon' => 'truck', 'value' => $event->transport->count(), 'label' => 'Movements'],
                    ['icon' => 'bell', 'value' => $event->transport->reject(fn ($m) => in_array($m->status, ['completed', 'cancelled'], true))->reject->isReady()->count(), 'label' => 'Not ready'],
                    ['icon' => 'users', 'value' => $event->transferGuests->count(), 'label' => 'Guests in pool'],
                    ['icon' => 'clipboard', 'value' => $event->transferGuests->whereNull('transport_id')->count(), 'label' => 'Unassigned'],
                ],
                [['label' => 'Transport', 'tab' => 'transportation', 'icon' => 'truck'], ['label' => 'Suppliers', 'tab' => 'suppliers', 'icon' => 'archive']],
                [EventTransport::class],
            ],
            'approvals' => [
                (function () use ($event) {
                    $pending = $event->approvals->where('status', 'pending');
                    $oldest = $pending->sortBy('created_at')->first();

                    return [
                        ['icon' => 'identification', 'value' => $pending->count(), 'label' => 'Pending'],
                        ['icon' => 'clipboard', 'value' => $event->approvals->where('status', 'approved')->count(), 'label' => 'Approved'],
                        ['icon' => 'bell', 'value' => $event->approvals->where('status', 'rejected')->count(), 'label' => 'Rejected'],
                        ['icon' => 'clock', 'value' => $oldest ? (int) $oldest->created_at->diffInDays(now()).'d' : '—', 'label' => 'Oldest waiting'],
                    ];
                })(),
                [['label' => 'Approvals', 'tab' => 'approvals', 'icon' => 'identification'], ['label' => 'Contract', 'tab' => 'contract', 'icon' => 'archive']],
                [EventApproval::class],
            ],
            'accommodation' => [
                (function () use ($event) {
                    $blocks = $event->roomBlocks;
                    $roomsHeld = $blocks->sum('rooms_count');
                    $roomsNamed = $blocks->sum(fn (EventRoomBlock $b) => $b->filled());

                    return [
                        ['icon' => 'home', 'value' => $blocks->count(), 'label' => 'Blocks'],
                        ['icon' => 'clipboard', 'value' => $roomsNamed.'/'.$roomsHeld, 'label' => 'Rooms named'],
                        ['icon' => 'bell', 'value' => max(0, $roomsHeld - $roomsNamed), 'label' => 'Still to name'],
                        ['icon' => 'chart', 'value' => $blocks->sum(fn (EventRoomBlock $b) => $b->roomNights()), 'label' => 'Room-nights'],
                    ];
                })(),
                [['label' => 'Accommodation', 'tab' => 'accommodation', 'icon' => 'home'], ['label' => 'Attendees', 'tab' => 'attendees', 'icon' => 'users']],
                [EventRoomBlock::class],
            ],
            'planning' => [
                (function () use ($event) {
                    $items = $event->planItems;
                    $overdue = $items->filter(fn (PlanItem $i) => $i->isOverdue())->count();

                    return [
                        ['icon' => 'clipboard', 'value' => $items->count(), 'label' => 'Plan items'],
                        ['icon' => 'bell', 'value' => $overdue, 'label' => 'Overdue'],
                        ['icon' => 'clock', 'value' => $items->where('status', 'needs_approval')->count(), 'label' => 'Awaiting approval'],
                        ['icon' => 'chart', 'value' => $items->where('status', 'done')->count().'/'.$items->count(), 'label' => 'Done'],
                    ];
                })(),
                [['label' => 'Planning', 'tab' => 'planning', 'icon' => 'list']],
                [PlanItem::class],
            ],
            'tasks' => [
                (function () use ($event) {
                    $all = $event->tasks;
                    $open = $all->filter->isOpen();

                    return [
                        ['icon' => 'clipboard', 'value' => $all->count(), 'label' => 'Total tasks'],
                        ['icon' => 'bell', 'value' => $open->filter(fn (Task $t) => $t->isOverdue())->count(), 'label' => 'Overdue'],
                        ['icon' => 'clock', 'value' => $all->where('status', 'review')->count(), 'label' => 'In review'],
                        // Same denominator the headline percentage uses — a
                        // cancelled task is not work still to do, so counting
                        // it here made the fraction and the % disagree.
                        ['icon' => 'chart', 'value' => $all->where('status', 'done')->count().'/'.$all->where('status', '!=', 'cancelled')->count(), 'label' => 'Done'],
                    ];
                })(),
                [['label' => 'Tasks', 'tab' => 'tasks', 'icon' => 'clipboard'], ['label' => 'Risks', 'tab' => 'risks', 'icon' => 'bell']],
                [Task::class],
            ],
            'scope' => [
                (function () use ($event) {
                    $scope = $event->scopeItems;
                    $met = $scope->filter(fn ($i) => ScopeStatus::isMet($i))->count();

                    return [
                        ['icon' => 'clipboard', 'value' => $scope->count(), 'label' => 'Deliverables'],
                        ['icon' => 'check', 'value' => $met, 'label' => 'Met'],
                        ['icon' => 'users', 'value' => $scope->whereNull('owner_id')->count(), 'label' => 'No owner'],
                        ['icon' => 'bell', 'value' => $scope->filter->isOverdue()->count(), 'label' => 'Overdue'],
                    ];
                })(),
                [['label' => 'Brief', 'tab' => 'brief', 'icon' => 'clipboard'], ['label' => 'Tasks', 'tab' => 'tasks', 'icon' => 'clipboard'], ['label' => 'Contract', 'tab' => 'contract', 'icon' => 'identification']],
                [EventScopeItem::class],
            ],
            'risks' => [
                (function () use ($event) {
                    $risks = $event->risks;
                    $open = $risks->filter->isOpen();
                    $critical = $open->filter(fn (EventRisk $r) => $r->severity() >= 15);

                    return [
                        ['icon' => 'flag', 'value' => $open->count(), 'label' => 'Open'],
                        ['icon' => 'bell', 'value' => $critical->count(), 'label' => 'Critical'],
                        ['icon' => 'check', 'value' => $risks->whereIn('status', ['mitigated', 'closed'])->count(), 'label' => 'Resolved'],
                        ['icon' => 'clipboard', 'value' => $risks->count(), 'label' => 'Total'],
                    ];
                })(),
                [['label' => 'Risks', 'tab' => 'risks', 'icon' => 'bell'], ['label' => 'Approvals', 'tab' => 'approvals', 'icon' => 'identification']],
                [EventRisk::class],
            ],
            'speakers' => [
                (function () use ($event) {
                    $speakers = $event->speakers;
                    $confirmed = $speakers->where('status', 'confirmed')->count();

                    return [
                        ['icon' => 'sparkles', 'value' => $speakers->count(), 'label' => 'Speakers'],
                        ['icon' => 'check', 'value' => $confirmed, 'label' => 'Confirmed'],
                        ['icon' => 'star', 'value' => $speakers->where('is_keynote', true)->count(), 'label' => 'Keynotes'],
                        ['icon' => 'currency', 'value' => $event->money($speakers->sum('fee_cents')), 'label' => 'Fees'],
                    ];
                })(),
                [['label' => 'Speakers', 'tab' => 'speakers', 'icon' => 'sparkles'], ['label' => 'Agenda', 'tab' => 'agenda', 'icon' => 'calendar'], ['label' => 'Contract', 'tab' => 'contract', 'icon' => 'archive']],
                [EventSpeaker::class],
            ],
            'venue' => [
                (function () use ($event) {
                    $rooms = $event->rooms;

                    return [
                        ['icon' => 'building', 'value' => $rooms->count(), 'label' => 'Venues'],
                        ['icon' => 'users', 'value' => number_format($rooms->sum('capacity')), 'label' => 'Capacity'],
                        ['icon' => 'clipboard', 'value' => $rooms->sum('sessions_count'), 'label' => 'On the agenda'],
                        ['icon' => 'currency', 'value' => $event->money($rooms->sum(fn (EventRoom $r) => $r->totalCents())), 'label' => 'Venue cost'],
                    ];
                })(),
                [['label' => 'Venue', 'tab' => 'venue', 'icon' => 'building'], ['label' => 'Suppliers', 'tab' => 'suppliers', 'icon' => 'truck'], ['label' => 'Agenda', 'tab' => 'agenda', 'icon' => 'calendar']],
                [EventRoom::class],
            ],
            'suppliers' => [
                (function () use ($event) {
                    $suppliers = $event->suppliers;
                    $readiness = ['requested' => 10, 'quoted' => 30, 'approved' => 50, 'contracted' => 70, 'in_production' => 80, 'delivered' => 95, 'completed' => 100, 'issue' => 20];
                    $avg = $suppliers->isEmpty() ? null : (int) round($suppliers->avg(fn ($s) => $readiness[$s->pivot->status] ?? 10));
                    $committed = (int) $event->budgetItems->whereNotNull('supplier_id')->sum(fn ($i) => $i->costCents());

                    return [
                        ['icon' => 'truck', 'value' => $suppliers->count(), 'label' => 'Suppliers'],
                        ['icon' => 'chart', 'value' => $avg !== null ? $avg.'%' : '—', 'label' => 'Avg readiness'],
                        ['icon' => 'currency', 'value' => $committed > 0 ? $event->money($committed) : '—', 'label' => 'Committed'],
                        ['icon' => 'flag', 'value' => $suppliers->filter(fn ($s) => $s->pivot->status === 'issue')->count(), 'label' => 'Issues'],
                    ];
                })(),
                [['label' => 'Suppliers', 'tab' => 'suppliers', 'icon' => 'truck'], ['label' => 'Budget', 'tab' => 'budget', 'icon' => 'currency'], ['label' => 'Venue', 'tab' => 'venue', 'icon' => 'building']],
                [],
            ],
            'catering' => [
                (function () use ($event) {
                    $items = $event->cateringItems->reject(fn (EventCateringItem $c) => $c->status === 'cancelled');

                    return [
                        ['icon' => 'cup', 'value' => $items->count(), 'label' => 'Occasions'],
                        ['icon' => 'check', 'value' => $items->where('status', 'confirmed')->count(), 'label' => 'Confirmed'],
                        ['icon' => 'users', 'value' => number_format($items->sum('headcount')), 'label' => 'Covers'],
                        ['icon' => 'currency', 'value' => $event->money($items->sum(fn (EventCateringItem $c) => $c->totalCents())), 'label' => 'Total cost'],
                    ];
                })(),
                [['label' => 'Food & Beverage', 'tab' => 'catering', 'icon' => 'cup'], ['label' => 'Venue', 'tab' => 'venue', 'icon' => 'building'], ['label' => 'Suppliers', 'tab' => 'suppliers', 'icon' => 'truck']],
                [EventCateringItem::class],
            ],
            'exhibition' => [
                (function () use ($event) {
                    $exhibitors = $event->exhibitors;
                    $confirmed = $exhibitors->whereIn('status', ['confirmed', 'paid'])->count();
                    $revenue = $exhibitors->where('status', '!=', 'cancelled')->sum('fee_cents');

                    return [
                        ['icon' => 'grid', 'value' => $exhibitors->count(), 'label' => 'Exhibitors'],
                        ['icon' => 'currency', 'value' => $event->money($revenue), 'label' => 'Revenue'],
                        ['icon' => 'check', 'value' => $event->money($exhibitors->sum('paid_cents')), 'label' => 'Collected'],
                        ['icon' => 'clipboard', 'value' => $confirmed, 'label' => 'Confirmed'],
                    ];
                })(),
                [['label' => 'Exhibition', 'tab' => 'exhibition', 'icon' => 'grid'], ['label' => 'Sponsors', 'tab' => 'sponsors', 'icon' => 'star'], ['label' => 'Suppliers', 'tab' => 'suppliers', 'icon' => 'truck']],
                [EventExhibitor::class],
            ],
            'sponsors' => [
                (function () use ($event) {
                    $sponsors = $event->sponsors;

                    return [
                        ['icon' => 'star', 'value' => $sponsors->count(), 'label' => 'Sponsors'],
                        ['icon' => 'currency', 'value' => $event->money($sponsors->sum('amount_cents')), 'label' => 'Committed'],
                        ['icon' => 'check', 'value' => $event->money($sponsors->sum('paid_cents')), 'label' => 'Received'],
                        ['icon' => 'archive', 'value' => $event->sponsorPackages->count(), 'label' => 'Packages'],
                    ];
                })(),
                [['label' => 'Sponsors', 'tab' => 'sponsors', 'icon' => 'star'], ['label' => 'Contract', 'tab' => 'contract', 'icon' => 'archive']],
                [EventSponsor::class],
            ],
            'attendees' => [
                (function () use ($event) {
                    $all = $event->attendees;
                    $active = $all->where('status', '!=', 'cancelled');
                    $capacity = (int) ($event->expected_participants ?? 0);

                    return [
                        ['icon' => 'users', 'value' => $active->count().($capacity ? ' / '.$capacity : ''), 'label' => 'Registered'],
                        ['icon' => 'check', 'value' => $all->where('status', 'checked_in')->count(), 'label' => 'Checked in'],
                        ['icon' => 'star', 'value' => $active->where('vip', true)->count(), 'label' => 'VIPs'],
                        ['icon' => 'currency', 'value' => $event->money($active->sum('amount_cents')), 'label' => 'Revenue'],
                    ];
                })(),
                [['label' => 'Attendees', 'tab' => 'attendees', 'icon' => 'users'], ['label' => 'Agenda', 'tab' => 'agenda', 'icon' => 'calendar']],
                [EventAttendee::class],
            ],
            'pricing' => [
                (function () use ($event) {
                    $items = $event->invoiceItems;

                    return [
                        ['icon' => 'archive', 'value' => $items->count(), 'label' => 'Items'],
                        ['icon' => 'currency', 'value' => $event->money($items->sum('sell_cents')), 'label' => 'Priced to sell'],
                        ['icon' => 'list', 'value' => $event->money($items->sum('cost_cents')), 'label' => 'Costs us'],
                        ['icon' => 'chart', 'value' => $event->money($items->sum('sell_cents') - $items->sum('cost_cents')), 'label' => 'Margin'],
                    ];
                })(),
                [['label' => 'Invoice items', 'tab' => 'pricing', 'icon' => 'archive'], ['label' => 'Budget', 'tab' => 'budget', 'icon' => 'currency']],
                [EventInvoiceItem::class],
            ],
            'brief' => [
                (function () use ($event) {
                    $brief = EventBrief::forEvent($event);
                    $hidden = count($brief->hidden_sections ?? []);
                    $total = count(EventBrief::SECTIONS);

                    return [
                        ['icon' => 'clipboard', 'value' => ($total - $hidden).'/'.$total, 'label' => 'Sections shown'],
                        ['icon' => 'check', 'value' => ucfirst($brief->status), 'label' => 'Status'],
                        ['icon' => 'archive', 'value' => 'v'.$brief->version, 'label' => 'Version'],
                    ];
                })(),
                [['label' => 'Brief', 'tab' => 'brief', 'icon' => 'clipboard'], ['label' => 'Contract', 'tab' => 'contract', 'icon' => 'identification']],
                [EventBrief::class],
            ],
            'contract' => [
                (function () use ($event) {
                    $contracts = $event->contracts()->withCount([
                        'signatories',
                        'signatories as signed_count' => fn ($q) => $q->whereNotNull('signed_at'),
                    ])->get();

                    return [
                        ['icon' => 'archive', 'value' => $contracts->count(), 'label' => 'Documents'],
                        ['icon' => 'check', 'value' => $contracts->where('status', 'signed')->count(), 'label' => 'Signed'],
                        ['icon' => 'bell', 'value' => (int) $contracts->sum(fn ($c) => max(0, $c->signatories_count - $c->signed_count)), 'label' => 'Pending signatures'],
                    ];
                })(),
                [['label' => 'Contract', 'tab' => 'contract', 'icon' => 'identification'], ['label' => 'Budget', 'tab' => 'budget', 'icon' => 'currency']],
                [EventContract::class],
            ],
            'files' => [
                [
                    ['icon' => 'archive', 'value' => $event->documents()->count(), 'label' => 'Documents'],
                ],
                [['label' => 'Files', 'tab' => 'files', 'icon' => 'archive']],
                [EventDocument::class],
            ],
            default => [null, [['label' => $label, 'tab' => $inspectTab, 'icon' => $icon]], []],
        };

        $recent = collect();
        if ($auditTypes !== []) {
            $recent = AuditLog::where('event_id', $event->id)
                ->whereIn('auditable_type', $auditTypes)
                ->with('user')->latest()->take(3)->get();
        }

        return [
            'tab' => $inspectTab,
            'label' => $label,
            'icon' => $icon,
            'color' => $color,
            'pct' => $pct,
            // What that percentage actually measures. It was labelled
            // "Readiness" on every module, which is a different, event-level
            // figure with its own gates — so the Budget header announced
            // "100% Readiness" directly beneath a pulse strip reading
            // "Readiness 17%". Each module now names its own number.
            'pctLabel' => self::PCT_LABELS[$inspectTab] ?? 'Progress',
            'statusWord' => $statusWord,
            'purpose' => self::PURPOSES[$inspectTab] ?? null,
            'metrics' => $metrics,
            'owner' => $event->projectManager,
            'nextAction' => $nextAction,
            // Never link to the tab you are standing on. Every module's
            // quickLinks list names itself among its neighbours, so the
            // panel was offering "Speakers" as a related module while you
            // were reading the Speakers tab.
            'quickLinks' => collect($quickLinks)->reject(fn ($l) => ($l['tab'] ?? null) === $inspectTab)->values()->all(),
            'recent' => $recent,
            'supportsAdd' => in_array($inspectTab, self::SUPPORTS_ADD_ACTION, true),
            'addLabel' => self::ADD_LABELS[$inspectTab] ?? 'Add',
        ];
    }
}
