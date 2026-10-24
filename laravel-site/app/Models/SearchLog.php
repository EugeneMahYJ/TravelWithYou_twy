<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchLog extends Model
{
    protected $fillable = ['user_id', 'query', 'filters', 'results_count'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'results_count' => 'integer'];
    }
}
