<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipCabin extends Model
{
    protected $fillable = ['ship_id', 'type', 'size', 'price_from', 'note'];

    protected function casts(): array
    {
        return ['price_from' => 'integer'];
    }

    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }
}
