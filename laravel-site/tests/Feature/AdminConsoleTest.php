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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function test_dashboard_month_selector_keeps_recent_months_when_an_older_month_is_selected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->withSession($this->verifiedSession());
        $currentMonth = now()->startOfMonth();
        $selectedMonth = $currentMonth->copy()->subMonths(5);

        $this->get(route('admin.dashboard', ['month' => $selectedMonth->format('Y-m')]))
            ->assertOk()
            ->assertSee('value="'.$selectedMonth->format('Y-m').'" selected', false)
            ->assertSee('value="'.$currentMonth->format('Y-m').'"', false)
            ->assertSee('Confirmed booking value · '.$selectedMonth->format('F Y'));
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
            'type' => 'Flight + Hotel',
            'days' => '1',
            'description' => 'A short summary.',
            'long_description' => 'A longer itinerary description.',
            'price_from' => '1000',
            'price_to' => '1500',
            'image' => '1624253321171-1be53e12f5f4',
            'departure_port' => 'Kuala Lumpur',
            'destination_port' => 'Tokyo',
            'departures' => [Carbon::now()->addMonth()->toDateString()],
            'guest_types' => [['id' => 'adult', 'label' => 'Adult', 'age' => '', 'role' => 'adult']],
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
            'type' => 'Flight + Hotel',
            'days' => '1',
            'description' => 'A short summary.',
            'long_description' => 'A longer description.',
            'price_from' => '1000',
            'price_to' => '1500',
            'image' => '1624253321171-1be53e12f5f4',
            'departure_port' => 'Kuala Lumpur',
            'destination_port' => 'Tokyo',
            'departures' => [Carbon::now()->addMonth()->toDateString()],
            'guest_types' => [['id' => 'adult', 'label' => 'Adult', 'age' => '', 'role' => 'adult']],
            'departure_prices' => [['adult' => '1000']],
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
            'type' => 'Flight + Hotel',
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
            ->assertDontSee('package-pax-price-table')
            ->assertDontSee('Price table');
        $this->get(route('booking', ['id' => $package->slug, 'date' => $date]))
            ->assertOk()
            ->assertSee('"paxTiers":[{"from":1,"to":2,"prices":{"adult":4000}}', false)
            ->assertSee('"from":3,"to":5,"prices":{"adult":5000}', false);
    }

    public function test_admin_package_editor_exposes_inclusion_and_exclusion_fields(): void
    {
        $admin = $this->admin();
        $package = TravelPackage::firstOrFail();
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $this->get(route('admin.packages.edit', $package))
            ->assertOk()
            ->assertSee('name="included[]"', false)
            ->assertSee('name="excluded[]"', false)
            ->assertSee('included_present', false)
            ->assertSee('excluded_present', false);
    }

    public function test_admin_can_upload_replace_and_remove_a_package_itinerary_pdf(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $package = TravelPackage::firstOrFail();
        $payload = $this->packageEditPayload($package);
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $firstPdf = UploadedFile::fake()->createWithContent('itinerary.pdf', "%PDF-1.4\nFirst itinerary\n%%EOF");
        $this->put(route('admin.packages.update', $package), $payload + ['itinerary_pdf' => $firstPdf])
            ->assertRedirect(route('admin.packages'))
            ->assertSessionHas('success', 'Package saved.');

        $package->refresh();
        $firstPath = $package->itinerary_pdf_path;
        $this->assertNotNull($firstPath);
        Storage::disk('local')->assertExists($firstPath);
        $this->get(route('admin.packages.edit', $package))
            ->assertOk()
            ->assertSee('Current file:')
            ->assertSee('Preview current PDF');

        $inline = $this->get(route('packages.pdf', $package))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        ob_start();
        $inline->baseResponse->sendContent();
        $this->assertSame("%PDF-1.4\nFirst itinerary\n%%EOF", ob_get_clean());
        $this->assertStringContainsString('inline', $inline->headers->get('Content-Disposition'));

        $download = $this->get(route('packages.pdf', ['package' => $package, 'download' => 1]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', $download->headers->get('Content-Disposition'));

        $this->put(route('admin.packages.update', $package), $payload)
            ->assertRedirect(route('admin.packages'));
        $this->assertSame($firstPath, $package->fresh()->itinerary_pdf_path);
        Storage::disk('local')->assertExists($firstPath);

        $secondPdf = UploadedFile::fake()->createWithContent('replacement.pdf', "%PDF-1.4\nReplacement itinerary\n%%EOF");
        $this->put(route('admin.packages.update', $package), $payload + ['itinerary_pdf' => $secondPdf])
            ->assertRedirect(route('admin.packages'));
        $secondPath = $package->fresh()->itinerary_pdf_path;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($secondPath);

        $this->put(route('admin.packages.update', $package), $payload + ['remove_itinerary_pdf' => '1'])
            ->assertRedirect(route('admin.packages'));
        $this->assertNull($package->fresh()->itinerary_pdf_path);
        Storage::disk('local')->assertMissing($secondPath);
    }

    public function test_admin_package_itinerary_upload_rejects_non_pdf_and_files_over_50_mb(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $package = TravelPackage::firstOrFail();
        $payload = $this->packageEditPayload($package);
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $this->put(route('admin.packages.update', $package), $payload + [
            'itinerary_pdf' => UploadedFile::fake()->create('itinerary.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('itinerary_pdf');

        $this->put(route('admin.packages.update', $package), $payload + [
            'itinerary_pdf' => UploadedFile::fake()->create('oversized.pdf', 51201, 'application/pdf'),
        ])->assertSessionHasErrors('itinerary_pdf');

        $this->assertNull($package->fresh()->itinerary_pdf_path);
        $this->assertSame([], Storage::disk('local')->allFiles('package-itineraries'));
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

    public function test_booking_detail_shows_saved_summary_traveller_readiness_and_expiry_warning(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $package = TravelPackage::firstOrFail();
        $documentPath = 'Clients/Asia/Japan/sakura-rail_2027-03-27/TWY-DETAIL-01/traveller-01_'.str_repeat('a', 36).'.jpg';
        Storage::disk('local')->put($documentPath, 'private image bytes');
        $booking = Booking::create([
            'reference' => 'TWY-DETAIL-01',
            'order_reference' => 'ORD-8F3K2Q',
            'package_id' => $package->id,
            'departure_date' => '2027-03-27',
            'guests' => 2,
            'guest_details' => [
                'name' => 'Casey Traveller',
                'email' => 'casey@example.test',
                'phone' => '+60 12-345 6789',
                'contact_address' => '12 Jalan Contoh, Puchong',
                'billing_address' => '88 Jalan Bayar, Puchong',
                'booking_detail' => [
                    'rooms' => [['type' => 'Twin'], ['type' => 'Twin']],
                ],
                'addons' => ['insurance'],
                'travellers' => [
                    [
                        'name' => 'Casey Traveller',
                        'dob' => '1990-01-01',
                        'nationality' => 'Malaysian',
                        'passportExpiry' => '2030-08-14',
                        'document_path' => $documentPath,
                    ],
                    [
                        'name' => 'Taylor Traveller',
                        'dob' => '1960-04-04',
                        'nationality' => 'Malaysian',
                        'passportExpiry' => '2027-09-26',
                    ],
                ],
            ],
            'traveller_passports' => ['ZXCVBN123', 'QWERTY128'],
            'passport_number' => 'ZXCVBN123',
            'subtotal' => 10438,
            'discount' => 100,
            'taxes' => 731,
            'total' => 11069,
            'status' => 'Confirmed',
            'payment_method' => 'bank',
        ]);
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $this->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Booked by')
            ->assertSee('Casey Traveller')
            ->assertSee('casey@example.test')
            ->assertSee('+60 12-345 6789')
            ->assertSee('Lead traveller')
            ->assertSee('12 Jalan Contoh, Puchong')
            ->assertSee('88 Jalan Bayar, Puchong')
            ->assertSee('2 of 2 guests')
            ->assertSee('Twin')
            ->assertSee('Discount')
            ->assertSee('ORD-8F3K2Q')
            ->assertSee('Traveller 1 of 2')
            ->assertSee('Traveller 2 of 2')
            ->assertSee('age 37')
            ->assertSee('age 66')
            ->assertSee('******123')
            ->assertSee('******128')
            ->assertSee('Documents: 1 of 2 received')
            ->assertSee('Document received')
            ->assertSee('Document missing')
            ->assertSee('Passport is valid for less than 6 months after departure.')
            ->assertSee(route('admin.bookings.traveller-document', ['booking' => $booking, 'traveller' => 0]), false)
            ->assertDontSee(route('admin.bookings.traveller-document', ['booking' => $booking, 'traveller' => 1]), false)
            ->assertDontSee('ZXCVBN123')
            ->assertDontSee('QWERTY128');
    }

    public function test_only_admins_can_download_private_traveller_documents(): void
    {
        Storage::fake('local');
        $path = 'Clients/Asia/Japan/sakura-rail_2027-03-27/TWY-DOCUMENT-01/traveller-01_'.str_repeat('a', 36).'.jpg';
        Storage::disk('local')->put($path, 'private image bytes');
        $booking = Booking::create([
            'reference' => 'TWY-DOCUMENT-01',
            'order_reference' => 'TWY-12345',
            'package_id' => TravelPackage::where('slug', 'sakura-rail')->value('id'),
            'departure_date' => '2027-03-27',
            'guests' => 1,
            'guest_details' => [
                'travellers' => [[
                    'name' => 'Casey Traveller',
                    'document_path' => $path,
                ]],
            ],
            'subtotal' => 1000,
            'discount' => 0,
            'taxes' => 70,
            'total' => 1070,
            'status' => 'Pending payment',
        ]);
        $route = route('admin.bookings.traveller-document', ['booking' => $booking, 'traveller' => 0]);

        $this->actingAs(User::factory()->create())->get($route)->assertForbidden();

        $this->actingAs($this->admin('document-admin@example.test'))->withSession($this->verifiedSession())
            ->get($route)
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'attachment; filename=traveller-1.jpg');
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

    private function packageEditPayload(TravelPackage $package): array
    {
        $tripPlan = array_fill(0, $package->days, [
            'place' => 'On the journey',
            'activities' => 'Explore the destination.',
            'transport' => '',
        ]);
        $departure = Carbon::now()->addMonth()->toDateString();

        return [
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
            'departures' => [$departure],
            'guest_types' => [['id' => 'adult', 'label' => 'Adult', 'age' => '', 'role' => 'adult']],
            'departure_prices' => [['adult' => max(1, $package->price_from)]],
            'highlights' => ['A memorable journey'],
            'trip_plan' => $tripPlan,
            'status' => 'Published',
        ];
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
