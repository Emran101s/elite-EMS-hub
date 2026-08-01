<?php

namespace App\Services;

use App\Models\Event;

/**
 * Explainable Event Health Score.
 *
 * Relative weights (per product spec):
 *   Task completion 30 · Budget health 20 · Supplier readiness 15 ·
 *   Venue readiness 15 · Transport readiness 15 · Agenda completion 10 · Risk level 10
 *
 * Components without data yet return null and their weight is redistributed,
 * so a freshly created event is not punished for empty tabs.
 * Bands: 81–100 on_track · 61–80 in_progress · 41–60 at_risk · 0–40 behind.
 * Override: any open risk with severity ≥ 20/25 caps the event at at_risk.
 *
 * Draft and proposal events are NOT scored at all: score is null and the status
 * is not_started. Nothing is committed at those stages, so there is no schedule
 * to be behind on — scoring them made an untouched proposal read exactly like a
 * collapsing project, and three portfolio panels inherited that lie.
 */
class EventHealthService
{
    /**
     * Stages before the work is committed. An event here has no health, and
     * portfolio rollups must count it separately rather than as "at risk".
     */
    public const UNSCORED_STAGES = ['draft', 'proposal'];

    public static function isScored(Event $event): bool
    {
        return ! in_array($event->stage, self::UNSCORED_STAGES, true);
    }

    /**
     * The relations breakdown() reads. Eager-load these before scoring a set of
     * events, or each score silently costs one query per relation per event.
     */
    public const RELATIONS = ['tasks', 'risks', 'approvals', 'budgetItems', 'agendaSessions', 'rooms', 'suppliers', 'venue', 'transport.manifest', 'transferGuests'];

    private const WEIGHTS = [
        'tasks' => 30,
        'budget' => 20,
        'suppliers' => 15,
        'venue' => 15,
        'transport' => 15,
        'agenda' => 10,
        'risk' => 10,
    ];

    private const SUPPLIER_READINESS = [
        'requested' => 10, 'quoted' => 30, 'approved' => 50, 'contracted' => 70,
        'in_production' => 80, 'delivered' => 95, 'completed' => 100, 'issue' => 20,
    ];

    public function breakdown(Event $event): array
    {
        if (! self::isScored($event)) {
            return [
                'score' => null,
                'status' => 'not_started',
                'group' => 'neutral',
                // The meters still render, so the tabs read as "no data yet"
                // rather than vanishing the moment a stage changes.
                'components' => array_fill_keys(array_keys(self::WEIGHTS), null),
                'weights' => self::WEIGHTS,
                'critical_risk' => null,
                'pending_approvals' => $event->approvals->where('status', 'pending')->count(),
            ];
        }

        $components = [
            'tasks' => $this->taskScore($event),
            'budget' => $this->budgetScore($event),
            'suppliers' => $this->supplierScore($event),
            'venue' => $this->venueScore($event),
            'transport' => $this->transportScore($event),
            'agenda' => $this->agendaScore($event),
            'risk' => $this->riskScore($event),
        ];

        $weightSum = 0;
        $weighted = 0;
        foreach ($components as $key => $score) {
            if ($score !== null) {
                $weighted += $score * self::WEIGHTS[$key];
                $weightSum += self::WEIGHTS[$key];
            }
        }

        // No data anywhere → fall back to the manually tracked progress.
        $score = $weightSum > 0 ? (int) round($weighted / $weightSum) : $event->progress;

        $criticalRisk = $event->risks->first(fn ($risk) => $risk->isOpen() && $risk->severity() >= 20);
        if ($criticalRisk && $score > 60) {
            $score = 60; // hard cap: critical open risk ⇒ at best "at risk"
        }

        return [
            'score' => $score,
            'status' => $this->statusFor($score),
            'group' => $this->groupFor($score),
            'components' => $components,
            'weights' => self::WEIGHTS,
            'critical_risk' => $criticalRisk?->title,
            'pending_approvals' => $event->approvals->where('status', 'pending')->count(),
        ];
    }

    /**
     * Rule-based event advisor (v1) — the AI Insights daily summary.
     * Every point names its source record; swaps to an LLM backend later.
     */
    public function aiSummary(Event $event): array
    {
        $health = $this->breakdown($event);
        $attention = [];

        foreach ($event->approvals->where('status', 'pending') as $approval) {
            $attention[] = ucfirst($approval->type)." approval pending — “{$approval->title}”";
        }

        foreach ($event->risks->filter(fn ($risk) => $risk->isOpen())->sortByDesc(fn ($risk) => $risk->severity())->take(3) as $risk) {
            $attention[] = "Risk ({$risk->severity()}/25): {$risk->title}";
        }

        foreach ($event->suppliers->filter(fn ($supplier) => $supplier->pivot->status === 'issue') as $supplier) {
            $attention[] = "Supplier issue: {$supplier->name}";
        }

        foreach ($event->transport->where('status', 'issue') as $movement) {
            $attention[] = 'Transport issue: '.$movement->refLabel().' — '.($movement->issue_note ?: ($movement->route ?: 'unresolved'));
        }

        $unreadyVip = $event->transport->first(fn ($m) => $m->is_vip
            && ! in_array($m->status, ['completed', 'cancelled'], true) && ! $m->isReady());
        if ($unreadyVip) {
            $attention[] = 'VIP transfer not ready: '.$unreadyVip->refLabel().' is missing '.implode(' + ', $unreadyVip->readiness()['missing']).'.';
        }

        $pool = $event->transferGuests->whereNull('transport_id')->count();
        if ($pool > 0) {
            $attention[] = $pool.' transfer '.str('guest')->plural($pool).' still unassigned in the transport pool.';
        }

        $overdue = $event->tasks->filter(fn ($task) => $task->isOpen() && $task->due_on?->isPast());
        if ($overdue->isNotEmpty()) {
            $attention[] = $overdue->count().' overdue '.str('task')->plural($overdue->count()).', next: “'.$overdue->sortBy('due_on')->first()->title.'”';
        }

        if ($event->agendaSessions->isEmpty()) {
            $attention[] = 'Agenda is empty — no sessions scheduled yet.';
        }

        $statusLabel = str($health['status'])->replace('_', ' ')->title();

        return [
            'headline' => $health['score'] === null
                ? "{$event->name} is not scored yet — it is still at {$event->stage} stage."
                : "{$event->name} is {$health['score']}% — {$statusLabel}.",
            'attention' => array_slice($attention, 0, 5),
            'recommendation' => $attention[0] ?? 'No blockers detected — keep executing the plan.',
            'health' => $health,
        ];
    }

    private function taskScore(Event $event): ?int
    {
        $total = $event->tasks->count();

        return $total === 0 ? null
            : (int) round($event->tasks->where('status', 'done')->count() / $total * 100);
    }

    /**
     * Budget health, against both things a budget can go wrong against.
     *
     * This used to compare actual with estimate only — are our costs coming in
     * where we said. That is a real question, but it left an event forecast at
     * 153% of what the client agreed scoring a clean 100, because nothing had
     * been invoiced yet and so nothing had "overrun". A budget that is already
     * committed past its cap is not healthy; it is the single most useful thing
     * the score can tell you, and it was the one thing it could not.
     *
     * Both are measured and the worse one wins, so neither hides the other.
     */
    private function budgetScore(Event $event): ?int
    {
        $estimated = (int) $event->budgetItems->sum('estimated_cents');
        $cost = $event->costForecast();

        // A cap with nothing planned against it yet is not a healthy budget or
        // an unhealthy one — it is a budget nobody has written.
        if ($estimated === 0 && $cost['forecast'] === 0) {
            return null;
        }

        $scores = [];

        // Delivery: is what we are spending landing where we said it would?
        if ($estimated > 0) {
            $actual = (int) $event->budgetItems->sum('actual_cents');
            $scores[] = $actual <= $estimated
                ? 100
                : max(0, 100 - (int) round(($actual - $estimated) / $estimated * 200));
        }

        // Commitment: is what we have promised still inside what was agreed?
        if ($cost['cap'] > 0 && $cost['forecast'] > 0) {
            $scores[] = $cost['forecast'] <= $cost['cap']
                ? 100
                : max(0, 100 - (int) round($cost['over'] / $cost['cap'] * 200));
        }

        return $scores === [] ? null : min($scores);
    }

    private function supplierScore(Event $event): ?int
    {
        if ($event->suppliers->isEmpty()) {
            return null;
        }

        return (int) round($event->suppliers->avg(
            fn ($supplier) => self::SUPPLIER_READINESS[$supplier->pivot->status] ?? 10
        ));
    }

    /**
     * Transport readiness: are the movements staffed and the guests on vehicles?
     * Staffing weighs 70% (a completed run = 100, an open issue = 20, otherwise
     * the movement's own readiness thirds: vehicle/driver/passengers); guest
     * coverage — the share of transfer guests out of the pool — weighs 30%.
     */
    private function transportScore(Event $event): ?int
    {
        $movements = $event->transport->where('status', '!=', 'cancelled');
        $guests = $event->transferGuests;

        if ($movements->isEmpty() && $guests->isEmpty()) {
            return null;
        }

        $staffing = $movements->isEmpty() ? null : (int) round($movements->avg(fn ($m) => match ($m->status) {
            'completed' => 100,
            'issue' => 20,
            default => (int) round($m->readiness()['score'] / 3 * 100),
        }));

        $coverage = $guests->isEmpty() ? null
            : (int) round($guests->whereNotNull('transport_id')->count() / $guests->count() * 100);

        return match (true) {
            $staffing !== null && $coverage !== null => (int) round($staffing * 0.7 + $coverage * 0.3),
            default => $staffing ?? $coverage,
        };
    }

    private function venueScore(Event $event): ?int
    {
        if (! $event->venue_id && $event->rooms->isEmpty()) {
            return null;
        }

        return ($event->venue_id ? 60 : 0) + ($event->rooms->isNotEmpty() ? 40 : 0);
    }

    private function agendaScore(Event $event): ?int
    {
        $total = $event->agendaSessions->count();

        return $total === 0 ? null
            : (int) round($event->agendaSessions->whereIn('status', ['confirmed', 'final'])->count() / $total * 100);
    }

    private function riskScore(Event $event): ?int
    {
        $open = $event->risks->filter(fn ($risk) => $risk->isOpen());
        if ($event->risks->isEmpty()) {
            return null;
        }
        if ($open->isEmpty()) {
            return 100;
        }

        return max(0, 100 - (int) round($open->avg(fn ($risk) => $risk->severity()) / 25 * 100));
    }

    private function statusFor(int $score): string
    {
        return match (true) {
            $score >= 81 => 'on_track',
            $score >= 61 => 'in_progress',
            $score >= 41 => 'at_risk',
            default => 'behind',
        };
    }

    private function groupFor(int $score): string
    {
        return match (true) {
            $score >= 81 => 'track',
            $score >= 61 => 'warn',
            default => 'risk',
        };
    }
}
