<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Helpers for ending a user's other sign-ins after a credential change.
 */
final class AccountSessions
{
    /**
     * End every session of the user except the current one (pass null to end all),
     * and invalidate "remember me" tokens.
     *
     * Session rows can only be listed when the database session driver is used;
     * with other drivers only the remember-me token is rotated.
     */
    public static function endOtherSessions(User $user, ?string $currentSessionId = null): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        if (config('session.driver') !== 'database') {
            return;
        }

        $table = config('session.table', 'sessions');
        if (! Schema::hasTable($table)) {
            return;
        }

        $query = DB::table($table)->where('user_id', $user->id);
        if ($currentSessionId !== null) {
            $query->where('id', '!=', $currentSessionId);
        }
        $query->delete();
    }

    /** Forget every "trust this device" cookie issued to an administrator. */
    public static function revokeTrustedAdminDevices(User $user): void
    {
        if (Schema::hasTable('trusted_admin_devices')) {
            DB::table('trusted_admin_devices')->where('user_id', $user->id)->delete();
        }
    }
}
