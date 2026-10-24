<?php

namespace Database\Seeders;

use App\Models\TravelPackage;
use Illuminate\Database\Seeder;

class PackageContentSeeder extends Seeder
{
    public function run(): void
    {
        $path = __DIR__.'/data/package_content.json';
        $content = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($content as $sourceId => $fields) {
            $package = TravelPackage::query()->where('source_id', $sourceId)->first();

            if ($package === null) {
                continue;
            }

            $package->forceFill($fields)->save();
        }
    }
}
