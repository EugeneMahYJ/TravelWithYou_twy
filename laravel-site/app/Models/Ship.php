<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ship extends Model
{
    protected $fillable = [
        'slug', 'name', 'image', 'gallery', 'capacity', 'crew', 'class_name',
        'rating', 'year', 'overview', 'amenities', 'decks',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'amenities' => 'array',
            'decks' => 'array',
            'capacity' => 'integer',
            'crew' => 'integer',
            'rating' => 'float',
            'year' => 'integer',
        ];
    }

    public function cabins(): HasMany
    {
        return $this->hasMany(ShipCabin::class);
    }
}
