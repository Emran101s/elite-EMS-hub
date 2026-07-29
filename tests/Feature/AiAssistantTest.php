<?php

namespace Tests\Feature;

use App\Livewire\AiAssistant;
use App\Models\Event;
use App\Models\User;
use App\Services\PortfolioAdvisor;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $this->seed(DemoDataSeeder::class);

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_the_briefing_renders_and_says_what_it_is(): void
    {
        $user = $this->actor();

        $this->actingAs($user)->get(route('ai.index'))->assertOk()
            ->assertSee("Today's briefing", false)
            ->assertSee('Rule-based')
            ->assertSee('How this works');
    }

    public function test_every_line_names_a_record_and_links_to_it(): void
    {
        $user = $this->actor();

        $events = Event::whereNull('archived_at')->with(PortfolioAdvisor::RELATIONS)->get();
        $items = app(PortfolioAdvisor::class)->attention($events);

        $this->assertNotEmpty($items, 'the demo book has plenty to flag');

        foreach ($items as $item) {
            $this->assertNotEmpty($item['title']);
            // The source event, and a way to get to it — an assistant that says
            // something is wrong without saying where is the thing this avoids.
            $this->assertNotEmpty($item['where']);
            $this->assertStringContainsString('/events/', $item['href']);
            $this->assertContains($item['severity'], ['critical', 'warning', 'info']);
        }
    }

    public function test_worst_comes_first(): void
    {
        $user = $this->actor();

        $items = Livewire::actingAs($user)->test(AiAssistant::class)->viewData('items');
        $rank = ['critical' => 0, 'warning' => 1, 'info' => 2];

        $seen = $items->map(fn ($i) => $rank[$i['severity']])->all();
        $sorted = $seen;
        sort($sorted);

        $this->assertSame($sorted, $seen, 'the list is ordered worst first');
    }

    public function test_the_filter_narrows_without_reordering(): void
    {
        $user = $this->actor();

        $critical = Livewire::actingAs($user)->test(AiAssistant::class)
            ->call('setFilter', 'critical')
            ->viewData('items');

        foreach ($critical as $item) {
            $this->assertSame('critical', $item['severity']);
        }

        // An unknown filter is ignored rather than obeyed.
        Livewire::actingAs($user)->test(AiAssistant::class)
            ->call('setFilter', 'whatever')
            ->assertSet('filter', 'all');
    }

    public function test_focusing_an_event_opens_its_own_briefing_and_closes_again(): void
    {
        $user = $this->actor();
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();

        Livewire::actingAs($user)->test(AiAssistant::class)
            ->call('focus', $event->id)
            ->assertSet('focusId', $event->id)
            ->assertSee('ICFT 2026 is')
            ->call('focus', $event->id)
            ->assertSet('focusId', null);
    }
}
