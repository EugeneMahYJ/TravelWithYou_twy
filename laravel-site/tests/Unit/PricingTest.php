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
        $package = TravelPackage::where('slug', 'sakura-rail')->firstOrFail();
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

    public function test_group_discount_applies_only_at_six_guests(): void
    {
        $package = TravelPackage::where('slug', 'mara-migration')->firstOrFail();

        $this->assertEquals(4150, Pricing::unitPrice($package, 5));
        $this->assertEquals(3735, Pricing::unitPrice($package, 6));
    }

    public function test_cruise_children_are_priced_at_seventy_five_percent(): void
    {
        $package = TravelPackage::where('slug', 'med-grand')->firstOrFail();
        $lines = Pricing::bookingLines($package, '2027-06-05', [
            'kind' => 'cruise',
            'rooms' => [['type' => 'Interior', 'adults' => 1, 'children' => 1]],
        ], []);

        $this->assertEquals(3350, $lines[0]['amount']);
        $this->assertEquals(2512.5, $lines[1]['amount']);
        $this->assertEquals(5862.5, array_sum(array_column($lines, 'amount')));
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
        $this->assertSame('Maximum 4 guests per room. 7 guests need at least 2 rooms.', Pricing::roomError(5, 2, 1));
        $this->assertSame(
            'Each room needs at least 1 adult. 1 adult can fill at most 1 room.',
            Pricing::roomError(1, 1, 2)
        );
        $this->assertSame([
            ['adults' => 1, 'children' => 2],
            ['adults' => 1, 'children' => 1],
        ], Pricing::allocateRooms(2, 3, 2));
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

    public function test_prototype_data_seeders_are_complete_and_idempotent(): void
    {
        $this->assertDatabaseCount('countries', 11);
        $this->assertDatabaseCount('travel_packages', 14);
        $this->assertDatabaseCount('travel_deals', 8);
        $this->assertTrue(TravelDeal::where('slug', 'd4')->firstOrFail()->expires_at->isFuture());
        $this->assertDatabaseCount('guides', 7);
        $this->assertDatabaseCount('faqs', 6);
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
        $this->assertDatabaseCount('faqs', 6);
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
