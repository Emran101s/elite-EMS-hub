<?php

namespace Tests\Feature;

use App\Livewire\SponsorPackagesSettings;
use App\Models\CompanyProfile;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SponsorPackagesSettingsTest extends TestCase
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
        $this->actingAs($user)->get(route('settings.index'))->assertOk()->assertSee('Sponsorship Packages');
        $this->actingAs($user)->get(route('sponsor-packages.index'))->assertOk()->assertSee('standard sponsorship tiers');
    }

    public function test_saving_packages_seeds_new_event(): void
    {
        $user = $this->boot();

        Livewire::actingAs($user)->test(SponsorPackagesSettings::class)
            ->set('packages', [
                ['name' => 'Platinum', 'price' => '50000', 'slots' => '2', 'benefits' => "Main stage logo\n10 passes"],
                ['name' => 'Gold', 'price' => '25000', 'slots' => '5', 'benefits' => "5 passes"],
                ['name' => '', 'price' => '', 'slots' => '', 'benefits' => ''], // blank skipped
            ])
            ->call('save')
            ->assertHasNoErrors();

        $saved = CompanyProfile::current()->default_sponsor_packages;
        $this->assertCount(2, $saved);
        $this->assertSame('Platinum', $saved[0]['name']);
        $this->assertSame(5000000, $saved[0]['price_cents']);
        $this->assertSame(['Main stage logo', '10 passes'], $saved[0]['benefits']);

        // new event seeds its packages from the template
        $event = Event::create(['name' => 'Sponsor Test', 'type' => 'conference', 'city' => 'Amman', 'country' => 'Jordan', 'starts_at' => now(), 'status' => 'planning']);
        $event->ensureSponsorPackages();
        $pkgs = $event->sponsorPackages()->orderBy('position')->get();
        $this->assertSame(['Platinum', 'Gold'], $pkgs->pluck('name')->all());
        $this->assertSame(5000000, $pkgs->first()->price_cents);
        $this->assertSame(2, $pkgs->first()->slots);
        $this->assertSame(['Main stage logo', '10 passes'], $pkgs->first()->benefits);
    }
}
