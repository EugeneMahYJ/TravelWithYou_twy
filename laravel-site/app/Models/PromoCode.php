<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $fillable = ['code', 'discount_percent', 'active', 'starts_at', 'expires_at'];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'float',
            'active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
