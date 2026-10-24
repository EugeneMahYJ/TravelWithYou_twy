<?php

namespace Database\Seeders;

use App\Models\Guide;
use Illuminate\Database\Seeder;

class GuideSeeder extends Seeder
{
    public function run(): void
    {
        $prototypeGuides = PrototypeData::all()['blog'];
        foreach ($prototypeGuides as $index => $guide) {
            Guide::updateOrCreate(['slug' => $guide['id']], [
                'title' => $guide['title'],
                'category' => $guide['tag'],
                'read_time' => $guide['read'],
                'image' => $guide['image'],
                'excerpt' => $guide['body'],
                'body' => $guide['body'],
                'published' => true,
                'sort_order' => $index,
            ]);
        }

        $legacyGuides = [
            [
                'slug' => 'a-slower-way-through-kyoto',
                'title' => 'A slower way through Kyoto',
                'category' => 'Field notes',
                'image' => '1558870832-c8db4b5b47d1',
                'excerpt' => 'Find the quiet lanes, early-morning gardens and small rituals that make Kyoto linger.',
                'old_slug' => 'slow-travel-in-kyoto',
            ],
            [
                'slug' => 'when-to-see-the-mara-river-crossings',
                'title' => 'When to see the Great Migration',
                'category' => 'Wildlife',
                'image' => '1535940360221-641a69c43bac',
                'excerpt' => 'A thoughtful month-by-month guide to the Mara’s remarkable river crossings.',
                'old_slug' => 'when-to-see-the-great-migration',
            ],
            [
                'slug' => 'the-amalfi-coast-without-the-rush',
                'title' => 'The Amalfi Coast, without the rush',
                'category' => 'Coastlines',
                'image' => '1612698093158-e07ac200d44e',
                'excerpt' => 'Take the slow road between lemon groves, small harbours and family-run kitchens.',
                'old_slug' => 'amalfi-without-the-rush',
            ],
        ];

        foreach ($legacyGuides as $index => $guide) {
            Guide::updateOrCreate(['slug' => $guide['slug']], [
                'title' => $guide['title'],
                'category' => $guide['category'],
                'image' => $guide['image'],
                'excerpt' => $guide['excerpt'],
                'body' => $guide['excerpt'],
                'published' => true,
                'sort_order' => count($prototypeGuides) + $index,
            ]);
        }
    }
}
