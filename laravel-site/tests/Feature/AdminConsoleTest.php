<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Country;
use App\Models\Enquiry;
use App\Models\TravelDeal;
use App\Models\TravelPackage;
use App\Models\User;
use App\Support\AdminNotifications;
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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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

    public function test_non_admin_cannot_access_console_routes(): void
    {
        $user = User::create(['name' => 'Traveller', 'email' => 'traveller@example.test', 'password' => 'SafePass1234']);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admin_can_sign_in_with_username_and_sees_standalone_login(): void
    {
        $admin = $this->admin();
        $this->get(route('admin.login'))->assertOk()->assertSee('Username')->assertSee('Back to website');

        $this->post(route('admin.login.submit'), [
            'username' => $admin->username,
            'password' => 'SafePass1234',
        ])->assertRedirect(route('admin.mfa'));
    }

    public function test_admin_can_remember_a_device_after_mfa_verification(): void
    {
        $admin = $this->admin();

        $this->post(route('admin.login.submit'), [
            'username' => $admin->username,
            'password' => 'SafePass1234',
        ])->assertRedirect(route('admin.mfa'));

        $this->post(route('admin.mfa.verify'), [
            'code' => Totp::codeForTime($admin->mfa_secret, time()),
            'remember_device' => '1',
        ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertCookie('admin_trusted_device');

        $device = DB::table('trusted_admin_devices')->where('user_id', $admin->id)->first();
        $this->assertNotNull($device);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, Carbon::parse($device->expires_at)->timestamp, 2);
    }

    public function test_valid_trusted_admin_device_skips_the_mfa_challenge(): void
    {
        $admin = $this->admin();
        $token = 'trusted-device-token';
        DB::table('trusted_admin_devices')->insert([
            'user_id' => $admin->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withCookie('admin_trusted_device', $token)
            ->post(route('admin.login.submit'), [
                'username' => $admin->username,
                'password' => 'SafePass1234',
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('admin_mfa_verified', true);
    }

    public function test_expired_trusted_admin_device_still_requires_mfa(): void
    {
        $admin = $this->admin();
        $token = 'expired-trusted-device-token';
        DB::table('trusted_admin_devices')->insert([
            'user_id' => $admin->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->subSecond(),
            'created_at' => now()->subDays(31),
            'updated_at' => now()->subDays(31),
        ]);

        $this->withCookie('admin_trusted_device', $token)
            ->post(route('admin.login.submit'), [
                'username' => $admin->username,
                'password' => 'SafePass1234',
            ])
            ->assertRedirect(route('admin.mfa'))
            ->assertSessionHas('admin_mfa_verified', false);
    }

    public function test_admin_mfa_recovery_resets_secret_and_revokes_database_sessions(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->admin();
        DB::table('sessions')->insert([
            'id' => 'admin-session-test',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => time(),
        ]);
        DB::table('trusted_admin_devices')->insert([
            'user_id' => $admin->id,
            'token_hash' => hash('sha256', 'trusted-device-token'),
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('admin:mfa:reset', [
            'email' => $admin->email,
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertFalse($admin->fresh()->mfa_enabled);
        $this->assertNull($admin->fresh()->mfa_secret);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $admin->id)->count());
        $this->assertSame(0, DB::table('trusted_admin_devices')->where('user_id', $admin->id)->count());
    }

    public function test_package_filters_and_builder_validation_use_real_package_data(): void
    {
        $admin = $this->admin();
        $draft = TravelPackage::firstOrFail();
        $draft->update(['published' => false]);
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $this->get(route('admin.packages', ['status' => 'Draft', 'q' => $draft->name]))
            ->assertOk()->assertSee($draft->name)->assertSee('Draft');
        $this->get(route('admin.packages', ['status' => 'Draft', 'q' => 'not-a-package']))
            ->assertOk()->assertSee('No packages match these filters.');

        $country = Country::firstOrFail();
        $this->post(route('admin.packages.store'), [
            'builder_form' => '1',
            'name' => 'Incomplete Journey',
            'country' => $country->slug,
            'type' => 'Flight + hotel',
            'days' => '1',
            'description' => 'A short summary.',
            'long_description' => 'A longer itinerary description.',
            'price_from' => '1000',
            'price_to' => '1500',
            'image' => '1624253321171-1be53e12f5f4',
            'departure_port' => 'Kuala Lumpur',
            'destination_port' => 'Tokyo',
            'departures' => [Carbon::now()->addMonth()->toDateString()],
            'highlights' => ['A memorable day'],
            'trip_plan' => [['place' => 'Tokyo', 'activities' => '', 'transport' => 'Train']],
            'status' => 'Published',
        ])->assertSessionHasErrors('trip_plan');

        $this->assertContains(
            'Every day needs a place and activities.',
            session('errors')->get('trip_plan'),
        );
    }

    public function test_package_builder_saves_matching_trip_plan_days(): void
    {
        $admin = $this->admin();
        $country = Country::firstOrFail();
        $this->actingAs($admin)->withSession($this->verifiedSession())->post(route('admin.packages.store'), [
            'builder_form' => '1',
            'name' => 'Builder Test Journey',
            'country' => $country->slug,
            'type' => 'Flight + hotel',
            'days' => '1',
            'description' => 'A short summary.',
            'long_description' => 'A longer description.',
            'price_from' => '1000',
            'price_to' => '1500',
            'image' => '1624253321171-1be53e12f5f4',
            'departure_port' => 'Kuala Lumpur',
            'destination_port' => 'Tokyo',
            'departures' => [Carbon::now()->addMonth()->toDateString()],
            'highlights' => ['A memorable day'],
            'trip_plan' => [['place' => 'Tokyo', 'activities' => 'Visit the old town', 'transport' => 'Train']],
            'status' => 'Published',
        ])->assertRedirect(route('admin.packages'));

        $package = TravelPackage::where('slug', 'builder-test-journey')->firstOrFail();
        $this->assertTrue($package->published);
        $this->assertSame(['Tokyo', 'Visit the old town', 'Train'], $package->trip_plan[0]);
    }

    public function test_flight_package_builder_saves_and_displays_passenger_price_bands(): void
    {
        $admin = $this->admin();
        $country = Country::firstOrFail();
        $date = Carbon::now()->addMonth()->toDateString();
        $payload = [
            'builder_form' => '1',
            'name' => 'Tiered Fare Journey',
            'country' => $country->slug,
            'type' => 'Flight + hotel',
            'days' => '1',
            'description' => 'A tiered fare test.',
            'long_description' => 'A test of passenger price bands.',
            'price_from' => '1000',
            'price_to' => '5000',
            'image' => '1624253321171-1be53e12f5f4',
            'departure_port' => 'Kuala Lumpur',
            'destination_port' => 'Tokyo',
            'departures' => [$date],
            'guest_types' => [['id' => 'adult', 'label' => 'Adult', 'age' => '', 'role' => 'adult']],
            'departure_prices' => [['adult' => '1000']],
            'departure_pax_tiers' => [[
                ['from' => '1', 'to' => '2', 'prices' => ['adult' => '4000']],
                ['from' => '3', 'to' => '5', 'prices' => ['adult' => '5000']],
            ]],
            'highlights' => ['A memorable day'],
            'trip_plan' => [['place' => 'Tokyo', 'activities' => 'Explore the city.', 'transport' => 'Train']],
            'status' => 'Published',
        ];

        $this->actingAs($admin)->withSession($this->verifiedSession())
            ->from(route('admin.packages.create'))
            ->post(route('admin.packages.store'), array_replace($payload, [
                'departure_pax_tiers' => [[
                    ['from' => '1', 'to' => '3', 'prices' => ['adult' => '4000']],
                    ['from' => '3', 'to' => '5', 'prices' => ['adult' => '5000']],
                ]],
            ]))
            ->assertRedirect(route('admin.packages.create'))
            ->assertSessionHasErrors(['departure_pax_tiers.0']);

        $this->post(route('admin.packages.store'), $payload)
            ->assertRedirect(route('admin.packages'))
            ->assertSessionHas('success', 'Package saved.');

        $package = TravelPackage::where('slug', 'tiered-fare-journey')->firstOrFail();
        $this->assertSame([
            ['from' => 1, 'to' => 2, 'prices' => ['adult' => 4000]],
            ['from' => 3, 'to' => 5, 'prices' => ['adult' => 5000]],
        ], $package->pricing['departure_pax_tiers'][$date]);
        $this->get(route('admin.packages.edit', $package))
            ->assertOk()
            ->assertSee('Passenger price bands')
            ->assertSee('departure_pax_tiers[0][0][from]', false)
            ->assertSee('departure_pax_tiers[0][1][prices][adult]', false);
        $this->get(route('packages.show', $package))
            ->assertOk()
            ->assertSee('Price table')
            ->assertSee('Pax 1–2')
            ->assertSee('Pax 3–5')
            ->assertSee('RM 4,000')
            ->assertSee('RM 5,000');
    }

    public function test_admin_can_edit_included_and_excluded_package_items(): void
    {
        $admin = $this->admin();
        $package = TravelPackage::firstOrFail();
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $this->get(route('admin.packages.edit', $package))
            ->assertOk()
            ->assertSee('Included')
            ->assertSee('Not included')
            ->assertSee('name="included[]"', false)
            ->assertSee('name="excluded[]"', false);

        $tripPlan = collect($package->dailyPlan())->map(fn (array $day): array => [
            'place' => $day['place'],
            'activities' => $day['activities'] ?: 'Explore the destination.',
            'transport' => $day['transport'],
        ])->all();
        $payload = [
            'builder_form' => '1',
            'name' => $package->name,
            'slug' => $package->slug,
            'country' => $package->countryRecord->slug,
            'type' => $package->type,
            'ship' => $package->ship,
            'days' => $package->days,
            'nights' => $package->nights,
            'description' => $package->description,
            'long_description' => $package->long_description,
            'price_from' => $package->price_from,
            'price_to' => $package->price_to,
            'image' => $package->image,
            'departure_port' => $package->departure_port,
            'destination_port' => $package->destination_port,
            'departures' => $package->departures,
            'highlights' => $package->highlights,
            'trip_plan' => $tripPlan,
            'status' => 'Published',
            'included_present' => '1',
            'excluded_present' => '1',
        ];

        $this->put(route('admin.packages.update', $package), array_replace($payload, [
            'included' => ['Custom airport transfer', 'A guided museum visit', ''],
            'excluded' => ['Visa fees', 'Personal purchases', ''],
        ]))->assertRedirect(route('admin.packages'))->assertSessionHas('success', 'Package saved.');

        $package->refresh();
        $this->assertSame(['Custom airport transfer', 'A guided museum visit'], $package->included());
        $this->assertSame(['Visa fees', 'Personal purchases'], $package->excluded());
        $this->get(route('packages.show', $package))
            ->assertOk()
            ->assertSee('Custom airport transfer')
            ->assertSee('A guided museum visit')
            ->assertSee('Visa fees')
            ->assertSee('Personal purchases');

        $this->put(route('admin.packages.update', $package), $payload)
            ->assertRedirect(route('admin.packages'));
        $this->assertSame([], $package->fresh()->included());
        $this->assertSame([], $package->fresh()->excluded());
        $defaultInclusion = $package->type === 'Cruise'
            ? 'Cabin accommodation in your chosen category'
            : 'Accommodation as listed in the itinerary';
        $this->get(route('packages.show', $package))->assertDontSee($defaultInclusion);
    }

    public function test_admin_can_pause_deals_and_mark_enquiries_replied(): void
    {
        $admin = $this->admin();
        $deal = TravelDeal::firstOrFail();
        $enquiry = Enquiry::create([
            'name' => 'Casey Traveller',
            'email' => 'casey@example.test',
            'subject' => 'Booking enquiry',
            'message' => 'Could you help me with this trip?',
            'status' => 'New',
        ]);
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $this->patch(route('admin.deals.toggle', $deal))->assertRedirect();
        $this->assertDatabaseHas('travel_deals', ['id' => $deal->id, 'active' => false]);
        $this->patch(route('admin.status', ['resource' => 'enquiries', 'id' => $enquiry->id]), ['status' => 'Replied'])
            ->assertRedirect();
        $this->assertDatabaseHas('enquiries', ['id' => $enquiry->id, 'status' => 'Replied']);
    }

    public function test_booking_detail_masks_decrypted_passports_and_status_can_change(): void
    {
        $admin = $this->admin();
        $package = TravelPackage::firstOrFail();
        $booking = Booking::create([
            'reference' => 'TWY-ADMIN-01',
            'order_reference' => 'TWY-12345',
            'package_id' => $package->id,
            'departure_date' => Carbon::now()->addMonths(2)->toDateString(),
            'guests' => 1,
            'guest_details' => [
                'name' => 'Casey Traveller',
                'email' => 'casey@example.test',
                'travellers' => [['name' => 'Casey Traveller', 'dob' => '1990-01-01', 'nationality' => 'Malaysian']],
            ],
            'traveller_passports' => ['ZXCVBN123'],
            'passport_number' => 'AB1234567',
            'subtotal' => 1000,
            'discount' => 0,
            'taxes' => 70,
            'total' => 1070,
            'status' => 'Pending payment',
            'payment_method' => 'card',
        ]);
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $this->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('******567')
            ->assertSee('******123')
            ->assertDontSee('AB1234567')
            ->assertDontSee('ZXCVBN123');
        $this->assertStringNotContainsString('AB1234567', DB::table('bookings')->where('id', $booking->id)->value('passport_number'));

        $this->patch(route('admin.status', ['resource' => 'bookings', 'id' => $booking->id]), ['status' => 'Confirmed'])
            ->assertRedirect();
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'Confirmed']);
    }

    public function test_notification_read_state_is_per_admin_and_unread_items_drive_the_badge(): void
    {
        $this->quietNotifications();
        $enquiry = $this->notificationEnquiry('first@example.test');
        $pendingBooking = $this->notificationBooking('TWY-READ-1', 'Pending payment');
        $infoBooking = $this->notificationBooking('TWY-READ-2', 'Confirmed');
        $admin = $this->admin();
        $this->actingAs($admin)->withSession($this->verifiedSession());
        $service = app(AdminNotifications::class);
        $items = collect($service->itemsForAdmin($admin->id))->keyBy('key');
        $this->assertFalse($items['enquiries.unanswered']['is_read']);
        $this->assertFalse($items['bookings.pending_payment']['is_read']);
        $this->assertFalse($items['bookings.new_24h']['is_read']);

        $this->postJson(route('admin.notifications.read', ['key' => 'enquiries.unanswered']), [
            'fingerprint' => $items['enquiries.unanswered']['fingerprint'],
        ])->assertOk()->assertJsonPath('unread_badge', 1);
        $this->assertDatabaseHas('admin_notification_reads', [
            'user_id' => $admin->id,
            'key' => 'enquiries.unanswered',
            'fingerprint' => $items['enquiries.unanswered']['fingerprint'],
        ]);
        $this->assertTrue(collect((new AdminNotifications)->itemsForAdmin($admin->id))
            ->firstWhere('key', 'enquiries.unanswered')['is_read']);

        $this->postJson(route('admin.notifications.read', ['key' => 'bookings.new_24h']), [
            'fingerprint' => $items['bookings.new_24h']['fingerprint'],
        ])->assertOk()->assertJsonPath('unread_badge', 1);
        $this->assertSame('Pending payment', $pendingBooking->fresh()->status);
        $this->assertSame('Confirmed', $infoBooking->fresh()->status);

        $secondAdmin = $this->admin('second-admin@example.test');
        $secondItems = collect((new AdminNotifications)->itemsForAdmin($secondAdmin->id))->keyBy('key');
        $this->assertFalse($secondItems['enquiries.unanswered']['is_read']);
        $this->assertSame(2, (new AdminNotifications)->unreadBadgeCount($secondAdmin->id));
    }

    public function test_marking_all_does_not_hide_dashboard_attention_and_empty_state_is_per_admin(): void
    {
        $this->quietNotifications();
        $this->notificationEnquiry();
        $admin = $this->admin();
        $this->actingAs($admin)->withSession($this->verifiedSession());
        $items = (new AdminNotifications)->items();
        $payload = collect($items)->map(fn (array $item): array => [
            'key' => $item['key'],
            'fingerprint' => $item['fingerprint'],
        ])->values()->all();

        $this->postJson(route('admin.notifications.read-all'), ['items' => $payload])
            ->assertOk()
            ->assertJsonPath('unread_badge', 0)
            ->assertJsonPath('items.0.is_read', true);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('1 enquiries waiting for a reply');

        $this->get(route('admin.deals'))->assertOk();
        $this->get(route('home'))->assertOk()->assertDontSee('admin-notifications-panel');
        $this->assertDatabaseCount('admin_notification_reads', 1);
    }

    public function test_notification_fingerprints_reopen_on_change_and_stale_marks_stay_unread(): void
    {
        $this->quietNotifications();
        $enquiry = $this->notificationEnquiry();
        $admin = $this->admin();
        $this->actingAs($admin)->withSession($this->verifiedSession());
        $first = collect((new AdminNotifications)->items())->firstWhere('key', 'enquiries.unanswered');

        $this->postJson(route('admin.notifications.read', ['key' => 'enquiries.unanswered']), [
            'fingerprint' => $first['fingerprint'],
        ])->assertOk();
        $this->assertTrue(collect((new AdminNotifications)->itemsForAdmin($admin->id))
            ->firstWhere('key', 'enquiries.unanswered')['is_read']);

        $this->notificationEnquiry('second@example.test');
        $changed = collect((new AdminNotifications)->itemsForAdmin($admin->id))
            ->firstWhere('key', 'enquiries.unanswered');
        $this->assertFalse($changed['is_read']);
        $this->assertNotSame($first['fingerprint'], $changed['fingerprint']);

        $this->postJson(route('admin.notifications.read', ['key' => 'enquiries.unanswered']), [
            'fingerprint' => $first['fingerprint'],
        ])->assertOk()->assertJsonPath('unread_badge', 2)
            ->assertJsonPath('items.0.is_read', false);

        Enquiry::query()->update(['status' => 'Replied']);
        $this->assertNull(collect((new AdminNotifications)->items())->firstWhere('key', 'enquiries.unanswered'));
        $this->notificationEnquiry('third@example.test');
        $recreated = collect((new AdminNotifications)->itemsForAdmin($admin->id))
            ->firstWhere('key', 'enquiries.unanswered');
        $this->assertFalse($recreated['is_read']);
        $this->assertSame(1, $recreated['count']);
        $this->assertDatabaseHas('enquiries', ['id' => $enquiry->id, 'status' => 'Replied']);
    }

    public function test_notification_read_routes_validate_keys_fingerprints_and_require_admin(): void
    {
        $this->quietNotifications();
        $admin = $this->admin();
        $this->actingAs($admin)->withSession($this->verifiedSession());
        $url = route('admin.notifications.read', ['key' => 'enquiries.unanswered']);

        $this->postJson($url, ['fingerprint' => 'not-a-fingerprint'])->assertUnprocessable();
        $this->postJson(route('admin.notifications.read', ['key' => 'unknown.key']), ['fingerprint' => str_repeat('a', 40)])
            ->assertNotFound();
        $this->postJson(route('admin.notifications.read-all'), ['items' => [[
            'key' => 'unknown.key',
            'fingerprint' => str_repeat('a', 40),
        ]]])->assertNotFound();

        auth()->logout();
        $this->postJson($url, ['fingerprint' => str_repeat('a', 40)])->assertUnauthorized();
        $traveller = User::create(['name' => 'Traveller', 'email' => 'notice-traveller@example.test', 'password' => 'SafePass1234']);
        $this->actingAs($traveller)->withSession($this->verifiedSession())
            ->postJson($url, ['fingerprint' => str_repeat('a', 40)])->assertForbidden();
    }

    private function quietNotifications(): void
    {
        TravelPackage::query()->update(['published' => false]);
        TravelDeal::query()->update(['active' => false]);
    }

    private function notificationEnquiry(string $email = 'notice@example.test'): Enquiry
    {
        return Enquiry::create([
            'name' => 'Notification traveller',
            'email' => $email,
            'subject' => 'Notification question',
            'message' => 'Please send the travel details.',
            'status' => 'New',
        ]);
    }

    private function notificationBooking(string $reference, string $status): Booking
    {
        return Booking::create([
            'reference' => $reference,
            'package_id' => TravelPackage::firstOrFail()->id,
            'departure_date' => Carbon::now()->addMonths(2)->toDateString(),
            'guests' => 1,
            'guest_details' => ['name' => 'Notification traveller', 'email' => 'notice@example.test'],
            'subtotal' => 1000,
            'taxes' => 70,
            'total' => 1070,
            'status' => $status,
        ]);
    }

    private function admin(string $email = 'admin@example.test'): User
    {
        $admin = User::create(['name' => 'Site Admin', 'email' => $email, 'password' => 'SafePass1234']);
        $admin->forceFill(['is_admin' => true, 'mfa_enabled' => true, 'mfa_secret' => Totp::generateSecret()])->save();

        return $admin;
    }

    private function verifiedSession(): array
    {
        return ['admin_mfa_verified' => true, 'admin_last_activity' => time()];
    }
}
