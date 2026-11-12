<?php

namespace App\Support;

use App\Models\Ship;
use App\Models\TravelPackage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PackageItineraryPdf
{
    public function contents(TravelPackage $package): string
    {
        if ($package->itinerary_pdf_path !== null) {
            $disk = Storage::disk('local');
            if (! $disk->exists($package->itinerary_pdf_path)) {
                throw new NotFoundHttpException('The uploaded package itinerary PDF is missing.');
            }

            $contents = $disk->get($package->itinerary_pdf_path);
            if (! is_string($contents)) {
                throw new RuntimeException('The uploaded package itinerary PDF could not be read.');
            }

            return $contents;
        }

        $brochure = config('package_brochures.by_source_id.'.$package->source_id);
        if ($brochure !== null) {
            $path = public_path('assets/Package_Example/'.$brochure);
            if (! is_file($path)) {
                throw new NotFoundHttpException('The supplied package brochure PDF is missing.');
            }

            $contents = file_get_contents($path);
            if (! is_string($contents)) {
                throw new RuntimeException('The supplied package brochure PDF could not be read.');
            }

            return $contents;
        }

        $ship = $package->ship
            ? Ship::query()->with('cabins')->where('name', $package->ship)->first()
            : null;

        return Pdf::loadView('packages.pdf', [
            'package' => $package,
            'ship' => $ship,
        ])->setPaper('a4')->output();
    }
}
