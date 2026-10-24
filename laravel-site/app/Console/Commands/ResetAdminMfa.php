<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ResetAdminMfa extends Command
{
    protected $signature = 'admin:mfa:reset {email : The email address of the administrator} {--force : Skip the confirmation prompt}';

    protected $description = 'Reset an administrator authenticator and revoke their database sessions';

    public function handle(): int
    {
        if (config('session.driver') !== 'database') {
            $this->error('MFA recovery requires the database session driver so active sessions can be revoked safely.');

            return self::FAILURE;
        }

        $sessionTable = config('session.table');
        if (! is_string($sessionTable) || ! Schema::hasTable($sessionTable)) {
            $this->error('The configured database session table is unavailable; no MFA changes were made.');

            return self::FAILURE;
        }

        $email = Str::lower((string) $this->argument('email'));
        $admin = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('is_admin', true)
            ->first();

        if (! $admin) {
            $this->error('No administrator account was found for that email address.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Reset this administrator authenticator and sign out all active sessions?')) {
            $this->info('No changes were made.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($admin, $sessionTable): void {
            $admin->forceFill([
                'mfa_secret' => null,
                'mfa_enabled' => false,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table($sessionTable)->where('user_id', $admin->id)->delete();
            if (Schema::hasTable('trusted_admin_devices')) {
                DB::table('trusted_admin_devices')->where('user_id', $admin->id)->delete();
            }
        });

        $this->info('Authenticator reset and active sessions revoked. Sign in again to enroll a new authenticator.');

        return self::SUCCESS;
    }
}
