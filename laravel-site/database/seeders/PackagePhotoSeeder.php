<?php

namespace Database\Seeders;

use App\Models\TravelPackage;
use Illuminate\Database\Seeder;

class PackagePhotoSeeder extends Seeder
{
    private const PACKAGE_PHOTOS = [
        'sakura-rail' => [2, 3, 7],
        'kyoto-retreat' => [7, 4, 2],
        'amalfi-escape' => [8, 9, 11],
        'med-grand' => [19, 17, 9],
        'aegean-hopper' => [17, 18, 19],
        'inca-trail' => [20, 130, 131],
        'mara-migration' => [24, 26, 27],
        'iceland-aurora' => [83, 84, 86],
        'andaman-escape' => [28, 29, 31],
        'reef-cruise' => [33, 34, 32],
        'atlantic-crossing' => [89, 88, 84],
        'bali-terraces' => [37, 38, 39],
        'pacific-crossing' => [91, 92, 94],
        '5DCY4' => [96, 97, 98],
        'JPD-12' => [133, 134, 45],
        'CUS-6' => [48, 50, 51],
        '10XBI' => [52, 54, 55],
        'PECZ-3' => [57, 58, 59],
        'AMA-DANUBE-LNY-2027' => [60, 62, 63],
        'OCE-SIRENA-MAR2027' => [118, 122, 125],
        'OCE-RIVIERA-APR2027' => [103, 105, 82],
        'OCE-RIVIERA-MAY2027-ALASKA' => [68, 70, 71],
        'OCE-RIVIERA-MAY2027-WONDER' => [109, 110, 73],
        'SILV-ENDEAVOUR-JUL2027' => [74, 76, 77],
        'SILV-SPIRIT-MAY2027' => [78, 80, 81],
        'SILV-SPIRIT-JUN2027' => [127, 128, 111],
        'STAR-VOYAGER-PKL-2026' => [114, 103, 117],
    ];

    public function run(): void
    {
        foreach (self::PACKAGE_PHOTOS as $packageKey => $photoIndexes) {
            $package = TravelPackage::query()
                ->where('slug', $packageKey)
                ->orWhere('source_id', $packageKey)
                ->first();

            if ($package === null) {
                continue;
            }

            $photos = array_map(
                static fn (int $index): string => 'package-photo-'.$index,
                $photoIndexes,
            );

            $package->forceFill([
                'image' => $photos[0],
                'gallery' => $photos,
            ])->save();
        }
    }
}
