<?php

use App\Models\Deal;
use App\Models\Event;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Proposal-stage events are deals that were filed in the wrong module.
 *
 * They pre-date the CRM: with nowhere else to record an opportunity, work you
 * were merely bidding for was created as an event. That put unwon work on the
 * delivery board and, until health became stage-aware, reported it as "at risk".
 *
 * Each one becomes a Proposal-stage deal keeping its client, dates and value.
 * The event is ARCHIVED rather than deleted, so nothing is lost and this is
 * reversible — down() restores them and removes the deals it made.
 */
return new class extends Migration
{
    public function up(): void
    {
        $events = Event::whereNull('archived_at')
            ->whereIn('stage', ['draft', 'proposal'])
            ->whereNotNull('client_id')
            ->get();

        foreach ($events as $event) {
            $deal = Deal::create([
                'client_id' => $event->client_id,
                'owner_id' => $event->project_manager_id,
                'title' => $event->name,
                'stage' => 'proposal',
                'type' => $event->type,
                'value_cents' => (int) $event->budget_cents,
                'currency' => $event->currency ?: 'JOD',
                'probability' => Deal::STAGES['proposal'][1],
                'expected_event_on' => $event->starts_at?->toDateString(),
                // No decision date was ever recorded; the event date is the only
                // honest anchor, so aim to close a month before it would run.
                'expected_close_on' => $event->starts_at?->copy()->subMonth()->toDateString(),
                'source' => 'Migrated from events',
                'notes' => 'Created as an event before the pipeline existed.',
            ]);

            DB::table('deal_activities')->insert([
                'deal_id' => $deal->id,
                'client_id' => $deal->client_id,
                'type' => 'note',
                'subject' => 'Moved into the pipeline',
                'body' => "Was event #{$event->id}, filed as a proposal-stage event before the CRM existed. "
                    .'The event record is archived, not deleted.',
                'happened_at' => now(),
                'follow_up_done' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $event->update(['archived_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (Deal::where('source', 'Migrated from events')->get() as $deal) {
            Event::withoutGlobalScopes()
                ->whereNotNull('archived_at')
                ->where('name', $deal->title)
                ->where('client_id', $deal->client_id)
                ->update(['archived_at' => null]);

            $deal->activities()->delete();
            $deal->delete();
        }
    }
};
