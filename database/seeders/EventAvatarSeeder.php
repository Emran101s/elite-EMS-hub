<?php

namespace Database\Seeders;

use App\Models\EventAvatar;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The six launch avatars. Idempotent — matched by slug.
 * image_path stays null: each slug has a built-in premium SVG scene at
 * resources/views/components/avatars/{slug}.blade.php. Uploaded renders
 * (and later 3D models) simply fill the path columns.
 */
class EventAvatarSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $avatars = [
            [
                'name' => 'International Conference',
                'slug' => 'international-conference',
                'subtitle' => 'Modern Convention Center',
                'category' => 'conference',
                'best_for' => 'Conferences, Summits, Forums, Congresses',
                'colors' => ['#FFFFFF', '#0B1F3A', '#D4AF37'],
                'recommended_types' => ['conference', 'summit', 'hybrid_event', 'online_event'],
                'sort_order' => 1,
            ],
            [
                'name' => 'Gala Dinner',
                'slug' => 'gala-dinner',
                'subtitle' => 'Luxury Ballroom',
                'category' => 'gala',
                'best_for' => 'Gala Dinners, Awards, Celebrations',
                'colors' => ['#D4AF37', '#FFFFFF', '#0B1F3A'],
                'recommended_types' => ['gala_dinner', 'awards_ceremony'],
                'sort_order' => 2,
            ],
            [
                'name' => 'Exhibition',
                'slug' => 'exhibition',
                'subtitle' => 'Exhibition Park',
                'category' => 'exhibition',
                'best_for' => 'Exhibitions, Trade Shows, Career Fairs',
                'colors' => ['#3B82F6', '#FFFFFF', '#94A3B8'],
                'recommended_types' => ['exhibition', 'career_fair', 'product_launch'],
                'sort_order' => 3,
            ],
            [
                'name' => 'Workshop',
                'slug' => 'workshop',
                'subtitle' => 'Learning Center',
                'category' => 'workshop',
                'best_for' => 'Workshops, Training, Bootcamps',
                'colors' => ['#22C55E', '#FFFFFF', '#0B1F3A'],
                'recommended_types' => ['workshop', 'training_program'],
                'sort_order' => 4,
            ],
            [
                'name' => 'VIP Event',
                'slug' => 'vip-event',
                'subtitle' => 'Private Luxury Villa',
                'category' => 'vip',
                'best_for' => 'VIP Receptions, Embassy Events, CEO Meetings',
                'colors' => ['#F8FAFC', '#0B1F3A', '#D4AF37'],
                'recommended_types' => ['private_dinner', 'vip_reception', 'embassy_event'],
                'sort_order' => 5,
            ],
            [
                'name' => 'Festival / Outdoor Event',
                'slug' => 'festival-outdoor',
                'subtitle' => 'Outdoor Event Island',
                'category' => 'festival',
                'best_for' => 'Festivals, Public Events, National Events',
                'colors' => ['#F59E0B', '#3B82F6', '#22C55E'],
                'recommended_types' => ['outdoor_event', 'public_event'],
                'sort_order' => 6,
            ],
        ];

        foreach ($avatars as $avatar) {
            EventAvatar::updateOrCreate(['slug' => $avatar['slug']], $avatar);
        }
    }
}
