<?php

namespace Database\Seeders;

use App\Models\TravelDeal;
use App\Models\TravelPackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TravelDealSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PrototypeData::all()['deals'] as $dealData) {
            TravelDeal::updateOrCreate(['slug' => $dealData['id']], [
                'name' => $dealData['type'],
                'promo_code' => $dealData['id'],
                'description' => $dealData['terms'],
                'discount_percent' => $dealData['pct'],
                'ends_at' => null,
                'expires_at' => $dealData['endsInSeconds'] === null
                    ? null
                    : Carbon::now()->addSeconds($dealData['endsInSeconds']),
                'package_id' => TravelPackage::where('slug', $dealData['pkgId'])->value('id'),
                'type' => $dealData['type'],
                'terms' => $dealData['terms'],
                'active' => true,
            ]);
        }
    }
}
