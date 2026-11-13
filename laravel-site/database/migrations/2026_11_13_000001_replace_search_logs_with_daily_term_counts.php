<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_term_counts', function (Blueprint $table) {
            $table->id();
            $table->date('searched_on');
            $table->string('term', 120);
            $table->string('label', 120);
            $table->unsignedInteger('hits')->default(0);
            $table->unique(['searched_on', 'term']);
        });

        if (! Schema::hasTable('search_logs')) {
            return;
        }

        // Keep the trending-search history, but only as daily counts: no user ids, no filters.
        $counts = [];
        DB::table('search_logs')
            ->whereNotNull('query')
            ->where('query', '!=', '')
            ->orderBy('id')
            ->each(function ($row) use (&$counts): void {
                $term = mb_strtolower(mb_substr(trim((string) preg_replace('/\s+/', ' ', $row->query)), 0, 120));
                if (mb_strlen($term) < 2) {
                    return;
                }
                $day = Carbon::parse($row->created_at)->toDateString();
                $counts[$day][$term]['hits'] = ($counts[$day][$term]['hits'] ?? 0) + 1;
                $counts[$day][$term]['label'] ??= mb_substr(trim((string) preg_replace('/\s+/', ' ', $row->query)), 0, 120);
            });

        foreach ($counts as $day => $terms) {
            foreach ($terms as $term => $entry) {
                DB::table('search_term_counts')->insert([
                    'searched_on' => $day,
                    'term' => (string) $term,
                    'label' => $entry['label'],
                    'hits' => $entry['hits'],
                ]);
            }
        }

        Schema::drop('search_logs');
    }

    public function down(): void
    {
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('query')->nullable();
            $table->json('filters')->nullable();
            $table->unsignedInteger('results_count')->default(0);
            $table->timestamps();
        });

        Schema::dropIfExists('search_term_counts');
    }
};
