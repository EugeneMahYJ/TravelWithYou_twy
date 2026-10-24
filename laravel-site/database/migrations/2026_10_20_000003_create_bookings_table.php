<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('package_id')->constrained('travel_packages')->restrictOnDelete();
            $table->date('departure_date');
            $table->unsignedSmallInteger('guests')->default(1);
            $table->json('guest_details');
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('taxes');
            $table->unsignedInteger('total');
            $table->string('status')->default('Confirmed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
