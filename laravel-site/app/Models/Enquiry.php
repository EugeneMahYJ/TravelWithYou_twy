<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enquiry extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'subject',
        'message',
        'status',
        'phone',
        'company_name',
        'company_travellers',
        'company_address',
        'honorific',
        'destination',
        'budget_basis',
        'budget_from',
        'budget_to',
    ];

    public function replies(): HasMany
    {
        return $this->hasMany(EnquiryReply::class)->orderBy('sent_at')->orderBy('id');
    }

    public function latestReply(): HasOne
    {
        return $this->hasOne(EnquiryReply::class)->where('status', 'sent')->latestOfMany('sent_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
