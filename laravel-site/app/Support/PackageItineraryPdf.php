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

        // Every booking of the same package gets an identical generated PDF, so keep one copy
        // and rebuild it only when the package, ship, cabins or template change.
        $version = sha1(implode('|', [
            $package->id,
            $package->updated_at?->timestamp,
            $ship?->updated_at?->timestamp,
            $ship?->cabins->max('updated_at')?->timestamp,
            filemtime(resource_path('views/packages/pdf.blade.php')) ?: 0,
        ]));
        $disk = Storage::disk('local');
        $cachePath = 'itinerary-cache/'.$package->id.'-'.$version.'.pdf';
        try {
            if ($disk->exists($cachePath) && is_string($cached = $disk->get($cachePath)) && $cached !== '') {
                return $cached;
            }
        } catch (\Throwable) {
            // Fall through and generate the PDF again.
        }

        $contents = Pdf::loadView('packages.pdf', [
            'package' => $package,
            'ship' => $ship,
        ])->setPaper('a4')->output();

        try {
            foreach ($disk->files('itinerary-cache') as $old) {
                if (str_starts_with(basename($old), $package->id.'-')) {
                    $disk->delete($old);
                }
            }
            $disk->put($cachePath, $contents);
        } catch (\Throwable) {
            // The cache is only an optimisation; a write failure must not stop the email.
        }

        return $contents;
    }
}
