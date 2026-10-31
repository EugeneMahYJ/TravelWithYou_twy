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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
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
        config(['mail.default' => 'log']);
    }

    public function test_valid_card_checkout_uses_pricing_and_keeps_card_data_out_of_storage_and_logs(): void
    {
        Log::spy();
        $package = $this->addPackage('sakura-rail', 2);
        $item = session('cart.0');
        $this->post(route('cart.promo'), ['code' => 'WELCOME10'])
            ->assertSessionHas('promo_code', 'WELCOME10');
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
        $this->addPackage('sakura-rail', 1);
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
        $this->addPackage('kyoto-retreat', 1);
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

        $this->addPackage($eligiblePackage->slug, 1);
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
        $this->addPackage('sakura-rail', 1);
        $input = $this->checkoutInput();
        unset($input['terms']);

        $this->from(route('checkout'))->post(route('checkout.place'), $input)
            ->assertRedirect(route('checkout'))
            ->assertSessionHasErrors(['terms'])
            ->assertSessionMissing('_old_input.card_number')
            ->assertSessionMissing('_old_input.card_expiry')
            ->assertSessionMissing('_old_input.card_cvc');
    }

    public function test_checkout_rejects_passports_expiring_within_six_months_of_departure(): void
    {
        $package = $this->addPackage('sakura-rail', 1);
        $input = $this->checkoutInput();
        $input['passport_expiry'] = Carbon::parse($package->departures[0])
            ->addMonthsNoOverflow(5)
            ->toDateString();

        $this->from(route('checkout'))->post(route('checkout.place'), $input)
            ->assertRedirect(route('checkout'))
            ->assertSessionHasErrors(['passport_expiry'])
            ->assertSessionHas('errors', function ($errors) use ($package): bool {
                $minimumDate = Carbon::parse($package->departures[0])
                    ->addMonthsNoOverflow(6)
                    ->format('j M Y');

                return str_contains($errors->first('passport_expiry'), 'on or after '.$minimumDate);
            });
    }

    public function test_unconfigured_production_gateway_records_pending_payment(): void
    {
        $this->addPackage('sakura-rail', 1);
        $this->app->offsetUnset(PaymentGateway::class);

        $this->post(route('checkout.place'), $this->checkoutInput())
            ->assertRedirect(route('confirmation'));

        $this->assertSame('Pending payment', Booking::firstOrFail()->status);
    }

    public function test_multiple_cart_items_share_one_order_reference_and_match_pricing_totals(): void
    {
        $first = $this->addPackage('sakura-rail', 2);
        $this->addPackage('amalfi-escape', 1);
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
        $this->addPackage('sakura-rail', 1);
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
        $this->addPackage('sakura-rail', 1);
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

    private function checkoutInput(): array
    {
        return [
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
        ];
    }
}
