<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->string('source_id')->nullable()->unique();
            $table->string('brand')->nullable();
            $table->string('operator')->nullable();
            $table->unsignedSmallInteger('nights')->nullable();
            $table->json('destinations')->nullable();
            $table->json('itinerary')->nullable();
            $table->json('source_data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->dropUnique(['source_id']);
            $table->dropColumn([
                'source_id',
                'brand',
                'operator',
                'nights',
                'destinations',
                'itinerary',
                'source_data',
            ]);
        });
    }
};
