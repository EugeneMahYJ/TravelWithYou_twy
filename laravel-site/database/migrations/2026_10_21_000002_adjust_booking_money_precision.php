<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 2)->change();
            $table->decimal('discount', 12, 2)->default(0)->change();
            $table->decimal('taxes', 12, 2)->change();
            $table->decimal('total', 12, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('subtotal')->change();
            $table->unsignedInteger('discount')->default(0)->change();
            $table->unsignedInteger('taxes')->change();
            $table->unsignedInteger('total')->change();
        });
    }
};
