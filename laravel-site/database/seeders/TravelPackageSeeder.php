<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\TravelPackage;
use Illuminate\Database\Seeder;

class TravelPackageSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CountrySeeder::class);
        $countries = collect(Country::all())->keyBy('slug');

        foreach (PrototypeData::all()['packages'] as $package) {
            TravelPackage::updateOrCreate(['slug' => $package['id']], [
                'country_id' => $countries[$package['country']]->id,
                'name' => $package['name'],
                'country' => $countries[$package['country']]->name,
                'region' => $countries[$package['country']]->region,
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
