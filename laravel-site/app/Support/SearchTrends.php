<?php

namespace App\Support;

use App\Models\SearchLog;
use Illuminate\Support\Collection;

class SearchTrends
{
    public static function pastSixMonths(): Collection
    {
        $now = now();
        $start = $now->copy()->startOfMonth()->subMonths(5);

        return SearchLog::query()
            ->whereNotNull('query')
            ->whereRaw("TRIM(query) <> ''")
            ->whereBetween('created_at', [$start, $now])
            ->selectRaw('LOWER(TRIM(query)) as normalized_query, MIN(TRIM(query)) as query, COUNT(*) as searches')
            ->groupByRaw('LOWER(TRIM(query))')
            ->orderByDesc('searches')
            ->orderBy('normalized_query')
            ->limit(5)
            ->get();
    }
}
