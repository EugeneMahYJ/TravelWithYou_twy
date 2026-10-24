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
        $this->get(route('booking', ['id' => $package->slug, 'date' => '2027-06-05']))
            ->assertOk()
            ->assertSee('No room types on sale.')
            ->assertSee('Back to package');
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
        $this->from(route('booking', ['id' => 'med-grand', 'date' => '2027-06-05']))
            ->post(route('booking.submit', ['id' => 'med-grand', 'date' => '2027-06-05']), $noAdultCruise)
            ->assertRedirect()
            ->assertSessionHasErrors(['adults' => 'At least 1 adult must travel on each booking.']);

        $cruise = $this->cruisePayload(adults: 1, children: 16, rooms: 5);
        $this->from(route('booking', ['id' => 'med-grand', 'date' => '2027-06-05']))
            ->post(route('booking.submit', ['id' => 'med-grand', 'date' => '2027-06-05']), $cruise)
            ->assertRedirect()
            ->assertSessionHasErrors(['adults' => 'Online bookings are limited to 16 guests. Contact us for larger groups.']);
    }

    public function test_cruise_room_allocation_rules_are_revalidated(): void
    {
        $payload = $this->cruisePayload(adults: 1, children: 1, rooms: 2);
        $this->from(route('booking', ['id' => 'med-grand', 'date' => '2027-06-05']))
            ->post(route('booking.submit', ['id' => 'med-grand', 'date' => '2027-06-05']), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors(['rooms' => 'Each room needs at least 1 adult. 1 adult can fill at most 1 room.']);
    }

    public function test_cruise_requires_a_room_type_for_each_room(): void
    {
        $payload = $this->cruisePayload(adults: 2, children: 0, rooms: 1);
        unset($payload['room_types']);

        $this->from(route('booking', ['id' => 'med-grand', 'date' => '2027-06-05']))
            ->post(route('booking.submit', ['id' => 'med-grand', 'date' => '2027-06-05']), $payload)
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
        $this->assertSame('A12345678', Crypt::decryptString($item['detail']['guests'][0]['passport']));
    }

    public function test_proceed_action_enters_checkout_and_persists_encrypted_passports_and_server_totals(): void
    {
        $payload = $this->cruisePayload(adults: 2, children: 1, rooms: 1);
        $payload['action'] = 'proceed';
        $payload['addons'] = ['insurance'];

        $this->post(route('booking.submit', ['id' => 'med-grand', 'date' => '2027-06-05']), $payload)
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

    private function cruisePayload(int $adults, int $children, int $rooms): array
    {
        $package = TravelPackage::where('slug', 'med-grand')->firstOrFail();
        $ship = Ship::where('name', $package->ship)->with('cabins')->firstOrFail();

        return [
            'action' => 'proceed',
            'kind' => 'cruise',
            'adults' => $adults,
            'children' => $children,
            'rooms' => $rooms,
            'room_types' => array_fill(0, $rooms, $ship->cabins->first()->type),
            'addons' => [],
            'guests' => $this->guests($adults + $children),
        ];
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
