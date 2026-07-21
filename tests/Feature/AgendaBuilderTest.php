<?php

namespace Tests\Feature;

use App\Livewire\Hub\AgendaTab;
use App\Models\Event;
use App\Models\EventAgendaSession;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaBuilderTest extends TestCase
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

    public function test_session_can_be_added_edited_and_deleted(): void
    {
        [$event, $user] = $this->ctx();
        $day = $event->agendaDays()->first();

        $component = Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->call('newSession', $day->id)
            ->set('title', 'Fireside Chat: AI in Banking')
            ->set('type', 'panel')
            ->set('starts_at', '15:00')
            ->set('ends_at', '15:45')
            ->call('saveSession')
            ->assertHasNoErrors();

        $session = $event->agendaSessions()->where('title', 'Fireside Chat: AI in Banking')->firstOrFail();
        $this->assertSame('panel', $session->type);

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->call('editSession', $session->id)
            ->set('title', 'Fireside Chat: AI Everywhere')
            ->call('saveSession');
        $this->assertSame('Fireside Chat: AI Everywhere', $session->fresh()->title);

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->call('deleteSession', $session->id);
        $this->assertNull($session->fresh());
    }

    public function test_add_and_duplicate_day(): void
    {
        [$event, $user] = $this->ctx();
        $before = $event->agendaDays()->count();

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])->call('addDay');
        $this->assertSame($before + 1, $event->agendaDays()->count());

        $day1 = $event->agendaDays()->orderBy('sort')->first();
        $sessionCount = $day1->sessions()->count();

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])->call('duplicateDay', $day1->id);
        $copy = $event->agendaDays()->where('label', $day1->label.' (Copy)')->firstOrFail();
        $this->assertSame($sessionCount, $copy->sessions()->count());
    }

    public function test_drag_reorder_moves_session_between_days_and_sets_sort(): void
    {
        [$event, $user] = $this->ctx();
        $days = $event->agendaDays()->orderBy('sort')->get();
        [$dayA, $dayB] = [$days[0], $days[1]];
        $moved = $dayA->sessions()->first();
        $targetIds = $dayB->sessions()->pluck('id')->push($moved->id)->all();

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->call('reorder', [['dayId' => $dayB->id, 'ids' => $targetIds]]);

        $moved->refresh();
        $this->assertSame($dayB->id, $moved->agenda_day_id);
        $this->assertSame(count($targetIds) - 1, $moved->sort);
    }

    public function test_csv_import_creates_sessions(): void
    {
        [$event, $user] = $this->ctx();
        $before = $event->agendaSessions()->count();

        $csv = "title,type,start,end,room,speaker,moderator\n"
            ."Registration,networking,08:00,09:00,Main Hall,,\n"
            ."Welcome Keynote,keynote,09:00,10:00,Main Hall,Dr. Lina Odeh;Sara Kamal,Omar Nassar\n";
        $file = UploadedFile::fake()->createWithContent('agenda.csv', $csv);

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->set('importFile', $file)
            ->call('import')
            ->assertHasNoErrors();

        $this->assertSame($before + 2, $event->agendaSessions()->count());

        // Imported names land on the roster and are billed with their role.
        $session = $event->agendaSessions()->where('title', 'Welcome Keynote')->firstOrFail()->load('speakers');
        $this->assertCount(3, $session->speakers);
        $this->assertSame(
            'Moderator: Omar Nassar · Panellists: Dr. Lina Odeh, Sara Kamal',
            $session->speakerLine()
        );
        $this->assertNotNull($event->speakers()->where('name', 'Dr. Lina Odeh')->first());
    }

    public function test_pdf_export_downloads(): void
    {
        [$event, $user] = $this->ctx();

        // Three documents, one job each — all must render.
        foreach ([
            route('events.agenda.program.pdf', $event),   // delegates
            route('events.agenda.master.pdf', $event),    // the team
            route('events.run-of-show.pdf', $event),      // show day
        ] as $url) {
            $response = $this->actingAs($user)->get($url);
            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('content-type'), $url);
        }
    }

    public function test_reorder_ignores_foreign_sessions(): void
    {
        [$event, $user] = $this->ctx();
        $day = $event->agendaDays()->first();
        $foreign = Event::where('name', 'EY Annual Gala')->firstOrFail();
        $foreignDay = $foreign->agendaDays()->create(['date' => now(), 'label' => 'D1', 'sort' => 0]);
        $foreignSession = $foreign->agendaSessions()->create([
            'agenda_day_id' => $foreignDay->id, 'title' => 'Foreign', 'type' => 'keynote',
            'status' => 'draft', 'starts_at' => '09:00', 'ends_at' => '10:00', 'sort' => 0,
        ]);

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->call('reorder', [['dayId' => $day->id, 'ids' => [$foreignSession->id]]]);

        // Untouched — it belongs to another event.
        $this->assertSame($foreignDay->id, $foreignSession->fresh()->agenda_day_id);
    }
}
