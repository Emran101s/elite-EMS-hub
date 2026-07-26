<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fills one event to the scale a real summit actually runs at.
 *
 * Every screen in this product was being judged against 6 attendees, 0
 * suppliers and 0 spend — so layouts built for density looked barren and the
 * design was taking the blame for the data. This seeds the flagship event with
 * the volume the reference designs assume: ~620 participants, 38 suppliers,
 * 24 speakers, 8 sponsors and a budget that is genuinely part-spent.
 *
 * Idempotent: re-running tops up to target rather than duplicating.
 */
class FlagshipEventSeeder extends Seeder
{
    public function run(): void
    {
        $event = Event::query()->withCount('agendaSessions')
            ->orderByDesc('agenda_sessions_count')->first();

        if (! $event) {
            $this->command?->warn('No event to fill.');

            return;
        }

        $this->command?->info("Filling “{$event->name}” to flagship scale…");

        $this->attendees($event, 620);
        $this->suppliers($event, 38);
        $this->speakers($event, 24);
        $this->sponsors($event);
        $this->spend($event);

        $this->command?->info('Done.');
    }

    private function attendees(Event $event, int $target): void
    {
        $have = $event->attendees()->count();
        if ($have >= $target) {
            return;
        }

        $first = ['Layla', 'Omar', 'Rana', 'Khalid', 'Dana', 'Yousef', 'Maha', 'Tariq', 'Salma', 'Nabil',
            'Hala', 'Ziad', 'Farah', 'Sami', 'Lina', 'Bashar', 'Noor', 'Adel', 'Reem', 'Faris',
            'Anna', 'Mikael', 'Sophie', 'Daniel', 'Priya', 'Chen', 'Ahmed', 'Yara', 'Karim', 'Leila'];
        $last = ['Haddad', 'Khalil', 'Nasser', 'Farah', 'Aziz', 'Mansour', 'Darwish', 'Sayegh', 'Kassem',
            'Barakat', 'Toukan', 'Shami', 'Rahal', 'Zahran', 'Odeh', 'Salti', 'Muasher', 'Tell',
            'Lindqvist', 'Okonkwo', 'Meyer', 'Rossi', 'Sharma', 'Wei'];
        $orgs = ['Ministry of Planning', 'UNDP', 'World Bank', 'Royal Scientific Society', 'Zain Group',
            'Aramex', 'Hikma Pharmaceuticals', 'Arab Bank', 'GIZ', 'USAID', 'Crown Prince Foundation',
            'King Abdullah II Fund', 'Umniah', 'Orange Jordan', 'EBRD', 'IFC', 'Talal Abu-Ghazaleh',
            'Jordan Chamber of Industry', 'Luminus Education', 'Endeavor Jordan'];
        $titles = ['Director of Policy', 'Programme Manager', 'Senior Economist', 'Head of Partnerships',
            'Chief of Staff', 'Regional Director', 'Research Lead', 'Country Manager',
            'Head of Communications', 'Investment Officer', 'Advisor', 'Deputy Director'];

        // A realistic funnel: most confirmed, a tail pending, a small waitlist.
        $mix = array_merge(
            array_fill(0, 62, 'confirmed'),
            array_fill(0, 22, 'registered'),
            array_fill(0, 11, 'pending'),
            array_fill(0, 5, 'waitlist'),
        );

        $rows = [];
        for ($i = $have; $i < $target; $i++) {
            $name = $first[$i % count($first)].' '.$last[intdiv($i, 7) % count($last)];
            $status = $mix[$i % count($mix)];
            $vip = $i % 23 === 0;

            $rows[] = [
                'event_id' => $event->id,
                'name' => $name,
                'email' => strtolower(str_replace(' ', '.', $name)).$i.'@example.org',
                'organization' => $orgs[$i % count($orgs)],
                'job_title' => $titles[$i % count($titles)],
                'ticket_type' => $vip ? 'vip' : ($i % 9 === 0 ? 'speaker' : 'delegate'),
                'status' => $status,
                'amount_cents' => $vip ? 45000 : ($i % 9 === 0 ? 0 : 18000),
                'vip' => $vip,
                'checked_in_at' => null,
                'created_at' => now()->subDays(random_int(1, 120)),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('event_attendees')->insert($chunk);
        }
    }

    private function suppliers(Event $event, int $target): void
    {
        if ($event->suppliers()->count() >= $target) {
            return;
        }

        $catalogue = [
            ['Falcon AV Productions', 'av_production'], ['Amman Stage Works', 'av_production'],
            ['LightHouse Rigging', 'av_production'], ['SoundCore MENA', 'av_production'],
            ['Zaytouna Catering', 'catering'], ['Levant Fine Dining', 'catering'],
            ['Barakat Coffee Co.', 'catering'], ['Sweet Levant Patisserie', 'catering'],
            ['Royal Jordanian Transport', 'transport'], ['Petra Fleet Services', 'transport'],
            ['Desert Star Limousines', 'transport'], ['CityLink Coaches', 'transport'],
            ['Gulf Print House', 'printing'], ['Signature Signage', 'printing'],
            ['Badge & Lanyard Co.', 'printing'], ['Amman Exhibition Stands', 'exhibition'],
            ['Modular Booth Systems', 'exhibition'], ['Cedar Interpretation Services', 'interpretation'],
            ['LinguaBridge', 'interpretation'], ['Simultaneous MENA', 'interpretation'],
            ['SecureForce Events', 'security'], ['Guardian Event Security', 'security'],
            ['Jordan Medical Standby', 'medical'], ['FirstCare Event Medics', 'medical'],
            ['Bloom Floral Design', 'decor'], ['Atelier Décor Amman', 'decor'],
            ['Pixel & Frame Photography', 'media'], ['Northline Film', 'media'],
            ['Cloud9 Live Streaming', 'media'], ['Meridian Travel', 'travel'],
            ['Skyway Ticketing', 'travel'], ['Concierge Jordan', 'hospitality'],
            ['Elite Ushers', 'staffing'], ['EventForce Crew', 'staffing'],
            ['GreenClean Facilities', 'facilities'], ['PowerGen Rentals', 'facilities'],
            ['Connect WiFi Solutions', 'it'], ['RegDesk Systems', 'it'],
        ];

        // Most engaged and fine; a handful mid-negotiation; two genuinely in trouble.
        $statuses = array_merge(
            array_fill(0, 24, 'confirmed'),
            array_fill(0, 8, 'quoted'),
            array_fill(0, 4, 'contracted'),
            ['issue', 'issue'],
        );

        foreach (array_slice($catalogue, 0, $target) as $i => [$name, $category]) {
            $supplier = Supplier::firstOrCreate(
                ['name' => $name],
                ['category' => $category, 'city' => 'Amman', 'country' => 'Jordan', 'rating' => random_int(3, 5)]
            );

            if (! $event->suppliers()->where('suppliers.id', $supplier->id)->exists()) {
                $event->suppliers()->attach($supplier->id, ['status' => $statuses[$i % count($statuses)]]);
            }
        }
    }

    private function speakers(Event $event, int $target): void
    {
        if ($event->speakers()->count() >= $target) {
            return;
        }

        $people = [
            ['Dr. Layla Haddad', 'Chief Economist', 'World Bank', 'Financing the next decade of public infrastructure', true],
            ['H.E. Omar Nasser', 'Minister of Planning', 'Government of Jordan', 'Opening address', true],
            ['Prof. Anna Lindqvist', 'Director, Governance Lab', 'Stockholm University', 'Trust and the digital state', true],
            ['Dr. Chen Wei', 'Head of Urban Systems', 'Tsinghua University', 'Cities that pay for themselves', false],
            ['Rana Khalil', 'Partner', 'McKinsey & Company', 'From strategy to delivery', false],
            ['Yousef Mansour', 'CEO', 'Zain Group', 'Connectivity as public infrastructure', false],
            ['Dr. Priya Sharma', 'Director of Health Policy', 'WHO EMRO', 'Resilient health systems', false],
            ['Khalid Darwish', 'Secretary General', 'Arab Planning Institute', 'Regional coordination', false],
            ['Sophie Meyer', 'Head of Climate Finance', 'EBRD', 'Green transition funding', false],
            ['Tariq Sayegh', 'Founder', 'Endeavor Jordan', 'Building the entrepreneurial pipeline', false],
            ['Dana Barakat', 'Director of Communications', 'UNDP', 'Public narrative and reform', false],
            ['Dr. Nabil Toukan', 'Dean of Public Policy', 'University of Jordan', 'Educating the civil service', false],
            ['Maha Aziz', 'Regional Director', 'GIZ', 'Technical cooperation in practice', false],
            ['Daniel Okonkwo', 'Head of Digital Government', 'Smart Africa', 'Interoperable public services', false],
            ['Salma Kassem', 'Chief of Staff', 'Crown Prince Foundation', 'Youth and participation', false],
            ['Ziad Rahal', 'Managing Director', 'Arab Bank', 'Capital for national projects', false],
            ['Hala Shami', 'Director', 'Royal Scientific Society', 'Research to policy', false],
            ['Mikael Rossi', 'Senior Fellow', 'OECD', 'Measuring what matters', false],
            ['Farah Zahran', 'Head of Programmes', 'King Abdullah II Fund', 'Community delivery models', false],
            ['Sami Odeh', 'Country Manager', 'IFC', 'Blended finance', false],
            ['Lina Muasher', 'Director of Strategy', 'Aramex', 'Logistics and national competitiveness', false],
            ['Bashar Salti', 'Advisor', 'Talal Abu-Ghazaleh', 'Regulatory reform', false],
            ['Noor Tell', 'Programme Lead', 'USAID', 'Partnership at scale', false],
            ['Adel Ahmed', 'Head of Innovation', 'Umniah', 'Public-private innovation', false],
        ];

        $statuses = ['confirmed', 'confirmed', 'confirmed', 'confirmed', 'invited', 'pending'];

        foreach (array_slice($people, 0, $target) as $i => [$name, $title, $org, $topic, $keynote]) {
            $event->speakers()->firstOrCreate(['name' => $name], [
                'title' => $title,
                'organization' => $org,
                'topic' => $topic,
                'is_keynote' => $keynote,
                'status' => $keynote ? 'confirmed' : $statuses[$i % count($statuses)],
                'fee_cents' => $keynote ? 850000 : ($i % 4 === 0 ? 250000 : 0),
                'email' => strtolower(str_replace([' ', '.'], ['.', ''], $name)).'@example.org',
                'sort_order' => $i,
            ]);
        }
    }

    private function sponsors(Event $event): void
    {
        if ($event->sponsors()->count() > 0) {
            return;
        }

        foreach ([
            ['Zain Group', 'platinum', 12000000, 12000000, 'A1'],
            ['Arab Bank', 'platinum', 12000000, 6000000, 'A2'],
            ['Aramex', 'gold', 7500000, 7500000, 'B1'],
            ['Hikma Pharmaceuticals', 'gold', 7500000, 0, 'B2'],
            ['Umniah', 'silver', 4000000, 4000000, 'C1'],
            ['Orange Jordan', 'silver', 4000000, 2000000, 'C2'],
            ['Luminus Education', 'bronze', 2000000, 2000000, 'D1'],
            ['Endeavor Jordan', 'bronze', 2000000, 0, 'D2'],
        ] as [$name, $package, $amount, $paid, $booth]) {
            $event->sponsors()->create([
                'name' => $name,
                'package' => $package,
                'amount_cents' => $amount,
                'paid_cents' => $paid,
                'payment_status' => $paid >= $amount ? 'paid' : ($paid > 0 ? 'partial' : 'pending'),
                'booth' => $booth,
            ]);
        }
    }

    /** Put real spend against the budget so the money screens have something to say. */
    private function spend(Event $event): void
    {
        if ($event->budgetItems()->sum('actual_cents') > 0) {
            return;
        }

        foreach ($event->budgetItems as $i => $item) {
            $estimate = (int) $item->estimated_cents;
            if ($estimate <= 0) {
                continue;
            }

            // Most lines land close to estimate; a few over, a few not yet invoiced.
            $actual = match ($i % 7) {
                0 => 0,                              // not started
                1 => (int) round($estimate * 1.12),  // over
                2 => (int) round($estimate * 0.55),  // part-delivered
                default => (int) round($estimate * random_int(92, 104) / 100),
            };

            $item->update([
                'actual_cents' => $actual,
                'payment_status' => $actual === 0 ? 'pending' : ($i % 3 === 0 ? 'paid' : 'invoiced'),
            ]);
        }
    }
}
