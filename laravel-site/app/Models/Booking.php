<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'reference',
        'order_reference',
        'user_id',
        'package_id',
        'departure_date',
        'guests',
        'guest_details',
        'traveller_passports',
        'subtotal',
        'discount',
        'taxes',
        'total',
        'passport_number',
        'status',
        'payment_method',
        'cancelled_at',
        'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'cancelled_at' => 'datetime',
            'guest_details' => 'array',
            'traveller_passports' => 'encrypted:array',
            'subtotal' => 'float',
            'discount' => 'float',
            'taxes' => 'float',
            'total' => 'float',
            'passport_number' => 'encrypted',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class, 'package_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
