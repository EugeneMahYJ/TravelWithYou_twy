<?php

namespace Database\Seeders;

use App\Models\PromoCode;
use Illuminate\Database\Seeder;

class PromoCodeSeeder extends Seeder
{
    public function run(): void
    {
        $promos = collect(PrototypeData::all()['promos'])
            ->map(fn (float $discount) => $discount * 100)
            ->all();

        foreach ([
            ['AMALFI20', 20, '2027-04-30'],
            ['INCA15', 15, '2027-03-31'],
            ['MARA10', 10, '2027-06-30'],
        ] as $legacyPromo) {
            $promos[$legacyPromo[0]] = [
                'discount' => $legacyPromo[1],
                'expires_at' => $legacyPromo[2],
            ];
        }

        foreach ($promos as $code => $discount) {
            $percentage = is_array($discount) ? $discount['discount'] : $discount;
            PromoCode::updateOrCreate(['code' => $code], [
                'discount_percent' => $percentage,
                'active' => true,
                'starts_at' => null,
                'expires_at' => is_array($discount) ? $discount['expires_at'] : null,
            ]);
        }
    }
}
