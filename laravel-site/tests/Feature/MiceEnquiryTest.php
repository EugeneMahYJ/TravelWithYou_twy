<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiceEnquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_mice_page_is_in_the_client_navigation_and_uses_the_site_theme(): void
    {
        $response = $this->get(route('mice'))->assertOk()
            ->assertSee('Meetings · Incentives · Conferences · Events')
            ->assertSee('Stronger connections')
            ->assertSee('Meaningful rewards')
            ->assertSee('Company Details')
            ->assertSee('Contact Person')
            ->assertSee('Destination')
            ->assertSee('Budget')
            ->assertSee(route('privacy'));

        $content = $response->getContent();
        $cruisePosition = strpos($content, '>Cruise</a>');
        $micePosition = strpos($content, '>MICE</a>');
        $aboutPosition = strpos($content, '>About</a>');
        $this->assertNotFalse($cruisePosition);
        $this->assertNotFalse($micePosition);
        $this->assertNotFalse($aboutPosition);
        $this->assertTrue($cruisePosition < $micePosition && $micePosition < $aboutPosition);
        $this->assertStringContainsString('name="travellers" type="number"', $content);
        $this->assertStringContainsString('value="group"', $content);
        $this->assertStringContainsString('value="traveller"', $content);
        $this->assertMatchesRegularExpression('/name="budget_basis"[^>]*required/', $content);
        $this->assertMatchesRegularExpression('/name="budget_from"[^>]*required/', $content);
        $this->assertMatchesRegularExpression('/name="budget_to"[^>]*required/', $content);
        $this->assertStringNotContainsString('Not sure yet</option>', $content);
        $this->get(route('privacy'))->assertOk()->assertSee('MICE Enquiries')->assertSee('MICE Planning');
    }

    public function test_mice_submission_is_saved_and_visible_in_the_admin_enquiries_pages(): void
    {
        $this->from(route('mice'))->post(route('mice.submit'), [
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
        ])->assertRedirect(route('mice'))
            ->assertSessionHas('success', 'Your MICE enquiry has been sent. We’ll be in touch within one working day.');

        $enquiry = Enquiry::sole();
        $this->assertNull($enquiry->user_id);
        $this->assertSame('MICE enquiry', $enquiry->subject);
        $this->assertSame('Alex Tan', $enquiry->name);
        $this->assertSame('+60 12-345 6789', $enquiry->phone);
        $this->assertSame('Northstar Travel Group', $enquiry->company_name);
        $this->assertSame(24, $enquiry->company_travellers);
        $this->assertSame('Kyoto, Japan', $enquiry->destination);
        $this->assertSame('group', $enquiry->budget_basis);

        $this->actingAs($this->admin(), 'admin')->withSession([
            'admin_mfa_verified' => true,
            'admin_last_activity' => time(),
        ]);
        $this->get(route('admin.enquiries'))->assertOk()
            ->assertSee('Northstar Travel Group')
            ->assertSee('24 travellers')
            ->assertSee('Kyoto, Japan');
        $this->get(route('admin.enquiries', ['q' => 'Northstar']))->assertOk()
            ->assertSee('Northstar Travel Group');
        $this->get(route('admin.enquiries.show', $enquiry))->assertOk()
            ->assertSee('Company address')
            ->assertSee('12 Jalan Example, Kuala Lumpur')
            ->assertSee('Ms. Alex Tan')
            ->assertSee('RM 15,000.00 – RM 30,000.00')
            ->assertSee('for the whole group');
    }

    public function test_mice_submission_rejects_invalid_required_fields_budget_ranges_and_honeypot(): void
    {
        $valid = [
            'company' => 'Northstar Travel Group',
            'travellers' => '24',
            'company_address' => '12 Jalan Example, Kuala Lumpur',
            'honorific' => 'Ms.',
            'name' => 'Alex Tan',
            'mobile' => '+60 12-345 6789',
            'email' => 'alex@example.test',
            'destination' => 'Kyoto, Japan',
        ];

        $this->from(route('mice'))->post(route('mice.submit'), array_merge($valid, [
            'travellers' => '0',
            'budget_basis' => 'group',
            'budget_from' => '30000',
            'budget_to' => '15000',
        ]))->assertRedirect(route('mice'))
            ->assertSessionHasErrors(['travellers', 'budget_to']);

        $this->from(route('mice'))->post(route('mice.submit'), $valid)
            ->assertRedirect(route('mice'))
            ->assertSessionHasErrors(['budget_basis', 'budget_from', 'budget_to']);

        $this->from(route('mice'))->post(route('mice.submit'), $valid + [
            'company_website' => 'spam.example',
        ])->assertRedirect(route('mice'))
            ->assertSessionHasErrors('company_website');

        $this->assertSame(0, Enquiry::count());
    }

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'MICE Test Admin',
            'email' => 'mice-admin@example.test',
            'password' => 'SafePass1234',
        ]);
        $admin->forceFill([
            'is_admin' => true,
            'mfa_enabled' => true,
            'mfa_secret' => Totp::generateSecret(),
        ])->save();

        return $admin;
    }
}
