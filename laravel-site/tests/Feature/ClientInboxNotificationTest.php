<?php

namespace Tests\Feature;

use App\Mail\EnquiryReplyMail;
use App\Models\Booking;
use App\Models\Enquiry;
use App\Models\TravelDeal;
use App\Models\TravelPackage;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\ClientNotifications;
use App\Support\Totp;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FaqSeeder;
use Database\Seeders\GuideSeeder;
use Database\Seeders\PromoCodeSeeder;
use Database\Seeders\ShipSeeder;
use Database\Seeders\TravelDealSeeder;
use Database\Seeders\TravelPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientInboxNotificationTest extends TestCase
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

    public function test_signed_in_mice_enquiries_receive_admin_replies_in_the_account_inbox_and_by_email(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->actingAs($user)->from(route('mice'))->post(route('mice.submit'), [
            'company_website' => '',
            'company' => 'Northstar Travel Group',
            'travellers' => '24',
            'company_address' => '12 Jalan Example, Kuala Lumpur',
            'honorific' => 'Ms.',
            'name' => 'Alex Tan',
            'mobile' => '+60 12-345 6789',
            'email' => 'alex@example.test',
            'destination' => 'Kyoto, Japan',
            'budget_basis' => 'group',
            'budget_from' => '15000',
            'budget_to' => '30000',
        ])->assertRedirect(route('mice'));

        $enquiry = Enquiry::sole();
        $this->assertSame($user->id, $enquiry->user_id);

        $admin = $this->admin();
        $this->actingAs($admin, 'admin')->withSession($this->verifiedSession())
            ->post(route('admin.enquiries.reply', $enquiry), [
                'subject' => 'Re: MICE enquiry',
                'message' => 'We would be happy to help plan your group trip.',
            ])->assertRedirect(route('admin.enquiries.show', $enquiry))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'enquiry_reply',
            'url' => route('account', ['tab' => 'inbox'], false).'#account-enquiry-'.$enquiry->id,
        ]);
        Mail::assertSent(EnquiryReplyMail::class, fn (EnquiryReplyMail $mail): bool => $mail->hasTo('alex@example.test'));

        $this->actingAs($user)->get(route('account', ['tab' => 'inbox']))
            ->assertOk()
            ->assertSee('Northstar Travel Group')
            ->assertSee('We would be happy to help plan your group trip.')
            ->assertSee('View all notifications');
    }

    public function test_contact_enquiries_are_linked_only_when_the_sender_is_signed_in(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->from(route('contact'))->post(route('contact.send'), [
            'name' => $user->name,
            'email' => $user->email,
            'subject' => 'Booking enquiry',
            'message' => 'Please send details about my upcoming booking.',
        ])->assertRedirect(route('contact'));

        $this->assertSame($user->id, Enquiry::sole()->user_id);
    }

    public function test_notification_links_are_owner_scoped_and_opening_marks_them_read(): void
    {
        $owner = User::factory()->create();
        $anotherUser = User::factory()->create();
        $notification = UserNotification::create([
            'user_id' => $owner->id,
            'type' => 'new_deal',
            'title' => 'A new deal is available',
            'message' => 'A new offer is ready to explore.',
            'url' => route('deals', [], false),
        ]);

        $this->actingAs($anotherUser)
            ->get(route('account.notifications.open', $notification->id))
            ->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);

        $this->actingAs($owner)
            ->get(route('account.notifications.open', $notification->id))
            ->assertRedirect(route('deals'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_authenticated_header_uses_a_native_notification_disclosure(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertSee('<details class="user-notification-menu" data-user-notification-menu>', false)
            ->assertSee('<summary class="header-icon user-notification-toggle"', false)
            ->assertSee('data-user-notification-panel', false);
    }

    public function test_mark_all_read_only_updates_the_signed_in_users_notifications(): void
    {
        $owner = User::factory()->create();
        $anotherUser = User::factory()->create();
        $owned = $this->notification($owner);
        $unowned = $this->notification($anotherUser);

        $this->actingAs($owner)
            ->post(route('account.notifications.read-all'))
            ->assertRedirect(route('account', ['tab' => 'inbox']));

        $this->assertNotNull($owned->fresh()->read_at);
        $this->assertNull($unowned->fresh()->read_at);
    }

    public function test_deal_notifications_respect_user_preferences_and_exclude_admins(): void
    {
        $subscribed = User::factory()->create(['notification_preferences' => ['deals' => true]]);
        $unsubscribed = User::factory()->create(['notification_preferences' => ['deals' => false]]);
        $admin = $this->admin();
        $deal = TravelDeal::create([
            'name' => 'Kyoto spring offer',
            'slug' => 'kyoto-spring-offer-test',
            'promo_code' => 'KYOTOTEST1',
            'description' => 'A seasonal offer.',
            'discount_percent' => 15,
            'type' => 'Limited-time',
            'package_id' => TravelPackage::firstOrFail()->id,
            'active' => true,
        ]);

        app(ClientNotifications::class)->dealPublished($deal);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $subscribed->id,
            'type' => 'new_deal',
        ]);
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $unsubscribed->id]);
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $admin->id]);
    }

    public function test_admin_booking_status_changes_notify_the_booking_owner(): void
    {
        $user = User::factory()->create();
        $booking = Booking::create([
            'reference' => 'TWY-ALERT-01',
            'user_id' => $user->id,
            'package_id' => TravelPackage::firstOrFail()->id,
            'departure_date' => Carbon::now()->addMonths(2)->toDateString(),
            'guests' => 1,
            'guest_details' => ['name' => $user->name, 'email' => $user->email],
            'subtotal' => 1000,
            'taxes' => 70,
            'total' => 1070,
            'status' => 'Pending payment',
        ]);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->withSession($this->verifiedSession())
            ->patch(route('admin.status', ['resource' => 'bookings', 'id' => $booking->id]), [
                'status' => 'Confirmed',
            ])->assertRedirect();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'payment_updates',
            'title' => 'Booking confirmed',
        ]);
    }

    public function test_failed_payment_notifies_signed_in_user_and_keeps_retry_target(): void
    {
        Storage::fake('local');
        $package = TravelPackage::where('slug', 'sakura-rail')->firstOrFail();
        $this->post(route('cart.add', $package), [
            'departure_date' => $package->departures[0],
            'guests' => 1,
        ]);
        $cart = session('cart');
        $item = $cart[0];
        $documentPath = 'Clients/test-package/test-departure/.pending/'.$item['uid'].'/traveller-1.jpg';
        Storage::disk('local')->put($documentPath, 'test document');
        $cart[0]['detail'] = [
            'kind' => 'fh',
            'counts' => ['adult' => 0, 'single' => 1],
            'rooms' => 1,
            'guests' => [[
                'name' => 'Taylor Traveller',
                'dob' => '1990-01-01',
                'nation' => 'Malaysian',
                'passport' => Crypt::encryptString('A1234567'),
                'passportExpiry' => Carbon::parse($package->departures[0])->addMonthsNoOverflow(7)->toDateString(),
                'email' => 'taylor@example.test',
                'phone' => '+60123456789',
            ]],
            'document_paths' => [$documentPath],
        ];
        $this->withSession(['cart' => $cart]);
        $input = [
            'name' => 'Taylor Traveller',
            'email' => 'taylor@example.test',
            'phone' => '+60123456789',
            'contact_address' => '12 Jalan Example',
            'payment_method' => 'card',
            'card_number' => '4242424242420000',
            'card_expiry' => '08/29',
            'card_cvc' => '123',
            'card_name' => 'Taylor Traveller',
            'billing_same' => '1',
            'terms' => '1',
            'privacy' => '1',
            'cancellation' => '1',
        ];
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('checkout.place'), $input)
            ->assertRedirect(route('payment.failed'));

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'payment_updates',
            'title' => 'Payment failed',
            'url' => route('checkout', [], false),
        ]);
    }

    private function notification(User $user): UserNotification
    {
        return UserNotification::create([
            'user_id' => $user->id,
            'type' => 'security',
            'title' => 'Account changed',
            'message' => 'A security update occurred.',
            'url' => route('account', ['tab' => 'security'], false),
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
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
