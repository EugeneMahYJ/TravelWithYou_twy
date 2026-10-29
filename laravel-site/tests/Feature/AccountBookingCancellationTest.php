<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\TravelPackage;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FaqSeeder;
use Database\Seeders\GuideSeeder;
use Database\Seeders\PromoCodeSeeder;
use Database\Seeders\ShipSeeder;
use Database\Seeders\TravelDealSeeder;
use Database\Seeders\TravelPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AccountBookingCancellationTest extends TestCase
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

    public function test_only_pending_payment_bookings_can_be_cancelled_and_the_audit_is_visible_to_admins(): void
    {
        $owner = User::factory()->create();
        $pending = $this->booking($owner, 'TWY-CANCEL-01', 'Pending payment');
        $confirmed = $this->booking($owner, 'TWY-CANCEL-02', 'Confirmed');

        $account = $this->actingAs($owner)->get(route('account', ['tab' => 'bookings']))
            ->assertOk()
            ->assertSee('Cancel Booking');
        $this->assertSame(1, substr_count($account->getContent(), 'Cancel Booking'));

        $this->from(route('account', ['tab' => 'bookings']))
            ->post(route('account.bookings.cancel', $confirmed))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only payment pending bookings can be cancelled.');
        $this->assertSame('Confirmed', $confirmed->fresh()->status);
        $this->assertNull($confirmed->fresh()->cancelled_at);

        $this->post(route('account.bookings.cancel', $pending))
            ->assertRedirect()
            ->assertSessionHas('success', 'Booking cancelled.');

        $pending->refresh();
        $this->assertSame('Cancelled', $pending->status);
        $this->assertNotNull($pending->cancelled_at);
        $this->assertSame($owner->id, $pending->cancelled_by);

        $this->get(route('account', ['tab' => 'bookings']))
            ->assertOk()
            ->assertSee('Cancelled')
            ->assertDontSee('Cancel Booking');

        $admin = User::factory()->create(['email' => 'booking-admin@example.test']);
        $admin->forceFill(['is_admin' => true, 'mfa_enabled' => true, 'mfa_secret' => 'test-secret'])->save();
        $this->actingAs($admin)->withSession([
            'admin_mfa_verified' => true,
            'admin_last_activity' => time(),
        ])->get(route('admin.bookings'))
            ->assertOk()
            ->assertSee('TWY-CANCEL-01')
            ->assertSee('Cancelled')
            ->assertSee($owner->name)
            ->assertSee($pending->cancelled_at->format('j M Y, H:i'));
    }

    public function test_users_cannot_cancel_another_users_booking(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $booking = $this->booking($owner, 'TWY-CANCEL-03', 'Pending payment');

        $this->actingAs($otherUser)
            ->post(route('account.bookings.cancel', $booking))
            ->assertForbidden();

        $this->assertSame('Pending payment', $booking->fresh()->status);
        $this->assertNull($booking->fresh()->cancelled_at);
    }

    private function booking(User $owner, string $reference, string $status): Booking
    {
        return Booking::create([
            'reference' => $reference,
            'user_id' => $owner->id,
            'package_id' => TravelPackage::firstOrFail()->id,
            'departure_date' => Carbon::now()->addMonths(2)->toDateString(),
            'guests' => 1,
            'guest_details' => ['name' => $owner->name, 'email' => $owner->email],
            'subtotal' => 1000,
            'taxes' => 70,
            'total' => 1070,
            'status' => $status,
        ]);
    }
}
