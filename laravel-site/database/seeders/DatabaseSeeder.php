<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ShipSeeder::class,
            ShipMediaSeeder::class,
            TravelPackageSeeder::class,
            TravelDealSeeder::class,
            PromoCodeSeeder::class,
            GuideSeeder::class,
            FaqSeeder::class,
            TourPackageSeeder::class,
            PackagePhotoSeeder::class,
            PackageContentSeeder::class,
        ]);

        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        if ($email && $password) {
            $admin = User::updateOrCreate(
                ['email' => $email],
                ['name' => env('ADMIN_NAME', 'Travel With You Admin'), 'password' => Hash::make($password)]
            );
            $admin->forceFill([
                'is_admin' => true,
                'mfa_secret' => $admin->mfa_secret ?: (env('ADMIN_MFA_SECRET') ?: Totp::generateSecret()),
            ])->save();
        }
    }
}
