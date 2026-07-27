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

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $organization = '';

    public string $job_title = '';

    public string $ticket_type = '';

    public string $dietary = '';

    /** Set once the form has been accepted — the page becomes a receipt. */
    public ?string $reference = null;

    public function mount(string $token): void
    {
        $this->event = Event::where('registration_token', $token)->firstOrFail();
        $this->ticket_type = (string) ($this->ticketTypes()[0] ?? '');
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
            $this->addError('name', 'Registration for this event has closed.');

            return;
        }

        // A public form with no session behind it: one visitor should not be
        // able to fill the list on their own.
        $key = 'register:'.$this->event->id.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('name', 'Too many registrations from here just now. Try again in a few minutes.');

            return;
        }

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'organization' => ['nullable', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'ticket_type' => ['nullable', 'string', 'max:80'],
            'dietary' => ['nullable', 'string', 'max:160'],
        ]);

        // Registering twice with the same address updates the earlier one
        // rather than making a second badge for the same person.
        $existing = $this->event->attendees()
            ->whereRaw('lower(email) = ?', [mb_strtolower($data['email'])])
            ->first();

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
            'types' => $this->ticketTypes(),
        ])->title('Register · '.$this->event->name);
    }
}
