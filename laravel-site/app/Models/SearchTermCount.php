<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * How many times each search term was used per day. Stores no user, session or
 * filter data, and old rows are pruned by the scheduled search-terms:prune command.
 */
class SearchTermCount extends Model
{
    public $timestamps = false;

    protected $fillable = ['searched_on', 'term', 'label', 'hits'];

    protected function casts(): array
    {
        // searched_on stays a plain Y-m-d string so it compares exactly with where() and between().
        return ['hits' => 'integer'];
    }

    public static function normalise(string $term): string
    {
        return mb_strtolower(mb_substr(trim((string) preg_replace('/\s+/', ' ', $term)), 0, 120));
    }

    /** The wording as typed (first spelling seen that day), used when showing a trending search. */
    public static function label(string $term): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/', ' ', $term)), 0, 120);
    }

    public static function record(string $term): void
    {
        $label = self::label($term);
        $term = self::normalise($term);
        if (mb_strlen($term) < 2) {
            return;
        }

        $day = now()->toDateString();
        $where = ['searched_on' => $day, 'term' => $term];
        if (self::query()->where($where)->increment('hits') > 0) {
            return;
        }

        try {
            self::query()->create($where + ['label' => $label, 'hits' => 1]);
        } catch (QueryException) {
            // Another request created today's row first.
            self::query()->where($where)->increment('hits');
        }
    }
}
