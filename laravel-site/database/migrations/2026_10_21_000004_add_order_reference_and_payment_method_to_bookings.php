<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('order_reference', 9)->nullable()->after('reference');
            $table->string('payment_method', 24)->nullable()->after('status');
            $table->index('order_reference');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['order_reference']);
            $table->dropColumn(['order_reference', 'payment_method']);
        });
    }
};
