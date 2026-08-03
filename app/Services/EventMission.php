<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One event, described once, for all three views of the portfolio.
 *
 * The Deck, the List and the Flight Path are three ways of looking at the same
 * thing, so they must not each work out what an event's status is — three
 * answers to one question is how a card ends up green while the row under it
 * is amber. Everything any of them shows comes from here.
 *
 * Status is a vocabulary of five, and it is derived rather than stored: an
 * event whose dates contain today IS in progress, whatever the stage field was
 * last set to by a human.
 */
class EventMission
{
    /** The five states, their colours, and the order they run in. */
    public const STATUSES = [
        'draft' => ['Draft', 'slate', '#94A3B8'],
        'planning' => ['Planning', 'orange', '#F97316'],
        'upcoming' => ['Upcoming', 'indigo', '#6366F1'],
        'progress' => ['In Progress', 'blue', '#3B82F6'],
        'completed' => ['Completed', 'green', '#10B981'],
    ];

    /**
     * Flight Path lanes. An event's type decides which lane it flies in;
     * anything unmapped lands in Internal Meetings rather than vanishing.
     */
    public const LANES = [
        'conferences' => ['Conferences', 'chat', ['conference', 'hybrid_event', 'online_event']],
        'summits' => ['Summits', 'globe', ['summit', 'public_event']],
        'exhibitions' => ['Exhibitions', 'grid', ['exhibition', 'career_fair', 'product_launch']],
        'workshops' => ['Workshops', 'clipboard', ['workshop', 'training_program']],
        'galas' => ['Gala Dinners', 'star', ['gala_dinner', 'awards_ceremony']],
        'vip' => ['VIP Events', 'identification', ['vip_reception', 'embassy_event', 'private_dinner']],
        'internal' => ['Internal Meetings', 'users', []],
    ];

    /** Relations every field below reads. Eager-load these or pay per event. */
    public const RELATIONS = [
        'client', 'venue', 'rooms', 'teamMembers', 'attendees', 'speakers', 'sponsors',
        'tasks.assignee', 'budgetItems', 'risks.owner', 'approvals.requester',
        'agendaDays', 'agendaSessions', 'suppliers', 'transport', 'brief',
    ];

    public function __construct(
        private EventHealthService $health,
        private EventJourney $journey,
    ) {}

    /**
     * @return Collection<int,array>
     */
    public function all(Collection $events): Collection
    {
        return $events->map(fn (Event $event) => $this->for($event))->values();
    }

    public function for(Event $event): array
    {
        $today = Carbon::today();
        $h = $this->health->breakdown($event);

        $start = $event->starts_at?->copy()->startOfDay();
        $end = ($event->ends_at ?? $event->starts_at)?->copy()->endOfDay();

        $live = $start && $end && $start->lte($today) && $end->gte($today);
        $done = $end && $end->lt($today);

        $status = match (true) {
            $event->stage === 'draft' => 'draft',
            in_array($event->stage, ['completed', 'closed'], true), $done => 'completed',
            $live, $event->stage === 'live' => 'progress',
            in_array($event->stage, ['proposal', 'planning'], true) => 'planning',
            default => 'upcoming',
        };

        [$statusLabel, $statusTone, $statusHex] = self::STATUSES[$status];

        $tasks = $event->tasks->where('status', '!=', 'cancelled');
        $tasksDone = $tasks->whereIn('status', ['done', 'approved'])->count();

        // See EventCommandHeader::meters() — the column is estimated_cents.
        $budget = (int) ($event->budget_cents ?: $event->budgetItems->sum('estimated_cents'));
        $spent = (int) $event->budgetItems->sum('actual_cents');

        $openRisks = $event->risks->filter->isOpen();
        $severity = $openRisks->max(fn ($r) => $r->severity()) ?? 0;

        $days = $start ? (int) $today->diffInDays($start, false) : null;

        return [
            'event' => $event,
            'id' => $event->id,

            // ── identity ──
            'name' => $event->name,
            'description' => str($event->description ?: $event->brief?->summary ?: '')->limit(150)->toString(),
            'where' => collect([$event->venue?->name, $event->city, $event->country])->filter()->take(2)->implode(', ') ?: 'Venue TBC',
            'client' => $event->client?->name,
            'cover' => $event->coverUrl(),
            'day' => $start?->format('d'),
            'month' => $start?->format('M'),
            'year' => $start?->format('Y'),
            'dates' => $start
                ? $start->format('j M').($end && ! $end->isSameDay($start) ? ' – '.$end->format('j, Y') : ', '.$start->format('Y'))
                : 'Dates to be set',
            'shortDates' => $start ? $start->format('j M').($end && ! $end->isSameDay($start) ? ' – '.$end->format('j') : '') : 'TBC',
            'duration' => $start && $end
                ? ($d = (int) round($start->diffInDays($end)) + 1).' '.str('day')->plural($d)
                : '—',

            // ── status, one vocabulary of five ──
            'status' => $status,
            'statusLabel' => $statusLabel,
            'statusTone' => $statusTone,
            'statusHex' => $statusHex,
            'live' => $live,
            'past' => $done,

            // ── the numbers every view shows ──
            'progress' => max(0, min(100, (int) ($h['score'] ?? $event->progress))),
            'attendees' => $event->attendees->count() ?: (int) $event->expected_participants,
            'attendeeWord' => $event->attendees->count() ? 'Confirmed' : 'Expected',

            'budget' => $budget,
            'spent' => $spent,
            'budgetPct' => $budget > 0 ? min(100, (int) round($spent / $budget * 100)) : null,
            'budgetLabel' => self::money($spent, $event->currency),
            'budgetOf' => $budget > 0 ? 'of '.self::money($budget, $event->currency) : 'No budget set',

            'tasksDone' => $tasksDone,
            'tasksTotal' => $tasks->count(),
            'tasksPct' => $tasks->count() ? (int) round($tasksDone / $tasks->count() * 100) : 0,

            'team' => $event->teamMembers->take(3),
            'teamMore' => max(0, $event->teamMembers->count() - 3),
            'teamCount' => $event->teamMembers->count(),

            'risk' => match (true) {
                $severity >= 20 => 'Critical',
                $severity >= 12 => 'High',
                $severity > 0 => 'Medium',
                default => 'Low',
            },
            'riskHex' => match (true) {
                $severity >= 20 => '#EF4444', $severity >= 12 => '#F97316',
                $severity > 0 => '#F59E0B', default => '#10B981',
            },
            'riskCount' => $openRisks->count(),

            'health' => $h['score'] === null ? 'Not scored'
                : match ($h['group']) { 'risk' => 'At risk', 'warn' => 'Medium', default => 'Excellent' },
            'healthGroup' => $h['group'],

            'timeline' => match (true) {
                $live => 'Day '.((int) round($start->diffInDays($today)) + 1).' of the run',
                $done => 'Delivered',
                $days === null => 'Unscheduled',
                default => $days.' '.str('day')->plural($days).' out',
            },
            'daysOut' => $days,

            // ── what happens next, and what the rules make of it ──
            'milestone' => $this->milestone($event),
            'insight' => $this->insight($event, $tasks, $openRisks->count()),

            // ── where it flies ──
            'lane' => $this->laneFor($event),
        ];
    }

    /** The next dated thing on the event. */
    private function milestone(Event $event): array
    {
        $next = $event->tasks
            ->filter(fn (Task $t) => $t->isOpen() && $t->due_on)
            ->sortBy('due_on')->first();

        if (! $next) {
            return ['title' => 'Nothing scheduled', 'due' => '—', 'overdue' => false, 'tab' => 'tasks'];
        }

        $overdue = $next->due_on->isPast();

        return [
            'title' => $next->title,
            'due' => $overdue
                ? (int) $next->due_on->diffInDays(Carbon::today()).'d overdue'
                : 'Due '.$next->due_on->format('j M Y'),
            'overdue' => $overdue,
            'owner' => $next->assignee,
            'tab' => 'tasks',
        ];
    }

    /**
     * The one sentence a producer would say reading this event's numbers.
     * Rules, from the records — the same policy the briefing runs on.
     */
    private function insight(Event $event, Collection $tasks, int $risks): string
    {
        $overdue = $tasks->filter(fn (Task $t) => $t->isOpen() && $t->due_on?->isPast())->count();
        $pending = $event->approvals->where('status', 'pending')->count();
        $unconfirmed = $event->speakers->where('status', '!=', 'confirmed')->count();
        $unassigned = $tasks->filter(fn (Task $t) => $t->isOpen() && ! $t->assignee_id)->count();

        return match (true) {
            $risks > 0 => $risks.' open '.str('risk')->plural($risks).' on the register. Clear the severe one before it sets the date.',
            $overdue > 0 => $overdue.' '.str('task')->plural($overdue).' past their date. Consider reallocating resources.',
            $pending > 0 => $pending.' '.str('approval')->plural($pending).' waiting on a decision — somebody is blocked until they land.',
            $unconfirmed > 0 => $unconfirmed.' '.str('speaker')->plural($unconfirmed).' unconfirmed. The programme cannot be printed until they reply.',
            $unassigned > 0 => $unassigned.' open '.str('task')->plural($unassigned).' with nobody on them.',
            $event->agendaSessions->isEmpty() => 'No sessions scheduled yet — the agenda is the spine everything else hangs off.',
            default => 'Nothing flagged. Keep executing the plan.',
        };
    }

    private function laneFor(Event $event): string
    {
        foreach (self::LANES as $key => [$label, $icon, $types]) {
            if (in_array($event->type, $types, true)) {
                return $key;
            }
        }

        return 'internal';
    }

    /** Short money for cards, where 350,000.00 does not fit and never will. */
    public static function money(int $cents, ?string $currency): string
    {
        $symbol = Event::CURRENCIES[$currency ?? 'JOD'][0] ?? '';
        $value = $cents / 100;

        return $symbol.match (true) {
            abs($value) >= 1_000_000 => round($value / 1_000_000, 1).'M',
            abs($value) >= 1_000 => round($value / 1_000).'K',
            default => number_format($value),
        };
    }
}
