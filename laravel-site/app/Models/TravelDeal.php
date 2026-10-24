<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelDeal extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'promo_code',
        'description',
        'discount_percent',
        'ends_at',
        'type',
        'terms',
        'expires_at',
        'package_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'ends_at' => 'date',
            'expires_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class);
    }

    protected static function booted(): void
    {
        static::saving(function (TravelDeal $deal): void {
            $deal->description = $deal->terms ?? $deal->description;
        });
    }
}
