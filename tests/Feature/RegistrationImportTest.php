<?php

namespace Tests\Feature;

use App\Livewire\Hub\AttendeesTab;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * The sheet's columns are this event's own questions.
 *
 * A fixed template was fine while every event asked the same ten things. Now
 * that a form is a list of questions, a fixed sheet cannot carry the answers:
 * somebody registers by email with their workshop track, it is typed into a
 * spreadsheet, and the column for it does not exist.
 */
class RegistrationImportTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        $event = Event::factory()->create(['registration_open' => true]);
        $event->registrationForm();

        return $event->fresh();
    }

    private function csv(string $body): UploadedFile
    {
        // Livewire binds a TemporaryUploadedFile; a plain UploadedFile has no
        // $name for it to read.
        return UploadedFile::fake()->createWithContent(
            'attendees.csv',
            implode("\n", array_map('trim', explode("\n", trim($body)))),
        );
    }

    private function headers(Event $event): array
    {
        $res = $this->actingAs(User::factory()->create(['role' => 'manager']))
            ->get(route('events.attendees.template', $event))->assertOk();

        $path = tempnam(sys_get_temp_dir(), 't').'.xlsx';
        file_put_contents($path, $res->streamedContent());
        $header = IOFactory::load($path)->getActiveSheet()->toArray()[0];
        @unlink($path);

        return array_values(array_filter($header, fn ($h) => $h !== null && $h !== ''));
    }

    public function test_a_question_this_event_added_becomes_a_column(): void
    {
        $event = $this->event();
        $event->registrationFields()->create([
            'key' => 'track', 'label' => 'Which workshop track?', 'type' => 'select',
            'options' => ['Track A', 'Track B'], 'position' => 20,
        ]);

        $headers = $this->headers($event->fresh());

        $this->assertContains('Which workshop track?', $headers);
        $this->assertContains('Full name', $headers);
    }

    public function test_a_question_taken_off_the_form_leaves_the_sheet(): void
    {
        $event = $this->event();
        $event->registrationFields()->where('key', 'dietary')->delete();

        $this->assertNotContains('Dietary requirements', $this->headers($event->fresh()));
    }

    public function test_an_answer_imports_into_the_answers_it_would_have_had_from_the_form(): void
    {
        $event = $this->event();
        $event->registrationFields()->create([
            'key' => 'track', 'label' => 'Which workshop track?', 'type' => 'select',
            'options' => ['Track A', 'Track B'], 'position' => 20,
        ]);

        Livewire::actingAs(User::factory()->create(['role' => 'manager']))
            ->test(AttendeesTab::class, ['event' => $event->fresh()])
            ->set('importFile', $this->csv(<<<'CSV'
            Full name,Email address,Which workshop track?
            Dana Haddad,dana@icft.org,Track B
            CSV))
            ->call('import')
            ->assertHasNoErrors();

        $dana = $event->attendees()->where('email', 'dana@icft.org')->firstOrFail();

        $this->assertSame('Dana Haddad', $dana->name);
        $this->assertSame('Track B', $dana->answer('track'));
    }

    /** One cell, several answers — stored the way the public form stores them. */
    public function test_a_several_choice_answer_arrives_as_a_list(): void
    {
        $event = $this->event();
        $event->registrationFields()->create([
            'key' => 'days', 'label' => 'Days attending', 'type' => 'multiselect',
            'options' => ['Monday', 'Tuesday', 'Wednesday'], 'position' => 20,
        ]);

        Livewire::actingAs(User::factory()->create(['role' => 'manager']))
            ->test(AttendeesTab::class, ['event' => $event->fresh()])
            ->set('importFile', $this->csv(<<<'CSV'
            Full name,Email address,Days attending
            Dana Haddad,dana@icft.org,"Monday, Wednesday"
            CSV))
            ->call('import');

        $dana = $event->attendees()->where('email', 'dana@icft.org')->firstOrFail();

        $this->assertSame(['Monday', 'Wednesday'], $dana->answers['days']);
        $this->assertSame('Monday, Wednesday', $dana->answer('days'));
    }

    /** A sheet somebody else prepared, with their own words for the columns. */
    public function test_a_sheet_that_names_its_columns_differently_still_imports(): void
    {
        $event = $this->event();

        Livewire::actingAs(User::factory()->create(['role' => 'manager']))
            ->test(AttendeesTab::class, ['event' => $event])
            ->set('importFile', $this->csv(<<<'CSV'
            Name,E-mail,Company,Mobile,Attendee Type
            Dana Haddad,dana@icft.org,Ministry of Culture,+962795550111,Speaker
            CSV))
            ->call('import')
            ->assertHasNoErrors();

        $dana = $event->attendees()->where('email', 'dana@icft.org')->firstOrFail();

        $this->assertSame('Ministry of Culture', $dana->organization);
        $this->assertSame('+962795550111', $dana->phone);
        $this->assertSame('Speaker', $dana->ticket_type);
    }

    /** Choices come as a dropdown: a track typed four ways is four tracks. */
    public function test_a_choice_question_brings_its_choices_to_the_sheet(): void
    {
        $event = $this->event();
        $event->registrationFields()->create([
            'key' => 'track', 'label' => 'Which workshop track?', 'type' => 'select',
            'options' => ['Track A', 'Track B'], 'position' => 20,
        ]);

        $res = $this->actingAs(User::factory()->create(['role' => 'manager']))
            ->get(route('events.attendees.template', $event->fresh()))->assertOk();

        $path = tempnam(sys_get_temp_dir(), 't').'.xlsx';
        file_put_contents($path, $res->streamedContent());
        $ss = IOFactory::load($path);
        @unlink($path);

        $names = collect($ss->getAllSheets())->map(fn ($s) => $s->getTitle())->all();

        $this->assertContains('Lists', $names, 'the choices ride along in the workbook');

        $lists = collect($ss->getSheetByName('Lists')->toArray())->flatten()->filter()->all();

        $this->assertContains('Track A', $lists);
        $this->assertContains('Track B', $lists);
    }
}
