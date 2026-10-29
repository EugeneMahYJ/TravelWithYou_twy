<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\TravelPackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TravelPackageSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CountrySeeder::class);
        $countries = collect(Country::all())->keyBy(fn (Country $country) => Str::slug($country->name));

        foreach (PrototypeData::all()['packages'] as $package) {
            $countryKey = Str::slug((string) ($package['country'] ?? ''));
            $country = $countries[$countryKey] ?? Country::firstOrFail();

            TravelPackage::updateOrCreate(['slug' => $package['id']], [
                'country_id' => $country->id,
                'name' => $package['name'],
                'country' => $country->name,
                'region' => $country->region,
                'type' => $package['type'],
                'ship' => $package['ship'] ?? null,
                'days' => $package['days'],
                'nights' => $package['days'] - 1,
                'description' => $package['desc'],
                'long_description' => $package['long'],
                'details' => $package['long'],
                'highlights' => $package['highlights'],
                'fares' => $package['fares'] ?? [],
                'gallery' => $package['gallery'],
                'trip_plan' => $package['trip_plan'],
                'included' => $package['included'],
                'excluded' => $package['excluded'],
                'price_from' => $package['priceFrom'],
                'price_to' => $package['priceTo'],
                'rating' => $package['rating'],
                'reviews' => $package['reviews'],
                'departure_port' => $package['depPort'],
                'destination_port' => $package['destPort'],
                'departures' => $package['departures'],
                'image' => $package['image'],
                'published' => true,
            ]);
        }
    }
}
