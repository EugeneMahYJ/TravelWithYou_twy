<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = ['slug', 'name', 'region', 'image', 'blurb'];

    public function packages(): HasMany
    {
        return $this->hasMany(TravelPackage::class);
    }
}
