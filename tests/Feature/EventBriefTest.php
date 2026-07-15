<?php

namespace Tests\Feature;

use App\Livewire\Hub\BriefTab;
use App\Models\Event;
use App\Models\EventBrief;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventBriefTest extends TestCase
{
    use RefreshDatabase;

    private function make(): array
    {
        $user = User::create(['name' => 'PM', 'email' => 'pm@ebh.test', 'password' => bcrypt('x')]);
        $event = Event::create(['name' => 'Test Summit', 'type' => 'conference', 'city' => 'Amman', 'country' => 'Jordan', 'starts_at' => now(), 'status' => 'planning']);

        return [$user, $event];
    }

    public function test_brief_is_created_with_seeded_sections(): void
    {
        [$user, $event] = $this->make();

        $brief = EventBrief::forEvent($event);

        $this->assertSame('conference', $brief->template);
        $this->assertSame('Test Summit', $brief->data['event_info']['name']);
        $this->assertSame('Plenary Programme', $brief->data['components'][0]['area']);
        $this->assertNotEmpty($brief->data['kpis']);
    }

    public function test_editing_a_field_persists(): void
    {
        [$user, $event] = $this->make();
        EventBrief::forEvent($event);

        Livewire::actingAs($user)->test(BriefTab::class, ['event' => $event])
            ->set('data.components.0.area', 'Opening Plenary')
            ->set('data.event_info.attendance', '1,200 delegates');

        $brief = EventBrief::where('event_id', $event->id)->first();
        $this->assertSame('Opening Plenary', $brief->data['components'][0]['area']);
        $this->assertSame('1,200 delegates', $brief->data['event_info']['attendance']);
    }

    public function test_add_and_remove_row(): void
    {
        [$user, $event] = $this->make();
        EventBrief::forEvent($event);

        $c = Livewire::actingAs($user)->test(BriefTab::class, ['event' => $event])
            ->call('addRow', 'objectives');
        $this->assertCount(7, EventBrief::where('event_id', $event->id)->first()->data['objectives']);

        $c->call('removeRow', 'objectives', 0);
        $this->assertCount(6, EventBrief::where('event_id', $event->id)->first()->data['objectives']);
    }

    public function test_all_five_templates_seed_a_full_brief(): void
    {
        [, $event] = $this->make();

        foreach (array_keys(\App\Support\BriefTemplates::TEMPLATES) as $key) {
            $data = EventBrief::defaultData($event, $key);

            foreach (array_keys(EventBrief::SECTIONS) as $section) {
                $this->assertArrayHasKey($section, $data, "[$key] missing section: $section");
                $this->assertNotEmpty($data[$section], "[$key] empty section: $section");
            }
        }
    }

    public function test_switching_template_swaps_the_content_set(): void
    {
        [$user, $event] = $this->make();
        EventBrief::forEvent($event);

        Livewire::actingAs($user)->test(BriefTab::class, ['event' => $event])
            ->call('switchTemplate', 'gala');

        $brief = EventBrief::where('event_id', $event->id)->first();
        $this->assertSame('gala', $brief->template);
        $this->assertSame('Gala Dinner & Awards Ceremony', $brief->data['event_info']['type']);
        $this->assertSame('Arrival Experience', $brief->data['components'][0]['area']);
    }

    public function test_generate_requires_approval(): void
    {
        [$user, $event] = $this->make();
        EventBrief::forEvent($event);

        Livewire::actingAs($user)->test(BriefTab::class, ['event' => $event])
            ->call('generatePlan')
            ->assertHasErrors('generate');

        $this->assertSame(0, $event->planItems()->count());
    }

    public function test_approved_brief_generates_the_erp_records(): void
    {
        [$user, $event] = $this->make();
        EventBrief::forEvent($event);

        $c = Livewire::actingAs($user)->test(BriefTab::class, ['event' => $event])
            ->call('toggleApproved')
            ->call('generatePlan')
            ->assertHasNoErrors();

        // Plan: tasks from components/venue/branding/operational + milestones, across the 7 phases.
        $this->assertGreaterThan(10, $event->planItems()->count());
        $this->assertDatabaseHas('event_plan_items', ['event_id' => $event->id, 'title' => 'Plenary Programme']);

        // Budget categories, risks, sponsors, approvals.
        $this->assertDatabaseHas('event_budget_categories', ['event_id' => $event->id, 'name' => 'AV & Production']);
        $this->assertDatabaseHas('event_risks', ['event_id' => $event->id, 'title' => 'Keynote / VIP Cancellation', 'category' => 'speaker']);
        $this->assertDatabaseHas('event_sponsor_packages', ['event_id' => $event->id, 'name' => 'Platinum']);
        $this->assertDatabaseHas('event_approvals', ['event_id' => $event->id, 'title' => 'Client approval — Budget', 'type' => 'budget']);

        $this->assertNotNull(EventBrief::where('event_id', $event->id)->first()->generated_at);

        // Idempotent: a second run creates nothing new.
        $before = $event->planItems()->count();
        $c->call('generatePlan');
        $this->assertSame($before, $event->planItems()->count());
        $this->assertSame(0, array_sum($c->get('generated')));
    }

    public function test_generate_does_not_overwrite_hand_made_items(): void
    {
        [$user, $event] = $this->make();
        EventBrief::forEvent($event);
        $event->ensurePlanCategories();
        $mine = $event->planItems()->create([
            'category_id' => $event->planCategories()->value('id'),
            'title' => 'My own task', 'status' => 'in_progress', 'priority' => 'high',
        ]);

        Livewire::actingAs($user)->test(BriefTab::class, ['event' => $event])
            ->call('toggleApproved')->call('generatePlan');

        $this->assertSame('in_progress', $mine->fresh()->status);
        $this->assertDatabaseHas('event_plan_items', ['id' => $mine->id, 'title' => 'My own task']);
    }

    public function test_pdf_downloads(): void
    {
        [$user, $event] = $this->make();

        $res = $this->actingAs($user)->get(route('events.brief.pdf', $event));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
    }
}
