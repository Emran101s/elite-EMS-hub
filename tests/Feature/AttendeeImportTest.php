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

class AttendeeImportTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Event,1:User} */
    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);

        return [
            Event::where('name', 'ICFT 2026')->firstOrFail(),
            User::where('email', 'emran.itan@elitebhub.com')->firstOrFail(),
        ];
    }

    private function csv(string $body): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('attendees.csv', $body);
    }

    public function test_a_spreadsheet_imports_the_full_attendee_record(): void
    {
        [$event, $user] = $this->ctx();
        $before = $event->attendees()->count();

        $file = $this->csv(<<<'CSV'
        Full Name,Email,Mobile,Company,Job Title,Attendee Type,Dietary,VIP,Notes,Fee
        Dana Haddad,dana@icft.org,+962795550111,Ministry of Culture,Director,Speaker,Vegetarian,yes,Needs early check-in,250
        Omar Nasser,omar@icft.org,+962795550112,Acme Group,Head of Ops,Delegate,,no,,150
        CSV);

        Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->set('importFile', $file)
            ->call('import')
            ->assertHasNoErrors();

        $this->assertSame($before + 2, $event->attendees()->count());

        $dana = $event->attendees()->where('email', 'dana@icft.org')->firstOrFail();
        $this->assertSame('Dana Haddad', $dana->name);
        $this->assertSame('+962795550111', $dana->phone);
        $this->assertSame('Ministry of Culture', $dana->organization);
        $this->assertSame('Director', $dana->job_title);
        $this->assertSame('Speaker', $dana->ticket_type);
        $this->assertSame('Vegetarian', $dana->dietary);
        $this->assertSame('Needs early check-in', $dana->notes);
        $this->assertTrue($dana->vip);
        $this->assertSame(25000, $dana->amount_cents);

        $this->assertFalse($event->attendees()->where('email', 'omar@icft.org')->firstOrFail()->vip);
    }

    public function test_reimporting_a_corrected_sheet_updates_instead_of_duplicating(): void
    {
        [$event, $user] = $this->ctx();

        $first = $this->csv("Name,Email,Company\nDana Haddad,dana@icft.org,Old Employer\n");
        Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->set('importFile', $first)->call('import');

        $count = $event->attendees()->where('email', 'dana@icft.org')->count();
        $this->assertSame(1, $count);

        $corrected = $this->csv("Name,Email,Company\nDana Haddad,dana@icft.org,New Employer\n");
        Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->set('importFile', $corrected)->call('import');

        $this->assertSame(1, $event->attendees()->where('email', 'dana@icft.org')->count(),
            'the same person must not be imported twice');
        $this->assertSame('New Employer',
            $event->attendees()->where('email', 'dana@icft.org')->firstOrFail()->organization);
    }

    public function test_a_real_excel_file_imports_too(): void
    {
        [$event, $user] = $this->ctx();
        $before = $event->attendees()->count();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['Name', 'Email', 'Organization', 'Attendee Type'],
            ['Lina Kaddoura', 'lina@icft.org', 'Royal Society', 'Press'],
            ['Yusuf Barakat', 'yusuf@icft.org', 'Oxford', 'Speaker'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'att').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->set('importFile', UploadedFile::fake()->createWithContent('attendees.xlsx', file_get_contents($path)))
            ->call('import')
            ->assertHasNoErrors();

        $this->assertSame($before + 2, $event->attendees()->count());
        $this->assertSame('Press', $event->attendees()->where('email', 'lina@icft.org')->value('ticket_type'));
    }

    public function test_rows_without_a_name_are_skipped(): void
    {
        [$event, $user] = $this->ctx();
        $before = $event->attendees()->count();

        $file = $this->csv("Name,Email\n,orphan@icft.org\nReal Person,real@icft.org\n");
        Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->set('importFile', $file)->call('import');

        $this->assertSame($before + 1, $event->attendees()->count());
    }

    public function test_an_empty_file_reports_an_error_rather_than_importing_nothing_silently(): void
    {
        [$event, $user] = $this->ctx();

        Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->set('importFile', $this->csv(''))
            ->call('import')
            ->assertHasErrors('importFile');
    }
}
