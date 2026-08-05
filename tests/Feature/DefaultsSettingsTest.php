<?php

namespace Tests\Feature;

use App\Livewire\DefaultsSettings;
use App\Models\CompanyProfile;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DefaultsSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function boot(): User
    {
        $this->seed(DemoDataSeeder::class);

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_page_renders_from_settings(): void
    {
        $user = $this->boot();
        $this->actingAs($user)->get(route('settings.index'))->assertOk()->assertSee('Defaults & Templates');
        $this->actingAs($user)->get(route('defaults.index'))->assertOk()->assertSee('Default budget categories');
    }

    public function test_saving_defaults_seeds_new_event_budget(): void
    {
        $user = $this->boot();

        Livewire::actingAs($user)->test(DefaultsSettings::class)
            ->set('categories', ['Venue', 'Catering', 'Marketing'])
            ->set('fee', '12.5')
            ->call('save')
            ->assertHasNoErrors();

        $p = CompanyProfile::current();
        $this->assertSame(['Venue', 'Catering', 'Marketing'], $p->default_budget_categories);
        $this->assertSame(12.5, $p->default_management_fee_pct);

        // a brand-new event seeds its budget from the workspace defaults
        $event = Event::create(['name' => 'Fresh Event', 'type' => 'conference', 'city' => 'Amman', 'country' => 'Jordan', 'starts_at' => now(), 'status' => 'planning']);
        $event->ensureBudgetCategories();
        $this->assertSame(['Venue', 'Catering', 'Marketing'], $event->budgetCategories()->orderBy('position')->pluck('name')->all());
    }

    public function test_add_remove_reorder_categories(): void
    {
        $user = $this->boot();
        $c = Livewire::actingAs($user)->test(DefaultsSettings::class)
            ->set('categories', ['A', 'B']);

        $c->set('newCategory', 'C')->call('addCategory');
        $this->assertSame(['A', 'B', 'C'], $c->get('categories'));

        $c->call('move', 2, -1); // C up
        $this->assertSame(['A', 'C', 'B'], $c->get('categories'));

        $c->call('removeCategory', 0); // remove A
        $this->assertSame(['C', 'B'], $c->get('categories'));

        $c->set('newCategory', 'c')->call('addCategory')->assertHasErrors('newCategory'); // dup (case-insensitive)
    }

    public function test_the_approval_threshold_can_be_set_and_cleared(): void
    {
        $user = $this->boot();

        Livewire::actingAs($user)->test(DefaultsSettings::class)
            ->set('approvalThreshold', '10000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1_000_000, CompanyProfile::current()->approval_threshold_cents);

        // blank turns the policy back off, not zero — a zero threshold would
        // escalate every priced request, which "off" must not mean.
        Livewire::actingAs($user)->test(DefaultsSettings::class)
            ->set('approvalThreshold', '')
            ->call('save');

        $this->assertNull(CompanyProfile::current()->approval_threshold_cents);
    }
}
