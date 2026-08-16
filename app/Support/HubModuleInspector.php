<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventAgendaDay;
use App\Models\EventAgendaSession;
use App\Models\EventApproval;
use App\Models\EventBudgetItem;
use App\Models\EventBudgetVersion;
use App\Models\EventTransport;

/**
 * One per-module view-model, read by both the Universal Module Header
 * (shown inside a module's own tab content) and the Universal Right
 * Inspector (shown in the Event Hub's side panel) — a single place computing
 * "what does this module's readiness/status/metrics/owner/next-action say",
 * so the two surfaces can never quietly disagree about the same module.
 *
 * Every figure here reads an already-loaded Event relation or
 * EventCommandHeader's own numbers (meters()/critical()) — nothing is
 * computed fresh that isn't already computed elsewhere.
 */
class HubModuleInspector
{
    private const PURPOSES = [
        'agenda' => 'Programme planning, sessions, speakers and timeline control.',
        'budget' => 'Costs, forecast, margin and commercial control.',
        'transportation' => 'Movements, vehicles, drivers and VIP transfer readiness.',
        'approvals' => 'Decisions, sign-offs and pending confirmations.',
        'files' => 'Documents, templates, uploads and event references.',
    ];

    /** Modules whose Livewire component honours ?action=add on mount. */
    private const SUPPORTS_ADD_ACTION = ['agenda', 'budget', 'approvals'];

    public static function data(Event $event, array $header, string $tab): array
    {
        $modules = Event::HUB_TABS;
        // Overview has no metrics of its own to inspect — Agenda is the
        // default landing module, same as the reference mockup shows.
        $inspectTab = $tab === 'overview' ? 'agenda' : $tab;
        [$label,, $icon] = $modules[$inspectTab] ?? [ucfirst($inspectTab), '', 'archive'];
        $color = Event::moduleColor($inspectTab);

        $metersByKey = collect($header['meters'] ?? [])->keyBy('key');
        $meterAlias = ['transportation' => 'logistics', 'venue' => 'logistics', 'suppliers' => 'logistics'];
        $pct = $metersByKey->get($meterAlias[$inspectTab] ?? $inspectTab)['pct'] ?? null;

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
                        ['icon' => 'chart', 'value' => $cost['cap'] > 0 ? $cost['pct'].'%' : '—', 'label' => 'Of cap'],
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
            'statusWord' => $statusWord,
            'purpose' => self::PURPOSES[$inspectTab] ?? null,
            'metrics' => $metrics,
            'owner' => $event->projectManager,
            'nextAction' => $nextAction,
            'quickLinks' => $quickLinks,
            'recent' => $recent,
            'supportsAdd' => in_array($inspectTab, self::SUPPORTS_ADD_ACTION, true),
        ];
    }
}
