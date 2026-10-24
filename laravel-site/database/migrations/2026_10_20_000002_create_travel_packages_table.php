<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_packages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('country');
            $table->string('region');
            $table->string('type');
            $table->unsignedSmallInteger('days');
            $table->text('description');
            $table->text('details');
            $table->unsignedInteger('price_from');
            $table->unsignedInteger('price_to');
            $table->decimal('rating', 2, 1)->default(0);
            $table->unsignedInteger('reviews')->default(0);
            $table->string('departure_port')->nullable();
            $table->string('destination_port')->nullable();
            $table->json('departures');
            $table->string('image');
            $table->boolean('published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_packages');
    }
};
