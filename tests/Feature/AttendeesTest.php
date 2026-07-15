<?php

namespace Tests\Feature;

use App\Livewire\Hub\AttendeesTab;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class AttendeesTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);

        return [
            Event::where('name', 'ICFT 2026')->firstOrFail(),
            User::where('email', 'emran.itan@elitebhub.com')->firstOrFail(),
        ];
    }

    public function test_tab_renders_in_the_hub(): void
    {
        [$event, $user] = $this->ctx();
        $event->update(['enabled_modules' => ['attendees']]);
        $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'attendees']))
            ->assertOk()->assertSee('Registered')->assertSee('Ticket revenue');
    }

    public function test_add_attendee_and_check_in(): void
    {
        [$event, $user] = $this->ctx();
        $c = Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event]);

        $c->call('newItem')
            ->set('name', 'Noura Said')->set('email', 'noura@x.test')
            ->set('ticket_type', 'VIP')->set('amount', '150')->set('vip', true)
            ->call('save')->assertHasNoErrors();

        $a = $event->attendees()->where('name', 'Noura Said')->firstOrFail();
        $this->assertSame(15000, $a->amount_cents);
        $this->assertTrue((bool) $a->vip);

        // check-in toggles status + timestamp
        $c->call('toggleCheckIn', $a->id);
        $a->refresh();
        $this->assertSame('checked_in', $a->status);
        $this->assertNotNull($a->checked_in_at);

        $c->call('toggleCheckIn', $a->id);
        $this->assertSame('confirmed', $a->fresh()->status);
    }

    public function test_csv_import_creates_attendees(): void
    {
        [$event, $user] = $this->ctx();
        $before = $event->attendees()->count();

        $csv = "name,email,organization,ticket,amount\nSara N,sara@x.test,Aramco,Delegate,100\nOmar K,,Gov,Speaker,\n,,skip,,\n";
        $file = UploadedFile::fake()->createWithContent('reg.csv', $csv);

        Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->set('importFile', $file)->call('import')->assertHasNoErrors();

        $this->assertSame($before + 2, $event->attendees()->count());
        $this->assertSame(10000, $event->attendees()->where('name', 'Sara N')->value('amount_cents'));
        $this->assertSame('Speaker', $event->attendees()->where('name', 'Omar K')->value('ticket_type'));
    }
}
