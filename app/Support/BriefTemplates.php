<?php

namespace App\Support;

/**
 * Content sets for the 5 Event Brief templates.
 *
 * All templates share ONE design + the same 12-section spine (see EventBrief::SECTIONS).
 * defaultData() reads: type, overview, audience, components, venue, budget, risks, success.
 * (Older 'objectives' / 'operational' / 'milestones' keys remain in the arrays below but
 * are no longer consumed — the trimmed 12-section brief ignores them.)
 */
class BriefTemplates
{
    public const TEMPLATES = [
        'conference' => 'Conference & Summit',
        'exhibition' => 'Exhibition & Expo',
        'workshop' => 'Workshop & Training',
        'gala' => 'Gala Dinner & Awards',
        'festival' => 'Festival & Public Event',
    ];

    /** Map an event's `type` to the closest template. */
    public static function forEventType(?string $type): string
    {
        return match (strtolower((string) $type)) {
            'exhibition', 'expo', 'trade_show' => 'exhibition',
            'workshop', 'training' => 'workshop',
            'gala', 'dinner', 'awards' => 'gala',
            'festival', 'public', 'concert' => 'festival',
            default => 'conference',
        };
    }

    public static function key(string $key): string
    {
        return array_key_exists($key, self::TEMPLATES) ? $key : 'conference';
    }

    /** Template-specific section content. */
    public static function content(string $key, string $eventName): array
    {
        return match (self::key($key)) {
            'exhibition' => self::exhibition($eventName),
            'workshop' => self::workshop($eventName),
            'gala' => self::gala($eventName),
            'festival' => self::festival($eventName),
            default => self::conference($eventName),
        };
    }

    private static function two(array $pairs): array
    {
        return array_map(fn ($p) => ['area' => $p[0], 'notes' => $p[1]], $pairs);
    }

    private static function kpi(array $pairs): array
    {
        return array_map(fn ($p) => ['kpi' => $p[0], 'target' => $p[1]], $pairs);
    }

    // ─────────────────────────── 1 · CONFERENCE & SUMMIT ───────────────────────────
    private static function conference(string $name): array
    {
        return [
            'type' => 'International Summit & Conference',
            'overview' => "The {$name} is a high-level regional platform bringing together government leaders, policymakers, "
                .'international organizations, academia, private sector representatives, and development partners. The summit will feature '
                .'keynote addresses, plenary sessions, parallel knowledge tracks, workshops, an exhibition of partner initiatives, a VIP '
                .'programme, and structured networking. This Event Brief defines the project direction, success measures, operational '
                .'requirements, and governance structure required to begin detailed planning.',
            'objectives' => [
                'Facilitate regional dialogue and strategic knowledge exchange.',
                'Share best practices, case studies, and success stories.',
                'Promote innovation, digital transformation, and sector modernization.',
                'Strengthen partnerships between public, private, and international stakeholders.',
                'Generate meaningful media visibility and stakeholder engagement.',
                'Attract sponsors, exhibitors, and strategic partners.',
            ],
            'success' => self::kpi([
                ['Registered Delegates', '1,500'], ['Actual Attendance', '90%+'], ['VIP Attendance', '120'],
                ['Speakers', '40'], ['Sponsors', '15'], ['Session Attendance', '75%+'],
                ['Participant Satisfaction', '4.6 / 5'], ['Media Mentions', '100+'], ['Net Promoter Score', '60+'],
            ]),
            'audience' => [
                'Government officials and senior decision makers.',
                'International organizations and development agencies.',
                'Diplomatic corps, embassies, and protocol guests.',
                'Private sector leaders and technology companies.',
                'Academics, researchers, NGOs, and think tanks.',
                'Media representatives, sponsors, exhibitors, and speakers.',
            ],
            'components' => self::two([
                ['Plenary Programme', 'Opening ceremony, keynote addresses, high-level plenaries, and closing session.'],
                ['Parallel Tracks', 'Breakout rooms running concurrent knowledge tracks, panels, and technical sessions.'],
                ['Workshops', 'Capacity-building sessions, interactive roundtables, and deep-dive clinics.'],
                ['Exhibition', 'Partner showcase, sponsor booths, innovation area, and government stands.'],
                ['Networking & VIP', 'VIP lounge, receptions, bilateral meetings, coffee breaks, and gala dinner.'],
            ]),
            'venue' => self::two([
                ['Main Plenary Hall', 'Theatre capacity for full delegation, stage, LED wall, premium lighting, sound, interpretation, and livestreaming.'],
                ['Breakout Rooms', 'Multiple parallel rooms with screens, sound, microphones, signage, and layout per session format.'],
                ['Registration Area', 'Branded counters, QR scanning, badge printing, help desk, and a dedicated VIP lane.'],
                ['Exhibition Area', 'Booths, visitor flow, power access, branding points, cleaning, and security coverage.'],
                ['Support Areas', 'VIP lounge, speaker preparation room, media room, organizer office, storage, and backstage.'],
                ['Interpretation', 'Simultaneous interpretation booths, receivers, and technician support.'],
            ]),
            'operational' => self::two([
                ['Registration', 'Online registration, approval workflow, QR check-in, live attendance dashboard, and onsite badge printing.'],
                ['Speaker Management', 'Outreach, confirmations, bios and photos, briefing pack, prep room, and rehearsal schedule.'],
                ['Production', 'Stage design, LED screens, sound, lighting, livestreaming, show calling, cue sheet, and technical rehearsals.'],
                ['Hospitality', 'Catering, accommodation, airport transfers, VIP transport, dietary needs, and accessibility support.'],
                ['Media & PR', 'Press accreditation, media room, interviews, photography, and post-event media pack.'],
                ['Security & Safety', 'Access control, VIP protocol, emergency procedures, crowd flow, and first-aid coordination.'],
            ]),
            'risks' => self::two([
                ['Low Registration Conversion', 'Start the participant list early, launch invitation waves, monitor RSVP conversion, and activate partners.'],
                ['Keynote / VIP Cancellation', 'Maintain backup speakers, confirm early, and prepare an alternative session format.'],
                ['Technical or Livestream Failure', 'Backup equipment, full technical rehearsal, redundant files, and onsite technical support.'],
                ['Agenda Overrun', 'Detailed run-of-show, a show caller, and enforced session timings.'],
                ['Sponsor Deliverable Slippage', 'Sponsor pipeline, clear contracts, a deliverables tracker, and an escalation process.'],
            ]),
            'budget' => self::two([
                ['Venue & Rooms', 'TBD'], ['AV & Production', 'TBD'], ['Interpretation', 'TBD'],
                ['Speakers & Travel', 'TBD'], ['Registration Technology', 'TBD'], ['Catering', 'TBD'],
                ['Branding & Printing', 'TBD'], ['Marketing & Digital', 'TBD'], ['Exhibition Build', 'TBD'],
                ['Transportation', 'TBD'], ['Contingency', 'TBD'],
            ]),
            'milestones' => self::two([
                ['Event Brief Approval', 'TBC'], ['Venue Confirmation', 'TBC'], ['Speaker Line-up Locked', 'TBC'],
                ['Sponsorship Launch', 'TBC'], ['Registration Launch', 'TBC'], ['Agenda Published', 'TBC'],
                ['Final Delegate List & Badges', 'TBC'], ['Technical Rehearsal', 'TBC'], ['Final Report Submission', 'TBC'],
            ]),
        ];
    }

    // ─────────────────────────── 2 · EXHIBITION & EXPO ───────────────────────────
    private static function exhibition(string $name): array
    {
        return [
            'type' => 'Exhibition & Expo',
            'overview' => "The {$name} is a business-to-business exhibition bringing together exhibitors, sponsors, government "
                .'entities, and trade visitors around a curated show floor. The event combines a booth-based exhibition, an innovation and '
                .'demo zone, partner activations, and a structured lead-generation and business-matchmaking programme. This Event Brief '
                .'defines the show concept, commercial targets, floor and operational requirements, and the governance needed to begin '
                .'exhibitor sales and build planning.',
            'objectives' => [
                'Deliver a commercially successful show floor with high booth occupancy.',
                'Attract qualified trade visitors and decision-making buyers.',
                'Generate measurable leads and business meetings for exhibitors.',
                'Showcase innovation, government initiatives, and partner solutions.',
                'Secure sponsorship and partner activation revenue.',
                'Position the exhibition as the sector’s leading annual marketplace.',
            ],
            'audience' => [
                'Exhibitors, sponsors, and partner organizations.',
                'Trade visitors, buyers, and procurement decision makers.',
                'Government entities and regulatory bodies.',
                'Investors, distributors, and channel partners.',
                'Industry associations, media, and analysts.',
                'Start-ups, innovators, and technology providers.',
            ],
            'success' => self::kpi([
                ['Exhibitors', '120'], ['Trade Visitors', '6,000'], ['Booth Occupancy', '95%+'],
                ['Leads Generated', '4,000+'], ['Business Meetings', '500+'], ['Booth Revenue', 'Target'],
                ['Exhibitor Satisfaction', '4.5 / 5'], ['Sponsor Satisfaction', '4.5 / 5'], ['Media Mentions', '80+'],
            ]),
            'components' => self::two([
                ['Exhibition Hall', 'Main show floor with shell-scheme and custom booths, aisles, and visitor flow design.'],
                ['Sponsor & Government Zones', 'Premium sponsor booths, government showcase, and pavilion areas.'],
                ['Innovation & Demo Zone', 'Start-up area, live demonstrations, and product launch stage.'],
                ['Business Matchmaking', 'Meeting pods, pre-scheduled B2B meetings, and networking lounge.'],
                ['Visitor Journey', 'Registration desks, information desk, wayfinding, and lead-capture scanning.'],
            ]),
            'venue' => self::two([
                ['Exhibition Hall', 'Total floor area, ceiling height, floor loading, rigging points, and hall access.'],
                ['Booth Packages', 'Standard shell scheme (fascia, power, lighting, furniture) and custom build specification.'],
                ['Registration & Info', 'Visitor registration counters, QR scanning, badge printing, and information desk.'],
                ['Utilities', 'Power distribution per booth, internet and Wi-Fi capacity, water and compressed air where required.'],
                ['Logistics Areas', 'Loading bays, freight access, storage, and empty-crate handling.'],
                ['Support & Safety', 'Cleaning, security, fire safety compliance, first aid, and emergency egress.'],
            ]),
            'operational' => self::two([
                ['Exhibitor Onboarding', 'Exhibitor manual, contracts, booth allocation, deadlines, and an exhibitor services portal.'],
                ['Move-in / Move-out', 'Build and dismantle schedule, vehicle marshalling, dock slots, and contractor accreditation.'],
                ['Floorplan Management', 'Floorplan design, fire-safety approval, booth numbering, and space allocation.'],
                ['Visitor Acquisition', 'Marketing campaign, pre-registration, buyer invitations, and visitor promotion.'],
                ['Lead Capture', 'Scanning devices or app, exhibitor lead reports, and post-show data delivery.'],
                ['Security & Safety', 'Overnight security, access control, fire marshals, and incident escalation.'],
            ]),
            'risks' => self::two([
                ['Low Booth Sales', 'Early sales launch, tiered pricing, partner channels, and an active exhibitor pipeline.'],
                ['Low Visitor Turnout', 'Targeted buyer invitation campaign, association partnerships, and pre-registration tracking.'],
                ['Floorplan / Fire-Code Rejection', 'Early authority submission, certified contractors, and compliance review.'],
                ['Move-in Congestion', 'Staggered dock slots, marshalling plan, and enforced build schedule.'],
                ['Power or Internet Failure', 'Redundant supply, backup generator, tested distribution, and onsite technicians.'],
            ]),
            'budget' => self::two([
                ['Hall Rental', 'TBD'], ['Booth Build (Shell & Custom)', 'TBD'], ['Floor, Rigging & Signage', 'TBD'],
                ['Power & Internet', 'TBD'], ['Registration & Lead Capture', 'TBD'], ['Security & Cleaning', 'TBD'],
                ['Visitor Marketing', 'TBD'], ['Sponsor Servicing', 'TBD'], ['Logistics & Handling', 'TBD'],
                ['Contingency', 'TBD'],
            ]),
            'milestones' => self::two([
                ['Event Brief Approval', 'TBC'], ['Hall & Dates Confirmed', 'TBC'], ['Floorplan v1 Issued', 'TBC'],
                ['Exhibitor Sales Open', 'TBC'], ['Sponsorship Packages Closed', 'TBC'], ['Floorplan Frozen', 'TBC'],
                ['Exhibitor Manual Issued', 'TBC'], ['Move-in & Build', 'TBC'], ['Show Days', 'TBC'],
                ['Move-out & Post-Show Report', 'TBC'],
            ]),
        ];
    }

    // ─────────────────────────── 3 · WORKSHOP & TRAINING ───────────────────────────
    private static function workshop(string $name): array
    {
        return [
            'type' => 'Workshop & Training Programme',
            'overview' => "The {$name} is a structured capacity-building programme designed to deliver measurable learning outcomes "
                .'for a defined participant group. The programme combines expert facilitation, interactive exercises, group work, and '
                .'assessment, with certification on completion. This Event Brief defines the learning objectives, participant profile, '
                .'delivery methodology, materials, evaluation approach, and the operational requirements needed to deliver the training.',
            'objectives' => [
                'Deliver clearly defined, measurable learning outcomes.',
                'Build practical capability participants can apply immediately.',
                'Ensure high engagement through interactive, hands-on methodology.',
                'Assess knowledge improvement from pre- to post-training.',
                'Certify participants who complete the programme.',
                'Capture evaluation data to improve future cohorts.',
            ],
            'audience' => [
                'Nominated staff and target beneficiaries of the programme.',
                'Technical practitioners and subject-matter teams.',
                'Middle management and team leads.',
                'Partner-organization delegates and sponsored participants.',
                'Observers, mentors, and programme stakeholders.',
            ],
            'success' => self::kpi([
                ['Enrolled Participants', '40'], ['Attendance Rate', '95%+'], ['Completion Rate', '90%+'],
                ['Knowledge Improvement', '+30%'], ['Participant Satisfaction', '4.6 / 5'], ['Trainer Rating', '4.7 / 5'],
                ['Certificates Issued', '100%'], ['Assessment Pass Rate', '85%+'], ['Learning Outcomes Met', '90%+'],
            ]),
            'components' => self::two([
                ['Curriculum', 'Session structure, modules, learning objectives, and daily agenda.'],
                ['Facilitation', 'Lead trainer and co-facilitator profiles, delivery methodology, and briefing.'],
                ['Interactive Work', 'Breakout groups, case studies, roleplay, simulations, and practical exercises.'],
                ['Assessment', 'Pre-assessment, in-session checks, post-assessment, and scoring rubric.'],
                ['Certification', 'Attendance tracking, certificate design, issuance, and record keeping.'],
            ]),
            'venue' => self::two([
                ['Training Room', 'Capacity for the cohort with a layout matched to methodology (classroom, U-shape, or roundtable).'],
                ['Breakout Space', 'Separate areas or zones for small-group work and syndicate exercises.'],
                ['Presentation Setup', 'Projector or screen, sound system, microphones, and presenter clicker.'],
                ['Training Materials', 'Flipcharts, whiteboards, stationery, printed workbooks, and a materials table.'],
                ['Participant Services', 'Name tags, attendance sheets, evaluation forms, and a help desk.'],
                ['Hospitality & Access', 'Coffee breaks, lunch, dietary requirements, and accessibility provision.'],
            ]),
            'operational' => self::two([
                ['Registration', 'Nomination and approval workflow, participant confirmation, and joining instructions.'],
                ['Materials Production', 'Workbook design, printing, digital copies, and distribution.'],
                ['Trainer Logistics', 'Contracting, travel, accommodation, briefing, and rehearsal.'],
                ['Assessment Delivery', 'Pre- and post-assessment distribution, marking, and results analysis.'],
                ['Certification', 'Eligibility rules, certificate generation, signatures, and issuance.'],
                ['Evaluation', 'Daily feedback, end-of-course evaluation, and a consolidated training report.'],
            ]),
            'risks' => self::two([
                ['Low Enrolment', 'Early nomination campaign, stakeholder follow-up, and a reserve waiting list.'],
                ['Trainer Unavailability', 'Contract early, confirm a backup facilitator, and maintain the full trainer pack.'],
                ['Material or Print Delay', 'Freeze content early, confirm print lead times, and hold digital backups.'],
                ['Low Completion Rate', 'Track attendance daily, follow up absentees, and enforce completion criteria.'],
                ['Assessment Integrity', 'Standardized rubric, invigilation, and consistent marking.'],
            ]),
            'budget' => self::two([
                ['Venue / Training Room', 'TBD'], ['Trainer Fees & Travel', 'TBD'], ['Materials & Printing', 'TBD'],
                ['Certificates', 'TBD'], ['AV & Equipment', 'TBD'], ['Catering (Breaks & Lunch)', 'TBD'],
                ['Assessment Platform', 'TBD'], ['Participant Transport', 'TBD'], ['Contingency', 'TBD'],
            ]),
            'milestones' => self::two([
                ['Event Brief Approval', 'TBC'], ['Curriculum Sign-off', 'TBC'], ['Trainer Confirmed', 'TBC'],
                ['Nominations Open', 'TBC'], ['Materials Finalized & Printed', 'TBC'], ['Pre-assessment Issued', 'TBC'],
                ['Delivery Days', 'TBC'], ['Post-assessment & Certificates', 'TBC'], ['Evaluation Report', 'TBC'],
            ]),
        ];
    }

    // ─────────────────────────── 4 · GALA DINNER & AWARDS ───────────────────────────
    private static function gala(string $name): array
    {
        return [
            'type' => 'Gala Dinner & Awards Ceremony',
            'overview' => "The {$name} is a premium evening event combining a formal reception, a seated dinner, and a stage "
                .'programme with awards and entertainment. The event is designed around the guest journey — arrival and welcome, reception, '
                .'dinner service, ceremony, and departure — with full protocol handling for VIP guests. This Event Brief defines the concept, '
                .'guest profile, seating and protocol requirements, ceremony flow, and the production standards required to deliver a flawless evening.',
            'objectives' => [
                'Deliver a flawless, premium guest experience from arrival to departure.',
                'Honour award recipients with a dignified, well-paced ceremony.',
                'Ensure full protocol compliance for VIP and dignitary guests.',
                'Showcase sponsors and partners with tasteful, high-value visibility.',
                'Generate strong photography, video, and media coverage.',
                'Strengthen relationships with key guests and stakeholders.',
            ],
            'audience' => [
                'VIP guests, dignitaries, and protocol attendees.',
                'Award nominees, recipients, and their guests.',
                'Client executives and board members.',
                'Sponsors, partners, and honoured guests.',
                'Media, photographers, and content teams.',
            ],
            'success' => self::kpi([
                ['Confirmed Guests', '400'], ['VIP Attendance', '60'], ['Seating Accuracy', '100%'],
                ['Programme Timing Accuracy', '±5 min'], ['Guest Satisfaction', '4.7 / 5'], ['Protocol Compliance', '100%'],
                ['Sponsor Visibility Delivered', '100%'], ['Media / Photo Coverage', 'Full'], ['Client Satisfaction', '4.8 / 5'],
            ]),
            'components' => self::two([
                ['Arrival Experience', 'Valet, red carpet, welcome hosts, photo wall, and guest reception line.'],
                ['Reception', 'Pre-dinner drinks, canapés, background music, and networking.'],
                ['Dinner Service', 'Seated dinner, menu, service timings, and table layout.'],
                ['Stage Programme', 'Ceremony flow, speeches, award presentations, and show calling.'],
                ['Entertainment', 'Live performance, background music, and audio-visual moments.'],
            ]),
            'venue' => self::two([
                ['Reception Area', 'Welcome space, photo wall, branding, drinks stations, and coat check.'],
                ['Dining Hall', 'Round or long tables, seating plan, place cards, menu cards, and centerpieces.'],
                ['Stage & Screen', 'Stage build, LED screen, podium, awards table, and trophy/gift table.'],
                ['Lighting & Sound', 'Ambient and stage lighting design, sound system, and microphones.'],
                ['VIP Provision', 'VIP seating, holding room, dedicated entrance, and protocol officer.'],
                ['Access & Transport', 'Valet parking, VIP transportation, drop-off flow, and ushers.'],
            ]),
            'operational' => self::two([
                ['Guest Management', 'Invitations, RSVP tracking, guest list, seating plan, and place cards.'],
                ['Protocol', 'Order of precedence, receiving line, seating protocol, and dignitary handling.'],
                ['Run of Show', 'Minute-by-minute ceremony script, cue sheet, show caller, and rehearsal.'],
                ['Catering', 'Menu tasting, service timings, dietary requirements, and beverage plan.'],
                ['Awards Production', 'Trophy design and production, citation scripts, and presentation sequence.'],
                ['Photography & Media', 'Photographer, videographer, shot list, media access, and post-event assets.'],
            ]),
            'risks' => self::two([
                ['VIP No-show or Late Change', 'Confirm attendance close to the event and hold a flexible seating contingency.'],
                ['Seating or Protocol Error', 'Verified seating plan, protocol sign-off, and briefed ushers.'],
                ['Programme Overrun', 'Strict run-of-show, a show caller, and speech time limits.'],
                ['Entertainment or AV Failure', 'Technical rehearsal, backup equipment, and standby audio.'],
                ['Catering Timing', 'Service rehearsal, agreed timings with the venue, and a floor manager.'],
            ]),
            'budget' => self::two([
                ['Venue & Food and Beverage', 'TBD'], ['Stage, AV & Lighting', 'TBD'], ['Entertainment & Talent', 'TBD'],
                ['Awards & Trophies', 'TBD'], ['Décor & Centerpieces', 'TBD'], ['Print (Menus, Place Cards)', 'TBD'],
                ['Photography & Video', 'TBD'], ['Protocol & Ushering', 'TBD'], ['Transportation & Valet', 'TBD'],
                ['Contingency', 'TBD'],
            ]),
            'milestones' => self::two([
                ['Event Brief Approval', 'TBC'], ['Venue Confirmation', 'TBC'], ['Concept & Run-of-Show v1', 'TBC'],
                ['Invitations Issued', 'TBC'], ['Award Recipients Confirmed', 'TBC'], ['Menu Tasting', 'TBC'],
                ['Seating Plan Locked', 'TBC'], ['Technical Rehearsal', 'TBC'], ['Gala Night', 'TBC'],
                ['Post-event & Media Pack', 'TBC'],
            ]),
        ];
    }

    // ─────────────────────────── 5 · FESTIVAL & PUBLIC EVENT ───────────────────────────
    private static function festival(string $name): array
    {
        return [
            'type' => 'Festival & Public Event',
            'overview' => "The {$name} is a large-scale public event delivered across a managed site with multiple stages, activation "
                .'zones, food and beverage areas, and sponsor experiences. Public safety, crowd management, and authority compliance are '
                .'central to delivery. This Event Brief defines the concept, expected footfall, site and zoning plan, safety and emergency '
                .'framework, and the operational requirements needed to secure permits and begin site planning.',
            'objectives' => [
                'Deliver a safe, well-managed public event with zero major incidents.',
                'Attract and engage the target public audience and community.',
                'Create memorable entertainment and activation experiences.',
                'Deliver measurable value and visibility for sponsors and partners.',
                'Support local vendors and generate on-site commercial return.',
                'Secure all permits and comply fully with authority requirements.',
            ],
            'audience' => [
                'General public and families from the surrounding community.',
                'Youth and entertainment-seeking attendees.',
                'Tourists, visitors, and day-trippers.',
                'Sponsors, brand partners, and activation teams.',
                'Vendors, performers, and community groups.',
                'Media, content creators, and local authorities.',
            ],
            'success' => self::kpi([
                ['Total Footfall', '25,000'], ['Peak Attendance', '8,000'], ['Ticket Sales', 'Target'],
                ['Crowd Flow Efficiency', 'No bottlenecks'], ['Incident Rate', 'Zero major'], ['Vendor Sales', 'Target'],
                ['Sponsor Engagement', '4.5 / 5'], ['Public Satisfaction', '4.5 / 5'], ['Social Media Reach', '1M+'],
            ]),
            'components' => self::two([
                ['Main Stage', 'Headline performances, stage programme, and show calling.'],
                ['Secondary Stage & Zones', 'Community stage, activation zones, and family area.'],
                ['Food & Beverage', 'Vendor village, seating areas, and queue management.'],
                ['Sponsor Activations', 'Brand zones, experiential activations, and sampling areas.'],
                ['Public Safety', 'Entry and exit gates, crowd barriers, first aid, and security points.'],
            ]),
            'venue' => self::two([
                ['Site & Zoning', 'Overall site plan, zone boundaries, capacity per zone, and crowd flow modelling.'],
                ['Stages', 'Main stage and secondary stage build, rigging, sound, lighting, and barriers.'],
                ['Entry & Exit', 'Gates, ticketing and check-in lanes, accreditation, and emergency egress routes.'],
                ['Power & Utilities', 'Generators, power distribution, lighting towers, and water supply.'],
                ['Welfare & Sanitation', 'Toilets, waste management, cleaning crew, and drinking water.'],
                ['Safety Infrastructure', 'First-aid station, security points, fire points, signage, and assembly points.'],
            ]),
            'operational' => self::two([
                ['Permits & Authorities', 'Municipality, police, civil defence, and health approvals with submission timeline.'],
                ['Crowd Management', 'Capacity limits, flow plan, stewarding, barriers, and counting systems.'],
                ['Ticketing / Entry', 'Ticketing platform or free-entry logic, scanning, and gate staffing.'],
                ['Safety & Medical', 'Medical team, ambulance standby, incident reporting, and escalation protocol.'],
                ['Weather Contingency', 'Weather monitoring, shelter provision, and show-stop / postponement criteria.'],
                ['Public Communication', 'Wayfinding, announcements, social channels, and emergency messaging.'],
            ]),
            'risks' => self::two([
                ['Adverse Weather', 'Weather monitoring, contingency plan, shelter, and defined show-stop procedure.'],
                ['Overcrowding / Crush', 'Enforced capacity, crowd-flow design, stewarding, and real-time monitoring.'],
                ['Permit or Authority Delay', 'Early submission, authority liaison, and a compliance checklist.'],
                ['Power / Generator Failure', 'Redundant generators, load testing, and standby technicians.'],
                ['Security or Medical Incident', 'Trained security, medical standby, command centre, and escalation protocol.'],
            ]),
            'budget' => self::two([
                ['Site & Infrastructure', 'TBD'], ['Stages, AV & Lighting', 'TBD'], ['Power & Generators', 'TBD'],
                ['Talent & Entertainment', 'TBD'], ['Security & Stewarding', 'TBD'], ['Medical & Safety', 'TBD'],
                ['Sanitation & Waste', 'TBD'], ['Permits & Authorities', 'TBD'], ['Marketing & Ticketing', 'TBD'],
                ['Signage & Wayfinding', 'TBD'], ['Contingency', 'TBD'],
            ]),
            'milestones' => self::two([
                ['Event Brief Approval', 'TBC'], ['Site Confirmed', 'TBC'], ['Site Plan v1', 'TBC'],
                ['Permits Submitted', 'TBC'], ['Safety & Traffic Plan Approved', 'TBC'], ['Talent Booked', 'TBC'],
                ['Sponsor Activations Closed', 'TBC'], ['Ticketing Live', 'TBC'], ['Build & Site Setup', 'TBC'],
                ['Event Days', 'TBC'], ['De-rig & Post-Event Report', 'TBC'],
            ]),
        ];
    }
}
