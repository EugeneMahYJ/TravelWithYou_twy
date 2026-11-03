<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->unsignedInteger('company_travellers')->nullable();
            $table->text('company_address')->nullable();
            $table->string('honorific', 10)->nullable();
            $table->string('destination')->nullable();
            $table->string('budget_basis', 20)->nullable();
            $table->decimal('budget_from', 12, 2)->nullable();
            $table->decimal('budget_to', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'company_name',
                'company_travellers',
                'company_address',
                'honorific',
                'destination',
                'budget_basis',
                'budget_from',
                'budget_to',
            ]);
        });
    }
};
