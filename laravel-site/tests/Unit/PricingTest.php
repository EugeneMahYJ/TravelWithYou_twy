<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\Ship;
use App\Models\TravelDeal;
use App\Models\TravelPackage;
use App\Support\Pricing;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FaqSeeder;
use Database\Seeders\GuideSeeder;
use Database\Seeders\PromoCodeSeeder;
use Database\Seeders\ShipSeeder;
use Database\Seeders\TravelDealSeeder;
use Database\Seeders\TravelPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PricingTest extends TestCase
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

    public function test_sakura_deal_addon_and_welcome_promo_totals_match_the_prototype_vector(): void
    {
        $package = TravelPackage::create([
            'slug' => 'custom-pricing-test',
            'name' => 'Custom Pricing Test',
            'country' => 'Japan',
            'region' => 'Asia',
            'type' => 'Rail',
            'days' => 3,
            'description' => 'A test journey.',
            'details' => 'A test journey.',
            'price_from' => 1000,
            'price_to' => 1500,
            'departures' => ['2027-03-27'],
            'image' => 'custom-pricing-test.jpg',
            'published' => true,
        ]);
        $item = [
            'package' => $package,
            'departure_date' => '2027-04-03',
            'guests' => 2,
            'addons' => ['insurance'],
        ];

        $this->assertEquals(7180, Pricing::itemTotal($item));

        $promo = Pricing::promoForCode('WELCOME10');
        $totals = Pricing::cartTotals([$item], $promo);
        $this->assertEquals(718, $totals['discount']);
        $this->assertEquals(452.34, $totals['taxes']);
        $this->assertEquals(6914.34, $totals['total']);
        $this->assertSame('RM 6,914', fmt_rm($totals['total']));

        $withoutPromo = Pricing::cartTotals([$item], null);
        $this->assertEquals(7682.6, $withoutPromo['total']);
        $this->assertSame('RM 7,683', fmt_rm($withoutPromo['total']));
    }

    public function test_fixed_promo_discount_only_applies_to_selected_packages_and_is_capped_at_their_subtotal(): void
    {
        $eligiblePackage = TravelPackage::where('slug', 'sakura-rail')->firstOrFail();
        $ineligiblePackage = TravelPackage::where('slug', 'kyoto-retreat')->firstOrFail();
        $promo = PromoCode::create([
            'code' => 'ASIA500',
            'discount_percent' => 0,
            'discount_type' => 'fixed',
            'discount_amount' => 500,
            'all_packages' => false,
            'active' => true,
        ]);
        $promo->packages()->sync([$eligiblePackage->id]);
        $items = [
            ['package' => $eligiblePackage, 'departure_date' => $eligiblePackage->departures[0], 'guests' => 1, 'addons' => []],
            ['package' => $ineligiblePackage, 'departure_date' => $ineligiblePackage->departures[0], 'guests' => 1, 'addons' => []],
        ];

        $totals = Pricing::cartTotals($items, $promo);
        $this->assertEquals(500, $totals['discount']);
        $this->assertEquals([500, 0], $totals['discount_allocations']);

        $promo->update(['discount_amount' => 100000]);
        $cappedTotals = Pricing::cartTotals($items, $promo);
        $eligibleSubtotal = Pricing::itemTotal($items[0]);
        $this->assertEquals($eligibleSubtotal, $cappedTotals['discount']);
        $this->assertEquals([$eligibleSubtotal, 0], $cappedTotals['discount_allocations']);

        $universalPromo = PromoCode::create([
            'code' => 'TRIP20',
            'discount_percent' => 20,
            'discount_type' => 'percent',
            'all_packages' => true,
            'active' => true,
        ]);
        $universalTotals = Pricing::cartTotals($items, $universalPromo);
        $cartSubtotal = array_sum(array_map(fn (array $item) => Pricing::itemTotal($item), $items));
        $this->assertEquals(round($cartSubtotal * 0.2, 2), $universalTotals['discount']);
        $this->assertEquals($universalTotals['discount'], array_sum($universalTotals['discount_allocations']));
    }

    public function test_group_discount_applies_only_at_six_guests(): void
    {
        $package = TravelPackage::where('slug', 'mara-migration')->firstOrFail();

        $this->assertEquals(4150, Pricing::unitPrice($package, 5));
        $this->assertEquals(3735, Pricing::unitPrice($package, 6));
    }

    public function test_cruise_passengers_use_the_package_cabin_price(): void
    {
        $package = TravelPackage::where('slug', 'med-grand')->firstOrFail();
        $package->pricing = ['cabin_prices' => ['Interior' => 3350]];
        $package->save();
        $lines = Pricing::bookingLines($package, '2027-06-05', [
            'kind' => 'cruise',
            'rooms' => [['type' => 'Interior', 'adults' => 1, 'children' => 1]],
        ], []);

        $this->assertEquals(3350, $lines[0]['amount']);
        $this->assertEquals(3350, $lines[1]['amount']);
        $this->assertEquals(6700, array_sum(array_column($lines, 'amount')));
    }

    public function test_cruise_single_guests_get_private_rooms_and_the_single_supplement(): void
    {
        $package = TravelPackage::where('slug', 'med-grand')->firstOrFail();
        $package->pricing = [
            'cabin_prices' => ['Interior' => 1000],
            'guest_types' => Pricing::GUEST_KINDS,
        ];
        $package->save();
        TravelDeal::where('package_id', $package->id)->update(['active' => false]);
        $lines = Pricing::bookingLines($package, '2027-06-05', [
            'kind' => 'cruise',
            'rooms' => [
                ['type' => 'Interior', 'adults' => 0, 'children' => 0, 'singles' => 1],
                ['type' => 'Interior', 'adults' => 0, 'children' => 0, 'singles' => 1],
                ['type' => 'Interior', 'adults' => 2, 'children' => 0],
            ],
        ], []);

        $this->assertEquals([1500, 1500, 2000], array_column($lines, 'amount'));
        $this->assertEquals(5000, array_sum(array_column($lines, 'amount')));
    }

    public function test_custom_guest_prices_and_addons_are_package_and_departure_specific(): void
    {
        $package = TravelPackage::create([
            'slug' => 'custom-pricing-test',
            'name' => 'Custom Pricing Test',
            'country' => 'Japan',
            'region' => 'Asia',
            'type' => 'Rail',
            'days' => 3,
            'description' => 'A test journey.',
            'details' => 'A test journey.',
            'price_from' => 1000,
            'price_to' => 1500,
            'departures' => ['2027-03-27'],
            'image' => 'custom-pricing-test.jpg',
            'published' => true,
        ]);
        $package->pricing = [
            'guest_types' => [
                ['id' => 'adult', 'label' => 'Adult', 'role' => 'adult'],
                ['id' => 'child', 'label' => 'Child', 'role' => 'child'],
            ],
            'departure_prices' => ['2027-03-27' => ['adult' => 1200, 'child' => 800]],
            'addons' => [['id' => 'guide', 'label' => 'Private guide', 'price' => 100]],
        ];
        $package->save();

        $lines = Pricing::bookingLines($package, '2027-03-27', [
            'kind' => 'fh',
            'counts' => ['adult' => 1, 'child' => 1],
        ], ['guide']);

        $this->assertEquals(1200, $lines[0]['amount']);
        $this->assertEquals(800, $lines[1]['amount']);
        $this->assertEquals(200, $lines[2]['amount']);
    }

    public function test_flight_price_bands_are_applied_to_the_correct_passenger_numbers(): void
    {
        $date = now()->addMonth()->toDateString();
        $package = TravelPackage::create([
            'slug' => 'pax-tier-pricing-test',
            'name' => 'Pax Tier Pricing Test',
            'country' => 'Japan',
            'region' => 'Asia',
            'type' => 'Flight + hotel',
            'days' => 3,
            'description' => 'A test journey.',
            'details' => 'A test journey.',
            'price_from' => 1000,
            'price_to' => 5000,
            'departures' => [$date],
            'image' => 'pax-tier-pricing-test.jpg',
            'published' => true,
        ]);
        $package->pricing = [
            'guest_types' => [
                ['id' => 'adult', 'label' => 'Adult', 'role' => 'adult'],
                ['id' => 'child', 'label' => 'Child', 'role' => 'child'],
            ],
            'departure_prices' => [$date => ['adult' => 1000, 'child' => 800]],
            'departure_pax_tiers' => [$date => [
                ['from' => 1, 'to' => 2, 'prices' => ['adult' => 4000, 'child' => 3500]],
                ['from' => 3, 'to' => 5, 'prices' => ['adult' => 5000, 'child' => 4500]],
            ]],
        ];
        $package->save();

        $lines = Pricing::bookingLines($package, $date, [
            'kind' => 'fh',
            'counts' => ['adult' => 3, 'child' => 1],
        ], []);

        $this->assertSame([
            ['group' => 'Guests', 'label' => 'Adult (Pax 1–2) × 2 @ RM 4,000', 'amount' => 8000.0],
            ['group' => 'Guests', 'label' => 'Adult (Pax 3–5) × 1 @ RM 5,000', 'amount' => 5000.0],
            ['group' => 'Guests', 'label' => 'Child (Pax 3–5) × 1 @ RM 4,500', 'amount' => 4500.0],
        ], $lines);
    }

    public function test_bogo_fare_is_half_the_pair_price(): void
    {
        $package = TravelPackage::where('slug', '5DCY4')->firstOrFail();
        $fare = Pricing::fareFor($package, '2027-04-03');

        $this->assertNotNull($fare);
        $this->assertEquals(1599.5, Pricing::fareUnitPrice($package, $fare));
    }

    public function test_promo_stacking_can_be_disabled_for_packages_with_a_deal(): void
    {
        config(['travel.stack_promo_on_deals' => false]);
        $package = TravelPackage::where('slug', 'sakura-rail')->firstOrFail();
        $item = [
            'package' => $package,
            'departure_date' => '2027-04-03',
            'guests' => 2,
            'addons' => ['insurance'],
        ];

        $totals = Pricing::cartTotals([$item], Pricing::promoForCode('WELCOME10'));

        $this->assertEquals(0, $totals['discount']);
        $this->assertEquals(7682.6, $totals['total']);
    }

    public function test_room_allocation_errors_and_item_availability_match_the_rules(): void
    {
        $this->assertSame(2, Pricing::minRooms(5, 2));
        $this->assertSame(3, Pricing::minRooms(2, 0, 2));
        $this->assertSame('At most 4 guests per room. Choose at least 2 rooms for 7 guests.', Pricing::roomError(5, 2, 1));
        $this->assertSame(
            'At most 4 guests per room. Choose at least 3 rooms for 4 guests, including 2 private Single rooms.',
            Pricing::roomError(2, 0, 1, 2)
        );
        $this->assertSame(
            'Children must share with an Adult. Add a sharing Adult or change a Single to Adult.',
            Pricing::roomError(0, 1, 1, 1)
        );
        $this->assertSame(
            'You can book up to 1 room with your selected Adults. Reduce the number of rooms or add enough Adults or Singles.',
            Pricing::roomError(1, 1, 2)
        );
        $this->assertSame(
            'You can book up to 3 rooms with your selected Adults and Singles. Reduce the number of rooms or add enough Adults or Singles.',
            Pricing::roomError(2, 0, 5, 2)
        );
        $this->assertSame(
            'A solo Adult must be marked as Single. Choose 1 room or add enough guests to fill more rooms.',
            Pricing::roomError(1, 0, 2)
        );
        $this->assertSame(
            'A solo Adult needs to be marked as Single to book a private room.',
            Pricing::roomError(1, 0, 1)
        );
        $this->assertSame(
            'This guest mix cannot fit the room rules. Add or reclassify guests, or reduce the group, so each shared room has at least 2 guests and an Adult.',
            Pricing::roomError(1, 4, 2)
        );
        $this->assertSame('Add at least one Adult or Single to continue.', Pricing::roomError(0, 0, 1));
        $this->assertSame('', Pricing::roomError(4, 0, 2));
        $this->assertSame(
            'For 3 rooms, mark at least 2 Adults as Single, or reduce the number of rooms.',
            Pricing::roomError(4, 0, 3)
        );
        $this->assertSame(
            'For 3 rooms, mark at least 1 Adult as Single, or reduce the number of rooms.',
            Pricing::roomError(3, 0, 3, 1)
        );
        $this->assertSame([
            ['adults' => 1, 'children' => 2],
            ['adults' => 1, 'children' => 1],
        ], Pricing::allocateRooms(2, 3, 2));
        $this->assertSame([
            ['adults' => 0, 'children' => 0, 'singles' => 1],
            ['adults' => 0, 'children' => 0, 'singles' => 1],
            ['adults' => 2, 'children' => 0],
        ], Pricing::allocateRooms(2, 0, 3, 2));
        $this->assertTrue(Pricing::itemAvailable([
            'pkgId' => 'sakura-rail',
            'date' => '2027-03-27',
        ]));
        $this->assertFalse(Pricing::itemAvailable([
            'pkgId' => 'missing-package',
            'date' => '2027-03-27',
        ]));
        $this->assertTrue(Pricing::itemAvailable([
            'pkgId' => '5DCY4',
            'date' => '2027-04-03',
        ]));
    }

    public function test_room_validation_and_allocations_match_rules_for_guest_mix_matrix(): void
    {
        for ($adults = 0; $adults <= 8; $adults++) {
            for ($children = 0; $children <= 8; $children++) {
                for ($singles = 0; $singles <= 8; $singles++) {
                    for ($rooms = 1; $rooms <= 8; $rooms++) {
                        $sharingGuests = $adults + $children;
                        $expectedValid = $adults + $children + $singles > 0
                            && ! ($children > 0 && $adults === 0);
                        if ($expectedValid && $sharingGuests > 0) {
                            $minimum = $singles + (int) ceil($sharingGuests / Pricing::MAX_PER_ROOM);
                            $maximum = $singles + min($adults, (int) floor($sharingGuests / 2));
                            $expectedValid = $rooms >= $minimum && $rooms <= $maximum;
                        } elseif ($expectedValid) {
                            $expectedValid = $rooms === $singles;
                        }

                        $valid = Pricing::roomError($adults, $children, $rooms, $singles) === '';
                        $this->assertSame($expectedValid, $valid, sprintf(
                            'Unexpected validity for %d Adults, %d Children, %d Singles, and %d rooms.',
                            $adults,
                            $children,
                            $singles,
                            $rooms
                        ));

                        if (! $valid) {
                            continue;
                        }

                        $allocation = Pricing::allocateRooms($adults, $children, $rooms, $singles);
                        $this->assertCount($rooms, $allocation);
                        $this->assertSame($adults, array_sum(array_column($allocation, 'adults')));
                        $this->assertSame($children, array_sum(array_column($allocation, 'children')));
                        $this->assertSame($singles, array_sum(array_column($allocation, 'singles')));
                        foreach ($allocation as $room) {
                            if (($room['singles'] ?? 0) === 1) {
                                $this->assertSame(0, $room['adults'] + $room['children']);
                                continue;
                            }

                            $occupants = $room['adults'] + $room['children'];
                            $this->assertGreaterThanOrEqual(2, $occupants);
                            $this->assertLessThanOrEqual(Pricing::MAX_PER_ROOM, $occupants);
                            $this->assertGreaterThanOrEqual(1, $room['adults']);
                        }
                    }
                }
            }
        }
    }

    public function test_prototype_data_seeders_are_complete_and_idempotent(): void
    {
        $this->assertDatabaseCount('countries', 11);
        $this->assertDatabaseCount('travel_packages', 14);
        $this->assertDatabaseCount('travel_deals', 8);
        $this->assertTrue(TravelDeal::where('slug', 'd4')->firstOrFail()->expires_at->isFuture());
        $this->assertDatabaseCount('guides', 7);
        $this->assertDatabaseCount('faqs', 17);
        $this->assertCount(5, Ship::with('cabins')->get());
        $this->assertSame([4, 3, 3, 3, 4], Ship::with('cabins')->orderBy('id')->get()->map(
            fn (Ship $ship) => $ship->cabins->count()
        )->all());
        $this->assertSame(14, TravelPackage::whereNotNull('long_description')
            ->whereNotNull('highlights')
            ->whereNotNull('trip_plan')
            ->whereNotNull('gallery')
            ->count());
        $this->assertSame(5, PromoCode::count());

        $this->seed([
            CountrySeeder::class,
            ShipSeeder::class,
            TravelPackageSeeder::class,
            TravelDealSeeder::class,
            PromoCodeSeeder::class,
            GuideSeeder::class,
            FaqSeeder::class,
        ]);

        $this->assertDatabaseCount('countries', 11);
        $this->assertDatabaseCount('travel_packages', 14);
        $this->assertDatabaseCount('ships', 5);
        $this->assertDatabaseCount('ship_cabins', 17);
        $this->assertDatabaseCount('travel_deals', 8);
        $this->assertDatabaseCount('guides', 7);
        $this->assertDatabaseCount('faqs', 17);
        $this->assertSame([
            'AMALFI20' => 0.2,
            'INCA15' => 0.15,
            'MARA10' => 0.1,
            'SUMMER15' => 0.15,
            'WELCOME10' => 0.1,
        ], Pricing::promos()->all());
        $this->assertSame('23 Jan 2027', fmt_date('2027-01-23'));
        $this->assertSame('Jan 2027', fmt_month('2027-01-23'));
    }

    public function test_booking_passport_number_is_encrypted_at_rest(): void
    {
        $package = TravelPackage::where('slug', 'sakura-rail')->firstOrFail();
        $booking = Booking::create([
            'reference' => 'TWY-ENCRYPT',
            'package_id' => $package->id,
            'departure_date' => '2027-03-27',
            'guests' => 1,
            'guest_details' => [],
            'subtotal' => 100,
            'discount' => 0,
            'taxes' => 7,
            'total' => 107,
            'passport_number' => 'A12345678',
        ]);

        $storedValue = DB::table('bookings')->where('id', $booking->id)->value('passport_number');

        $this->assertNotSame('A12345678', $storedValue);
        $this->assertSame('A12345678', $booking->fresh()->passport_number);
    }

    public function test_asset_image_helper_uses_local_images_and_a_sea_placeholder(): void
    {
        $this->assertStringEndsWith('/assets/1555400038-63f5ba517a47.jpg', asset_img('1555400038-63f5ba517a47'));
        $placeholder = rawurldecode(asset_img('not-found'));
        $this->assertStringStartsWith('data:image/svg+xml,', asset_img('not-found'));
        $this->assertStringContainsString('#1f4e5f', $placeholder);
    }
}
