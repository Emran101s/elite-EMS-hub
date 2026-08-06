<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * One question on an event's registration form.
 *
 * The form used to be seven fixed columns, so every event asked exactly the
 * same things and an event needing a passport number or a workshop track sent
 * people back to a spreadsheet.
 *
 * A field either fills a core attendee column (`maps_to`) or files its answer
 * under its key. Core columns are the ones the rest of the platform depends on
 * — the badge prints them, check-in matches on them, and the duplicate check
 * compares them — so they are named here rather than left to whoever builds a
 * form on a Friday.
 */
class RegistrationField extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'event_id', 'key', 'label', 'type', 'required',
        'options', 'help', 'placeholder', 'maps_to', 'position', 'active'];

    protected $casts = [
        'options' => 'array',
        'required' => 'boolean',
        'active' => 'boolean',
        'position' => 'integer',
    ];

    /** type => [label, takes options, what it is for] */
    public const TYPES = [
        'text' => ['Short text', false, 'A line — a name, a title, a number.'],
        'textarea' => ['Long text', false, 'A paragraph — notes, requirements.'],
        'email' => ['Email', false, 'Checked as an address.'],
        'phone' => ['Phone', false, 'A number people can be reached on.'],
        'number' => ['Number', false, 'Digits only — a count, an age.'],
        'date' => ['Date', false, 'A day — an arrival, a birthday.'],
        'select' => ['Choose one', true, 'A list, one answer.'],
        'multiselect' => ['Choose several', true, 'A list, any number of answers.'],
        'checkbox' => ['Yes / no', false, 'A single tick — consent, a preference.'],

        // The choices are this event's agenda, read live. A session added
        // tomorrow is on the form tomorrow, without anybody re-editing it.
        'sessions' => ['Sessions', false, 'Pick from this event\'s agenda — seats are counted and a full session closes.'],
    ];

    /**
     * The attendee columns a question may fill.
     *
     * Everything else on the form becomes an answer. These are here because
     * the platform reads them by name: name and email identify a person,
     * ticket_type prices and sorts them, the rest print on badges and lists.
     *
     * @var array<string,string>
     */
    public const CORE_COLUMNS = [
        'name' => 'Full name',
        'email' => 'Email address',
        'phone' => 'Phone',
        'organization' => 'Organisation',
        'job_title' => 'Job title',
        'ticket_type' => 'Ticket type',
        'dietary' => 'Dietary requirements',
        'notes' => 'Notes',
    ];

    /** Without these two nobody can be identified, badged or checked in. */
    public const REQUIRED_CORE = ['name', 'email'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function takesOptions(): bool
    {
        return self::TYPES[$this->type][1] ?? false;
    }

    /** A question answered with seats rather than words. */
    public function isSessions(): bool
    {
        return $this->type === 'sessions';
    }

    /**
     * The sessions this question offers.
     *
     * Read from the agenda every time rather than copied into `options`: a
     * session added, renamed or moved after the form was built would otherwise
     * be offered under a name that no longer exists, or not offered at all.
     */
    public function sessionChoices(): Collection
    {
        if (! $this->isSessions()) {
            return collect();
        }

        return EventAgendaSession::where('event_id', $this->event_id)
            ->with(['day', 'attendees'])
            ->orderBy('agenda_day_id')->orderBy('sort')->orderBy('starts_at')
            ->get();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? 'Short text';
    }

    /** A question that fills a column the platform reads by name. */
    public function isCore(): bool
    {
        return $this->maps_to !== null;
    }

    /** Core questions the form cannot do without — they may not be removed. */
    public function isLocked(): bool
    {
        return in_array($this->maps_to, self::REQUIRED_CORE, true);
    }

    /**
     * The validation this question imposes on an answer.
     *
     * @return list<string>
     */
    public function rules(): array
    {
        $rules = [$this->required ? 'required' : 'nullable'];

        $rules[] = match ($this->type) {
            'email' => 'email',
            'number' => 'numeric',
            'date' => 'date',
            'checkbox' => 'boolean',
            'multiselect', 'sessions' => 'array',
            default => 'string',
        };

        if (in_array($this->type, ['text', 'phone'], true)) {
            $rules[] = 'max:190';
        }

        if ($this->type === 'textarea') {
            $rules[] = 'max:2000';
        }

        // A choice has to be one of the choices offered — otherwise the list
        // is decoration and anything at all can arrive in it.
        if ($this->type === 'select' && $this->options) {
            $rules[] = 'in:'.implode(',', $this->options);
        }

        return $rules;
    }

    /**
     * A key that is stable, unique on the event, and readable in an export.
     *
     * Derived from the label once, at creation. Renaming a question afterwards
     * leaves the key alone: it is what every answer already given is filed
     * under, and changing it would orphan every one of them.
     */
    public static function keyFor(Event $event, string $label): string
    {
        $base = Str::of($label)->slug('_')->limit(40, '')->toString() ?: 'question';
        $key = $base;
        $n = 2;

        while ($event->registrationFields()->where('key', $key)->exists()) {
            $key = $base.'_'.$n++;
        }

        return $key;
    }
}
