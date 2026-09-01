<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventBudgetItem;
use App\Services\EventHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Budget health reports what it has measured, and nothing else.
 *
 * budgetScore()'s delivery leg compared actual against estimate and, with no
 * actual recorded, scored 0 <= estimate as a clean 100. EventBudgetItem::
 * varianceCents() already sets the opposite rule for the same question — "an
 * uncosted line has not saved or overspent anything yet" — and the leg broke
 * it.
 *
 * It mattered most where the commitment leg cannot run either: an event with
 * no agreed cap had that vacuous 100 as its ONLY leg, so budget health read
 * perfect for an event nobody had measured at all.
 */
class BudgetHealthMeasurementTest extends TestCase
{
    use RefreshDatabase;

    /** budget_cents is NOT NULL in the schema: no cap is stored as 0. */
    private function event(int $cap): Event
    {
        return Event::factory()->create([
            'stage' => 'confirmed',
            'budget_cents' => $cap,
            'management_fee_pct' => 0,
        ]);
    }

    private function line(Event $event, int $estimated, ?int $actual = null): void
    {
        EventBudgetItem::create([
            'event_id' => $event->id,
            'category' => 'production',
            'description' => 'Stage build',
            'estimated_cents' => $estimated,
            'actual_cents' => $actual,
            'paid_cents' => 0,
        ]);
    }

    private function budgetScore(Event $event): ?int
    {
        return app(EventHealthService::class)
            ->breakdown($event->fresh()->load(EventHealthService::RELATIONS))['components']['budget'];
    }

    public function test_an_uncapped_event_with_nothing_recorded_is_unmeasured_not_perfect(): void
    {
        $event = $this->event(cap: 0);
        $this->line($event, 100_000_00);   // planned, nothing spent against it

        $this->assertNull($this->budgetScore($event),
            'nothing has been measured, so there is no budget health to report');
    }

    public function test_a_capped_event_is_still_scored_on_its_commitment(): void
    {
        $event = $this->event(cap: 200_000_00);
        $this->line($event, 100_000_00);

        $this->assertSame(100, $this->budgetScore($event),
            'the commitment leg can run: the forecast is inside the agreed cap');
    }

    public function test_recording_an_actual_brings_the_delivery_leg_back(): void
    {
        $event = $this->event(cap: 0);
        $this->line($event, 100_000_00, actual: 100_000_00);

        $this->assertSame(100, $this->budgetScore($event),
            'spend landed on the estimate, and now there is something to judge');
    }

    public function test_an_overspend_against_the_estimate_still_scores_it_down(): void
    {
        $event = $this->event(cap: 0);
        $this->line($event, 100_000_00, actual: 130_000_00);

        $this->assertSame(40, $this->budgetScore($event), '100 - round(30000/100000 * 200)');
    }
}
