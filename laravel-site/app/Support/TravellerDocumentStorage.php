<?php

namespace App\Support;

use App\Models\TravelPackage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class TravellerDocumentStorage
{
    private const ROOT = 'Clients';

    public function storePending(
        UploadedFile $file,
        TravelPackage $package,
        string $departureDate,
        string $cartUid,
        int $travellerIndex,
    ): string {
        if (Str::isUuid($cartUid) === false) {
            throw new RuntimeException('Invalid traveller document staging reference.');
        }

        return $this->store($file, $this->packageFolder($package, $departureDate).'/.pending/'.$cartUid, $travellerIndex);
    }

    public function storeForBooking(
        UploadedFile $file,
        TravelPackage $package,
        string $departureDate,
        string $bookingReference,
        int $travellerIndex,
    ): string {
        return $this->store($file, $this->packageFolder($package, $departureDate).'/'.$bookingReference, $travellerIndex);
    }

    public function moveToBooking(
        string $path,
        TravelPackage $package,
        string $departureDate,
        string $bookingReference,
    ): string {
        if (! $this->isClientPath($path)) {
            throw new RuntimeException('Invalid traveller document path.');
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            throw new RuntimeException('A staged traveller document is missing.');
        }

        $destination = $this->packageFolder($package, $departureDate).'/'.$bookingReference.'/'.basename($path);
        $disk->makeDirectory(dirname($destination));
        if (! $disk->move($path, $destination)) {
            throw new RuntimeException('A traveller document could not be moved into its booking folder.');
        }

        return $destination;
    }

    public function restorePending(string $bookingPath, string $pendingPath): bool
    {
        if (! $this->isClientPath($bookingPath) || ! $this->isClientPath($pendingPath)) {
            throw new RuntimeException('Invalid traveller document path.');
        }

        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($pendingPath));

        return $disk->exists($bookingPath) && $disk->move($bookingPath, $pendingPath);
    }

    public function delete(string $path): bool
    {
        if (! $this->isClientPath($path)) {
            throw new RuntimeException('Invalid traveller document path.');
        }

        $disk = Storage::disk('local');
        return ! $disk->exists($path) || $disk->delete($path);
    }

    public function exists(string $path): bool
    {
        return $this->isClientPath($path) && Storage::disk('local')->exists($path);
    }

    public function cleanupPendingBefore(Carbon $cutoff): int
    {
        $disk = Storage::disk('local');
        $deleted = 0;

        foreach ($disk->allFiles(self::ROOT) as $path) {
            if (! str_contains($path, '/.pending/')) {
                continue;
            }

            if (Carbon::createFromTimestamp($disk->lastModified($path))->greaterThanOrEqualTo($cutoff)) {
                continue;
            }

            if (! $disk->delete($path)) {
                throw new RuntimeException('Unable to delete an expired staged traveller document: '.$path);
            }
            $deleted++;
        }

        return $deleted;
    }

    private function store(UploadedFile $file, string $directory, int $travellerIndex): string
    {
        $disk = Storage::disk('local');
        if (! $disk->makeDirectory($directory)) {
            throw new RuntimeException('Unable to create the private traveller document folder.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $filename = sprintf('traveller-%02d_%s.%s', $travellerIndex + 1, Str::uuid(), $extension);
        $path = $file->storeAs($directory, $filename, 'local');
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Unable to store a traveller document.');
        }

        return $path;
    }

    private function packageFolder(TravelPackage $package, string $departureDate): string
    {
        $country = $package->countryRecord;
        $regionName = $country?->region ?: $package->region;
        $countryName = $country?->name ?: $package->country;
        $packageName = Str::slug($package->slug ?: $package->name) ?: 'travel-package';

        return implode('/', [
            self::ROOT,
            $this->safeSegment($regionName),
            $this->safeSegment($countryName),
            $packageName.'_'.Carbon::parse($departureDate)->format('Y-m-d'),
        ]);
    }

    private function safeSegment(string $value): string
    {
        $segment = trim((string) preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]/u', '-', $value), " .");
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return 'unspecified';
        }

        return Str::substr($segment, 0, 100);
    }

    private function isClientPath(string $path): bool
    {
        return str_starts_with($path, self::ROOT.'/')
            && ! str_contains($path, "\0")
            && ! preg_match('#(?:^|/)\.\.(?:/|$)#', $path);
    }
}
