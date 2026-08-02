<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A question set you build once and start events from.
 *
 * Making the form editable per event immediately means rebuilding the same
 * conference form by hand every time you run a conference. This is that form,
 * kept.
 *
 * Applying one COPIES it. The event owns what it ends up with — the same rule
 * the price list and the event's invoice items follow — so editing next year's
 * "Conference" never rewrites what last year's delegates were asked.
 */
class RegistrationTemplate extends Model
{
    protected $fillable = ['name', 'note', 'fields', 'position'];

    protected $casts = ['fields' => 'array', 'position' => 'integer'];

    /** The questions, as field definitions. */
    public function questions(): Collection
    {
        return collect($this->fields ?? [])->map(fn (array $f) => $f + [
            'type' => 'text',
            'required' => false,
            'options' => null,
            'help' => null,
            'placeholder' => null,
            'maps_to' => null,
        ]);
    }

    /**
     * Write this template onto an event's form.
     *
     * Replacing is deliberate and destructive, so it is a decision made once
     * here rather than an accident of ordering: `replace` clears the event's
     * questions first, and without it a template ADDS what the event does not
     * already ask. Either way, a key already on the event is left exactly as
     * it is — answers are filed under it, and a template must not reach into
     * an event that has already taken registrations and change the meaning of
     * a question people have answered.
     *
     * @return int how many questions were added
     */
    public function applyTo(Event $event, bool $replace = false): int
    {
        // Seeded first, so "replace" starts from a form rather than nothing and
        // the locked core questions are present to be preserved.
        $event->registrationForm();

        if ($replace) {
            // Name and email survive: without them nobody can be identified,
            // badged or checked in, whatever a template happens to say.
            $event->registrationFields()
                ->whereNotIn('maps_to', RegistrationField::REQUIRED_CORE)
                ->orWhereNull('maps_to')
                ->delete();
        }

        $taken = $event->registrationFields()->pluck('key')->all();
        $usedColumns = $event->registrationFields()->whereNotNull('maps_to')->pluck('maps_to')->all();
        $position = (int) $event->registrationFields()->max('position');
        $added = 0;

        foreach ($this->questions() as $q) {
            $key = $q['key'] ?? RegistrationField::keyFor($event, $q['label'] ?? 'Question');

            if (in_array($key, $taken, true)) {
                continue;   // already asked; the event's own wording wins
            }

            // Two questions cannot fill the same column — the second would
            // silently overwrite the first on every registration.
            $mapsTo = $q['maps_to'] ?? null;

            if ($mapsTo && in_array($mapsTo, $usedColumns, true)) {
                $mapsTo = null;
            }

            $event->registrationFields()->create([
                'key' => $key,
                'label' => $q['label'] ?? 'Question',
                'type' => $q['type'],
                'required' => (bool) $q['required'],
                'options' => $q['options'] ?: null,
                'help' => $q['help'] ?: null,
                'placeholder' => $q['placeholder'] ?: null,
                'maps_to' => $mapsTo,
                'position' => ++$position,
            ]);

            $taken[] = $key;

            if ($mapsTo) {
                $usedColumns[] = $mapsTo;
            }

            $added++;
        }

        return $added;
    }

    /** Take a template FROM an event that has the form you want to keep. */
    public static function fromEvent(Event $event, string $name): self
    {
        return static::create([
            'name' => $name,
            'note' => 'Taken from '.$event->name,
            'position' => (int) static::max('position') + 1,
            'fields' => $event->registrationFields()->get()
                ->map(fn (RegistrationField $f) => [
                    'key' => $f->key,
                    'label' => $f->label,
                    'type' => $f->type,
                    'required' => $f->required,
                    'options' => $f->options,
                    'help' => $f->help,
                    'placeholder' => $f->placeholder,
                    'maps_to' => $f->maps_to,
                ])->all(),
        ]);
    }

    /**
     * The three most events start from. Seeded once, and editable after —
     * they are a starting point, not a rule.
     */
    public static function seedDefaults(): int
    {
        $core = [
            ['key' => 'name', 'label' => 'Full name', 'type' => 'text', 'required' => true, 'maps_to' => 'name'],
            ['key' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true, 'maps_to' => 'email'],
            ['key' => 'phone', 'label' => 'Phone', 'type' => 'phone', 'maps_to' => 'phone'],
            ['key' => 'organization', 'label' => 'Organisation', 'type' => 'text', 'maps_to' => 'organization'],
            ['key' => 'job_title', 'label' => 'Job title', 'type' => 'text', 'maps_to' => 'job_title'],
        ];

        $sets = [
            ['Conference & Summit', 'Delegates, sessions and a dinner.', [
                ...$core,
                ['key' => 'ticket_type', 'label' => 'Ticket type', 'type' => 'select', 'maps_to' => 'ticket_type',
                    'options' => ['Delegate', 'VIP', 'Speaker', 'Press']],
                ['key' => 'dietary', 'label' => 'Dietary requirements', 'type' => 'text', 'maps_to' => 'dietary',
                    'help' => 'Allergies or preferences we should tell the kitchen about.'],
                ['key' => 'attending_dinner', 'label' => 'Will you attend the gala dinner?', 'type' => 'select',
                    'options' => ['Yes', 'No'], 'required' => true],
                ['key' => 'arrival_flight', 'label' => 'Arrival flight number', 'type' => 'text',
                    'placeholder' => 'RJ 112', 'help' => 'So we can arrange your airport pick-up.'],
            ]],

            ['Workshop & Training', 'A small room, a track, and a certificate.', [
                ...$core,
                ['key' => 'track', 'label' => 'Which track will you join?', 'type' => 'select',
                    'options' => ['Track A', 'Track B', 'Track C'], 'required' => true],
                ['key' => 'experience', 'label' => 'How much experience do you have in this area?', 'type' => 'select',
                    'options' => ['New to it', 'Some', 'A great deal']],
                ['key' => 'certificate_name', 'label' => 'Name as it should appear on your certificate', 'type' => 'text',
                    'help' => 'Leave blank to use your full name.'],
            ]],

            ['Gala & Ceremony', 'Seating, guests and what people can eat.', [
                ...$core,
                ['key' => 'guests', 'label' => 'How many guests are coming with you?', 'type' => 'number',
                    'help' => 'Including yourself.'],
                ['key' => 'dietary', 'label' => 'Dietary requirements', 'type' => 'text', 'maps_to' => 'dietary'],
                ['key' => 'seating_request', 'label' => 'Anyone you would like to be seated with?', 'type' => 'textarea'],
            ]],
        ];

        $made = 0;

        foreach ($sets as $i => [$name, $note, $fields]) {
            $template = static::firstOrCreate(['name' => $name], [
                'note' => $note,
                'fields' => $fields,
                'position' => $i,
            ]);

            $made += $template->wasRecentlyCreated ? 1 : 0;
        }

        return $made;
    }
}
