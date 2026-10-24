<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guide extends Model
{
    protected $fillable = [
        'slug', 'title', 'category', 'read_time', 'image', 'excerpt',
        'body', 'published', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['published' => 'boolean', 'sort_order' => 'integer'];
    }
}
