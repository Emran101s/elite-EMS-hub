<?php

namespace App\Models;

use App\Support\BriefTemplates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EventBrief extends Model
{
    protected $fillable = ['event_id', 'template', 'status', 'version', 'data', 'approved_at'];

    protected $casts = [
        'data' => 'array',
        'approved_at' => 'datetime',
    ];

    /**
     * Section schema: key => [number, title, type].
     * types: kv | text | bullets | kpi | twocol
     */
    public const SECTIONS = [
        'overview' => ['1', 'Event Overview', 'text'],
        'event_info' => ['2', 'Event Details', 'kv'],
        'stakeholders' => ['3', 'Stakeholders', 'twocol'],
        'audience' => ['4', 'Audience & Participants', 'bullets'],
        'components' => ['5', 'Event Components', 'twocol'],
        'venue' => ['6', 'Venue Requirements', 'twocol'],
        'branding' => ['7', 'Branding & Production', 'twocol'],
        'registration' => ['8', 'Registration & Participants', 'twocol'],
        'sponsors' => ['9', 'Sponsors & Partners', 'twocol'],
        'budget' => ['10', 'Budget Overview', 'twocol'],
        'risks' => ['11', 'Risks & Special Requirements', 'twocol'],
        'success' => ['12', 'Success Criteria', 'kpi'],
    ];

    /** Event Information fields: slug => label. */
    public const INFO_FIELDS = [
        'name' => 'Event Name',
        'type' => 'Event Type',
        'format' => 'Event Format',
        'dates' => 'Event Dates',
        'location' => 'Location',
        'venue' => 'Venue',
        'attendance' => 'Expected Attendance',
        'language' => 'Primary Language',
        'owner' => 'Event Owner',
        'organizer' => 'Event Organizer',
    ];

    /** Column headers for two-column sections. */
    public const TWOCOL_HEADS = ['Requirement Area', 'Required Items / Notes'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public static function forEvent(Event $event): self
    {
        $template = BriefTemplates::forEventType($event->type);

        return static::firstOrCreate(
            ['event_id' => $event->id],
            ['template' => $template, 'status' => 'draft', 'version' => '1.0', 'data' => static::defaultData($event, $template)],
        );
    }

    /**
     * Seed a brief: one shared 12-section structure, with the content set of the
     * chosen template (conference | exhibition | workshop | gala | festival).
     */
    public static function defaultData(Event $event, string $template = 'conference'): array
    {
        $t = BriefTemplates::content($template, $event->name);
        $client = $event->client?->name ?? 'Organizing Committee';

        $location = trim(($event->city ? $event->city.', ' : '').($event->country ?? ''), ', ');
        $dates = $event->starts_at && $event->ends_at
            ? $event->starts_at->format('j').'–'.$event->ends_at->format('j M Y')
            : ($event->starts_at?->format('j M Y') ?? 'TBC');

        return [
            'meta' => [
                'subtitle' => 'Professional event project brief and single source of truth',
                'prepared_for' => $event->client?->name ?? 'Client / Organizing Committee',
                'prepared_by' => 'Elite Business Hub',
                'purpose' => 'Initial event project brief and single source of truth',
                'confidentiality' => 'Confidential',
                'how_to' => 'Use this Event Brief as the single source of truth for the event. Once approved, it drives the project plan, budget structure, participant targets, sponsorship plan, registration setup, and supplier scope of work.',
            ],

            // 1 · Event Overview
            'overview' => $t['overview'],

            // 2 · Event Details
            'event_info' => [
                'name' => $event->name,
                'type' => $t['type'],
                'format' => 'In-Person',
                'dates' => $dates,
                'location' => $location ?: 'Amman, Jordan',
                'venue' => $event->venue?->name ?? 'To be confirmed',
                'attendance' => '800 participants',
                'language' => 'English / Arabic',
                'owner' => $client,
                'organizer' => 'Elite Business Hub',
            ],

            // 3 · Stakeholders
            'stakeholders' => [
                ['area' => 'Client', 'notes' => $client],
                ['area' => 'Organizer', 'notes' => 'Elite Business Hub'],
                ['area' => 'Project Manager', 'notes' => $event->projectManager?->name ?? 'Assigned Event Manager'],
                ['area' => 'Strategic Partners', 'notes' => 'Government entities, development partners, international organizations, and private sector partners.'],
                ['area' => 'Internal Team', 'notes' => 'Project Director, Operations Lead, Production Lead, Registration Lead, Marketing Lead, Sponsorship Lead, Finance Lead.'],
            ],

            // 4 · Audience & Participants
            'audience' => $t['audience'],

            // 5 · Event Components
            'components' => $t['components'],

            // 6 · Venue Requirements
            'venue' => $t['venue'],

            // 7 · Branding & Production
            'branding' => [
                ['area' => 'Event Identity', 'notes' => 'Event name, theme, key visual, colour palette, typography, and logo usage guidelines.'],
                ['area' => 'Collateral', 'notes' => 'Badges, lanyards, invitations, signage, backdrops, printed agenda, and certificates.'],
                ['area' => 'Digital', 'notes' => 'Event website / landing page, social media toolkit, and digital invitation templates.'],
                ['area' => 'Stage & Production', 'notes' => 'Stage design, LED screens, lighting, sound, livestreaming, show calling, cue sheet, and technical rehearsals.'],
                ['area' => 'Sponsor Visibility', 'notes' => 'Sponsor branding matrix and on-site visibility plan across zones and materials.'],
            ],

            // 8 · Registration & Participants
            'registration' => [
                ['area' => 'Registration System', 'notes' => 'Online registration, approval workflow, payment processing (if applicable), and a live registration dashboard.'],
                ['area' => 'Check-in', 'notes' => 'QR code generation, on-site scanning, badge printing, and a dedicated VIP registration lane.'],
                ['area' => 'Participant Lists', 'notes' => 'Master participant list, VIP list, speaker list, media list, and exhibitor list.'],
                ['area' => 'Communication', 'notes' => 'Confirmation and reminder emails, agenda and venue information, and event updates.'],
                ['area' => 'Hospitality', 'notes' => 'Catering, accommodation, transportation, dietary needs, and accessibility support.'],
            ],

            // 9 · Sponsors & Partners
            'sponsors' => [
                ['area' => 'Sponsorship Tiers', 'notes' => 'Strategic Partner, Platinum, Gold, Silver, plus category partners (Airline, Tourism, Media).'],
                ['area' => 'Prospecting', 'notes' => 'Target sponsor pipeline, outreach plan, meetings, and contracting.'],
                ['area' => 'Deliverables', 'notes' => 'Sponsor deliverables matrix — branding, speaking slots, passes, booth, and digital visibility.'],
                ['area' => 'Strategic Partners', 'notes' => 'Government, institutional, and knowledge partners supporting the event.'],
            ],

            // 10 · Budget Overview
            'budget' => $t['budget'],

            // 11 · Risks & Special Requirements
            'risks' => $t['risks'],

            // 12 · Success Criteria
            'success' => $t['success'],
        ];
    }

    public function slug(): string
    {
        return Str::slug($this->event->name).'-event-brief';
    }
}
