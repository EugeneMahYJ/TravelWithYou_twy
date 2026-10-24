<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        foreach (PrototypeData::all()['countries'] as $country) {
            Country::updateOrCreate(['slug' => $country['id']], [
                'name' => $country['name'],
                'region' => $country['region'],
                'image' => $country['image'],
                'blurb' => $country['blurb'],
            ]);
        }
    }
}
