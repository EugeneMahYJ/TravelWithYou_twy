<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\TravelDeal;
use App\Models\TravelPackage;
use App\Models\User;
use App\Support\Pricing;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FaqSeeder;
use Database\Seeders\GuideSeeder;
use Database\Seeders\PromoCodeSeeder;
use Database\Seeders\ShipSeeder;
use Database\Seeders\TravelDealSeeder;
use Database\Seeders\TravelPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([
            CountrySeeder::class,
            ShipSeeder::class,
            TravelPackageSeeder::class,
            TravelDealSeeder::class,
            PromoCodeSeeder::class,
            GuideSeeder::class,
            FaqSeeder::class,
        ]);
        config(['mail.default' => 'log']);
    }

    public function test_valid_card_checkout_uses_pricing_and_keeps_card_data_out_of_storage_and_logs(): void
    {
        Log::spy();
        $package = $this->addPackageWithDetails('sakura-rail', 2);
        $item = session('cart.0');
        $this->post(route('cart.promo'), ['code' => 'WELCOME10'])
            ->assertSessionHas('promo_code', 'WELCOME10');
        $this->get(route('checkout'))
            ->assertOk()
            ->assertSee('Contact details')
            ->assertSee('Payment')
            ->assertDontSee('Traveller details')
            ->assertDontSee('traveller_name');
        $expected = Pricing::cartTotals(
            [[...$item, 'package' => $package]],
            Pricing::promoForCode('WELCOME10'),
        );

        $this->post(route('checkout.place'), $this->checkoutInput())
            ->assertRedirect(route('confirmation'))
            ->assertSessionMissing('cart')
            ->assertSessionMissing('promo_code');

        $booking = Booking::firstOrFail();
        $this->assertSame('Paid', $booking->status);
        $this->assertSame('Please note any special requirements.', $booking->guest_details['booking_detail']['requests']);
        $this->assertEqualsWithDelta($expected['total'], $booking->total, 0.01);
        $this->assertMatchesRegularExpression('/^TWY-\d{5}$/', $booking->order_reference);
        $this->actingAs(User::factory()->create())
            ->get(route('confirmation'))
            ->assertOk()
            ->assertSee($booking->order_reference);
        $stored = json_encode(DB::table('bookings')->first(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('4242424242424242', $stored);
        $this->assertStringNotContainsString('08/29', $stored);
        $this->assertStringNotContainsString('"123"', $stored);
        $this->assertArrayNotHasKey('card_number', session('_old_input', []));
        $this->assertArrayNotHasKey('card_expiry', session('_old_input', []));
        $this->assertArrayNotHasKey('card_cvc', session('_old_input', []));
        Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context): bool {
            $serialized = json_encode([$message, $context]);

            return ! str_contains($serialized, '4242424242424242')
                && ! str_contains($serialized, '08/29')
                && ! str_contains($serialized, '123');
        });
    }

    public function test_simulated_card_failure_keeps_cart_and_creates_no_booking(): void
    {
        $this->addPackageWithDetails('sakura-rail', 1);
        $input = $this->checkoutInput();
        $input['card_number'] = '4242424242420000';

        $this->from(route('checkout'))->post(route('checkout.place'), $input)
            ->assertRedirect(route('payment.failed'))
            ->assertSessionHas('cart');
        $this->get(route('payment.failed'))
            ->assertOk()
            ->assertSee('Payment didn&apos;t', false)
            ->assertSee('Your cart is saved.');
        $this->assertSame(0, Booking::count());
    }

    public function test_invalid_and_inactive_promos_are_rejected_and_valid_code_applies(): void
    {
        $this->addPackage('sakura-rail', 1);
        $this->from(route('cart'))->post(route('cart.promo'), ['code' => 'NOPE'])
            ->assertRedirect(route('cart'))
            ->assertSessionHas('error', 'That code is not valid. Try WELCOME10.');

        PromoCode::create([
            'code' => 'INACTIVE',
            'discount_percent' => 25,
            'active' => false,
            'starts_at' => null,
            'expires_at' => null,
        ]);
        $this->from(route('cart'))->post(route('cart.promo'), ['code' => 'INACTIVE'])
            ->assertRedirect(route('cart'))
            ->assertSessionHas('error', 'That code is not valid. Try WELCOME10.');
        $this->post(route('cart.promo'), ['code' => 'WELCOME10'])
            ->assertSessionHas('promo_code', 'WELCOME10');

        $legacyCode = TravelDeal::where('active', true)->value('promo_code');
        $this->post(route('cart.promo'), ['code' => $legacyCode])
            ->assertSessionHas('promo_code', $legacyCode);

        PromoCode::query()->update(['active' => false]);
        $this->from(route('cart'))->post(route('cart.promo'), ['code' => 'NO-DEMO-CODES'])
            ->assertRedirect(route('cart'))
            ->assertSessionHas('error', 'That code is not valid.');
    }

    public function test_package_restricted_promo_is_rejected_without_an_eligible_trip_and_applies_to_one_when_added(): void
    {
        $this->addPackageWithDetails('kyoto-retreat', 1);
        $eligiblePackage = TravelPackage::where('slug', 'sakura-rail')->firstOrFail();
        $promo = PromoCode::create([
            'code' => 'ASIA500',
            'discount_percent' => 0,
            'discount_type' => 'fixed',
            'discount_amount' => 500,
            'all_packages' => false,
            'active' => true,
        ]);
        $promo->packages()->sync([$eligiblePackage->id]);

        $this->from(route('cart'))->post(route('cart.promo'), ['code' => 'ASIA500'])
            ->assertRedirect(route('cart'))
            ->assertSessionHas('error', 'This promo code does not apply to the packages in your cart.')
            ->assertSessionMissing('promo_code');

        $this->addPackageWithDetails($eligiblePackage->slug, 1);
        $this->post(route('cart.promo'), ['code' => 'ASIA500'])
            ->assertSessionHas('promo_code', 'ASIA500');
        $this->get(route('cart'))
            ->assertOk()
            ->assertSee('ASIA500, RM 500')
            ->assertSee('− RM 500');
        $this->get(route('checkout'))
            ->assertOk()
            ->assertSee('ASIA500, RM 500')
            ->assertSee('href="'.route('terms').'#terms-cancellation"', false);
    }

    public function test_unavailable_departure_blocks_checkout_until_cart_date_is_updated(): void
    {
        $package = TravelPackage::where('slug', 'sakura-rail')->firstOrFail();
        $this->withSession(['cart' => [[
            'uid' => 'stale-date',
            'package_id' => $package->id,
            'departure_date' => '2020-01-01',
            'guests' => 1,
            'addons' => [],
        ]]]);
        $this->get(route('cart'))->assertOk()->assertSee('needs a new date before you can check out');
        $this->get(route('checkout'))->assertOk()->assertSee('Update your cart')->assertSee('disabled', false);
        $this->post(route('checkout.place'), $this->checkoutInput())
            ->assertRedirect(route('cart'))
            ->assertSessionHas('error', 'Update your cart before checking out.');
        $this->assertSame(0, Booking::count());
    }

    public function test_plain_cart_item_can_be_updated_with_validated_fields(): void
    {
        $package = $this->addPackage('sakura-rail', 1);
        $uid = session('cart.0.uid');

        $this->patch(route('cart.update', ['uid' => $uid]), [
            'departure_date' => $package->departures[1],
            'pax' => 3,
            'addons' => ['insurance'],
        ])->assertRedirect(route('cart'));

        $this->assertSame($package->departures[1], session('cart.0.departure_date'));
        $this->assertSame(3, session('cart.0.guests'));
        $this->assertSame(['insurance'], session('cart.0.addons'));
        $this->assertGreaterThan(0, Pricing::itemTotal([
            ...session('cart.0'),
            'package' => $package,
        ]));
    }

    public function test_unknown_cart_uid_cannot_update_or_remove_another_item(): void
    {
        $this->addPackage('sakura-rail', 1);

        $this->patch(route('cart.update', ['uid' => 'unknown-item']), [
            'departure_date' => '2027-04-03',
            'pax' => 4,
        ])->assertNotFound();
        $this->delete(route('cart.remove', ['uid' => 'unknown-item']))->assertRedirect(route('cart'));

        $this->assertCount(1, session('cart'));
        $this->assertSame(1, session('cart.0.guests'));
    }

    public function test_checkout_validation_never_flashes_card_fields(): void
    {
        $this->addPackageWithDetails('sakura-rail', 1);
        $input = $this->checkoutInput();
        unset($input['terms']);

        $this->from(route('checkout'))->post(route('checkout.place'), $input)
            ->assertRedirect(route('checkout'))
            ->assertSessionHasErrors(['terms'])
            ->assertSessionMissing('_old_input.card_number')
            ->assertSessionMissing('_old_input.card_expiry')
            ->assertSessionMissing('_old_input.card_cvc');
    }

    public function test_checkout_redirects_quick_add_items_to_the_booking_wizard(): void
    {
        $package = $this->addPackage('sakura-rail', 1);
        $item = session('cart.0');
        $bookingUrl = route('booking', [
            'id' => $package->slug,
            'date' => $item['departure_date'],
            'cart_item' => $item['uid'],
        ]);

        $this->get(route('checkout'))
            ->assertRedirect($bookingUrl)
            ->assertSessionHas('success', 'Complete traveller details and upload the required documents for each trip before checkout.');
        $this->post(route('checkout.place'), $this->checkoutInput())
            ->assertRedirect($bookingUrl);
    }

    public function test_each_quick_added_package_in_a_multi_package_cart_must_be_completed(): void
    {
        $this->addPackageWithDetails('sakura-rail', 1);
        $incompletePackage = $this->addPackage('amalfi-escape', 1);
        $incompleteItem = collect(session('cart'))->last();

        $this->get(route('checkout'))->assertRedirect(route('booking', [
            'id' => $incompletePackage->slug,
            'date' => $incompleteItem['departure_date'],
            'cart_item' => $incompleteItem['uid'],
        ]));
    }

    public function test_unconfigured_production_gateway_records_pending_payment(): void
    {
        $this->addPackageWithDetails('sakura-rail', 1);
        $this->app->offsetUnset(PaymentGateway::class);

        $this->post(route('checkout.place'), $this->checkoutInput())
            ->assertRedirect(route('confirmation'));

        $booking = Booking::firstOrFail();
        $this->assertSame('Pending payment', $booking->status);
        $path = $booking->guest_details['travellers'][0]['document_path'];
        $this->assertStringContainsString('Asia/Japan/sakura-rail_2027-03-27/'.$booking->reference, $path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_quick_add_item_can_be_completed_in_the_booking_wizard(): void
    {
        $this->addPackage('sakura-rail', 1);
        $uid = session('cart.0.uid');
        $this->get(route('booking', [
            'id' => 'sakura-rail',
            'date' => session('cart.0.departure_date'),
            'cart_item' => $uid,
        ]))
            ->assertOk()
            ->assertSee('data-booking-config')
            ->assertSee('"editing":true', false);
    }

    public function test_expired_staged_and_booked_documents_are_removed_by_cleanup_command(): void
    {
        $pendingPath = 'Clients/Asia/Japan/sakura-rail_2027-03-27/.pending/'.str_repeat('a', 36).'/traveller-01_'.str_repeat('b', 36).'.jpg';
        Storage::disk('local')->put($pendingPath, 'old');
        touch(Storage::disk('local')->path($pendingPath), now()->subDays(31)->timestamp);

        $bookedPath = 'Clients/Asia/Japan/sakura-rail_2027-03-27/TWY-CLEANUP-01/traveller-01_'.str_repeat('c', 36).'.jpg';
        Storage::disk('local')->put($bookedPath, 'old');
        $booking = Booking::create([
            'reference' => 'TWY-CLEANUP-01',
            'order_reference' => 'TWY-54321',
            'package_id' => TravelPackage::where('slug', 'sakura-rail')->value('id'),
            'departure_date' => today()->subDays(91),
            'guests' => 1,
            'guest_details' => ['travellers' => [['name' => 'Taylor Traveller', 'document_path' => $bookedPath]]],
            'subtotal' => 1000,
            'taxes' => 70,
            'total' => 1070,
            'status' => 'Confirmed',
        ]);

        $this->artisan('traveller-documents:cleanup')->assertExitCode(0);

        Storage::disk('local')->assertMissing($pendingPath);
        Storage::disk('local')->assertMissing($bookedPath);
        $this->assertArrayNotHasKey('document_path', $booking->fresh()->guest_details['travellers'][0]);
    }

    public function test_multiple_cart_items_share_one_order_reference_and_match_pricing_totals(): void
    {
        $first = $this->addPackageWithDetails('sakura-rail', 2);
        $this->addPackageWithDetails('amalfi-escape', 1);
        $items = collect(session('cart'))->map(function (array $item) {
            return [...$item, 'package' => TravelPackage::findOrFail($item['package_id'])];
        });
        $expected = Pricing::cartTotals($items, null);

        $this->post(route('checkout.place'), $this->checkoutInput())
            ->assertRedirect(route('confirmation'));

        $bookings = Booking::orderBy('id')->get();
        $this->assertCount(2, $bookings);
        $this->assertCount(1, $bookings->pluck('order_reference')->unique());
        $this->assertCount(2, $bookings->pluck('reference')->unique());
        $this->assertEqualsWithDelta($expected['total'], $bookings->sum('total'), 0.01);
        $this->assertMatchesRegularExpression('/^TWY-\d{5}$/', $bookings->first()->order_reference);
        $this->assertTrue($first->exists);
    }

    public function test_confirmation_and_invoice_are_limited_to_the_order_session_or_owner(): void
    {
        $this->addPackageWithDetails('sakura-rail', 1);
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('checkout.place'), $this->checkoutInput())
            ->assertRedirect(route('confirmation'));
        $booking = Booking::firstOrFail();
        $reference = $booking->order_reference;

        $this->get(route('confirmation'))->assertOk()->assertSee($reference);
        $this->get(route('confirmation.invoice', ['reference' => $reference]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        auth()->logout();
        session()->forget('confirmation_order_reference');
        $this->get(route('confirmation'))
            ->assertOk()
            ->assertSee('No recent booking.')
            ->assertDontSee($reference);
        $this->get(route('confirmation.invoice', ['reference' => $reference]))->assertNotFound();

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)->get(route('confirmation'))
            ->assertOk()
            ->assertSee('No recent booking.')
            ->assertDontSee($reference);
    }

    public function test_authenticated_checkout_records_the_account_owner_and_confirmation(): void
    {
        $this->addPackageWithDetails('sakura-rail', 1);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('checkout.place'), $this->checkoutInput())
            ->assertRedirect(route('confirmation'));

        $booking = Booking::firstOrFail();
        $this->assertSame($user->id, $booking->user_id);
        $this->get(route('confirmation'))->assertOk()->assertSee($booking->order_reference);
    }

    private function addPackage(string $slug, int $guests): TravelPackage
    {
        $package = TravelPackage::where('slug', $slug)->firstOrFail();
        $this->post(route('cart.add', ['package' => $package->id]), [
            'departure_date' => $package->departures[0],
            'guests' => $guests,
        ])->assertRedirect(route('cart'));

        return $package;
    }

    private function addPackageWithDetails(string $slug, int $guests): TravelPackage
    {
        $package = $this->addPackage($slug, $guests);
        $item = collect(session('cart'))->last();
        $date = $item['departure_date'];
        $kinds = Pricing::guestKinds($package);
        $counts = array_fill_keys(array_column($kinds, 'id'), 0);
        $singleKind = collect($kinds)->first(fn (array $kind): bool => $kind['id'] === 'single');
        $adultKind = collect($kinds)->first(fn (array $kind): bool => ($kind['role'] ?? 'adult') === 'adult' && $kind['id'] !== 'single');
        $counts[$guests === 1 && $singleKind ? 'single' : $adultKind['id']] = $guests;
        $roomCount = max(1, (int) ceil($guests / Pricing::MAX_PER_ROOM));
        $isCruise = $package->type === TravelPackage::TYPE_CRUISE;
        $rooms = $isCruise
            ? \App\Models\Ship::where('name', $package->ship)->firstOrFail()->cabins()->get()->first()->type
            : null;
        $travellers = [];
        $documents = [];
        for ($index = 0; $index < $guests; $index++) {
            $travellers[] = [
                'name' => $index === 0 ? 'Taylor Traveller' : 'Guest Traveller '.$index,
                'dob' => '1990-04-12',
                'nation' => 'Malaysian',
                'passport' => 'A1234567'.($index + 1),
                'passportExpiry' => Carbon::parse($date)->addMonthsNoOverflow(12)->toDateString(),
                'email' => $index === 0 ? 'taylor@example.test' : '',
                'phone' => $index === 0 ? '+60123456789' : '',
            ];
            $documents[] = UploadedFile::fake()->image('passport-'.$index.'.jpg', 10, 10);
        }
        $adultCount = collect($kinds)->filter(fn (array $kind): bool => ($kind['role'] ?? 'adult') === 'adult')
            ->sum(fn (array $kind): int => (int) $counts[$kind['id']]);
        $childCount = collect($kinds)->filter(fn (array $kind): bool => ($kind['role'] ?? 'adult') === 'child')
            ->sum(fn (array $kind): int => (int) $counts[$kind['id']]);
        $payload = [
            'action' => 'proceed',
            'kind' => $isCruise ? 'cruise' : 'fh',
            'counts' => $counts,
            'adults' => $adultCount,
            'children' => $childCount,
            'rooms' => $roomCount,
            'room_types' => $isCruise ? array_fill(0, $roomCount, $rooms) : [],
            'addons' => [],
            'guests' => $travellers,
            'documents' => $documents,
            'requests' => 'Please note any special requirements.',
        ];

        $this->post(route('booking.submit', [
            'id' => $slug,
            'date' => $date,
            'cart_item' => $item['uid'],
        ]), $payload)->assertRedirect(route('checkout'));

        return $package;
    }

    private function checkoutInput(): array
    {
        return [
            'name' => 'Taylor Traveller',
            'email' => 'taylor@example.test',
            'phone' => '+60123456789',
            'contact_address' => '12 Jalan Example, Kuala Lumpur',
            'payment_method' => 'card',
            'billing_same' => '1',
            'terms' => '1',
            'privacy' => '1',
            'cancellation' => '1',
            'card_number' => '4242424242424242',
            'card_name' => 'Taylor Traveller',
            'card_expiry' => '08/29',
            'card_cvc' => '123',
        ];
    }
}
