<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Idempotent demo dataset mirroring the Command Center reference mockup.
 * Safe to re-run: rows are matched by unique names/emails, not recreated.
 */
class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(EventAvatarSeeder::class);

        // ── Venues ──────────────────────────────────────────────────────
        $venues = collect([
            ['name' => 'Royal Convention Centre', 'city' => 'Amman', 'country' => 'Jordan', 'capacity' => 2500],
            ['name' => 'Gulf Grand Ballroom', 'city' => 'Manama', 'country' => 'Bahrain', 'capacity' => 800],
            ['name' => 'Jumeirah Learning Hub', 'city' => 'Dubai', 'country' => 'UAE', 'capacity' => 300],
            ['name' => 'Doha Exhibition Center', 'city' => 'Doha', 'country' => 'Qatar', 'capacity' => 5000],
            ['name' => 'GJU Main Campus Hall', 'city' => 'Amman', 'country' => 'Jordan', 'capacity' => 1200],
            ['name' => 'Al Faisaliah Private Suites', 'city' => 'Riyadh', 'country' => 'KSA', 'capacity' => 60],
        ])->mapWithKeys(fn (array $venue) => [
            $venue['name'] => Venue::updateOrCreate(['name' => $venue['name']], $venue),
        ]);

        // ── Projects ────────────────────────────────────────────────────
        $projects = collect([
            [
                'name' => 'Conference Season 2026',
                'status' => 'active',
                'description' => 'All large-format conferences and expos for the 2026 season.',
                'starts_on' => '2026-01-15', 'ends_on' => '2026-12-20',
                'budget_cents' => 145000000,
            ],
            [
                'name' => 'Corporate Events Portfolio',
                'status' => 'active',
                'description' => 'Galas, private dinners and corporate hospitality.',
                'starts_on' => '2026-03-01', 'ends_on' => '2026-11-30',
                'budget_cents' => 60000000,
            ],
            [
                'name' => 'Education & Training Series',
                'status' => 'active',
                'description' => 'University fairs and professional training workshops.',
                'starts_on' => '2026-02-01', 'ends_on' => '2026-10-15',
                'budget_cents' => 40000000,
            ],
        ])->mapWithKeys(fn (array $project) => [
            $project['name'] => Project::updateOrCreate(['name' => $project['name']], $project),
        ]);

        // ── Events (the six islands from the mockup) ────────────────────
        $events = collect([
            [
                'name' => 'ICFT 2026', 'type' => 'conference', 'status' => 'on_track',
                'city' => 'Amman', 'country' => 'Jordan',
                'venue' => 'Royal Convention Centre', 'project' => 'Conference Season 2026',
                'starts_at' => '2026-10-19', 'ends_at' => '2026-10-21',
                'budget_cents' => 65000000, 'progress' => 92, 'expected_participants' => 400,
            ],
            [
                'name' => 'EY Annual Gala', 'type' => 'gala_dinner', 'status' => 'in_progress',
                'city' => 'Manama', 'country' => 'Bahrain',
                'venue' => 'Gulf Grand Ballroom', 'project' => 'Corporate Events Portfolio',
                'starts_at' => '2026-11-12', 'ends_at' => '2026-11-12',
                'budget_cents' => 28000000, 'progress' => 76, 'expected_participants' => 120,
            ],
            [
                'name' => 'NDI Workshop', 'type' => 'workshop', 'status' => 'at_risk',
                'city' => 'Dubai', 'country' => 'UAE',
                'venue' => 'Jumeirah Learning Hub', 'project' => 'Education & Training Series',
                'starts_at' => '2026-08-05', 'ends_at' => '2026-08-07',
                'budget_cents' => 9000000, 'progress' => 61, 'expected_participants' => 80,
            ],
            [
                'name' => 'Tech Expo 2026', 'type' => 'exhibition', 'status' => 'behind',
                'city' => 'Doha', 'country' => 'Qatar',
                'venue' => 'Doha Exhibition Center', 'project' => 'Conference Season 2026',
                'starts_at' => '2026-12-10', 'ends_at' => '2026-12-12',
                'budget_cents' => 82000000, 'progress' => 45, 'expected_participants' => 1000,
            ],
            [
                'name' => 'GJU Career Fair', 'type' => 'career_fair', 'status' => 'on_track',
                'city' => 'Amman', 'country' => 'Jordan',
                'venue' => 'GJU Main Campus Hall', 'project' => 'Education & Training Series',
                'starts_at' => '2026-09-28', 'ends_at' => '2026-09-29',
                'budget_cents' => 12000000, 'progress' => 85, 'expected_participants' => 600,
            ],
            [
                'name' => 'Private Dinner', 'type' => 'private_dinner', 'status' => 'on_track',
                'city' => 'Riyadh', 'country' => 'KSA',
                'venue' => 'Al Faisaliah Private Suites', 'project' => 'Corporate Events Portfolio',
                'starts_at' => '2026-11-20', 'ends_at' => '2026-11-20',
                'budget_cents' => 15000000, 'progress' => 90, 'expected_participants' => 45,
            ],
        ])->mapWithKeys(function (array $event) use ($venues, $projects) {
            $attributes = collect($event)->except(['venue', 'project'])->all();
            $attributes['venue_id'] = $venues[$event['venue']]->id;
            $attributes['project_id'] = $projects[$event['project']]->id;

            return [$event['name'] => Event::updateOrCreate(['name' => $event['name']], $attributes)];
        });

        // Give each demo event its library avatar.
        $avatarMap = [
            'ICFT 2026' => 'convention-center', // ICFT-branded flagship render
            'EY Annual Gala' => 'gala-dinner',
            'NDI Workshop' => 'workshop',
            'Tech Expo 2026' => 'exhibition',
            'GJU Career Fair' => 'exhibition',
            'Private Dinner' => 'vip-event',
        ];
        foreach ($avatarMap as $eventName => $slug) {
            $events[$eventName]->update([
                'avatar_id' => \App\Models\EventAvatar::where('slug', $slug)->value('id'),
            ]);
        }

        // ── Suppliers (top three from the mockup + supporting cast) ─────
        $suppliers = collect([
            ['name' => 'Creative Vision Co.', 'category' => 'support', 'rating' => 4.9, 'city' => 'Amman', 'country' => 'Jordan'],
            ['name' => 'Event Tech Solutions', 'category' => 'av_lighting', 'rating' => 4.8, 'city' => 'Dubai', 'country' => 'UAE'],
            ['name' => 'Royal Catering Services', 'category' => 'catering', 'rating' => 4.7, 'city' => 'Riyadh', 'country' => 'KSA'],
            ['name' => 'Falcon Stage Production', 'category' => 'production', 'rating' => 4.5, 'city' => 'Doha', 'country' => 'Qatar'],
            ['name' => 'Oasis Logistics', 'category' => 'logistics', 'rating' => 4.3, 'city' => 'Manama', 'country' => 'Bahrain'],
            ['name' => 'Petra Decor Studio', 'category' => 'decor', 'rating' => 4.6, 'city' => 'Amman', 'country' => 'Jordan'],
        ])->mapWithKeys(fn (array $supplier) => [
            $supplier['name'] => Supplier::updateOrCreate(['name' => $supplier['name']], $supplier),
        ]);

        // Attach each event to a plausible supplier mix (idempotent sync-without-detach).
        $mix = [
            'ICFT 2026' => ['Creative Vision Co.', 'Event Tech Solutions', 'Royal Catering Services', 'Oasis Logistics'],
            'EY Annual Gala' => ['Royal Catering Services', 'Petra Decor Studio', 'Event Tech Solutions'],
            'NDI Workshop' => ['Event Tech Solutions', 'Creative Vision Co.'],
            'Tech Expo 2026' => ['Falcon Stage Production', 'Event Tech Solutions', 'Oasis Logistics'],
            'GJU Career Fair' => ['Creative Vision Co.', 'Oasis Logistics'],
            'Private Dinner' => ['Royal Catering Services', 'Petra Decor Studio'],
        ];
        foreach ($mix as $eventName => $supplierNames) {
            $events[$eventName]->suppliers()->syncWithoutDetaching(
                collect($supplierNames)->map(fn (string $name) => $suppliers[$name]->id)->all()
            );
        }

        // ── Team ────────────────────────────────────────────────────────
        $team = collect([
            ['name' => 'Emran Ahmed', 'email' => 'emran.itan@elitebhub.com', 'title' => 'Super Admin', 'avatar_path' => 'images/team/emran-ahmed.jpg'],
            ['name' => 'Layla Haddad', 'email' => 'layla.haddad@elitebhub.com', 'title' => 'Operations Manager'],
            ['name' => 'Omar Nassar', 'email' => 'omar.nassar@elitebhub.com', 'title' => 'Event Producer'],
            ['name' => 'Sara Al-Rashid', 'email' => 'sara.alrashid@elitebhub.com', 'title' => 'Finance Lead'],
            ['name' => 'Khalid Mansour', 'email' => 'khalid.mansour@elitebhub.com', 'title' => 'Logistics Coordinator'],
            ['name' => 'Dana Qasem', 'email' => 'dana.qasem@elitebhub.com', 'title' => 'Client Relations'],
        ])->mapWithKeys(fn (array $member) => [
            $member['name'] => User::updateOrCreate(
                ['email' => $member['email']],
                $member + ['password' => 'password'],
            ),
        ]);

        // ── Tasks (mockup ratio: 72 completed / 36 in progress / 20 pending) ──
        if (Task::count() === 0) {
            $eventIds = $events->pluck('id')->all();
            $assigneeIds = $team->pluck('id')->all();

            $titles = [
                'Venue payment', 'Marketing materials', 'Speaker decks', 'Hotel contract',
                'Booth production', 'Speaker confirmation', 'Payment approval', 'Transport schedule',
                'Catering tasting', 'Badge printing', 'AV rehearsal', 'Sponsor pack delivery',
                'Registration page QA', 'Security briefing', 'Floor plan sign-off', 'Press invitations',
            ];

            foreach (['completed' => 72, 'in_progress' => 36, 'pending' => 20] as $status => $count) {
                Task::factory($count)->create([
                    'status' => $status,
                    'title' => fn () => fake()->randomElement($titles).' — '.fake()->words(2, true),
                    'event_id' => fn () => fake()->randomElement($eventIds),
                    'project_id' => fn () => fake()->optional(0.5)->randomElement(Project::pluck('id')->all()),
                    'assignee_id' => fn () => fake()->randomElement($assigneeIds),
                ]);
            }
        }

        $this->seedEventOperations($events, $suppliers, $team);
    }

    /**
     * Operational data for the Event Hubs: clients, lifecycle stages, PMs,
     * rooms, agenda, budget lines, sponsors, risks, approvals, team roles,
     * supplier pipeline statuses. All idempotent.
     */
    private function seedEventOperations($events, $suppliers, $team): void
    {
        // ── Clients ─────────────────────────────────────────────────────
        foreach ([
            ['name' => 'ICFT Global Committee', 'organization' => 'ICFT Global', 'event' => 'ICFT 2026'],
            ['name' => 'Ernst & Young MENA', 'organization' => 'EY', 'event' => 'EY Annual Gala'],
            ['name' => 'National Development Institute', 'organization' => 'NDI', 'event' => 'NDI Workshop'],
            ['name' => 'Qatar Tech Authority', 'organization' => 'QTA', 'event' => 'Tech Expo 2026'],
            ['name' => 'German Jordanian University', 'organization' => 'GJU', 'event' => 'GJU Career Fair'],
            ['name' => 'Private Office — Al Faisaliah', 'organization' => 'Private Office', 'event' => 'Private Dinner'],
        ] as $row) {
            $client = \App\Models\Client::updateOrCreate(['name' => $row['name']], collect($row)->except('event')->all());
            $events[$row['event']]->update(['client_id' => $client->id]);
        }

        // ── Stage, PM, color theme ──────────────────────────────────────
        $ops = [
            'ICFT 2026' => ['stage' => 'planning', 'pm' => 'Layla Haddad'],
            'EY Annual Gala' => ['stage' => 'production', 'pm' => 'Omar Nassar',
                'theme' => ['#10141A', '#F8FAFC', '#D4AF37', '#0F172A']], // Black + Gold
            'NDI Workshop' => ['stage' => 'planning', 'pm' => 'Dana Qasem'],
            'Tech Expo 2026' => ['stage' => 'production', 'pm' => 'Khalid Mansour'],
            'GJU Career Fair' => ['stage' => 'confirmed', 'pm' => 'Layla Haddad'],
            'Private Dinner' => ['stage' => 'live', 'pm' => 'Emran Ahmed'],
        ];
        foreach ($ops as $eventName => $config) {
            $events[$eventName]->update([
                'stage' => $config['stage'],
                'project_manager_id' => $team[$config['pm']]->id,
                'primary_color' => $config['theme'][0] ?? null,
                'secondary_color' => $config['theme'][1] ?? null,
                'accent_color' => $config['theme'][2] ?? null,
                'text_color' => $config['theme'][3] ?? null,
            ]);
        }

        // ── Team roles (ICFT full crew, others PM-led) ──────────────────
        $events['ICFT 2026']->teamMembers()->syncWithoutDetaching([
            $team['Layla Haddad']->id => ['role' => 'project_manager'],
            $team['Omar Nassar']->id => ['role' => 'production_owner'],
            $team['Sara Al-Rashid']->id => ['role' => 'finance_owner'],
            $team['Dana Qasem']->id => ['role' => 'client_rm'],
            $team['Khalid Mansour']->id => ['role' => 'operations_lead'],
        ]);

        // ── Rooms ───────────────────────────────────────────────────────
        $icft = $events['ICFT 2026'];
        $rooms = [];
        foreach ([
            ['name' => 'Main Hall', 'type' => 'main_hall', 'capacity' => 1200],
            ['name' => 'Breakout Room 1', 'type' => 'breakout', 'capacity' => 150],
            ['name' => 'Breakout Room 2', 'type' => 'breakout', 'capacity' => 150],
            ['name' => 'Exhibition Area', 'type' => 'exhibition', 'capacity' => 600],
            ['name' => 'Registration Area', 'type' => 'registration', 'capacity' => null],
            ['name' => 'VIP Lounge', 'type' => 'vip', 'capacity' => 60],
        ] as $room) {
            $rooms[$room['name']] = \App\Models\EventRoom::updateOrCreate(
                ['event_id' => $icft->id, 'name' => $room['name']], $room + ['event_id' => $icft->id]);
        }
        foreach ([
            ['name' => 'Hall A', 'type' => 'exhibition', 'capacity' => 2000],
            ['name' => 'Hall B', 'type' => 'exhibition', 'capacity' => 2000],
            ['name' => 'Registration Plaza', 'type' => 'registration', 'capacity' => null],
        ] as $room) {
            \App\Models\EventRoom::updateOrCreate(
                ['event_id' => $events['Tech Expo 2026']->id, 'name' => $room['name']],
                $room + ['event_id' => $events['Tech Expo 2026']->id]);
        }

        // ── ICFT agenda: 2 days ─────────────────────────────────────────
        \App\Models\EventAgendaDay::where('event_id', $icft->id)
            ->whereNotIn('date', ['2026-10-19', '2026-10-20'])->delete();
        $day1 = \App\Models\EventAgendaDay::updateOrCreate(
            ['event_id' => $icft->id, 'date' => '2026-10-19'],
            ['label' => 'Day 1 — Opening & Keynotes', 'sort' => 1]);
        $day2 = \App\Models\EventAgendaDay::updateOrCreate(
            ['event_id' => $icft->id, 'date' => '2026-10-20'],
            ['label' => 'Day 2 — Workshops & Closing', 'sort' => 2]);

        foreach ([
            [$day1, '08:00', '09:00', 'Registration & Welcome Coffee', 'networking', 'Registration Area', 'final', null, null, null],
            [$day1, '09:00', '09:30', 'Opening Ceremony', 'opening', 'Main Hall', 'final', 'H.E. Minister of Digital Economy', null, null],
            [$day1, '09:30', '10:30', 'Keynote: The Future of Financial Technology', 'keynote', 'Main Hall', 'confirmed', 'Dr. Sarah Chen', null, 'Main Stage'],
            [$day1, '10:30', '11:00', 'Coffee Break', 'break', 'Exhibition Area', 'final', null, null, null],
            [$day1, '11:00', '12:30', 'Panel: Regional Regulation Roundtable', 'panel', 'Main Hall', 'waiting_speaker', null, 'Layla Haddad', 'Main Stage'],
            [$day1, '12:30', '13:30', 'Lunch', 'lunch', 'Exhibition Area', 'final', null, null, null],
            [$day1, '14:00', '15:30', 'Workshop: Open Banking APIs', 'workshop', 'Breakout Room 1', 'confirmed', 'Eng. Faisal Odeh', null, 'Developers'],
            [$day2, '09:00', '10:30', 'Keynote: AI in Capital Markets', 'keynote', 'Main Hall', 'draft', null, null, 'Main Stage'],
            [$day2, '11:00', '12:30', 'Workshop: RegTech in Practice', 'workshop', 'Breakout Room 2', 'needs_review', 'Dr. Amal Khoury', null, 'Compliance'],
            [$day2, '16:00', '17:00', 'Closing Remarks & Awards', 'closing', 'Main Hall', 'confirmed', null, 'Omar Nassar', null],
        ] as [$day, $start, $end, $title, $type, $roomName, $status, $speaker, $moderator, $track]) {
            \App\Models\EventAgendaSession::updateOrCreate(
                ['event_id' => $icft->id, 'title' => $title],
                [
                    'agenda_day_id' => $day->id, 'room_id' => $rooms[$roomName]?->id,
                    'starts_at' => $start, 'ends_at' => $end, 'type' => $type,
                    'status' => $status, 'speaker' => $speaker, 'moderator' => $moderator, 'track' => $track,
                ]);
        }

        // ── Budget lines ────────────────────────────────────────────────
        $budgets = [
            'ICFT 2026' => [
                ['venue', 'Royal Convention Centre — 3 days', 18000000, 9000000, 'partial', 'INV-2026-014'],
                ['catering', 'Full board — 1,200 pax', 9500000, 0, 'pending', null],
                ['av', 'Main stage AV + breakout kits', 12000000, 6000000, 'partial', 'INV-2026-021'],
                ['branding', 'Stage, wayfinding, badges', 4500000, 4500000, 'paid', 'INV-2026-009'],
                ['production', 'Stage build + exhibition booths', 8000000, 2000000, 'pending', null],
            ],
            'EY Annual Gala' => [
                ['venue', 'Gulf Grand Ballroom', 6000000, 6000000, 'paid', 'INV-2026-031'],
                ['catering', 'Gala dinner — 400 pax', 8500000, 4000000, 'partial', 'INV-2026-035'],
                ['entertainment', 'Orchestra + show', 3000000, 0, 'pending', null],
                ['av', 'Lighting + sound design', 4500000, 4500000, 'paid', 'INV-2026-028'],
            ],
            'Tech Expo 2026' => [
                ['venue', 'Doha Exhibition Center — 4 days', 25000000, 15000000, 'partial', 'INV-2026-040'],
                ['production', 'Booth fabrication — 120 booths', 22000000, 24000000, 'paid', 'INV-2026-044'],
                ['branding', 'Expo branding package', 6000000, 2000000, 'partial', null],
            ],
            'NDI Workshop' => [
                ['venue', 'Jumeirah Learning Hub', 1200000, 1200000, 'paid', 'INV-2026-018'],
                ['catering', 'Coffee + lunch — 80 pax', 800000, 0, 'pending', null],
                ['av', 'Training room AV', 1500000, 500000, 'partial', null],
            ],
            'GJU Career Fair' => [
                ['branding', 'Campus branding + booths', 1500000, 500000, 'partial', null],
                ['catering', 'Student refreshments', 2000000, 0, 'pending', null],
            ],
            'Private Dinner' => [
                ['catering', 'Private chef — 24 covers', 4000000, 4000000, 'paid', 'INV-2026-050'],
                ['venue', 'Al Faisaliah Private Suites', 2500000, 2500000, 'paid', 'INV-2026-049'],
                ['entertainment', 'Oud ensemble', 1000000, 1000000, 'paid', 'INV-2026-051'],
            ],
        ];
        foreach ($budgets as $eventName => $items) {
            foreach ($items as [$category, $description, $estimated, $actual, $paymentStatus, $invoice]) {
                \App\Models\EventBudgetItem::updateOrCreate(
                    ['event_id' => $events[$eventName]->id, 'category' => $category, 'description' => $description],
                    ['estimated_cents' => $estimated, 'actual_cents' => $actual, 'payment_status' => $paymentStatus, 'invoice_number' => $invoice]);
            }
        }

        // ── Sponsors ────────────────────────────────────────────────────
        foreach ([
            ['ICFT 2026', 'Gulf National Bank', 'platinum', 15000000, 'paid', 'P-01'],
            ['ICFT 2026', 'Zain Telecom', 'gold', 9000000, 'partial', 'G-03'],
            ['ICFT 2026', 'FinServe Group', 'silver', 4500000, 'pending', 'S-07'],
            ['Tech Expo 2026', 'QTech Ventures', 'platinum', 20000000, 'partial', 'P-01'],
            ['Tech Expo 2026', 'Doha Bank', 'gold', 12000000, 'pending', 'G-02'],
        ] as [$eventName, $name, $package, $amount, $paymentStatus, $booth]) {
            \App\Models\EventSponsor::updateOrCreate(
                ['event_id' => $events[$eventName]->id, 'name' => $name],
                ['package' => $package, 'amount_cents' => $amount, 'payment_status' => $paymentStatus, 'booth' => $booth]);
        }

        // ── Risks ───────────────────────────────────────────────────────
        foreach ([
            ['ICFT 2026', 'Keynote speaker visa delay', 'speaker', 2, 4, 'monitoring', 'Embassy fast-track letter submitted.'],
            ['ICFT 2026', 'Sponsor logo files missing', 'client_approval', 3, 2, 'open', 'Design team chasing Zain brand kit.'],
            ['Tech Expo 2026', 'Booth production behind schedule', 'production', 4, 5, 'escalated', 'Second fabrication shift added; daily standup with Falcon.'],
            ['Tech Expo 2026', 'Production budget overrun', 'budget', 4, 4, 'open', 'Change-order freeze; renegotiating Hall B build.'],
            ['NDI Workshop', 'Trainer availability conflict', 'speaker', 4, 4, 'open', 'Backup trainer shortlisted.'],
            ['EY Annual Gala', 'Ballroom AV rigging restrictions', 'venue', 2, 3, 'mitigated', 'Ground-supported truss approved by venue.'],
            ['GJU Career Fair', 'Rain contingency for outdoor booths', 'weather', 2, 2, 'monitoring', 'Tent supplier on standby.'],
        ] as [$eventName, $title, $category, $probability, $impact, $status, $mitigation]) {
            \App\Models\EventRisk::updateOrCreate(
                ['event_id' => $events[$eventName]->id, 'title' => $title],
                ['category' => $category, 'probability' => $probability, 'impact' => $impact, 'status' => $status, 'mitigation' => $mitigation]);
        }

        // ── Approvals ───────────────────────────────────────────────────
        foreach ([
            ['ICFT 2026', 'Q3 budget revision', 'budget', 'pending', 'Sara Al-Rashid', null],
            ['ICFT 2026', 'Main stage design concept', 'design', 'approved', 'Omar Nassar', 'Emran Ahmed'],
            ['EY Annual Gala', 'Gala menu selection', 'client', 'pending', 'Dana Qasem', null],
            ['Tech Expo 2026', 'Additional production budget', 'budget', 'pending', 'Khalid Mansour', null],
            ['GJU Career Fair', 'University branding kit', 'design', 'approved', 'Layla Haddad', 'Emran Ahmed'],
        ] as [$eventName, $title, $type, $status, $requester, $decider]) {
            \App\Models\EventApproval::updateOrCreate(
                ['event_id' => $events[$eventName]->id, 'title' => $title],
                [
                    'type' => $type, 'status' => $status,
                    'requested_by' => $team[$requester]->id,
                    'decided_by' => $decider ? $team[$decider]->id : null,
                    'decided_at' => $decider ? now()->subDays(3) : null,
                ]);
        }

        // ── Supplier pipeline statuses ──────────────────────────────────
        $pipeline = [
            'ICFT 2026' => ['Creative Vision Co.' => 'contracted', 'Event Tech Solutions' => 'in_production', 'Royal Catering Services' => 'quoted', 'Oasis Logistics' => 'approved'],
            'EY Annual Gala' => ['Royal Catering Services' => 'contracted', 'Petra Decor Studio' => 'delivered', 'Event Tech Solutions' => 'contracted'],
            'NDI Workshop' => ['Event Tech Solutions' => 'issue', 'Creative Vision Co.' => 'approved'],
            'Tech Expo 2026' => ['Falcon Stage Production' => 'in_production', 'Event Tech Solutions' => 'contracted', 'Oasis Logistics' => 'requested'],
            'GJU Career Fair' => ['Creative Vision Co.' => 'delivered', 'Oasis Logistics' => 'contracted'],
            'Private Dinner' => ['Royal Catering Services' => 'completed', 'Petra Decor Studio' => 'completed'],
        ];
        foreach ($pipeline as $eventName => $statuses) {
            foreach ($statuses as $supplierName => $status) {
                $events[$eventName]->suppliers()->updateExistingPivot($suppliers[$supplierName]->id, ['status' => $status]);
            }
        }
    }
}
