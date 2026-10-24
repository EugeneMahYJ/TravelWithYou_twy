<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enquiry extends Model
{
    protected $fillable = ['name', 'email', 'subject', 'message', 'status'];

    public function replies(): HasMany
    {
        return $this->hasMany(EnquiryReply::class)->orderBy('sent_at')->orderBy('id');
    }

    public function latestReply(): HasOne
    {
        return $this->hasOne(EnquiryReply::class)->where('status', 'sent')->latestOfMany('sent_at');
    }
}
