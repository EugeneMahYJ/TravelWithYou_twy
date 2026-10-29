<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Ship;
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
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingWizardTest extends TestCase
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

    public function test_booking_get_shows_the_prototype_empty_states(): void
    {
        $this->get(route('booking', ['id' => 'missing-package', 'date' => '2027-04-03']))
            ->assertOk()
            ->assertSee("We couldn't find that package.")
            ->assertSee('Browse destinations');

        $this->get(route('booking', ['id' => 'sakura-rail', 'date' => '2027-12-31']))
            ->assertOk()
            ->assertSee("That departure isn't available.")
            ->assertSee('Choose a departure');

        $package = TravelPackage::where('slug', 'med-grand')->firstOrFail();
        $ship = Ship::where('name', $package->ship)->firstOrFail();
        $ship->cabins()->delete();
        $this->get(route('booking', ['id' => $package->slug, 'date' => '2027-06-03']))
            ->assertOk()
            ->assertSee('No room types on sale.')
            ->assertSee('Back to package');
    }

    public function test_booking_form_receives_departure_specific_passenger_price_bands(): void
    {
        $package = TravelPackage::where('slug', 'sakura-rail')->firstOrFail();
        $date = Pricing::availableDepartureDates($package)[0];
        $package->pricing = [
            'guest_types' => [
                ['id' => 'adult', 'label' => 'Adult', 'role' => 'adult'],
            ],
            'departure_prices' => [$date => ['adult' => 1000]],
            'departure_pax_tiers' => [$date => [
                ['from' => 1, 'to' => 2, 'prices' => ['adult' => 4000]],
                ['from' => 3, 'to' => 5, 'prices' => ['adult' => 5000]],
            ]],
        ];
        $package->save();

        $this->get(route('booking', ['id' => $package->slug, 'date' => $date]))
            ->assertOk()
            ->assertSee('"paxTiers":[{"from":1,"to":2,"prices":{"adult":4000}}', false)
            ->assertSee('"from":3,"to":5,"prices":{"adult":5000}', false);
    }

    public function test_land_and_cruise_guest_limits_are_enforced_with_prototype_messages(): void
    {
        $emptyLand = $this->landPayload([
            'adult' => 0,
            'single' => 0,
            'childBed' => 0,
            'childTwin' => 0,
            'childNoBed' => 0,
        ]);
        $emptyLand['guests'] = [];
        $this->from(route('booking', ['id' => 'sakura-rail', 'date' => '2027-03-27']))
            ->post(route('booking.submit', ['id' => 'sakura-rail', 'date' => '2027-03-27']), $emptyLand)
            ->assertRedirect()
            ->assertSessionHasErrors(['counts' => 'Add at least one guest to continue.']);

        $land = $this->landPayload([
            'adult' => 0,
            'single' => 0,
            'childBed' => 1,
            'childTwin' => 0,
            'childNoBed' => 0,
        ]);
        $this->from(route('booking', ['id' => 'sakura-rail', 'date' => '2027-03-27']))
            ->post(route('booking.submit', ['id' => 'sakura-rail', 'date' => '2027-03-27']), $land)
            ->assertRedirect()
            ->assertSessionHasErrors(['counts' => 'Children must travel with at least one Adult or Single guest.']);

        $largeLand = $this->landPayload(['adult' => 11, 'childBed' => 2]);
        $this->from(route('booking', ['id' => 'sakura-rail', 'date' => '2027-03-27']))
            ->post(route('booking.submit', ['id' => 'sakura-rail', 'date' => '2027-03-27']), $largeLand)
            ->assertRedirect()
            ->assertSessionHasErrors(['counts' => 'Online bookings are limited to 12 guests. Contact us for larger groups.']);

        $noAdultCruise = $this->cruisePayload(adults: 0, children: 1, rooms: 1);
        $this->from(route('booking', ['id' => 'med-grand', 'date' => '2027-06-03']))
            ->post(route('booking.submit', ['id' => 'med-grand', 'date' => '2027-06-03']), $noAdultCruise)
            ->assertRedirect()
            ->assertSessionHasErrors(['adults' => 'At least 1 adult must travel on each booking.']);

        $cruise = $this->cruisePayload(adults: 1, children: 16, rooms: 5);
        $this->from(route('booking', ['id' => 'med-grand', 'date' => '2027-06-03']))
            ->post(route('booking.submit', ['id' => 'med-grand', 'date' => '2027-06-03']), $cruise)
            ->assertRedirect()
            ->assertSessionHasErrors(['adults' => 'Online bookings are limited to 16 guests. Contact us for larger groups.']);
    }

    public function test_cruise_room_allocation_rules_are_revalidated(): void
    {
        $payload = $this->cruisePayload(adults: 1, children: 1, rooms: 2);
        $this->from(route('booking', ['id' => 'med-grand', 'date' => '2027-06-03']))
            ->post(route('booking.submit', ['id' => 'med-grand', 'date' => '2027-06-03']), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors(['rooms' => 'Each room needs at least 1 adult. 1 adult can fill at most 1 room.']);
    }

    public function test_each_cruise_single_guest_requires_a_private_room(): void
    {
        $payload = $this->cruisePayload(adults: 2, children: 0, rooms: 1, singles: 2);
        $this->from(route('booking', ['id' => 'med-grand', 'date' => '2027-06-03']))
            ->post(route('booking.submit', ['id' => 'med-grand', 'date' => '2027-06-03']), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors([
                'rooms' => 'Maximum 4 guests per room. 4 guests need at least 3 rooms (including 2 private single rooms).',
            ]);
    }

    public function test_cruise_single_guest_must_be_at_least_eighteen(): void
    {
        $payload = $this->cruisePayload(adults: 1, children: 0, rooms: 2, singles: 1);
        $payload['guests'][1]['dob'] = '2010-01-01';

        $this->from(route('booking', ['id' => 'med-grand', 'date' => '2027-06-03']))
            ->post(route('booking.submit', ['id' => 'med-grand', 'date' => '2027-06-03']), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors([
                'guests.1.dob' => 'Single guests must be at least 18 years old.',
            ]);
    }

    public function test_cruise_children_cannot_share_a_single_guest_private_room(): void
    {
        $payload = $this->cruisePayload(adults: 0, children: 1, rooms: 1, singles: 1);

        $this->from(route('booking', ['id' => 'med-grand', 'date' => '2027-06-03']))
            ->post(route('booking.submit', ['id' => 'med-grand', 'date' => '2027-06-03']), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors([
                'rooms' => 'Children must share a room with an Adult; Single guests stay in their own room.',
            ]);
    }

    public function test_cruise_requires_a_room_type_for_each_room(): void
    {
        $payload = $this->cruisePayload(adults: 2, children: 0, rooms: 1);
        unset($payload['room_types']);

        $this->from(route('booking', ['id' => 'med-grand', 'date' => '2027-06-03']))
            ->post(route('booking.submit', ['id' => 'med-grand', 'date' => '2027-06-03']), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors(['room_types' => 'Choose a room type for every room.']);
    }

    public function test_passport_expiry_must_extend_six_months_after_departure(): void
    {
        $payload = $this->landPayload(['adult' => 1]);
        $payload['guests'][0]['passportExpiry'] = '2027-09-26';

        $this->from(route('booking', ['id' => 'sakura-rail', 'date' => '2027-03-27']))
            ->post(route('booking.submit', ['id' => 'sakura-rail', 'date' => '2027-03-27']), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors([
                'guests.0.passportExpiry' => 'Passport must be valid for 6 months after departure (on or after 27 Sep 2027)',
            ]);
    }

    public function test_guest_details_use_the_prototype_validation_messages(): void
    {
        $payload = $this->landPayload(['adult' => 1]);
        $payload['guests'][0] = [
            'name' => 'A',
            'dob' => '1899-01-01',
            'nation' => '',
            'passport' => 'A1',
            'passportExpiry' => '',
            'email' => 'not-an-email',
            'phone' => '123',
        ];

        $this->from(route('booking', ['id' => 'sakura-rail', 'date' => '2027-03-27']))
            ->post(route('booking.submit', ['id' => 'sakura-rail', 'date' => '2027-03-27']), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors([
                'guests.0.name' => 'Enter the full name as on the passport',
                'guests.0.dob' => 'Enter a valid date of birth',
                'guests.0.nation' => 'Required',
                'guests.0.passport' => '6 to 12 letters or digits',
                'guests.0.passportExpiry' => 'Passport expiry date is required',
                'guests.0.email' => 'Enter a valid email address',
                'guests.0.phone' => 'Enter a valid phone number',
            ]);
    }

    public function test_guest_age_is_checked_in_completed_years_on_the_departure_date(): void
    {
        $adultTooYoung = $this->landPayload(['adult' => 1]);
        $adultTooYoung['guests'][0]['dob'] = '2015-03-28';

        $this->from(route('booking', ['id' => 'sakura-rail', 'date' => '2027-03-27']))
            ->post(route('booking.submit', ['id' => 'sakura-rail', 'date' => '2027-03-27']), $adultTooYoung)
            ->assertRedirect()
            ->assertSessionHasErrors(['guests.0.dob' => 'Adult guests must be at least 12 years old.']);

        $childTooOld = $this->landPayload(['adult' => 1, 'childBed' => 1]);
        $childTooOld['guests'][1]['dob'] = '2015-03-26';

        $this->from(route('booking', ['id' => 'sakura-rail', 'date' => '2027-03-27']))
            ->post(route('booking.submit', ['id' => 'sakura-rail', 'date' => '2027-03-27']), $childTooOld)
            ->assertRedirect()
            ->assertSessionHasErrors(['guests.1.dob' => 'Children must be 11 years old or younger.']);

        $atBoundary = $this->landPayload(['adult' => 1, 'childBed' => 1]);
        $atBoundary['action'] = 'cart';
        $atBoundary['guests'][0]['dob'] = '2015-03-27';
        $atBoundary['guests'][1]['dob'] = '2016-03-27';

        $this->post(route('booking.submit', ['id' => 'sakura-rail', 'date' => '2027-03-27']), $atBoundary)
            ->assertRedirect(route('home'))
            ->assertSessionHas('success', 'Added to cart');
    }

    public function test_cart_action_recomputes_total_and_stores_the_detailed_item(): void
    {
        $payload = $this->landPayload(['adult' => 2], ['insurance']);
        $payload['action'] = 'cart';
        $payload['total'] = 1;
        $payload['unit_price'] = 1;

        $this->post(route('booking.submit', ['id' => 'sakura-rail', 'date' => '2027-03-27']), $payload)
            ->assertRedirect(route('home'))
            ->assertSessionHas('success', 'Added to cart');

        $item = session('cart.0');
        $this->assertSame('fh', $item['detail']['kind']);
        $this->assertSame(2, $item['guests']);
        $this->assertEqualsWithDelta(
            array_sum(array_column(Pricing::bookingLines(
                TravelPackage::where('slug', 'sakura-rail')->firstOrFail(),
                '2027-03-27',
                $item['detail'],
                $item['addons'],
            ), 'amount')),
            Pricing::itemTotal($item),
            0.01,
        );
        $this->assertNotSame('A12345678', $item['detail']['guests'][0]['passport']);
    }

    public function test_editing_a_cart_booking_updates_the_same_cart_item(): void
    {
        $payload = $this->landPayload(['adult' => 2]);
        $payload['action'] = 'cart';
        $this->post(route('booking.submit', ['id' => 'sakura-rail', 'date' => '2027-03-27']), $payload)
            ->assertRedirect(route('home'));

        $uid = session('cart.0.uid');
        $this->assertNotEmpty($uid);
        $this->get(route('cart'))
            ->assertOk()
            ->assertSee('Edit booking')
            ->assertSee('cart_item='.$uid, false);

        $this->get(route('booking', [
            'id' => 'sakura-rail',
            'date' => '2027-03-27',
            'cart_item' => $uid,
        ]))
            ->assertOk()
            ->assertSee('Taylor Traveller 0')
            ->assertSee('"passport":"A12345678"', false)
            ->assertSee('"editing":true', false)
            ->assertSee('booking.js?v=', false);

        $payload = $this->landPayload(['adult' => 1], ['insurance']);
        $payload['action'] = 'cart';
        $payload['guests'][0]['name'] = 'Updated Traveller';
        $payload['guests'][0]['passport'] = 'B98765432';

        $this->post(route('booking.submit', [
            'id' => 'sakura-rail',
            'date' => '2027-03-27',
            'cart_item' => $uid,
        ]), $payload)
            ->assertRedirect(route('cart'))
            ->assertSessionHas('success', 'Booking updated in your cart.');

        $cart = session('cart');
        $this->assertCount(1, $cart);
        $this->assertSame($uid, $cart[0]['uid']);
        $this->assertSame(1, $cart[0]['guests']);
        $this->assertSame(['insurance'], $cart[0]['addons']);
        $this->assertSame('Updated Traveller', $cart[0]['detail']['guests'][0]['name']);
        $this->assertSame('B98765432', Crypt::decryptString($cart[0]['detail']['guests'][0]['passport']));
    }

    public function test_custom_guest_types_prices_and_addons_are_used_by_the_booking_flow(): void
    {
        $package = TravelPackage::create([
            'slug' => 'custom-booking-test',
            'name' => 'Custom Booking Test',
            'country' => 'Japan',
            'region' => 'Asia',
            'type' => 'Rail',
            'days' => 3,
            'description' => 'A test journey.',
            'details' => 'A test journey.',
            'price_from' => 1000,
            'price_to' => 1500,
            'departures' => ['2027-03-27'],
            'image' => 'custom-booking-test.jpg',
            'published' => true,
        ]);
        $package->pricing = [
            'guest_types' => [
                ['id' => 'standard', 'label' => 'Standard adult', 'age' => '12+', 'role' => 'adult'],
                ['id' => 'youth', 'label' => 'Youth', 'age' => '6–11', 'role' => 'child'],
            ],
            'departure_prices' => ['2027-03-27' => ['standard' => 1400, 'youth' => 900]],
            'addons' => [['id' => 'guide', 'label' => 'Private guide', 'price' => 120]],
        ];
        $package->save();

        $this->get(route('booking', ['id' => $package->slug, 'date' => '2027-03-27']))
            ->assertOk()
            ->assertSee('Standard adult')
            ->assertSee('Youth');

        $payload = [
            'action' => 'cart',
            'kind' => 'fh',
            'counts' => ['standard' => 1, 'youth' => 1],
            'addons' => ['guide'],
            'guests' => $this->guests(2),
        ];
        $payload['guests'][1]['dob'] = '2016-03-27';
        $this->post(route('booking.submit', ['id' => $package->slug, 'date' => '2027-03-27']), $payload)
            ->assertRedirect(route('home'));

        $item = session('cart.0');
        $this->assertSame(['standard' => 1, 'youth' => 1], $item['detail']['counts']);
        $this->assertEquals(2540, Pricing::itemTotal($item));
        $this->assertSame('A12345678', Crypt::decryptString($item['detail']['guests'][0]['passport']));
    }

    public function test_proceed_action_enters_checkout_and_persists_encrypted_passports_and_server_totals(): void
    {
        $payload = $this->cruisePayload(adults: 2, children: 1, rooms: 1);
        $payload['action'] = 'proceed';
        $payload['addons'] = ['insurance'];

        $this->post(route('booking.submit', ['id' => 'med-grand', 'date' => '2027-06-03']), $payload)
            ->assertRedirect(route('checkout'))
            ->assertSessionHas('cart.0.detail.kind', 'cruise');

        $cartItem = session('cart.0');
        $expectedSubtotal = Pricing::itemTotal($cartItem);
        $this->post(route('checkout.place'), [
            'name' => 'Taylor Traveller',
            'email' => 'taylor@example.test',
            'phone' => '+60123456789',
            'contact_address' => '12 Jalan Example, Kuala Lumpur',
            'traveller_name' => 'Taylor Traveller',
            'traveller_dob' => '1990-04-12',
            'nationality' => 'Malaysian',
            'passport_number' => 'A12345678',
            'passport_expiry' => '2030-01-01',
            'payment_method' => 'card',
            'billing_same' => '1',
            'terms' => '1',
            'privacy' => '1',
            'cancellation' => '1',
            'card_number' => '4242424242424242',
            'card_name' => 'Taylor Traveller',
            'card_expiry' => '08/29',
            'card_cvc' => '123',
        ])->assertRedirect(route('confirmation'));

        $booking = Booking::firstOrFail();
        $this->assertEqualsWithDelta($expectedSubtotal, $booking->subtotal, 0.01);
        $this->assertSame(3, $booking->guests);
        $this->assertSame('A12345678', $booking->passport_number);
        $this->assertSame(['A12345678', 'A12345678', 'A12345678'], $booking->traveller_passports);
        $this->assertNotSame('A12345678', DB::table('bookings')->where('id', $booking->id)->value('passport_number'));
        $this->assertNotSame('A12345678', DB::table('bookings')->where('id', $booking->id)->value('traveller_passports'));
        $this->assertArrayNotHasKey('passport', $booking->guest_details['travellers'][0]);
    }

    private function landPayload(array $counts = ['adult' => 2], array $addons = []): array
    {
        $counts = array_merge([
            'adult' => 0,
            'single' => 0,
            'childBed' => 0,
            'childTwin' => 0,
            'childNoBed' => 0,
        ], $counts);

        return [
            'action' => 'proceed',
            'kind' => 'fh',
            'counts' => $counts,
            'addons' => $addons,
            'guests' => $this->guests(array_sum($counts)),
        ];
    }

    private function cruisePayload(int $adults, int $children, int $rooms, int $singles = 0): array
    {
        $package = TravelPackage::where('slug', 'med-grand')->firstOrFail();
        $ship = Ship::where('name', $package->ship)->with('cabins')->firstOrFail();
        $guests = $this->guests($adults + $children + $singles);
        for ($index = $adults + $singles; $index < count($guests); $index++) {
            $guests[$index]['dob'] = '2018-01-01';
        }

        $payload = [
            'action' => 'proceed',
            'kind' => 'cruise',
            'adults' => $adults,
            'children' => $children,
            'rooms' => $rooms,
            'room_types' => array_fill(0, $rooms, $ship->cabins->first()->type),
            'addons' => [],
            'guests' => $guests,
        ];
        if ($singles > 0) {
            $payload['counts'] = [
                'adult' => $adults,
                'single' => $singles,
                'childBed' => 0,
                'childTwin' => $children,
                'childNoBed' => 0,
            ];
        }

        return $payload;
    }

    private function guests(int $count): array
    {
        return array_map(fn ($index) => [
            'name' => 'Taylor Traveller '.$index,
            'dob' => '1990-01-01',
            'nation' => 'Malaysian',
            'passport' => 'A12345678',
            'passportExpiry' => '2028-01-01',
            'email' => $index === 0 ? 'taylor@example.test' : '',
            'phone' => $index === 0 ? '+60123456789' : '',
        ], range(0, max(0, $count - 1)));
    }
}
