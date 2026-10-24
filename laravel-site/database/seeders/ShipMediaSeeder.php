<?php

namespace Database\Seeders;

use App\Models\Ship;
use Illuminate\Database\Seeder;

class ShipMediaSeeder extends Seeder
{
    public function run(): void
    {
        $ship = Ship::query()->where('slug', 'navigator-of-the-seas')->first();

        if ($ship === null) {
            return;
        }

        $video = 'video:Ship_example.mp4';

        if (! is_file(public_path('assets/video/Ship_example.mp4'))) {
            return;
        }

        $gallery = $ship->gallery ?? [];

        if (! in_array($video, $gallery, true)) {
            $gallery[] = $video;
            $ship->forceFill(['gallery' => $gallery])->save();
        }
    }
}
