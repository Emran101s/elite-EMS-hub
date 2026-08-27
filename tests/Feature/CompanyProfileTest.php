<?php

namespace Tests\Feature;

use App\Livewire\CompanySettings;
use App\Livewire\EventCreate;
use App\Models\CompanyProfile;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CompanyProfileTest extends TestCase
{
    use RefreshDatabase;

    private function boot(): User
    {
        $this->seed(DemoDataSeeder::class);

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_page_renders_and_is_a_singleton(): void
    {
        $user = $this->boot();
        $this->actingAs($user)->get(route('settings.index'))->assertOk()->assertSee('Company Profile');
        $this->actingAs($user)->get(route('company.index'))->assertOk()->assertSee('Company Profile');

        CompanyProfile::current();
        CompanyProfile::current();
        $this->assertSame(1, CompanyProfile::count());
    }

    public function test_save_updates_profile_with_logo(): void
    {
        Storage::fake('public');
        $user = $this->boot();

        Livewire::actingAs($user)->test(CompanySettings::class)
            ->set('name', 'Elite BH')
            ->set('default_currency', 'JOD')
            ->set('default_timezone', 'Asia/Amman')
            ->set('country', 'Jordan')
            ->set('logo', UploadedFile::fake()->image('logo.png'))
            ->call('save')
            ->assertHasNoErrors();

        $p = CompanyProfile::current();
        $this->assertSame('JOD', $p->default_currency);
        $this->assertSame('Jordan', $p->country);
        $this->assertStringStartsWith('storage/company/', $p->logo_path);
    }

    public function test_new_events_inherit_company_defaults(): void
    {
        $user = $this->boot();
        CompanyProfile::current()->update([
            'default_currency' => 'AED', 'default_timezone' => 'Asia/Dubai', 'country' => 'UAE', 'city' => 'Dubai',
        ]);

        Livewire::actingAs($user)->test(EventCreate::class)
            ->call('chooseCategory', 'conference')
            ->set('originKind', 'commercial')
            ->set('originSource', 'deal')
            ->set('name', 'Gulf Forum')
            ->set('new_client', 'Gulf Org')
            ->set('starts_at', '2027-01-10')
            ->call('save');

        $event = Event::where('name', 'Gulf Forum')->firstOrFail();
        $this->assertSame('AED', $event->currency);
        $this->assertSame('Asia/Dubai', $event->timezone);
        $this->assertSame('UAE', $event->country);
        $this->assertSame('Dubai', $event->city);
    }
}
