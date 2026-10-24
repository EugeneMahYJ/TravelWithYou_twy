<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AssetLibrary
{
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];

    public const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'm4v'];

    public static function all(): Collection
    {
        return collect(self::disk()->allFiles())
            ->filter(fn (string $path): bool => self::kind($path) !== null)
            ->map(fn (string $path): array => self::toArray($path))
            ->sortBy(fn (array $asset): string => strtolower($asset['path']))
            ->values();
    }

    public static function store(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'asset';
        $name = $base.'-'.Str::lower(Str::random(8)).'.'.$extension;

        $path = $file->storeAs('uploads', $name, 'assets');

        return self::toArray($path);
    }

    public static function delete(string $path): ?bool
    {
        if (! self::isManagedPath($path)) {
            return null;
        }

        $disk = self::disk();

        if (! $disk->exists($path)) {
            return null;
        }

        return $disk->delete($path);
    }

    public static function rename(string $path, string $name): array|false|null
    {
        if (! self::isManagedPath($path) || $name === '' || str_contains($name, '/') || str_contains($name, '\\')
            || str_contains($name, "\0") || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._ -]*\z/', $name) !== 1) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $requestedExtension = pathinfo($name, PATHINFO_EXTENSION);
        if ($requestedExtension !== '' && strtolower($requestedExtension) !== strtolower($extension)) {
            return null;
        }

        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME));
        if ($base === '') {
            return null;
        }

        $directory = dirname($path);
        $destination = ($directory === '.' ? '' : $directory.'/').$base.'.'.$extension;
        $disk = self::disk();

        if (! $disk->exists($path)) {
            return null;
        }

        if ($destination === $path) {
            return self::toArray($path);
        }

        if ($disk->exists($destination)) {
            return null;
        }

        if (! $disk->move($path, $destination)) {
            return false;
        }

        return self::toArray($destination);
    }

    public static function kind(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match (true) {
            in_array($extension, self::IMAGE_EXTENSIONS, true) => 'image',
            in_array($extension, self::VIDEO_EXTENSIONS, true) => 'video',
            default => null,
        };
    }

    public static function toArray(string $path): array
    {
        $disk = self::disk();

        return [
            'path' => $path,
            'name' => basename($path),
            'url' => asset('assets/'.$path),
            'kind' => self::kind($path),
            'size' => $disk->size($path),
            'modified' => $disk->lastModified($path),
        ];
    }

    private static function disk()
    {
        return Storage::disk('assets');
    }

    private static function isManagedPath(string $path): bool
    {
        return $path !== ''
            && ! str_contains($path, '\\')
            && ! str_contains($path, "\0")
            && ! str_starts_with($path, '/')
            && ! collect(explode('/', $path))->contains(fn (string $segment): bool => in_array($segment, ['', '.', '..'], true))
            && self::kind($path) !== null;
    }
}
