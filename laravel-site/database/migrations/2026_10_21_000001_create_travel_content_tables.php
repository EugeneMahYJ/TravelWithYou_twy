<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('region');
            $table->string('image');
            $table->text('blurb');
            $table->timestamps();
        });

        Schema::create('ships', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('image');
            $table->json('gallery');
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('crew');
            $table->string('class_name');
            $table->decimal('rating', 2, 1);
            $table->unsignedSmallInteger('year');
            $table->text('overview');
            $table->json('amenities');
            $table->json('decks');
            $table->timestamps();
        });

        Schema::create('ship_cabins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ship_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('size');
            $table->unsignedInteger('price_from');
            $table->text('note');
            $table->timestamps();
            $table->unique(['ship_id', 'type']);
        });

        Schema::table('travel_packages', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->text('long_description')->nullable();
            $table->json('highlights')->nullable();
            $table->json('fares')->nullable();
            $table->json('gallery')->nullable();
            $table->json('trip_plan')->nullable();
            $table->json('included')->nullable();
            $table->json('excluded')->nullable();
        });

        Schema::table('travel_deals', function (Blueprint $table) {
            $table->string('type')->nullable();
            $table->text('terms')->nullable();
            $table->dateTime('expires_at')->nullable();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->text('passport_number')->nullable();
        });

        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->decimal('discount_percent', 5, 2);
            $table->boolean('active')->default(true);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('guides', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('category');
            $table->string('read_time')->nullable();
            $table->string('image')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->boolean('published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('query')->nullable();
            $table->json('filters')->nullable();
            $table->unsignedInteger('results_count')->default(0);
            $table->timestamps();
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->dateTime('subscribed_at')->nullable();
            $table->dateTime('unsubscribed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('search_logs');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('guides');
        Schema::dropIfExists('promo_codes');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('passport_number');
        });
        Schema::table('travel_deals', function (Blueprint $table) {
            $table->dropColumn(['type', 'terms', 'expires_at']);
        });
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_id');
            $table->dropColumn(['long_description', 'highlights', 'fares', 'gallery', 'trip_plan', 'included', 'excluded']);
        });
        Schema::dropIfExists('ship_cabins');
        Schema::dropIfExists('ships');
        Schema::dropIfExists('countries');
    }
};
