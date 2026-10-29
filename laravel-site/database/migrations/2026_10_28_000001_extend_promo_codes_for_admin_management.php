<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->string('discount_type')->default('percent');
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->boolean('all_packages')->default(true);
            $table->text('terms')->nullable();
        });

        Schema::create('promo_code_travel_package', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('travel_package_id')->constrained()->cascadeOnDelete();
            $table->primary(['promo_code_id', 'travel_package_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_code_travel_package');

        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_amount', 'all_packages', 'terms']);
        });
    }
};
