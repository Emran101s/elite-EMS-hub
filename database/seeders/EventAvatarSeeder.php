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
                'image_path' => 'avatars/international-conference.png',
                'thumbnail_path' => 'avatars/international-conference-thumb.png',
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
                'image_path' => 'avatars/gala-dinner.png',
                'thumbnail_path' => 'avatars/gala-dinner-thumb.png',
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
                'image_path' => 'avatars/exhibition.png',
                'thumbnail_path' => 'avatars/exhibition-thumb.png',
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
                'image_path' => 'avatars/workshop.png',
                'thumbnail_path' => 'avatars/workshop-thumb.png',
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
            [
                'name' => 'Grand Hall',
                'slug' => 'grand-hall',
                'subtitle' => 'Domed Grand Ballroom',
                'category' => 'gala',
                'best_for' => 'Award Ceremonies, State Dinners, Premium Galas',
                'colors' => ['#F5EFE0', '#0B1F3A', '#D4AF37'],
                'recommended_types' => ['awards_ceremony', 'gala_dinner'],
                'sort_order' => 7,
                'image_path' => 'avatars/grand-hall.png',
                'thumbnail_path' => 'avatars/grand-hall-thumb.png',
            ],
            [
                'name' => 'Seminar',
                'slug' => 'seminar-hall',
                'subtitle' => 'Executive Seminar Hall',
                'category' => 'workshop',
                'best_for' => 'Seminars, Executive Briefings, Knowledge Sessions',
                'colors' => ['#0B1F3A', '#FFFFFF', '#D4AF37'],
                'recommended_types' => ['training_program', 'summit'],
                'sort_order' => 8,
                'image_path' => 'avatars/seminar-hall.png',
                'thumbnail_path' => 'avatars/seminar-hall-thumb.png',
            ],
            [
                'name' => 'Convention Center',
                'slug' => 'convention-center',
                'subtitle' => 'Flagship Convention Campus',
                'category' => 'conference',
                'best_for' => 'Flagship Conferences, Congresses, Expo Campuses',
                'colors' => ['#FFFFFF', '#0B1F3A', '#D4AF37'],
                'recommended_types' => ['conference'],
                'sort_order' => 9,
                'image_path' => 'avatars/convention-center.png',
                'thumbnail_path' => 'avatars/convention-center-thumb.png',
            ],
        ];

        foreach ($avatars as $avatar) {
            EventAvatar::updateOrCreate(['slug' => $avatar['slug']], $avatar);
        }
    }
}
