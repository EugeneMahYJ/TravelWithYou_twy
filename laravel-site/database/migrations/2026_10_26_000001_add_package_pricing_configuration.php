<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->json('pricing')->nullable();
        });

        foreach (DB::table('travel_packages')->whereNotNull('ship')->get(['id', 'ship']) as $package) {
            $ship = DB::table('ships')->where('name', $package->ship)->first(['id']);
            if (! $ship) {
                continue;
            }

            $cabinPrices = DB::table('ship_cabins')
                ->where('ship_id', $ship->id)
                ->pluck('price_from', 'type')
                ->map(fn ($price) => (int) $price)
                ->all();

            if ($cabinPrices !== []) {
                DB::table('travel_packages')->where('id', $package->id)->update([
                    'pricing' => json_encode(['cabin_prices' => $cabinPrices], JSON_THROW_ON_ERROR),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->dropColumn('pricing');
        });
    }
};
