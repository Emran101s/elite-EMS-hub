<?php

namespace Tests\Feature;

use App\Livewire\CrmPipeline;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\Event;
use App\Models\User;
use App\Services\DealPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The pipeline, and the transition that justifies it: a won deal becomes an
 * event, carrying what the sales side knew rather than making someone retype it.
 */
class CrmPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function deal(array $attributes = []): Deal
    {
        $client = Client::create(['name' => 'World People Assembly']);

        return Deal::create(array_merge([
            'client_id' => $client->id,
            'title' => 'Regional Summit 2027',
            'stage' => 'proposal',
            'type' => 'summit',
            'value_cents' => 4_500_000,
            'currency' => 'JOD',
            'probability' => 55,
            'expected_event_on' => now()->addMonths(6)->toDateString(),
            'expected_close_on' => now()->addMonth()->toDateString(),
        ], $attributes));
    }

    public function test_winning_a_deal_opens_the_event_it_becomes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $deal = $this->deal(['owner_id' => $user->id]);
        $event = app(DealPipeline::class)->win($deal->fresh());

        $this->assertSame('Regional Summit 2027', $event->name);
        $this->assertSame('summit', $event->type);
        $this->assertSame('confirmed', $event->stage, 'won work is committed work');
        $this->assertSame($deal->client_id, $event->client_id);
        $this->assertSame(4_500_000, (int) $event->budget_cents);
        $this->assertSame($user->id, $event->project_manager_id);
        $this->assertSame(
            $deal->expected_event_on->toDateString(),
            $event->starts_at->toDateString(),
            'the date discussed in the deal is the date the event starts'
        );

        // linked both ways
        $this->assertSame($event->id, $deal->fresh()->event_id);
        $this->assertSame($deal->id, $event->deal->id);
        $this->assertSame('won', $deal->fresh()->stage);
        $this->assertSame(100, $deal->fresh()->probability);
        $this->assertNotNull($deal->fresh()->won_at);
    }

    public function test_winning_twice_does_not_create_a_second_event(): void
    {
        $this->actingAs(User::factory()->create());
        $deal = $this->deal();

        $first = app(DealPipeline::class)->win($deal->fresh());
        $second = app(DealPipeline::class)->win($deal->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Event::count());
    }

    public function test_the_win_is_recorded_in_the_activity_log(): void
    {
        $this->actingAs(User::factory()->create());
        $deal = $this->deal();

        app(DealPipeline::class)->win($deal->fresh());

        $this->assertDatabaseHas('deal_activities', [
            'deal_id' => $deal->id,
            'subject' => 'Deal won — event created',
        ]);
    }

    public function test_losing_a_deal_records_why(): void
    {
        $this->actingAs(User::factory()->create());
        $deal = $this->deal();

        app(DealPipeline::class)->lose($deal->fresh(), 'Budget cut');

        $deal->refresh();
        $this->assertSame('lost', $deal->stage);
        $this->assertSame(0, $deal->probability);
        $this->assertSame('Budget cut', $deal->lost_reason);
        $this->assertNotNull($deal->lost_at);
        $this->assertDatabaseHas('deal_activities', ['deal_id' => $deal->id, 'subject' => 'Deal lost']);
        $this->assertSame(0, Event::count(), 'a lost deal never becomes an event');
    }

    public function test_moving_between_stages_takes_the_stage_probability(): void
    {
        $this->actingAs(User::factory()->create());
        $deal = $this->deal(['stage' => 'enquiry', 'probability' => 10]);

        app(DealPipeline::class)->moveTo($deal, 'negotiation');

        $this->assertSame('negotiation', $deal->fresh()->stage);
        $this->assertSame(Deal::STAGES['negotiation'][1], $deal->fresh()->probability);
    }

    public function test_the_forecast_weights_the_open_pipeline(): void
    {
        $this->actingAs(User::factory()->create());
        $this->deal(['value_cents' => 1_000_000, 'probability' => 50]);
        $this->deal(['value_cents' => 2_000_000, 'probability' => 25]);
        // a won deal has left the pipeline and must not be forecast again
        app(DealPipeline::class)->win($this->deal(['value_cents' => 9_000_000])->fresh());

        $forecast = app(DealPipeline::class)->forecast();

        $this->assertSame(2, $forecast['count']);
        $this->assertSame(3_000_000, $forecast['value']);
        $this->assertSame(1_000_000, $forecast['weighted']);   // 500k + 500k
    }

    public function test_a_deal_past_its_decision_date_reads_as_stale(): void
    {
        $this->actingAs(User::factory()->create());

        $fresh = $this->deal(['expected_close_on' => now()->addWeek()->toDateString()]);
        $stale = $this->deal(['expected_close_on' => now()->subWeek()->toDateString()]);
        $won = $this->deal(['expected_close_on' => now()->subWeek()->toDateString()]);
        app(DealPipeline::class)->win($won->fresh());

        $this->assertFalse($fresh->isStale());
        $this->assertTrue($stale->isStale());
        $this->assertFalse($won->fresh()->isStale(), 'a closed deal cannot go stale');
    }

    public function test_the_board_renders_its_lanes_and_opens_a_deal_in_the_inspector(): void
    {
        $user = User::factory()->create();
        $deal = $this->deal();

        Livewire::actingAs($user)->test(CrmPipeline::class)
            ->assertSee('Pipeline')
            ->assertSee('Enquiry')->assertSee('Qualified')
            ->assertSee('Proposal')->assertSee('Negotiation')
            ->assertSee('Regional Summit 2027')
            ->assertDontSee('Decision by')          // nothing selected: inspector is empty
            ->call('select', $deal->id)
            ->assertSee('Decision by')
            ->assertSee('Weighted');
    }

    public function test_an_activity_can_be_logged_with_a_follow_up(): void
    {
        $user = User::factory()->create();
        $deal = $this->deal();

        Livewire::actingAs($user)->test(CrmPipeline::class)
            ->call('select', $deal->id)
            ->set('a_type', 'call')
            ->set('a_subject', 'Called about the venue')
            ->set('a_follow_up_on', now()->addDays(3)->toDateString())
            ->call('logActivity');

        $activity = DealActivity::firstWhere('subject', 'Called about the venue');
        $this->assertNotNull($activity);
        $this->assertSame('call', $activity->type);
        $this->assertSame($deal->client_id, $activity->client_id);
        $this->assertFalse($activity->follow_up_done);
    }

    public function test_the_client_record_shows_people_deals_and_delivered_work(): void
    {
        $user = User::factory()->create();
        $deal = $this->deal();
        $client = $deal->client;
        Contact::create(['client_id' => $client->id, 'name' => 'Layla Haddad', 'title' => 'Head of Events', 'is_primary' => true]);

        Livewire::actingAs($user)->test(\App\Livewire\ClientRecord::class, ['client' => $client])
            ->assertSee('Layla Haddad')
            ->assertSee('Head of Events')
            ->assertSee('Regional Summit 2027')
            ->assertSee('Lifetime value')
            ->assertSee('Win rate');
    }

    public function test_archived_events_are_not_counted_as_delivered_work(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $deal = $this->deal();

        $event = app(DealPipeline::class)->win($deal->fresh());
        $live = Livewire::actingAs($user)->test(\App\Livewire\ClientRecord::class, ['client' => $deal->client]);
        $this->assertSame(1, $live->viewData('stats')['events']);

        // Archiving it means it was not delivered — the record must stop counting it.
        $event->update(['archived_at' => now()]);
        $after = Livewire::actingAs($user)->test(\App\Livewire\ClientRecord::class, ['client' => $deal->client]);
        $this->assertSame(0, $after->viewData('stats')['events']);
        $this->assertSame(0, $after->viewData('stats')['lifetime']);
    }

    public function test_the_first_contact_added_becomes_primary_and_the_role_moves_on_delete(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Deep Root']);

        $record = Livewire::actingAs($user)->test(\App\Livewire\ClientRecord::class, ['client' => $client])
            ->set('c_name', 'Mustafa Sultan')->call('saveContact');

        $first = $client->contacts()->firstOrFail();
        $this->assertTrue($first->is_primary, 'the first person on a client is its primary');

        $record->set('c_name', 'Rania Odeh')->call('saveContact');
        $second = $client->contacts()->where('name', 'Rania Odeh')->firstOrFail();
        $this->assertFalse($second->fresh()->is_primary);

        // Deleting the primary must hand the role on, not leave the client without one.
        $record->call('deleteContact', $first->id);
        $this->assertTrue($second->fresh()->is_primary);
    }

    public function test_an_activity_can_be_logged_against_a_client_with_no_deal(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'World People Assembly']);

        Livewire::actingAs($user)->test(\App\Livewire\ClientRecord::class, ['client' => $client])
            ->set('a_type', 'meeting')
            ->set('a_subject', 'Coffee with the new director')
            ->call('logActivity');

        $activity = DealActivity::firstWhere('subject', 'Coffee with the new director');
        $this->assertNotNull($activity);
        $this->assertNull($activity->deal_id, 'a relationship does not pause between opportunities');
        $this->assertSame($client->id, $activity->client_id);
    }

    public function test_a_client_can_hold_several_contacts(): void
    {
        $client = Client::create(['name' => 'Ernst & Young MENA']);
        Contact::create(['client_id' => $client->id, 'name' => 'Layla Haddad', 'title' => 'Head of Events', 'is_primary' => true]);
        Contact::create(['client_id' => $client->id, 'name' => 'Omar Nasser', 'title' => 'Finance']);

        $this->assertSame(2, $client->contacts()->count());
        $this->assertSame('Layla Haddad', $client->primaryContact->name);
    }
}
