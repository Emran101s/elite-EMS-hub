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
     * types: kv | text | bullets | kpi | twocol | approval
     */
    public const SECTIONS = [
        'event_info' => ['1', 'Event Information', 'kv'],
        'exec_summary' => ['2', 'Executive Summary', 'text'],
        'objectives' => ['3', 'Objectives', 'bullets'],
        'kpis' => ['4', 'Success Metrics / KPIs', 'kpi'],
        'audience' => ['5', 'Audience Profile', 'bullets'],
        'components' => ['6', 'Event Components', 'twocol'],
        'stakeholders' => ['7', 'Key Stakeholders', 'twocol'],
        'venue' => ['8', 'Venue Requirements', 'twocol'],
        'branding' => ['9', 'Branding & Creative Requirements', 'bullets'],
        'operational' => ['10', 'Operational Requirements', 'twocol'],
        'risks' => ['11', 'Risk Overview', 'twocol'],
        'budget' => ['12', 'Budget Summary', 'twocol'],
        'milestones' => ['13', 'Key Milestones', 'twocol'],
        'governance' => ['14', 'Project Governance', 'twocol'],
        'approval' => ['15', 'Approval', 'approval'],
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
     * Seed a brief: one shared 16-section structure, with the content set of the
     * chosen template (conference | exhibition | workshop | gala | festival).
     */
    public static function defaultData(Event $event, string $template = 'conference'): array
    {
        $t = BriefTemplates::content($template, $event->name);

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
                'how_to' => 'Use this Event Brief in Phase 1 — Initiation & Strategy. Once approved, the Event Brief should drive the project plan, budget structure, milestones, participant targets, sponsorship plan, registration setup, and supplier scope of work.',
            ],
            'event_info' => [
                'name' => $event->name,
                'type' => $t['type'],
                'format' => 'In-Person',
                'dates' => $dates,
                'location' => $location ?: 'Amman, Jordan',
                'venue' => $event->venue?->name ?? 'To be confirmed',
                'attendance' => '800 participants',
                'language' => 'English / Arabic',
                'owner' => $event->client?->name ?? 'Organizing Committee',
                'organizer' => 'Elite Business Hub',
            ],
            'exec_summary' => $t['exec_summary'],
            'objectives' => $t['objectives'],
            'kpis' => $t['kpis'],
            'audience' => $t['audience'],
            'components' => $t['components'],
            'stakeholders' => [
                ['area' => 'Client', 'notes' => $event->client?->name ?? 'Organizing Committee'],
                ['area' => 'Organizer', 'notes' => 'Elite Business Hub'],
                ['area' => 'Strategic Partners', 'notes' => 'Government entities, development partners, international organizations, and private sector partners.'],
                ['area' => 'Sponsors', 'notes' => 'Strategic Partner, Airline Partner, Platinum, Gold, Silver, Tourism Partner, and Media Partner.'],
                ['area' => 'Internal Team', 'notes' => 'Project Director, Project Manager, Operations Lead, Production Lead, Registration Lead, Marketing Lead, Sponsorship Lead, Finance Lead.'],
            ],
            'venue' => $t['venue'],
            'branding' => [
                'Event identity and design direction.',
                'Brand guidelines including colors, typography, and logo usage.',
                'Event website or landing page visual direction.',
                'Social media toolkit and digital invitation template.',
                'Stage screen design, backdrops, signage, badges, lanyards, certificates, and printed agenda.',
                'Sponsor branding matrix and visibility plan.',
            ],
            'operational' => $t['operational'],
            'risks' => $t['risks'],
            'budget' => $t['budget'],
            'milestones' => [...$t['milestones'], ['area' => 'Event Dates', 'notes' => $dates]],
            'governance' => [
                ['area' => 'Project Sponsor', 'notes' => 'Executive Client Representative'],
                ['area' => 'Project Director', 'notes' => 'Elite Business Hub'],
                ['area' => 'Project Manager', 'notes' => $event->projectManager?->name ?? 'Assigned Event Manager'],
                ['area' => 'Workstream Leads', 'notes' => 'Operations, Production, Registration, Marketing, Sponsorship, Finance, Supplier Coordination, Protocol.'],
                ['area' => 'Reporting Rhythm', 'notes' => 'Weekly planning meeting, milestone review meetings, and daily operational briefings during event execution.'],
                ['area' => 'Approval Process', 'notes' => 'Client approval required for scope, budget, venue, branding, production design, final agenda, and final report.'],
            ],
            'approval' => [
                ['name' => '', 'title' => 'Client Representative — Project Sponsor'],
                ['name' => 'Elite Business Hub', 'title' => 'Project Director'],
                ['name' => $event->projectManager?->name ?? '', 'title' => 'Project Manager'],
            ],
        ];
    }

    public function slug(): string
    {
        return Str::slug($this->event->name).'-event-brief';
    }
}
