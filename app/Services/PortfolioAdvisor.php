<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The briefing: what needs a person today, across the whole book.
 *
 * Rule-based, and it says so on the page. Every line names the record it came
 * from and links to it, because an assistant that tells you something is wrong
 * without telling you where is worse than one that says nothing — you end up
 * checking all twenty tabs anyway.
 *
 * The rules are the ones a producer would apply reading the book on a Monday,
 * in the order they would apply them: what is on fire, what is blocked, what
 * is late, what is missing.
 */
class PortfolioAdvisor
{
    /** Relations the rules read. */
    public const RELATIONS = [
        'risks.owner', 'approvals.requester', 'tasks.assignee', 'suppliers',
        'transport', 'transferGuests', 'agendaSessions', 'speakers', 'budgetItems', 'venue',
    ];

    /**
     * Everything worth saying, worst first.
     *
     * @return Collection<int,array{severity:string,title:string,where:string,why:string,href:string,tone:string}>
     */
    public function attention(Collection $events): Collection
    {
        $out = collect();
        $today = Carbon::today();

        foreach ($events as $event) {
            $hub = fn (string $tab) => route('events.hub', [$event, 'tab' => $tab]);

            // ── on fire ──
            foreach ($event->risks->filter(fn ($r) => $r->isOpen() && $r->severity() >= 15) as $risk) {
                $out->push([
                    'severity' => 'critical', 'tone' => 'red',
                    'title' => $risk->title,
                    'where' => $event->name,
                    'why' => 'Open risk scoring '.$risk->severity().'/25'.($risk->owner ? ' · '.$risk->owner->name : ' · unowned'),
                    'href' => $hub('risks'),
                ]);
            }

            // ── blocked: somebody else cannot move until this is decided ──
            foreach ($event->approvals->where('status', 'pending') as $approval) {
                $waiting = (int) $approval->created_at->diffInDays($today);
                $out->push([
                    'severity' => $waiting >= 7 ? 'critical' : 'warning', 'tone' => $waiting >= 7 ? 'red' : 'amber',
                    'title' => $approval->title,
                    'where' => $event->name,
                    'why' => str($approval->type)->replace('_', ' ')->title().' approval waiting '.$waiting.' '.str('day')->plural($waiting),
                    'href' => $hub('approvals'),
                ]);
            }

            // ── late ──
            $overdue = $event->tasks->filter(fn (Task $t) => $t->isOpen() && $t->due_on?->isPast());
            if ($overdue->isNotEmpty()) {
                $worst = $overdue->sortBy('due_on')->first();
                $out->push([
                    'severity' => 'warning', 'tone' => 'amber',
                    'title' => $overdue->count().' overdue '.str('task')->plural($overdue->count()),
                    'where' => $event->name,
                    'why' => 'Oldest: “'.str($worst->title)->limit(40).'”, due '.$worst->due_on->format('j M'),
                    'href' => $hub('tasks'),
                ]);
            }

            // ── suppliers in trouble ──
            $issues = $event->suppliers->filter(fn ($s) => $s->pivot->status === 'issue');
            foreach ($issues as $supplier) {
                $out->push([
                    'severity' => 'warning', 'tone' => 'amber',
                    'title' => 'Supplier issue: '.$supplier->name,
                    'where' => $event->name,
                    'why' => 'Flagged on the supplier board',
                    'href' => $hub('suppliers'),
                ]);
            }

            // ── transport that will not roll ──
            $unready = $event->transport
                ->reject(fn ($m) => in_array($m->status, ['completed', 'cancelled'], true))
                ->reject->isReady();
            if ($unready->isNotEmpty()) {
                $out->push([
                    'severity' => 'warning', 'tone' => 'amber',
                    'title' => $unready->count().' '.str('movement')->plural($unready->count()).' without a driver or vehicle',
                    'where' => $event->name,
                    'why' => 'They cannot run as booked',
                    'href' => $hub('transportation'),
                ]);
            }

            // ── missing, rather than wrong ──
            $days = (int) $today->diffInDays($event->starts_at ?? $today, false);

            if ($event->agendaSessions->isEmpty() && $days >= 0 && $days <= 60 && EventHealthService::isScored($event)) {
                $out->push([
                    'severity' => 'info', 'tone' => 'navy',
                    'title' => 'No agenda yet',
                    'where' => $event->name,
                    'why' => $days.' days out and nothing is scheduled',
                    'href' => $hub('agenda'),
                ]);
            }

            if (! $event->venue_id && EventHealthService::isScored($event)) {
                $out->push([
                    'severity' => 'info', 'tone' => 'navy',
                    'title' => 'No venue assigned',
                    'where' => $event->name,
                    'why' => 'Every other module hangs off the venue',
                    'href' => $hub('venue'),
                ]);
            }

            $waitingSpeakers = $event->speakers->where('status', '!=', 'confirmed')->count();
            if ($waitingSpeakers > 0 && $days >= 0 && $days <= 30) {
                $out->push([
                    'severity' => 'warning', 'tone' => 'amber',
                    'title' => $waitingSpeakers.' '.str('speaker')->plural($waitingSpeakers).' unconfirmed',
                    'where' => $event->name,
                    'why' => $days.' days out — the programme cannot be printed',
                    'href' => $hub('speakers'),
                ]);
            }

            $pool = $event->transferGuests->whereNull('transport_id')->count();
            if ($pool > 0) {
                $out->push([
                    'severity' => 'info', 'tone' => 'navy',
                    'title' => $pool.' '.str('guest')->plural($pool).' with no transfer',
                    'where' => $event->name,
                    'why' => 'Still sitting in the transport pool',
                    'href' => $hub('transportation'),
                ]);
            }
        }

        $rank = ['critical' => 0, 'warning' => 1, 'info' => 2];

        return $out->sortBy(fn (array $row) => $rank[$row['severity']])->values();
    }

    /**
     * One sentence for the top of the page. It reports; it does not reassure.
     */
    public function headline(Collection $events, Collection $attention): string
    {
        $critical = $attention->where('severity', 'critical')->count();
        $live = $events->filter(fn (Event $e) => $e->starts_at
            && $e->starts_at->copy()->startOfDay()->lte(Carbon::today())
            && ($e->ends_at ?? $e->starts_at)->copy()->endOfDay()->gte(Carbon::today()))->count();

        $book = $events->count().' '.str('event')->plural($events->count());

        return match (true) {
            $critical > 0 => $critical.' '.str('thing')->plural($critical).' need you today, across '.$book.'.',
            $attention->isNotEmpty() => 'Nothing critical. '.$attention->count().' '.str('item')->plural($attention->count()).' worth a look across '.$book.'.',
            $live > 0 => $live.' '.str('event')->plural($live).' running and nothing flagged. Watch the room.',
            default => 'Nothing is flagged across '.$book.'. Good day to get ahead.',
        };
    }
}
