<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 20)->nullable()->unique();
            $table->text('traveller_details')->nullable();
            $table->json('notification_preferences')->nullable();
            $table->boolean('account_mfa_enabled')->default(false);
        });

        DB::table('users')->select('id', 'name')->orderBy('id')->get()->each(function (object $user): void {
            $base = Str::lower(Str::slug($user->name, '.'));
            $base = trim(Str::substr($base, 0, 20), '._-');
            if (strlen($base) < 3) {
                $base = 'traveller';
            }

            $username = $base;
            $suffix = 1;
            while (DB::table('users')->whereRaw('LOWER(username) = ?', [$username])->where('id', '!=', $user->id)->exists()) {
                $ending = (string) $suffix++;
                $username = Str::substr($base, 0, 20 - strlen($ending)).$ending;
            }

            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'traveller_details', 'notification_preferences', 'account_mfa_enabled']);
        });
    }
};
