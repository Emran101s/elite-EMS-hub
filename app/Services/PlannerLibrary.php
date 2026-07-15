<?php

namespace App\Services;

/**
 * Reusable Event Planner Task Library (Work Breakdown Structure).
 *
 * The canonical, version-controlled WBS used to generate a tailored plan for
 * any large-scale event. Each task carries a workstream, phase, P-priority,
 * a relative deadline (T-offset from the event start), a suggested owner role,
 * approval / budget / risk metadata, and the conditions under which it applies.
 */
class PlannerLibrary
{
    /** 21 workstreams: key => [label, icon]. */
    public const WORKSTREAMS = [
        'strategy' => ['Event Strategy & Scope', 'sparkles'],
        'governance' => ['Governance & Team', 'users'],
        'venue' => ['Venue & Multi-venue', 'building'],
        'program' => ['Agenda, Program & Tracks', 'calendar'],
        'speakers' => ['Speakers & Moderators', 'identification'],
        'exhibition' => ['Exhibition & Booths', 'grid'],
        'sponsors' => ['Sponsors & Partners', 'star'],
        'registration' => ['Registration & Attendees', 'clipboard'],
        'vip' => ['VIP & Protocol', 'star'],
        'accommodation' => ['Accommodation & Travel', 'home'],
        'transportation' => ['Transportation', 'truck'],
        'branding' => ['Branding & Creative', 'sparkles'],
        'production' => ['Production, Stage, AV & Technical', 'chart'],
        'marketing' => ['Marketing & Communications', 'chat'],
        'catering' => ['Catering & Hospitality', 'home'],
        'suppliers' => ['Suppliers & Procurement', 'truck'],
        'budget' => ['Budget & Finance', 'currency'],
        'staffing' => ['Staffing, Ushers & Volunteers', 'users'],
        'security' => ['Security, Safety & Risk', 'bell'],
        'operations' => ['Event-Day Operations', 'grid'],
        'closing' => ['Post-Event Closing & Reporting', 'archive'],
    ];

    /** 5 lifecycle phases. */
    public const PHASES = [
        'planning' => 'Planning',
        'preparation' => 'Preparation',
        'finalization' => 'Finalization',
        'event_day' => 'Event Day',
        'post_event' => 'Post-Event',
    ];

    /** Priority scheme: key => [label, rule, class]. */
    public const PRIORITIES = [
        'p0' => ['P0 · Critical', 'Can stop the event', 'bg-red-100 text-red-700 border-red-200'],
        'p1' => ['P1 · High', 'Major experience / operations', 'bg-amber-100 text-amber-700 border-amber-200'],
        'p2' => ['P2 · Medium', 'Important but manageable', 'bg-sky-100 text-sky-700 border-sky-200'],
        'p3' => ['P3 · Low', 'Enhancement', 'bg-navy-100 text-navy-600 border-navy-200'],
        'p4' => ['P4 · Post-event', 'Closure & wrap-up', 'bg-navy-100 text-navy-400 border-navy-200'],
    ];

    public const RISKS = ['low', 'medium', 'high', 'critical'];

    /** Generation conditions (wizard answers + derived flags). */
    public const CONDITIONS = [
        'has_exhibition', 'has_speakers', 'has_sponsors', 'has_vip', 'has_accommodation',
        'has_transportation', 'has_gala', 'has_workshops', 'has_livestream', 'has_interpretation',
        'has_app', 'multi_venue', 'multi_track', 'large',
    ];

    /**
     * Task library. Positional for density — order:
     * [workstream, name, description, priority, phase, deadline, owner, approval(bool), budget(bool), risk, when[]]
     */
    public const TASKS = [
        // ── Strategy & Scope ──
        ['strategy', 'Define event objectives & KPIs', 'Agree the strategic goals, target outcomes and success metrics with the client.', 'p0', 'planning', 'T-180', 'Event Director', true, false, 'high', []],
        ['strategy', 'Confirm event concept, theme & format', 'Lock the concept, theme, format (in-person / hybrid) and headline narrative.', 'p0', 'planning', 'T-180', 'Event Director', true, false, 'medium', []],
        ['strategy', 'Confirm dates, duration & scope', 'Finalise event dates, number of days and the overall scope of delivery.', 'p0', 'planning', 'T-180', 'Event Director', true, false, 'high', []],
        ['strategy', 'Master project plan & timeline', 'Build the master WBS, milestone timeline and critical path.', 'p1', 'planning', 'T-160', 'Project Manager', false, false, 'medium', []],
        ['strategy', 'Stakeholder map & approval matrix', 'Map stakeholders and define the sign-off / approval matrix.', 'p2', 'planning', 'T-150', 'Project Manager', false, false, 'low', []],

        // ── Governance & Team ──
        ['governance', 'Appoint core organising team & roles', 'Assign workstream owners and define RACI responsibilities.', 'p0', 'planning', 'T-160', 'Event Director', false, false, 'medium', []],
        ['governance', 'Kickoff meeting with client & team', 'Run the project kickoff, align on goals, roles and cadence.', 'p1', 'planning', 'T-150', 'Project Manager', false, false, 'low', []],
        ['governance', 'Set up communication & reporting cadence', 'Establish weekly status, tracker, and escalation channels.', 'p2', 'planning', 'T-140', 'Project Manager', false, false, 'low', []],
        ['governance', 'Contract & scope sign-off with client', 'Sign the delivery contract, scope and payment schedule.', 'p0', 'planning', 'T-140', 'Event Director', true, true, 'high', []],

        // ── Venue & Multi-venue ──
        ['venue', 'Shortlist & inspect venues', 'Source, site-visit and shortlist venues against capacity and technical needs.', 'p0', 'planning', 'T-150', 'Operations Lead', false, true, 'high', []],
        ['venue', 'Contract main venue', 'Negotiate and sign the main venue contract with all clauses.', 'p0', 'planning', 'T-120', 'Operations Lead', true, true, 'critical', []],
        ['venue', 'Confirm hall capacities & layouts', 'Validate plenary, breakout and exhibition capacities against attendee numbers.', 'p1', 'preparation', 'T-90', 'Operations Lead', false, false, 'high', []],
        ['venue', 'Build floor plans & seating layouts', 'Produce to-scale floor plans, seating and flow for each space.', 'p1', 'preparation', 'T-75', 'Operations Lead', true, false, 'medium', []],
        ['venue', 'Coordinate multiple venues & shuttle links', 'Align schedules, signage and shuttles across all venues.', 'p1', 'preparation', 'T-60', 'Logistics Coordinator', false, true, 'high', ['multi_venue']],
        ['venue', 'Confirm breakout room allocation', 'Assign and label breakout rooms to tracks and sessions.', 'p2', 'preparation', 'T-45', 'Operations Lead', false, false, 'medium', ['multi_track']],
        ['venue', 'Venue walkthrough & final layout sign-off', 'Final site walkthrough and layout approval with venue & client.', 'p1', 'finalization', 'T-14', 'Operations Lead', true, false, 'medium', []],

        // ── Agenda, Program & Tracks ──
        ['program', 'Draft agenda & run-of-show', 'Build the full agenda, session grid and detailed run-of-show.', 'p0', 'planning', 'T-120', 'Program Manager', true, false, 'high', []],
        ['program', 'Design multi-track session grid', 'Structure parallel tracks, timings and room assignments.', 'p1', 'preparation', 'T-90', 'Program Manager', false, false, 'medium', ['multi_track']],
        ['program', 'Plan workshops & side sessions', 'Define workshop content, facilitators, capacity and materials.', 'p2', 'preparation', 'T-75', 'Program Manager', false, true, 'medium', ['has_workshops']],
        ['program', 'Plan opening & closing ceremonies', 'Script and cue the opening/closing, VIP moments and awards.', 'p1', 'preparation', 'T-60', 'Program Manager', true, false, 'medium', []],
        ['program', 'Plan interpretation & translation', 'Arrange simultaneous interpretation booths, languages and receivers.', 'p1', 'preparation', 'T-60', 'Operations Lead', false, true, 'high', ['has_interpretation']],
        ['program', 'Lock final agenda & distribute', 'Freeze the agenda, publish and distribute to all stakeholders.', 'p1', 'finalization', 'T-14', 'Program Manager', true, false, 'medium', []],

        // ── Speakers & Moderators ──
        ['speakers', 'Build speaker & moderator long-list', 'Identify and prioritise target speakers, moderators and panellists.', 'p1', 'planning', 'T-120', 'Speaker Manager', false, false, 'medium', ['has_speakers']],
        ['speakers', 'Invite & confirm speakers', 'Send invitations, negotiate fees and confirm participation.', 'p0', 'preparation', 'T-90', 'Speaker Manager', true, true, 'high', ['has_speakers']],
        ['speakers', 'Collect bios, photos & presentations', 'Gather speaker assets, talk titles, abstracts and slide decks.', 'p2', 'preparation', 'T-45', 'Speaker Manager', false, false, 'medium', ['has_speakers']],
        ['speakers', 'Brief speakers & moderators', 'Share run-of-show, timings, AV format and moderator briefs.', 'p2', 'finalization', 'T-14', 'Speaker Manager', false, false, 'medium', ['has_speakers']],
        ['speakers', 'Speaker travel, hospitality & green room', 'Arrange speaker logistics, green room, hospitality and rehearsal slots.', 'p2', 'finalization', 'T-7', 'Logistics Coordinator', false, true, 'medium', ['has_speakers']],

        // ── Exhibition & Booths ──
        ['exhibition', 'Design exhibition floor & booth grid', 'Lay out the exhibition floor, booth sizes, aisles and traffic flow.', 'p1', 'planning', 'T-120', 'Exhibition Manager', true, true, 'medium', ['has_exhibition']],
        ['exhibition', 'Confirm exhibitors & sign contracts', 'Sell, allocate and contract exhibitor booths.', 'p1', 'preparation', 'T-90', 'Exhibition Manager', true, false, 'high', ['has_exhibition']],
        ['exhibition', 'Collect exhibitor requirements & branding', 'Gather booth specs, power, AV and branding assets from exhibitors.', 'p2', 'preparation', 'T-45', 'Exhibition Manager', false, false, 'medium', ['has_exhibition']],
        ['exhibition', 'Booth build, fit-out & handover', 'Manage booth construction, fit-out, testing and exhibitor handover.', 'p1', 'finalization', 'T-2', 'Exhibition Manager', false, true, 'high', ['has_exhibition']],

        // ── Sponsors & Partners ──
        ['sponsors', 'Build sponsorship packages & prospectus', 'Define tiers, benefits, pricing and the sponsor prospectus.', 'p1', 'planning', 'T-150', 'Sponsorship Manager', true, false, 'medium', ['has_sponsors']],
        ['sponsors', 'Secure sponsors & sign agreements', 'Close sponsor deals and execute contracts.', 'p1', 'preparation', 'T-90', 'Sponsorship Manager', true, false, 'high', ['has_sponsors']],
        ['sponsors', 'Deliver sponsor branding & activations', 'Fulfil sponsor deliverables, branding rights and on-site activations.', 'p2', 'finalization', 'T-14', 'Sponsorship Manager', false, true, 'medium', ['has_sponsors']],
        ['sponsors', 'Sponsor recognition & reporting', 'Prepare recognition moments and post-event sponsor reports.', 'p4', 'post_event', 'T+7', 'Sponsorship Manager', false, false, 'low', ['has_sponsors']],

        // ── Registration & Attendees ──
        ['registration', 'Set up registration system & ticketing', 'Configure the registration platform, ticket types and payments.', 'p0', 'preparation', 'T-90', 'Registration Lead', false, true, 'high', []],
        ['registration', 'Configure event app & agenda sync', 'Set up the event app, personal agendas, notifications and networking.', 'p2', 'preparation', 'T-60', 'Registration Lead', false, true, 'medium', ['has_app']],
        ['registration', 'Manage invitations, RSVPs & comms', 'Run invitation campaigns, RSVPs and attendee communications.', 'p1', 'preparation', 'T-45', 'Registration Lead', false, false, 'medium', []],
        ['registration', 'Badging, lanyards & access levels', 'Produce badges, define access zones and print on-site kit.', 'p1', 'finalization', 'T-7', 'Registration Lead', false, true, 'medium', []],
        ['registration', 'On-site registration desk & flow', 'Plan check-in desks, staffing, scanners and queue management.', 'p1', 'finalization', 'T-3', 'Registration Lead', false, false, 'high', ['large']],

        // ── VIP & Protocol ──
        ['vip', 'Build VIP guest list & protocol plan', 'Compile VIP list, seniority, seating and protocol requirements.', 'p1', 'preparation', 'T-60', 'Protocol Officer', true, false, 'high', ['has_vip']],
        ['vip', 'VIP invitations & confirmations', 'Issue VIP invitations and confirm attendance and needs.', 'p1', 'preparation', 'T-45', 'Protocol Officer', false, false, 'medium', ['has_vip']],
        ['vip', 'VIP arrival, seating & hospitality plan', 'Plan VIP arrivals, holding room, seating and hospitality.', 'p1', 'finalization', 'T-7', 'Protocol Officer', false, true, 'high', ['has_vip']],
        ['vip', 'VIP security & escort coordination', 'Coordinate VIP security, escorts and access with authorities.', 'p0', 'finalization', 'T-3', 'Security Manager', true, false, 'critical', ['has_vip']],

        // ── Accommodation & Travel ──
        ['accommodation', 'Negotiate hotel room blocks', 'Secure room blocks and rates for speakers, VIPs and delegates.', 'p1', 'preparation', 'T-90', 'Logistics Coordinator', true, true, 'medium', ['has_accommodation']],
        ['accommodation', 'Manage rooming lists & assignments', 'Build and manage rooming lists, check-in/out and special requests.', 'p2', 'finalization', 'T-14', 'Logistics Coordinator', false, false, 'medium', ['has_accommodation']],
        ['accommodation', 'Coordinate speaker & VIP travel', 'Book and coordinate flights and travel for speakers and VIPs.', 'p2', 'preparation', 'T-45', 'Logistics Coordinator', false, true, 'medium', ['has_accommodation']],

        // ── Transportation ──
        ['transportation', 'Plan airport transfers & shuttles', 'Design transfer and shuttle schedules for guests and staff.', 'p1', 'preparation', 'T-45', 'Logistics Coordinator', false, true, 'medium', ['has_transportation']],
        ['transportation', 'Book VIP ground transport', 'Arrange VIP cars, drivers and routing.', 'p2', 'finalization', 'T-14', 'Logistics Coordinator', false, true, 'medium', ['has_transportation', 'has_vip']],
        ['transportation', 'Parking, access & traffic plan', 'Plan parking, drop-off, access control and traffic management.', 'p2', 'finalization', 'T-7', 'Operations Lead', false, false, 'medium', ['has_transportation']],

        // ── Branding & Creative ──
        ['branding', 'Develop event identity & brand guide', 'Create the event identity, logo lockups and brand guidelines.', 'p1', 'planning', 'T-120', 'Creative Director', true, true, 'medium', []],
        ['branding', 'Design stage, backdrops & set', 'Design the stage set, backdrops, LED content frames and scenic.', 'p1', 'preparation', 'T-75', 'Creative Director', true, true, 'high', []],
        ['branding', 'Signage, wayfinding & environment', 'Design and produce signage, wayfinding and branded environment.', 'p2', 'finalization', 'T-14', 'Creative Director', false, true, 'medium', []],
        ['branding', 'Collateral: badges, lanyards, print', 'Design and produce all printed collateral and delegate kit.', 'p2', 'finalization', 'T-14', 'Creative Director', false, true, 'low', []],

        // ── Production, Stage, AV & Technical ──
        ['production', 'Appoint production & technical supplier', 'Select and contract the production house for stage, AV and lighting.', 'p0', 'planning', 'T-120', 'Technical Director', true, true, 'high', []],
        ['production', 'Sound, lighting & LED screen design', 'Design audio, lighting rig and LED screen configuration per venue.', 'p1', 'preparation', 'T-75', 'Technical Director', true, true, 'high', []],
        ['production', 'Set up hybrid streaming & recording', 'Plan livestream, encoders, platform, recording and moderation.', 'p1', 'preparation', 'T-60', 'Technical Director', false, true, 'high', ['has_livestream']],
        ['production', 'Presentation management & playback', 'Set up slide management, confidence monitors and media playback.', 'p2', 'finalization', 'T-7', 'Technical Director', false, false, 'medium', []],
        ['production', 'Technical rehearsal & full run-through', 'Complete a full technical rehearsal with cues, speakers and crew.', 'p0', 'finalization', 'T-1', 'Technical Director', false, false, 'critical', []],
        ['production', 'Load-in, rigging & power', 'Manage load-in, rigging, power distribution and H&S sign-off.', 'p1', 'finalization', 'T-2', 'Technical Director', false, true, 'high', []],

        // ── Marketing & Communications ──
        ['marketing', 'Build event website & landing pages', 'Design and launch the event website and registration pages.', 'p1', 'planning', 'T-120', 'Marketing Lead', false, true, 'medium', []],
        ['marketing', 'Marketing & advertising campaign', 'Plan and run the paid, owned and earned media campaign.', 'p2', 'preparation', 'T-90', 'Marketing Lead', true, true, 'medium', []],
        ['marketing', 'Press conference & media relations', 'Plan the press conference, media list, accreditation and kits.', 'p2', 'preparation', 'T-45', 'Marketing Lead', false, true, 'medium', []],
        ['marketing', 'Social media & content plan', 'Build the content calendar, social assets and live coverage plan.', 'p3', 'preparation', 'T-45', 'Marketing Lead', false, false, 'low', []],
        ['marketing', 'On-site photography & videography', 'Book and brief photo/video crew and capture shot list.', 'p3', 'finalization', 'T-14', 'Marketing Lead', false, true, 'low', []],

        // ── Catering & Hospitality ──
        ['catering', 'Plan catering & F&B for all attendees', 'Design menus, service style, timings and dietary handling.', 'p1', 'preparation', 'T-60', 'F&B Manager', true, true, 'medium', []],
        ['catering', 'Plan gala / VIP dinner', 'Design the gala dinner: venue, menu, seating, entertainment.', 'p1', 'preparation', 'T-60', 'F&B Manager', true, true, 'high', ['has_gala']],
        ['catering', 'Confirm final headcount & BEO', 'Confirm final numbers and banquet event orders with caterer.', 'p1', 'finalization', 'T-7', 'F&B Manager', false, true, 'medium', []],
        ['catering', 'VIP & speaker hospitality', 'Arrange green room, VIP lounge and hospitality service.', 'p2', 'finalization', 'T-3', 'F&B Manager', false, true, 'low', ['has_vip']],

        // ── Suppliers & Procurement ──
        ['suppliers', 'Build supplier list & issue RFQs', 'Identify suppliers, issue RFQs and collect quotations.', 'p1', 'planning', 'T-120', 'Procurement Lead', false, true, 'medium', []],
        ['suppliers', 'Evaluate quotes & award contracts', 'Compare bids, negotiate and award supplier contracts.', 'p1', 'preparation', 'T-90', 'Procurement Lead', true, true, 'medium', []],
        ['suppliers', 'Supplier schedules & load-in plan', 'Confirm supplier timings, access, load-in and coordination.', 'p2', 'finalization', 'T-7', 'Procurement Lead', false, false, 'medium', []],
        ['suppliers', 'Supplier payments & reconciliation', 'Process supplier payments and reconcile against POs.', 'p4', 'post_event', 'T+7', 'Finance Manager', true, true, 'low', []],

        // ── Budget & Finance ──
        ['budget', 'Build master budget & get approval', 'Develop the full budget, cash flow and secure client approval.', 'p0', 'planning', 'T-150', 'Finance Manager', true, true, 'high', []],
        ['budget', 'Set up PO & payment tracking', 'Establish PO process, payment schedule and tracking.', 'p2', 'preparation', 'T-90', 'Finance Manager', false, true, 'medium', []],
        ['budget', 'Budget review & variance control', 'Run periodic budget reviews and manage variance / contingency.', 'p2', 'finalization', 'T-14', 'Finance Manager', false, true, 'medium', []],
        ['budget', 'Final reconciliation & invoicing', 'Reconcile actuals, close POs and issue final invoices.', 'p4', 'post_event', 'T+7', 'Finance Manager', true, true, 'medium', []],

        // ── Staffing, Ushers & Volunteers ──
        ['staffing', 'Plan staffing & crew requirements', 'Define roles, headcount and shift plan for all crew.', 'p1', 'preparation', 'T-45', 'Staffing Coordinator', false, true, 'medium', []],
        ['staffing', 'Recruit ushers, hostesses & volunteers', 'Recruit, contract and schedule front-of-house staff.', 'p2', 'finalization', 'T-14', 'Staffing Coordinator', false, true, 'medium', ['large']],
        ['staffing', 'Staff briefing & uniforms', 'Brief all staff, assign zones and distribute uniforms/kit.', 'p1', 'finalization', 'T-1', 'Staffing Coordinator', false, false, 'medium', []],

        // ── Security, Safety & Risk ──
        ['security', 'Security & access-control plan', 'Design security, access zones, screening and credentials.', 'p0', 'preparation', 'T-60', 'Security Manager', true, true, 'critical', []],
        ['security', 'Health, safety & emergency plan', 'Produce H&S risk assessment, medical, fire and evacuation plans.', 'p0', 'preparation', 'T-45', 'Security Manager', true, false, 'critical', []],
        ['security', 'Permits, insurance & authorities', 'Secure permits, insurance and coordinate with authorities.', 'p0', 'preparation', 'T-45', 'Operations Lead', true, true, 'high', []],
        ['security', 'Event risk register & mitigation', 'Maintain the risk register with owners and mitigation actions.', 'p1', 'finalization', 'T-14', 'Operations Lead', false, false, 'high', []],

        // ── Event-Day Operations ──
        ['operations', 'Set up event-day command center', 'Stand up the control room, comms, radios and escalation.', 'p0', 'event_day', 'EVENT_DAY', 'Operations Lead', false, false, 'critical', []],
        ['operations', 'Final walkthrough & readiness check', 'Complete the final readiness walkthrough and go/no-go.', 'p0', 'event_day', 'EVENT_DAY', 'Operations Lead', true, false, 'critical', []],
        ['operations', 'Run the run-of-show & stage management', 'Execute the run-of-show, stage calls and live cueing.', 'p0', 'event_day', 'EVENT_DAY', 'Program Manager', false, false, 'critical', []],
        ['operations', 'Manage registration & attendee flow', 'Run check-in, crowd flow, wayfinding and issue resolution.', 'p1', 'event_day', 'EVENT_DAY', 'Registration Lead', false, false, 'high', []],
        ['operations', 'Live issue & incident management', 'Track and resolve live issues; manage incidents and comms.', 'p0', 'event_day', 'EVENT_DAY', 'Operations Lead', false, false, 'critical', []],

        // ── Post-Event Closing & Reporting ──
        ['closing', 'De-rig, load-out & venue handback', 'Manage de-rig, load-out, damages and venue handback.', 'p4', 'post_event', 'T+1', 'Operations Lead', false, true, 'medium', []],
        ['closing', 'Post-event debrief & lessons learned', 'Run the internal and client debrief; capture lessons learned.', 'p4', 'post_event', 'T+7', 'Project Manager', false, false, 'low', []],
        ['closing', 'Attendee feedback & survey analysis', 'Collect and analyse attendee, speaker and sponsor feedback.', 'p4', 'post_event', 'T+7', 'Reporting Lead', false, false, 'low', []],
        ['closing', 'Final event report & ROI', 'Produce the final report: outcomes, KPIs, media value and ROI.', 'p4', 'post_event', 'T+14', 'Reporting Lead', true, false, 'medium', []],
        ['closing', 'Financial close & final reconciliation', 'Close the budget, settle suppliers and issue the P&L.', 'p4', 'post_event', 'T+14', 'Finance Manager', true, true, 'medium', []],
    ];

    /**
     * Return the tasks that apply given a set of active condition flags.
     *
     * @param  array<string,bool>  $flags
     * @return array<int,array{ws:string,name:string,desc:string,priority:string,phase:string,deadline:string,owner:string,approval:bool,budget:bool,risk:string}>
     */
    public static function applicableTasks(array $flags): array
    {
        $out = [];
        foreach (self::TASKS as $i => [$ws, $name, $desc, $priority, $phase, $deadline, $owner, $approval, $budget, $risk, $when]) {
            foreach ($when as $cond) {
                if (empty($flags[$cond])) {
                    continue 2; // a required condition is off → skip task
                }
            }
            $out[] = [
                'key' => $ws.':'.$i,
                'ws' => $ws, 'name' => $name, 'desc' => $desc, 'priority' => $priority,
                'phase' => $phase, 'deadline' => $deadline, 'owner' => $owner,
                'approval' => $approval, 'budget' => $budget, 'risk' => $risk,
            ];
        }

        return $out;
    }

    /** Convert a T-offset code into a concrete date relative to the event start. */
    public static function resolveDate(string $code, ?\Carbon\CarbonInterface $start): ?string
    {
        if (! $start) {
            return null;
        }
        if ($code === 'EVENT_DAY') {
            return $start->toDateString();
        }
        if (preg_match('/^T([+-])(\d+)$/', $code, $m)) {
            $days = (int) $m[2];

            return ($m[1] === '-' ? $start->copy()->subDays($days) : $start->copy()->addDays($days))->toDateString();
        }

        return null;
    }
}
