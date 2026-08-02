<?php

namespace App\Livewire;

use App\Models\CompanyProfile;
use App\Models\Event;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The one page in the platform a stranger is meant to reach.
 *
 * Attendees could only be typed in or imported from a spreadsheet, which meant
 * somebody was retyping a form somebody else had already filled in. This is
 * that form, published.
 *
 * Everything here assumes the visitor is not signed in and never will be: the
 * event is found by token, nothing about the platform is revealed beyond the
 * event's own name and dates, and a full or closed registration says so
 * plainly rather than showing a form that will not work.
 */
#[Layout('components.layouts.guest', ['width' => 'max-w-xl'])]
class PublicRegistration extends Component
{
    public Event $event;

    /**
     * What the visitor has typed, keyed by question.
     *
     * One array rather than a property per question: the form is whatever the
     * event asks, and an event that asks for a passport number cannot have a
     * property declared for it here in advance.
     *
     * @var array<string,mixed>
     */
    public array $form = [];

    /** Set once the form has been accepted — the page becomes a receipt. */
    public ?string $reference = null;

    public function mount(string $token): void
    {
        $this->event = Event::where('registration_token', $token)->firstOrFail();

        foreach ($this->fields() as $field) {
            $this->form[$field->key] = match ($field->type) {
                'multiselect' => [],
                'checkbox' => false,
                // A one-choice list with nothing chosen is a question the
                // visitor has to answer before they have read it.
                'select' => $field->key === 'ticket_type' ? (string) ($field->options[0] ?? '') : '',
                default => '',
            };
        }
    }

    /** The questions this event asks — see Event::registrationForm(). */
    public function fields()
    {
        return $this->event->registrationForm();
    }

    /** The ticket types the company offers — editable in Defaults & Templates. */
    public function ticketTypes(): array
    {
        return CompanyProfile::current()->ticketTypes();
    }

    public function register(): void
    {
        // Open, not full, not archived — checked again here and not only in the
        // view, because the page may have been sitting open for an hour.
        if (! $this->event->fresh()->registrationIsLive()) {
            $this->addError('form.name', 'Registration for this event has closed.');

            return;
        }

        // A public form with no session behind it: one visitor should not be
        // able to fill the list on their own.
        $key = 'register:'.$this->event->id.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('form.name', 'Too many registrations from here just now. Try again in a few minutes.');

            return;
        }

        // Every question brings its own rules — see RegistrationField::rules().
        $fields = $this->fields();

        $this->validate(
            $fields->mapWithKeys(fn ($f) => ['form.'.$f->key => $f->rules()])->all(),
            [],
            $fields->mapWithKeys(fn ($f) => ['form.'.$f->key => mb_strtolower($f->label)])->all(),
        );

        // A question either fills a column the platform reads by name, or its
        // answer is filed under its key. Nothing else needs deciding here.
        $columns = [];
        $answers = [];

        foreach ($fields as $field) {
            $value = $this->form[$field->key] ?? null;

            if ($field->maps_to) {
                $columns[$field->maps_to] = is_array($value) ? implode(', ', $value) : $value;
            } else {
                $answers[$field->key] = $value;
            }
        }

        // Registering twice with the same address updates the earlier one
        // rather than making a second badge for the same person.
        $existing = $this->event->attendees()
            ->whereRaw('lower(email) = ?', [mb_strtolower((string) ($columns['email'] ?? ''))])
            ->first();

        $data = $columns + ['answers' => $answers];

        $attendee = $existing
            ? tap($existing)->update($data + ['status' => $existing->status === 'cancelled' ? 'registered' : $existing->status])
            : $this->event->attendees()->create($data + ['status' => 'registered']);

        RateLimiter::hit($key, 900);

        $this->reference = $attendee->reference();
    }

    public function render()
    {
        return view('livewire.public-registration', [
            'live' => $this->event->registrationIsLive(),
            'full' => $this->event->registrationIsFull(),
            'fields' => $this->fields(),
            'types' => $this->ticketTypes(),
        ])->title('Register · '.$this->event->name);
    }
}
