<?php

namespace Tests\Feature;

use App\Mail\EnquiryReplyMail;
use App\Models\Enquiry;
use App\Models\PromoCode;
use App\Models\TravelDeal;
use App\Models\TravelPackage;
use App\Models\User;
use App\Support\Totp;
use Database\Seeders\CountrySeeder;
use Database\Seeders\TravelDealSeeder;
use Database\Seeders\TravelPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminDealsAndEnquiriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CountrySeeder::class, TravelPackageSeeder::class, TravelDealSeeder::class]);
    }

    public function test_deals_list_has_one_new_deal_action_above_search_and_active_deals_are_editable(): void
    {
        $admin = $this->admin();
        $deal = TravelDeal::firstOrFail();
        $deal->update(['active' => true]);
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $list = $this->get(route('admin.deals'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($list, '>New deal</a>'));
        $this->assertLessThan(strpos($list, 'Search deals'), strpos($list, '>New deal</a>'));
        $this->assertStringContainsString(route('admin.deals.create'), $list);
        $this->assertStringContainsString(route('admin.deals.edit', $deal), $list);
        $this->assertStringNotContainsString('<details class="admin-new-form"', $list);

        $slug = $deal->slug;
        $end = now()->addDays(20)->startOfMinute();
        $this->put(route('admin.deals.update', $deal), [
            'name' => $deal->name,
            'type' => 'Early bird',
            'package_id' => $deal->package_id,
            'discount_percent' => 37,
            'terms' => 'Updated terms for travellers.',
            'expires_at' => $end->format('Y-m-d\TH:i'),
            'active' => '1',
        ])->assertRedirect(route('admin.deals'))->assertSessionHas('success', 'Deal saved');

        $this->assertSame($slug, $deal->fresh()->slug);
        $this->assertSame(37, (int) $deal->fresh()->discount_percent);
        $this->assertSame('Updated terms for travellers.', $deal->fresh()->description);
        $this->get(route('deals'))->assertOk()->assertSee('37% off')->assertSee('Updated terms for travellers.');
    }

    public function test_deal_create_validates_values_and_allows_an_unchanged_past_end_date_on_edit(): void
    {
        $admin = $this->admin();
        $package = TravelPackage::firstOrFail();
        $deal = TravelDeal::firstOrFail();
        $past = now()->subDays(3)->startOfMinute();
        $deal->update(['expires_at' => $past]);
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $this->get(route('admin.deals.create'))->assertOk()->assertSee('New deal')->assertSee('Save deal');
        $this->post(route('admin.deals.store'), [
            'name' => 'A valid offer',
            'type' => 'Limited-time',
            'package_id' => $package->id,
            'discount_percent' => 25,
            'terms' => 'Valid offer terms.',
            'expires_at' => now()->addDays(5)->format('Y-m-d\TH:i'),
            'active' => '1',
        ])->assertRedirect(route('admin.deals'));
        $created = TravelDeal::where('name', 'A valid offer')->firstOrFail();
        $this->assertNotEmpty($created->slug);

        $this->from(route('admin.deals.create'))->post(route('admin.deals.store'), [
            'name' => 'Invalid offer',
            'type' => 'Invalid type',
            'package_id' => $package->id,
            'discount_percent' => 91,
            'terms' => 'Invalid offer terms.',
            'expires_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ])->assertRedirect(route('admin.deals.create'))->assertSessionHasErrors(['type', 'discount_percent', 'expires_at']);

        $this->put(route('admin.deals.update', $deal), [
            'name' => $deal->name,
            'type' => $deal->type,
            'package_id' => $deal->package_id,
            'discount_percent' => 20,
            'terms' => 'Past end date remains unchanged.',
            'expires_at' => $past->format('Y-m-d\TH:i'),
            'active' => '1',
        ])->assertRedirect(route('admin.deals'))->assertSessionHas('success', 'Deal saved');
    }

    public function test_admin_can_create_universal_and_package_specific_promo_codes(): void
    {
        $admin = $this->admin();
        $package = TravelPackage::where('slug', 'sakura-rail')->firstOrFail();
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $this->get(route('admin.deals'))->assertOk()
            ->assertSee('New Promo')
            ->assertSee(route('admin.promos.create'));
        $this->get(route('admin.promos.create'))
            ->assertOk()
            ->assertSee('All packages, including future packages')
            ->assertSee('Select all packages')
            ->assertSee('Fixed amount (RM)');

        $this->post(route('admin.promos.store'), [
            'code' => 'asia',
            'discount_type' => 'fixed',
            'discount_value' => '500',
            'all_packages' => '0',
            'package_ids' => [$package->id],
            'terms' => 'Asia trips only.',
            'starts_at' => null,
            'expires_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'active' => '1',
        ])->assertRedirect(route('admin.deals'))->assertSessionHas('success', 'Promo code saved.');

        $asia = PromoCode::where('code', 'ASIA')->firstOrFail();
        $this->assertSame('fixed', $asia->discount_type);
        $this->assertEquals(500, $asia->discount_amount);
        $this->assertFalse($asia->all_packages);
        $this->assertSame([$package->id], $asia->packages()->pluck('travel_packages.id')->all());
        $this->get(route('admin.deals'))->assertOk()->assertSee('ASIA')->assertSee('RM 500');
        $this->put(route('admin.promos.update', $asia), [
            'code' => 'ASIA',
            'discount_type' => 'percent',
            'discount_value' => '25',
            'all_packages' => '1',
            'terms' => 'Updated universal terms.',
            'active' => '1',
        ])->assertRedirect(route('admin.deals'));
        $this->assertTrue($asia->fresh()->all_packages);
        $this->assertEquals(25, $asia->fresh()->discount_percent);
        $this->patch(route('admin.promos.toggle', $asia))->assertRedirect();
        $this->assertFalse($asia->fresh()->active);
        $this->delete(route('admin.promos.delete', $asia))->assertRedirect(route('admin.deals'));
        $this->assertDatabaseMissing('promo_codes', ['id' => $asia->id]);

        $this->post(route('admin.promos.store'), [
            'code' => 'welcometwy',
            'discount_type' => 'percent',
            'discount_value' => '20',
            'all_packages' => '1',
            'terms' => 'Welcome offer for every trip.',
            'expires_at' => null,
            'active' => '1',
        ])->assertRedirect(route('admin.deals'));

        $welcome = PromoCode::where('code', 'WELCOMETWY')->firstOrFail();
        $this->assertTrue($welcome->all_packages);
        $this->assertSame([], $welcome->packages()->pluck('travel_packages.id')->all());
        $this->assertEquals(20, $welcome->discount_percent);
    }

    public function test_promo_code_requires_eligible_package_selection_and_unique_codes(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $this->from(route('admin.promos.create'))->post(route('admin.promos.store'), [
            'code' => TravelDeal::firstOrFail()->promo_code,
            'discount_type' => 'percent',
            'discount_value' => '20',
            'all_packages' => '0',
            'package_ids' => [],
            'active' => '1',
        ])->assertRedirect(route('admin.promos.create'))
            ->assertSessionHasErrors(['code', 'package_ids']);

        $this->post(route('admin.promos.store'), [
            'code' => 'NEWUSER',
            'discount_type' => 'percent',
            'discount_value' => '91',
            'all_packages' => '1',
            'active' => '1',
        ])->assertSessionHasErrors(['discount_value']);
    }

    public function test_deal_editor_requires_an_admin(): void
    {
        $this->get(route('admin.deals.create'))->assertRedirect();
        $this->get(route('admin.promos.create'))->assertRedirect();
        $user = User::create(['name' => 'Traveller', 'email' => 'traveller@example.test', 'password' => 'SafePass1234']);
        $this->actingAs($user)->withSession($this->verifiedSession())
            ->get(route('admin.deals.create'))->assertForbidden();
        $this->actingAs($user)->withSession($this->verifiedSession())
            ->get(route('admin.promos.create'))->assertForbidden();
    }

    public function test_admin_can_delete_a_deal_and_it_disappears_from_the_storefront(): void
    {
        $admin = $this->admin();
        $deal = TravelDeal::firstOrFail();
        $deal->update(['active' => true, 'terms' => 'Unique deal removal marker.']);
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $this->get(route('admin.deals'))->assertOk()->assertSee(route('admin.deals.delete', $deal));
        $this->get(route('deals'))->assertOk()->assertSee('Unique deal removal marker.');

        $this->delete(route('admin.deals.delete', $deal))
            ->assertRedirect(route('admin.deals'))
            ->assertSessionHas('success', 'Deal deleted.');

        $this->assertDatabaseMissing('travel_deals', ['id' => $deal->id]);
        $this->get(route('deals'))->assertOk()->assertDontSee('Unique deal removal marker.');
    }

    public function test_only_admins_can_delete_deals(): void
    {
        $deal = TravelDeal::firstOrFail();
        $this->delete(route('admin.deals.delete', $deal))->assertRedirect();

        $user = User::create(['name' => 'Traveller', 'email' => 'deal-delete@example.test', 'password' => 'SafePass1234']);
        $this->actingAs($user)->withSession($this->verifiedSession())
            ->delete(route('admin.deals.delete', $deal))->assertForbidden();

        $this->assertDatabaseHas('travel_deals', ['id' => $deal->id]);
    }

    public function test_admin_can_send_enquiry_reply_and_subject_line_breaks_are_removed(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $enquiry = $this->enquiry();
        $this->actingAs($admin)->withSession($this->verifiedSession());

        $this->get(route('admin.enquiries.show', $enquiry))->assertOk()
            ->assertSee('Original enquiry')->assertSee('Conversation history')->assertSee('data-character-count');
        $this->post(route('admin.enquiries.reply', $enquiry), [
            'subject' => "Re: Booking question\r\nBcc: stranger@example.test",
            'message' => "Thanks for contacting us.\nWe can help.",
        ])->assertRedirect(route('admin.enquiries.show', $enquiry))
            ->assertSessionHas('success', 'Reply sent to customer@example.test');

        $this->assertDatabaseHas('enquiries', ['id' => $enquiry->id, 'status' => 'Replied']);
        $this->assertDatabaseHas('enquiry_replies', [
            'enquiry_id' => $enquiry->id,
            'admin_id' => $admin->id,
            'subject' => 'Re: Booking question Bcc: stranger@example.test',
            'status' => 'sent',
        ]);
        $this->get(route('admin.enquiries'))->assertOk()->assertSee('Last replied')->assertSee('Site Admin');
        Mail::assertSent(EnquiryReplyMail::class, function (EnquiryReplyMail $mail): bool {
            $this->assertTrue($mail->hasTo('customer@example.test'));
            $this->assertSame('Re: Booking question Bcc: stranger@example.test', $mail->envelope()->subject);
            $this->assertSame('emails.enquiry-reply-text', $mail->content()->text);
            $rendered = $mail->render();
            $this->assertStringContainsString('Thanks for contacting us.', $rendered);
            $this->assertStringContainsString('Could you help with our booking?', $rendered);

            return true;
        });
    }

    public function test_enquiry_reply_validation_and_mail_failure_preserve_unanswered_status(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $enquiry = $this->enquiry();
        $this->actingAs($admin)->withSession($this->verifiedSession());

        foreach (['', 'four'] as $message) {
            $this->post(route('admin.enquiries.reply', $enquiry), [
                'subject' => 'Re: Booking question',
                'message' => $message,
            ])->assertSessionHasErrors('message');
        }
        $this->post(route('admin.enquiries.reply', $enquiry), [
            'subject' => 'Re: Booking question',
            'message' => str_repeat('x', 5001),
        ])->assertSessionHasErrors('message');

        Mail::shouldReceive('to')->once()->with($enquiry->email)->andThrow(new \RuntimeException('smtp failed'));
        Log::shouldReceive('error')->once()->with('Admin enquiry email failed.', \Mockery::on(function (array $context): bool {
            return isset($context['exception'], $context['enquiry_reply_id']);
        }));

        $this->post(route('admin.enquiries.reply', $enquiry), [
            'subject' => 'Re: Booking question',
            'message' => 'A valid reply message.',
        ])->assertRedirect(route('admin.enquiries.show', $enquiry))
            ->assertSessionHas('error', 'The email could not be sent. Check the mail settings and try again.');

        $this->assertDatabaseHas('enquiries', ['id' => $enquiry->id, 'status' => 'New']);
        $this->assertDatabaseHas('enquiry_replies', [
            'enquiry_id' => $enquiry->id,
            'status' => 'failed',
            'error' => 'The email could not be sent.',
        ]);
    }

    public function test_enquiry_reply_is_admin_only_and_throttled(): void
    {
        $enquiry = $this->enquiry();
        $user = User::create(['name' => 'Traveller', 'email' => 'traveller@example.test', 'password' => 'SafePass1234']);
        $this->actingAs($user)->get(route('admin.enquiries.show', $enquiry))->assertForbidden();
        $this->post(route('admin.enquiries.reply', $enquiry), ['subject' => 'Re: question', 'message' => 'A valid reply.'])
            ->assertForbidden();

        Mail::fake();
        $this->actingAs($this->admin())->withSession($this->verifiedSession());
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post(route('admin.enquiries.reply', $enquiry), [
                'subject' => 'Re: question',
                'message' => 'A valid reply.',
            ])->assertRedirect(route('admin.enquiries.show', $enquiry));
        }
        $this->post(route('admin.enquiries.reply', $enquiry), [
            'subject' => 'Re: question',
            'message' => 'A valid reply.',
        ])->assertTooManyRequests();
    }

    private function admin(): User
    {
        $admin = User::create(['name' => 'Site Admin', 'email' => 'admin@example.test', 'password' => 'SafePass1234']);
        $admin->forceFill(['is_admin' => true, 'mfa_enabled' => true, 'mfa_secret' => Totp::generateSecret()])->save();

        return $admin;
    }

    private function enquiry(): Enquiry
    {
        return Enquiry::create([
            'name' => 'Casey Traveller',
            'email' => 'customer@example.test',
            'subject' => 'Booking question',
            'message' => 'Could you help with our booking?',
            'status' => 'New',
        ]);
    }

    private function verifiedSession(): array
    {
        return ['admin_mfa_verified' => true, 'admin_last_activity' => time()];
    }
}
