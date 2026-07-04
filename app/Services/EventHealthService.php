<?php

namespace App\Services;

use App\Models\Event;

/**
 * Explainable Event Health Score.
 *
 * Weights (per product spec):
 *   Task completion 30% · Budget health 20% · Supplier readiness 15% ·
 *   Venue readiness 15% · Agenda completion 10% · Risk level 10%
 *
 * Components without data yet return null and their weight is redistributed,
 * so a freshly created event is not punished for empty tabs.
 * Bands: 81–100 on_track · 61–80 in_progress · 41–60 at_risk · 0–40 behind.
 * Override: any open risk with severity ≥ 20/25 caps the event at at_risk.
 */
class EventHealthService
{
    private const WEIGHTS = [
        'tasks' => 30,
        'budget' => 20,
        'suppliers' => 15,
        'venue' => 15,
        'agenda' => 10,
        'risk' => 10,
    ];

    private const SUPPLIER_READINESS = [
        'requested' => 10, 'quoted' => 30, 'approved' => 50, 'contracted' => 70,
        'in_production' => 80, 'delivered' => 95, 'completed' => 100, 'issue' => 20,
    ];

    public function breakdown(Event $event): array
    {
        $components = [
            'tasks' => $this->taskScore($event),
            'budget' => $this->budgetScore($event),
            'suppliers' => $this->supplierScore($event),
            'venue' => $this->venueScore($event),
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

        $overdue = $event->tasks->filter(fn ($task) => $task->status !== 'completed' && $task->due_on?->isPast());
        if ($overdue->isNotEmpty()) {
            $attention[] = $overdue->count().' overdue '.str('task')->plural($overdue->count()).', next: “'.$overdue->sortBy('due_on')->first()->title.'”';
        }

        if ($event->agendaSessions->isEmpty()) {
            $attention[] = 'Agenda is empty — no sessions scheduled yet.';
        }

        $statusLabel = str($health['status'])->replace('_', ' ')->title();

        return [
            'headline' => "{$event->name} is {$health['score']}% — {$statusLabel}.",
            'attention' => array_slice($attention, 0, 5),
            'recommendation' => $attention[0] ?? 'No blockers detected — keep executing the plan.',
            'health' => $health,
        ];
    }

    private function taskScore(Event $event): ?int
    {
        $total = $event->tasks->count();

        return $total === 0 ? null
            : (int) round($event->tasks->where('status', 'completed')->count() / $total * 100);
    }

    private function budgetScore(Event $event): ?int
    {
        $estimated = $event->budgetItems->sum('estimated_cents');
        if ($estimated === 0) {
            return null;
        }

        $actual = $event->budgetItems->sum('actual_cents');

        // On/under estimate = 100; every % of overrun burns a point (2x).
        return $actual <= $estimated ? 100
            : max(0, 100 - (int) round(($actual - $estimated) / $estimated * 200));
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
