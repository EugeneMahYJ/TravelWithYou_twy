<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_deals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('promo_code')->unique();
            $table->text('description');
            $table->unsignedTinyInteger('discount_percent');
            $table->date('ends_at')->nullable();
            $table->foreignId('package_id')->nullable()->constrained('travel_packages')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_deals');
    }
};
