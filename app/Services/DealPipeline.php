<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

/**
 * Moving a deal through the pipeline.
 *
 * The only interesting transition is winning one: that is where a deal stops
 * being a conversation and becomes an Event, carrying its client, dates and
 * value across. Everything the sales side knew is handed to the delivery side
 * rather than retyped.
 */
class DealPipeline
{
    /**
     * Win the deal and open the event it becomes.
     *
     * Idempotent: winning an already-won deal returns the event it already
     * created rather than making a second one.
     */
    public function win(Deal $deal): Event
    {
        if ($deal->event) {
            return $deal->event;
        }

        return DB::transaction(function () use ($deal) {
            $company = CompanyProfile::current();

            $event = Event::create([
                'name' => $deal->title,
                'type' => $deal->type ?: 'conference',
                'stage' => 'confirmed',            // won work is committed work
                'client_id' => $deal->client_id,
                'city' => $company->city ?: 'TBD',
                'country' => $company->country ?: 'Jordan',
                'currency' => $deal->currency ?: ($company->default_currency ?: 'JOD'),
                'management_fee_pct' => $company->default_management_fee_pct ?? 15.0,
                'timezone' => $company->default_timezone ?: config('app.timezone'),
                'budget_cents' => $deal->value_cents,
                'starts_at' => $deal->expected_event_on,
                'progress' => 0,
                'primary_color' => '#0B1F3A',
                'secondary_color' => '#F8FAFC',
                'accent_color' => '#D4AF37',
                'text_color' => '#0F172A',
                'project_manager_id' => $deal->owner_id,
                'enabled_modules' => array_keys(Event::HUB_MODULES),
            ]);

            if ($event->starts_at) {
                $event->syncAgendaDays();
            }

            $deal->update([
                'stage' => 'won',
                'probability' => 100,
                'event_id' => $event->id,
                'won_at' => now(),
                'lost_at' => null,
                'lost_reason' => null,
            ]);

            // The win is itself a thing that happened, so it belongs in the log.
            DealActivity::create([
                'deal_id' => $deal->id,
                'client_id' => $deal->client_id,
                'contact_id' => $deal->contact_id,
                'user_id' => auth()->id(),
                'type' => 'note',
                'subject' => 'Deal won — event created',
                'body' => "“{$deal->title}” was won and opened as an event.",
                'happened_at' => now(),
            ]);

            return $event;
        });
    }

    public function lose(Deal $deal, ?string $reason = null): void
    {
        $deal->update([
            'stage' => 'lost',
            'probability' => 0,
            'lost_at' => now(),
            'lost_reason' => $reason,
            'won_at' => null,
        ]);

        DealActivity::create([
            'deal_id' => $deal->id,
            'client_id' => $deal->client_id,
            'contact_id' => $deal->contact_id,
            'user_id' => auth()->id(),
            'type' => 'note',
            'subject' => 'Deal lost',
            'body' => $reason ?: 'No reason recorded.',
            'happened_at' => now(),
        ]);
    }

    /**
     * Move a deal to any other stage. Winning and losing route through the
     * methods above so their side effects can never be skipped.
     */
    public function moveTo(Deal $deal, string $stage): void
    {
        if ($stage === 'won') {
            $this->win($deal);

            return;
        }

        if ($stage === 'lost') {
            $this->lose($deal);

            return;
        }

        $deal->update([
            'stage' => $stage,
            // The stage's default probability, unless someone has set their own.
            'probability' => Deal::STAGES[$stage][1] ?? $deal->probability,
            'won_at' => null,
            'lost_at' => null,
            'lost_reason' => null,
        ]);
    }

    /** Open pipeline, weighted and raw — what a forecast is made of. */
    public function forecast(): array
    {
        $open = Deal::open()->get();

        return [
            'count' => $open->count(),
            'value' => (int) $open->sum('value_cents'),
            'weighted' => (int) $open->sum(fn (Deal $d) => $d->weightedCents()),
            'stale' => $open->filter(fn (Deal $d) => $d->isStale())->count(),
            // whereMonth alone matches the month in ANY year, so a deal won
            // last August counted towards "won this month".
            'wonThisMonth' => Deal::where('stage', 'won')
                ->whereMonth('won_at', now()->month)
                ->whereYear('won_at', now()->year)
                ->count(),
        ];
    }
}
