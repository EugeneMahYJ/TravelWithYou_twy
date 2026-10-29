<?php

namespace App\Support;

use App\Models\Country;
use App\Models\Guide;
use App\Models\Ship;
use App\Models\TravelPackage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

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

    public static function folders(): Collection
    {
        return collect(self::disk()->allDirectories())
            ->filter(fn (string $path): bool => self::isManagedFolderPath($path))
            ->sort()
            ->values();
    }

    public static function foldersIn(string $parent = ''): Collection
    {
        if ($parent !== '' && ! self::isManagedFolderPath($parent)) {
            return collect();
        }

        return collect(self::disk()->directories($parent))
            ->filter(fn (string $path): bool => self::isManagedFolderPath($path))
            ->sort()
            ->values();
    }

    public static function filesIn(string $folder = ''): Collection
    {
        if ($folder !== '' && ! self::isManagedFolderPath($folder)) {
            return collect();
        }

        if ($folder === '') {
            return self::all();
        }

        return collect(self::disk()->files($folder))
            ->filter(fn (string $path): bool => self::kind($path) !== null)
            ->map(fn (string $path): array => self::toArray($path))
            ->sortBy(fn (array $asset): string => strtolower($asset['path']))
            ->values();
    }

    public static function store(UploadedFile $file, string $folder = 'uploads'): array
    {
        if ($folder !== '' && ! self::isManagedFolderPath($folder)) {
            throw new \InvalidArgumentException('Choose a valid media folder.');
        }

        if ($folder === 'uploads' && ! self::disk()->directoryExists($folder)) {
            self::disk()->makeDirectory($folder);
        } elseif ($folder !== '' && ! self::disk()->directoryExists($folder)) {
            throw new \InvalidArgumentException('The selected media folder does not exist.');
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'asset';
        $name = $base.'-'.Str::lower(Str::random(8)).'.'.$extension;

        $path = $file->storeAs($folder, $name, 'assets');

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

        if (! self::moveFileAndUpdateReferences($path, $destination)) {
            return false;
        }

        return self::toArray($destination);
    }

    public static function move(string $path, string $folder): array|false|null
    {
        if (! self::isManagedPath($path)
            || ($folder !== '' && ! self::isManagedFolderPath($folder))) {
            return null;
        }

        $disk = self::disk();
        if (! $disk->exists($path) || ($folder !== '' && ! $disk->directoryExists($folder))) {
            return null;
        }

        $destination = ($folder === '' ? '' : $folder.'/').basename($path);
        if ($destination === $path) {
            return self::toArray($path);
        }
        if ($disk->exists($destination)) {
            return null;
        }
        if (! self::moveFileAndUpdateReferences($path, $destination)) {
            return false;
        }

        return self::toArray($destination);
    }

    public static function createFolder(string $parent, string $name): string|false|null
    {
        if (($parent !== '' && ! self::isManagedFolderPath($parent)) || ! self::isValidFolderName($name)) {
            return null;
        }

        $disk = self::disk();
        if ($parent !== '' && ! $disk->directoryExists($parent)) {
            return null;
        }

        $path = ($parent === '' ? '' : $parent.'/').Str::slug($name);
        if ($path === '' || $disk->directoryExists($path) || $disk->exists($path)) {
            return null;
        }

        return $disk->makeDirectory($path) ? $path : false;
    }

    public static function renameFolder(string $folder, string $name): string|false|null
    {
        if (! self::isManagedFolderPath($folder) || ! self::isValidFolderName($name)) {
            return null;
        }

        $disk = self::disk();
        if (! $disk->directoryExists($folder)) {
            return null;
        }

        $parent = dirname($folder);
        $destination = ($parent === '.' ? '' : $parent.'/').Str::slug($name);
        if ($destination === $folder) {
            return $folder;
        }
        if ($disk->directoryExists($destination) || $disk->exists($destination)) {
            return null;
        }

        return self::moveFolderContents($folder, $destination);
    }

    public static function moveFolder(string $folder, string $destinationParent): string|false|null
    {
        if (! self::isManagedFolderPath($folder)
            || ($destinationParent !== '' && ! self::isManagedFolderPath($destinationParent))) {
            return null;
        }

        $disk = self::disk();
        if (! $disk->directoryExists($folder)
            || ($destinationParent !== '' && ! $disk->directoryExists($destinationParent))
            || ($destinationParent !== '' && str_starts_with($destinationParent.'/', $folder.'/'))) {
            return null;
        }

        $destination = ($destinationParent === '' ? '' : $destinationParent.'/').basename($folder);
        if ($destination === $folder) {
            return $folder;
        }
        if ($disk->directoryExists($destination) || $disk->exists($destination)) {
            return null;
        }

        return self::moveFolderContents($folder, $destination);
    }

    public static function deleteFolder(string $folder): bool|null
    {
        if (! self::isManagedFolderPath($folder) || ! self::disk()->directoryExists($folder)) {
            return null;
        }

        $disk = self::disk();
        if ($disk->allFiles($folder) !== [] || $disk->allDirectories($folder) !== []) {
            return false;
        }

        return $disk->deleteDirectory($folder);
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

    private static function moveFolderContents(string $source, string $destination): string|false
    {
        $disk = self::disk();
        $oldFiles = $disk->allFiles($source);
        $oldDirectories = $disk->allDirectories($source);
        if (! $disk->makeDirectory($destination)) {
            return false;
        }

        foreach ($oldDirectories as $oldDirectory) {
            $newDirectory = $destination.substr($oldDirectory, strlen($source));
            if (! $disk->makeDirectory($newDirectory)) {
                self::removeMovedFolder($destination, []);

                return false;
            }
        }

        $movedFiles = [];
        foreach ($oldFiles as $oldPath) {
            $newPath = $destination.substr($oldPath, strlen($source));
            $newParent = dirname($newPath);
            if ($newParent !== '.' && ! $disk->directoryExists($newParent) && ! $disk->makeDirectory($newParent)) {
                self::removeMovedFolder($destination, $movedFiles);

                return false;
            }
            if (! $disk->move($oldPath, $newPath)) {
                self::removeMovedFolder($destination, $movedFiles);

                return false;
            }
            $movedFiles[$oldPath] = $newPath;
        }

        try {
            DB::transaction(function () use ($movedFiles, $disk, $source): void {
                foreach ($movedFiles as $oldPath => $newPath) {
                    self::updateReferences($oldPath, $newPath);
                }

                if (! $disk->deleteDirectory($source)) {
                    throw new RuntimeException('Unable to remove the source media folder after moving its contents.');
                }
            });
        } catch (\Throwable $exception) {
            self::removeMovedFolder($destination, $movedFiles);

            throw $exception;
        }

        return $destination;
    }

    private static function moveFileAndUpdateReferences(string $source, string $destination): bool
    {
        $disk = self::disk();
        if (! $disk->move($source, $destination)) {
            return false;
        }

        try {
            DB::transaction(fn () => self::updateReferences($source, $destination));
        } catch (\Throwable $exception) {
            if (! $disk->move($destination, $source)) {
                throw new RuntimeException('Media references could not be updated and the file could not be restored.', 0, $exception);
            }

            throw $exception;
        }

        return true;
    }

    private static function removeMovedFolder(string $destination, array $movedFiles): void
    {
        $disk = self::disk();
        foreach (array_reverse($movedFiles, true) as $oldPath => $newPath) {
            $oldParent = dirname($oldPath);
            if ($oldParent !== '.' && ! $disk->directoryExists($oldParent) && ! $disk->makeDirectory($oldParent)) {
                throw new RuntimeException('A media folder could not be restored after a folder move failed.');
            }
            if (! $disk->move($newPath, $oldPath)) {
                throw new RuntimeException('A media file could not be restored after a folder move failed.');
            }
        }

        if (! $disk->deleteDirectory($destination)) {
            throw new RuntimeException('A partially moved media folder could not be cleaned up.');
        }
    }

    private static function updateReferences(string $oldPath, string $newPath): void
    {
        $replacements = [
            $oldPath => $newPath,
            'assets/'.$oldPath => 'assets/'.$newPath,
            'video:'.$oldPath => 'video:'.$newPath,
        ];
        if (! str_contains($oldPath, '/')) {
            $extensionless = pathinfo($oldPath, PATHINFO_FILENAME);
            $replacements[$extensionless] = $newPath;
        }

        foreach ([
            TravelPackage::class => ['image', 'gallery'],
            Ship::class => ['image', 'gallery'],
            Guide::class => ['image'],
            Country::class => ['image'],
        ] as $model => $fields) {
            foreach ($fields as $field) {
                $model::query()->select(['id', $field])->chunkById(100, function ($records) use ($model, $field, $replacements): void {
                    foreach ($records as $record) {
                        $value = $record->getAttribute($field);
                        $updated = self::replaceAssetReferences($value, $replacements);
                        if ($updated !== $value) {
                            $model::query()->whereKey($record->getKey())->update([$field => is_array($updated) ? json_encode($updated) : $updated]);
                        }
                    }
                });
            }
        }
    }

    private static function replaceAssetReferences(mixed $value, array $replacements): mixed
    {
        if (is_string($value)) {
            return $replacements[$value] ?? $value;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::replaceAssetReferences($item, $replacements);
            }
        }

        return $value;
    }

    private static function isValidFolderName(string $name): bool
    {
        return $name !== ''
            && strlen($name) <= 80
            && preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._ -]*\z/', $name) === 1
            && ! in_array($name, ['.', '..'], true);
    }

    private static function isManagedFolderPath(string $path): bool
    {
        return $path !== ''
            && ! str_contains($path, '\\')
            && ! str_contains($path, "\0")
            && ! str_starts_with($path, '/')
            && ! collect(explode('/', $path))->contains(fn (string $segment): bool => in_array($segment, ['', '.', '..'], true));
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
