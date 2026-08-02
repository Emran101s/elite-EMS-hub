<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GeneratesXlsxTemplate;
use App\Models\Event;
use App\Models\RegistrationField;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * This event's attendee sheet, with this event's own columns.
 *
 * The template carried ten fixed columns, which was fine while every event
 * asked the same ten things. Now that a form is a list of questions, a fixed
 * sheet is a sheet that cannot carry the answers: somebody registers by email
 * with their workshop track, it gets typed into a spreadsheet, and the column
 * for it does not exist.
 *
 * So the columns ARE the questions. A choice question brings its choices as a
 * dropdown, because a track typed four different ways is four tracks.
 */
class AttendeeTemplateController extends Controller
{
    use GeneratesXlsxTemplate;

    /** Two worked rows, so the shape of an answer is never in doubt. */
    private const EXAMPLES = [
        'name' => ['Layla Odeh', 'Omar Nassar'],
        'email' => ['layla@example.com', 'omar@example.com'],
        'phone' => ['+962 79 000 0000', ''],
        'organization' => ['Ministry of Culture', 'Acme Corp'],
        'job_title' => ['Director', 'Manager'],
        'dietary' => ['Vegetarian', ''],
        'notes' => ['Keynote guest', ''],
    ];

    public function __invoke(Event $event): StreamedResponse
    {
        $fields = $event->registrationForm();

        $headers = [];
        $widths = [];
        $lists = [];
        $rules = [];
        $rowA = [];
        $rowB = [];

        foreach ($fields as $i => $field) {
            $headers[] = $field->label;
            $widths[] = match ($field->type) {
                'textarea' => 34,
                'email' => 26,
                'select', 'multiselect' => 22,
                default => 20,
            };

            // Sessions are named rather than listed: a dropdown holds one
            // value, and somebody attends several.
            if ($field->isSessions()) {
                $widths[count($widths) - 1] = 40;
            }

            // A choice question offers its choices rather than trusting a typist.
            if ($field->takesOptions() && $field->options) {
                $name = 'list_'.$field->key;
                $lists[$name] = $field->options;
                $rules[$this->columnLetter($i)] = $name;
            }

            [$a, $b] = self::EXAMPLES[$field->maps_to ?? ''] ?? $this->exampleFor($field);
            $rowA[] = $a;
            $rowB[] = $b;
        }

        // Two columns the form never asks but the desk records: what somebody
        // paid, and whether they are a VIP. Neither is a question you put to a
        // registrant, which is why they are not on the public form.
        $headers[] = 'Amount Paid';
        $widths[] = 13;
        $rowA[] = '150';
        $rowB[] = '';

        $headers[] = 'VIP';
        $widths[] = 8;
        $rowA[] = 'Yes';
        $rowB[] = 'No';

        $lists['VIP'] = ['Yes', 'No'];
        $rules[$this->columnLetter(count($headers) - 1)] = 'VIP';

        return $this->xlsxTemplate(
            headers: $headers,
            example: [$rowA, $rowB],
            sheetTitle: 'Attendees',
            filename: str($event->name)->slug().'-attendees-template.xlsx',
            widths: $widths,
            lists: $lists,
            rules: $rules,
        );
    }

    /** A plausible answer for a question the platform did not write. */
    private function exampleFor(RegistrationField $field): array
    {
        return match ($field->type) {
            'select' => [$field->options[0] ?? '', $field->options[1] ?? ($field->options[0] ?? '')],
            'multiselect' => [implode(', ', array_slice($field->options ?? [], 0, 2)), $field->options[0] ?? ''],
            'sessions' => [$field->sessionChoices()->take(2)->pluck('title')->join(', '),
                $field->sessionChoices()->first()?->title ?? ''],
            'number' => ['1', '2'],
            'date' => [now()->addMonth()->format('Y-m-d'), ''],
            'checkbox' => ['Yes', 'No'],
            default => [$field->placeholder ?: '', ''],
        };
    }

    /** A, B, … Z, AA — the spreadsheet's own way of naming a column. */
    private function columnLetter(int $i): string
    {
        $letter = '';

        for ($n = $i; $n >= 0; $n = intdiv($n, 26) - 1) {
            $letter = chr(65 + $n % 26).$letter;
        }

        return $letter;
    }
}
