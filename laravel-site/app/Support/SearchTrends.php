<?php

namespace App\Support;

use App\Models\SearchTermCount;
use Illuminate\Support\Collection;

class SearchTrends
{
    public static function pastSixMonths(): Collection
    {
        $now = now();
        $start = $now->copy()->startOfMonth()->subMonths(5);

        return SearchTermCount::query()
            ->whereBetween('searched_on', [$start->toDateString(), $now->toDateString()])
            ->selectRaw('term as normalized_query, MIN(label) as query, SUM(hits) as searches')
            ->groupBy('term')
            ->orderByDesc('searches')
            ->orderBy('normalized_query')
            ->limit(5)
            ->get();
    }
}
