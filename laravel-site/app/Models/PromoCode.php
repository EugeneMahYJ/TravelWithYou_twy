<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'discount_percent',
        'discount_type',
        'discount_amount',
        'all_packages',
        'terms',
        'active',
        'starts_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'float',
            'discount_amount' => 'float',
            'all_packages' => 'boolean',
            'active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(TravelPackage::class);
    }
}
