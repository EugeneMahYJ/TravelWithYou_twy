<?php

namespace Database\Seeders;

use JsonException;

final class PrototypeData
{
    public static function all(): array
    {
        $data = json_decode(
            file_get_contents(database_path('seeders/data/regions_countries.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (! is_array($data)) {
            throw new JsonException('Prototype data must decode to an array.');
        }

        return $data;
    }
}
