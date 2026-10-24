<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Enquiry;
use App\Models\TravelDeal;
use App\Models\TravelPackage;
use App\Models\User;
use App\Support\Totp;
use Carbon\Carbon;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FaqSeeder;
use Database\Seeders\GuideSeeder;
use Database\Seeders\PromoCodeSeeder;
use Database\Seeders\ShipSeeder;
use Database\Seeders\TravelDealSeeder;
use Database\Seeders\TravelPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class AdminNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2027-01-20 12:00:00');
        $this->seed([
            CountrySeeder::class,
            ShipSeeder::class,
            TravelPackageSeeder::class,
            TravelDealSeeder::class,
            PromoCodeSeeder::class,
            GuideSeeder::class,
            FaqSeeder::class,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_notification_items_include_counts_and_filtered_urls_for_each_condition(): void
    {
        TravelPackage::query()->update(['published' => false]);
        TravelDeal::query()->update(['active' => false]);
        $basePackage = TravelPackage::firstOrFail();
        $this->packageWithDepartures('expired-one', [today()->subDay()->toDateString()]);
        $this->packageWithDepartures('expired-two', [today()->subDays(3)->toDateString()]);
        $this->packageWithDepartures('no-future', []);
        $this->packageWithDepartures('departing-soon', [today()->addDays(2)->toDateString()]);

        $this->deal('ended-one', now()->subHour());
        $this->deal('ended-two', now()->subDay());
        $this->deal('ending-soon', now()->addHours(24));
        foreach (['New', 'In progress', 'New'] as $index => $status) {
            Enquiry::create([
                'name' => 'Traveller '.$index,
                'email' => 'traveller'.$index.'@example.test',
                'subject' => 'Trip question',
                'message' => 'Please advise.',
                'status' => $status,
            ]);
        }
        $this->booking($basePackage, 'TWY-NOTICE-1', 'Pending payment');
        $this->booking($basePackage, 'TWY-NOTICE-2', 'Pending payment');
        $this->booking($basePackage, 'TWY-NOTICE-3', 'Confirmed');

        $notifications = app(\App\Support\AdminNotifications::class);
        $items = collect($notifications->items())->keyBy('key');

        $this->assertSame(2, $items['packages.expired']['count']);
        $this->assertSame(route('admin.packages', ['status' => 'Expiring']), $items['packages.expired']['url']);
        $this->assertSame(2, $items['deals.expired_active']['count']);
        $this->assertSame(route('admin.deals'), $items['deals.expired_active']['url']);
        $this->assertSame(3, $items['packages.no_departures']['count']);
        $this->assertSame(3, $items['enquiries.unanswered']['count']);
        $this->assertSame(route('admin.enquiries', ['status' => 'Unanswered']), $items['enquiries.unanswered']['url']);
        $this->assertSame(2, $items['bookings.pending_payment']['count']);
        $this->assertSame(route('admin.bookings', ['status' => 'Pending payment']), $items['bookings.pending_payment']['url']);
        $this->assertSame(1, $items['packages.expiring_soon']['count']);
        $this->assertSame(1, $items['deals.ending_soon']['count']);
        $this->assertSame(3, $items['bookings.new_24h']['count']);
        $this->assertSame(route('admin.bookings'), $items['bookings.new_24h']['url']);
        $this->assertSame(14, $notifications->attentionCount());

        $admin = $this->admin();
        $this->actingAs($admin)->withSession($this->verifiedSession())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('2 packages have expired')
            ->assertSee('2 deals ended but are still active')
            ->assertSee('3 enquiries waiting for a reply')
            ->assertSee(route('admin.enquiries', ['status' => 'Unanswered']), false)
            ->assertSee('Notifications')
            ->assertSee('Mark all as read')
            ->assertSee('Mark as read')
            ->assertDontSee('View site');
    }

    public function test_admin_dashboard_shows_empty_notification_state_and_non_admin_cannot_see_panel(): void
    {
        TravelPackage::query()->update(['published' => false]);
        TravelDeal::query()->update(['active' => false]);
        $admin = $this->admin();

        $this->actingAs($admin)->withSession($this->verifiedSession())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee("You're all caught up.", false);

        $traveller = User::create([
            'name' => 'Traveller',
            'email' => 'traveller@example.test',
            'password' => 'SafePass1234',
        ]);
        $this->actingAs($traveller)->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertDontSee('admin-notifications-panel');
    }

    public function test_admin_errors_are_scoped_to_admin_paths_and_idle_message_is_whitelisted(): void
    {
        $this->actingAs($this->admin())->withSession($this->verifiedSession())
            ->get('/admin/does-not-exist')
            ->assertNotFound()
            ->assertSee('Back to dashboard')
            ->assertSee(route('admin.dashboard'), false)
            ->assertDontSee('href="'.route('home').'"', false)
            ->assertDontSee('Browse destinations');

        $this->get('/does-not-exist')->assertNotFound()->assertSee('Back home');
        $this->post(route('admin.logout'));
        $this->get(route('admin.login', ['reason' => 'idle']))
            ->assertOk()
            ->assertSee('You were signed out after 15 minutes of inactivity.')
            ->assertSee('role="status"', false);
        $this->get(route('admin.login', ['reason' => '<script>alert(1)</script>']))
            ->assertOk()
            ->assertDontSee('alert(1)')
            ->assertDontSee('You were signed out after 15 minutes of inactivity.');
    }

    public function test_admin_token_mismatch_redirects_to_login_and_logout_clears_authentication(): void
    {
        $this->app->bind(ValidateCsrfToken::class, function ($app) {
            return new class($app, $app->make('encrypter')) extends ValidateCsrfToken
            {
                protected function runningUnitTests()
                {
                    return false;
                }
            };
        });
        $admin = $this->admin();
        $this->actingAs($admin)->withSession($this->verifiedSession())
            ->post(route('admin.logout'), ['_token' => 'invalid-token'])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', 'Your session expired. Please sign in again.');
        $this->assertGuest();

        $this->actingAs($admin)->withSession($this->verifiedSession())
            ->post(route('admin.activity'), ['_token' => 'invalid-token'])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', 'Your session expired. Please sign in again.');
        $this->assertGuest();
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Your session expired. Please sign in again.')
            ->assertSee('role="status"', false);
    }

    private function packageWithDepartures(string $slug, array $departures): TravelPackage
    {
        $package = TravelPackage::firstOrFail()->replicate();
        $package->forceFill([
            'slug' => $slug,
            'source_id' => null,
            'name' => $slug,
            'departures' => $departures,
            'published' => true,
        ])->save();

        return $package;
    }

    private function deal(string $slug, Carbon $endsAt): TravelDeal
    {
        return TravelDeal::create([
            'name' => $slug,
            'slug' => $slug,
            'promo_code' => strtoupper($slug),
            'description' => 'Notification test deal.',
            'discount_percent' => 10,
            'expires_at' => $endsAt,
            'active' => true,
        ]);
    }

    private function booking(TravelPackage $package, string $reference, string $status): Booking
    {
        return Booking::create([
            'reference' => $reference,
            'package_id' => $package->id,
            'departure_date' => today()->addMonth()->toDateString(),
            'guests' => 1,
            'guest_details' => ['name' => 'Test Traveller'],
            'subtotal' => 1000,
            'discount' => 0,
            'taxes' => 70,
            'total' => 1070,
            'status' => $status,
        ]);
    }

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'Site Admin',
            'email' => 'admin@example.test',
            'password' => 'SafePass1234',
        ]);
        $admin->forceFill([
            'is_admin' => true,
            'mfa_enabled' => true,
            'mfa_secret' => Totp::generateSecret(),
        ])->save();

        return $admin;
    }

    private function verifiedSession(): array
    {
        return ['admin_mfa_verified' => true, 'admin_last_activity' => time()];
    }
}
