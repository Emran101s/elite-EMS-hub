<?php

namespace Tests\Feature;

use App\Livewire\Hub\AccommodationTab;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AccommodationImportTest extends TestCase
{
    use RefreshDatabase;

    private function xlsx(array $rows): UploadedFile
    {
        $ss = new Spreadsheet;
        $ss->getActiveSheet()->fromArray($rows, null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'room').'.xlsx';
        (new Xlsx($ss))->save($path);
        $content = file_get_contents($path);
        @unlink($path);

        return UploadedFile::fake()->createWithContent('rooming.xlsx', $content);
    }

    public function test_excel_rooming_list_imports_into_a_block(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        $event = Event::create([
            'name' => 'Import Event', 'type' => 'summit', 'city' => 'Amman', 'country' => 'Jordan',
            'starts_at' => '2026-07-27', 'ends_at' => '2026-07-29',
        ]);
        $block = $event->roomBlocks()->create([
            'hotel' => 'Fairmont Amman', 'room_type' => 'Deluxe', 'occupancy' => 'double',
            'rooms_count' => 2, 'rate_cents' => 12000, 'check_in' => '2026-07-27', 'check_out' => '2026-07-29',
            'status' => 'booked', 'position' => 1,
        ]);

        // Headers are tolerant of spacing/synonyms; only Name is required.
        $file = $this->xlsx([
            ['Name', 'Email', 'Occupancy', 'Check In', 'Sharing With', 'Confirmation #'],
            ['Layla Odeh', 'layla@example.com', 'Twin', '2026-07-28', 'Sara Kamal', 'CN-1001'],
            ['Omar Nassar', '', 'single', '', '', ''],
            ['', '', '', '', '', ''],                     // blank name → skipped
        ]);

        Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->call('openImport', $block->id)
            ->set('importFile', $file)
            ->call('importRooms')
            ->assertHasNoErrors();

        $rooms = $block->rooms()->get();
        $this->assertCount(2, $rooms, 'two named rows imported, blank row skipped');

        // Block grew from 2 to fit everyone (was already 2 → stays 2 here).
        $this->assertSame(2, $block->fresh()->rooms_count);

        $layla = $rooms->firstWhere('guest', 'Layla Odeh');
        $this->assertSame('layla@example.com', $layla->guest_email);
        $this->assertSame('twin', $layla->occupancy);                       // resolved from "Twin"
        $this->assertSame('2026-07-28', $layla->check_in->format('Y-m-d')); // per-row override
        $this->assertSame('Sara Kamal', $layla->sharing_with);
        $this->assertSame('CN-1001', $layla->confirmation_number);
        $this->assertSame('Deluxe', $layla->room_type);                     // inherited from block

        $omar = $rooms->firstWhere('guest', 'Omar Nassar');
        $this->assertSame('single', $omar->occupancy);
        $this->assertSame('2026-07-27', $omar->check_in->format('Y-m-d'));  // inherited block check-in

        // Imported guests land on the attendee list too.
        $this->assertNotNull($event->attendees()->where('name', 'Layla Odeh')->first());
    }

    public function test_template_downloads_with_the_expected_headers(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        $event = Event::create([
            'name' => 'Tpl Event', 'type' => 'summit', 'city' => 'Amman', 'country' => 'Jordan',
            'starts_at' => '2026-07-27', 'ends_at' => '2026-07-29',
        ]);
        $block = $event->roomBlocks()->create(['hotel' => 'Fairmont', 'rooms_count' => 5, 'status' => 'booked', 'position' => 1]);

        $res = $this->actingAs($user)->get(route('events.rooming.template', [$event, $block]));
        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $res->headers->get('content-type'));

        // The active (first) sheet — the one the importer reads — carries the headers.
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, $res->streamedContent());
        $ss = IOFactory::load($path);
        @unlink($path);

        $this->assertSame('Rooming List', $ss->getActiveSheet()->getTitle());
        $header = $ss->getActiveSheet()->toArray()[0];
        $this->assertSame('Name', $header[0]);
        $this->assertContains('Check In', $header);
        $this->assertContains('Occupancy', $header);
        $this->assertCount(1, array_filter($ss->getActiveSheet()->toArray(), fn ($r) => trim((string) ($r[0] ?? '')) !== ''),
            'the fillable sheet ships with only the header row — no sample data to import by accident');
    }

    public function test_nights_are_counted_per_guest_not_per_block(): void
    {
        $event = Event::create([
            'name' => 'Nights Event', 'type' => 'summit', 'city' => 'Amman', 'country' => 'Jordan',
            'starts_at' => '2026-07-27', 'ends_at' => '2026-07-29',
        ]);
        $block = $event->roomBlocks()->create([
            'hotel' => 'Fairmont', 'rooms_count' => 3, 'check_in' => '2026-07-27', 'check_out' => '2026-07-29',
            'status' => 'booked', 'position' => 1,
        ]);
        // Block window is 2 nights, but guests stay different lengths.
        $onBlock = $event->accommodations()->create(['block_id' => $block->id, 'hotel' => 'Fairmont', 'guest' => 'On Block', 'rooms' => 1, 'check_in' => '2026-07-27', 'check_out' => '2026-07-29', 'status' => 'booked', 'position' => 1]);
        $early = $event->accommodations()->create(['block_id' => $block->id, 'hotel' => 'Fairmont', 'guest' => 'Early Bird', 'rooms' => 1, 'check_in' => '2026-07-26', 'check_out' => '2026-07-30', 'status' => 'booked', 'position' => 2]);
        $short = $event->accommodations()->create(['block_id' => $block->id, 'hotel' => 'Fairmont', 'guest' => 'Short Stay', 'rooms' => 1, 'check_in' => '2026-07-28', 'check_out' => '2026-07-29', 'status' => 'booked', 'position' => 3]);

        // Each guest's nights come from their own dates.
        $this->assertSame(2, $onBlock->nights());
        $this->assertSame(4, $early->nights());
        $this->assertSame(1, $short->nights());

        // Block total is the true sum (2+4+1), not block-window × rooms (2 × 3 = 6).
        $this->assertSame(7, $block->fresh()->namedRoomNights());
        $this->assertNotSame($block->roomNights(), $block->namedRoomNights());
    }

    public function test_import_grows_the_block_to_fit_everyone(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        $event = Event::create([
            'name' => 'Grow Event', 'type' => 'summit', 'city' => 'Amman', 'country' => 'Jordan',
            'starts_at' => '2026-07-27', 'ends_at' => '2026-07-28',
        ]);
        $block = $event->roomBlocks()->create([
            'hotel' => 'St Regis', 'rooms_count' => 1, 'status' => 'booked', 'position' => 1,
        ]);

        $file = $this->xlsx([
            ['Name'],
            ['Guest One'], ['Guest Two'], ['Guest Three'],
        ]);

        Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->call('openImport', $block->id)
            ->set('importFile', $file)
            ->call('importRooms')
            ->assertHasNoErrors();

        $this->assertSame(3, $block->rooms()->count());
        $this->assertSame(3, $block->fresh()->rooms_count, 'block auto-grows to hold every imported guest');
    }
}
