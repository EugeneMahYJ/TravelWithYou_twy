<?php

namespace Tests\Feature;

use App\Models\AdminNotificationRead;
use App\Models\Enquiry;
use App\Models\User;
use App\Support\AdminNotifications;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EnquiryConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_owner_and_admin_can_continue_a_thread_in_message_order(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $enquiry = $this->enquiry($owner, 'Replied');

        $this->travelTo(Carbon::parse('2026-11-01 10:00:00'));
        $this->actingAs($owner)
            ->post(route('account.enquiries.reply', $enquiry), ['message' => 'First user follow-up'])
            ->assertRedirect(route('account', ['tab' => 'inbox']).'#account-enquiry-'.$enquiry->id);

        $this->assertDatabaseHas('enquiries', ['id' => $enquiry->id, 'status' => 'New']);
        $this->assertDatabaseHas('enquiry_messages', [
            'enquiry_id' => $enquiry->id,
            'sender_id' => $owner->id,
            'body' => 'First user follow-up',
        ]);

        $admin = $this->admin();
        $this->travelTo(Carbon::parse('2026-11-01 10:01:00'));
        $this->actingAs($admin)->withSession($this->verifiedSession())
            ->post(route('admin.enquiries.reply', $enquiry), [
                'subject' => 'Re: Booking question',
                'message' => 'Admin response',
            ])->assertRedirect(route('admin.enquiries.show', $enquiry));

        $this->travelTo(Carbon::parse('2026-11-01 10:02:00'));
        $this->actingAs($owner)
            ->post(route('account.enquiries.reply', $enquiry), ['message' => 'Second user follow-up'])
            ->assertRedirect(route('account', ['tab' => 'inbox']).'#account-enquiry-'.$enquiry->id);

        $this->assertDatabaseHas('enquiries', ['id' => $enquiry->id, 'status' => 'New']);

        $this->actingAs($admin)->withSession($this->verifiedSession())
            ->get(route('admin.enquiries.show', $enquiry))
            ->assertOk()
            ->assertSeeInOrder(['First user follow-up', 'Admin response', 'Second user follow-up']);
        $this->get(route('admin.enquiries', ['status' => 'Unanswered']))
            ->assertOk()
            ->assertSee('Latest message from')
            ->assertSee('Second user follow-up');

        $this->actingAs($owner)
            ->get(route('account', ['tab' => 'inbox']))
            ->assertOk()
            ->assertSeeInOrder(['Original enquiry message', 'First user follow-up', 'Admin response', 'Second user follow-up'])
            ->assertSee('Reply to this thread')
            ->assertSee('account-enquiry-reply-compose');
    }

    public function test_enquiry_reply_requires_authentication_and_owner_access(): void
    {
        $owner = User::factory()->create();
        $anotherUser = User::factory()->create();
        $enquiry = $this->enquiry($owner);

        $this->post(route('account.enquiries.reply', $enquiry), ['message' => 'Not signed in'])
            ->assertRedirect(route('login'));
        $this->actingAs($anotherUser)
            ->post(route('account.enquiries.reply', $enquiry), ['message' => 'Not the owner'])
            ->assertNotFound();

        $this->assertDatabaseCount('enquiry_messages', 0);
    }

    public function test_guest_enquiries_use_email_link_without_in_site_reply_or_history(): void
    {
        $admin = $this->admin();
        $enquiry = Enquiry::create([
            'name' => 'Guest Traveller',
            'email' => 'guest@example.test',
            'subject' => 'Guest booking question',
            'message' => 'Original enquiry message',
        ]);

        $this->actingAs($admin)->withSession($this->verifiedSession())
            ->get(route('admin.enquiries.show', $enquiry))
            ->assertOk()
            ->assertSee('Reply by email')
            ->assertSee('mailto:guest@example.test?subject=Re%3A%20Guest%20booking%20question')
            ->assertSee('Mark replied')
            ->assertDontSee('Write a reply')
            ->assertDontSee('Conversation history');

        $this->post(route('admin.enquiries.reply', $enquiry), [
            'subject' => 'Re: Guest booking question',
            'message' => 'This should be handled outside the site.',
        ])->assertNotFound();
    }

    public function test_guest_enquiry_forms_explain_email_replies_without_requiring_an_account(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('No account is needed.')
            ->assertSee('reply to the email address you provide within one working day');

        $this->get(route('mice'))
            ->assertOk()
            ->assertSee('No account is needed.')
            ->assertSee('reply to your email within one working day');
    }

    public function test_user_reply_reopens_a_read_admin_attention_notification(): void
    {
        $owner = User::factory()->create();
        $admin = $this->admin();
        $enquiry = $this->enquiry($owner, 'New');
        $initial = collect((new AdminNotifications)->itemsForAdmin($admin->id))
            ->firstWhere('key', 'enquiries.unanswered');

        AdminNotificationRead::create([
            'user_id' => $admin->id,
            'key' => 'enquiries.unanswered',
            'fingerprint' => $initial['fingerprint'],
            'read_at' => now(),
        ]);
        $enquiry->update(['status' => 'Replied']);

        $this->actingAs($owner)
            ->post(route('account.enquiries.reply', $enquiry), ['message' => 'Please follow up'])
            ->assertRedirect();

        $reopened = collect((new AdminNotifications)->itemsForAdmin($admin->id))
            ->firstWhere('key', 'enquiries.unanswered');
        $this->assertFalse($reopened['is_read']);
        $this->assertSame(1, $reopened['count']);
        $this->assertNotSame($initial['fingerprint'], $reopened['fingerprint']);
    }

    private function enquiry(User $owner, string $status = 'New'): Enquiry
    {
        return Enquiry::create([
            'user_id' => $owner->id,
            'name' => $owner->name,
            'email' => $owner->email,
            'subject' => 'Booking question',
            'message' => 'Original enquiry message',
            'status' => $status,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['email' => 'admin@example.test']);
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
