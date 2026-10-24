<?php

namespace Database\Seeders;

use App\Models\Ship;
use App\Models\ShipCabin;
use Illuminate\Database\Seeder;

class ShipSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PrototypeData::all()['ships'] as $shipData) {
            $ship = Ship::updateOrCreate(['slug' => $shipData['id']], [
                'name' => $shipData['name'],
                'image' => $shipData['image'],
                'gallery' => $shipData['gallery'],
                'capacity' => $shipData['capacity'],
                'crew' => $shipData['crew'],
                'class_name' => $shipData['cls'],
                'rating' => $shipData['rating'],
                'year' => $shipData['year'],
                'overview' => $shipData['overview'],
                'amenities' => $shipData['amenities'],
                'decks' => $shipData['decks'],
            ]);

            foreach ($shipData['cabins'] as $cabin) {
                ShipCabin::updateOrCreate(
                    ['ship_id' => $ship->id, 'type' => $cabin['type']],
                    [
                        'size' => $cabin['size'],
                        'price_from' => $cabin['from'],
                        'note' => $cabin['note'],
                    ]
                );
            }
        }
    }
}
