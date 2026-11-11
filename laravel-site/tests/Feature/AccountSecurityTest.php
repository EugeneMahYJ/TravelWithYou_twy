<?php

namespace Tests\Feature;

use App\Mail\CustomerLoginCodeMail;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function twoFactorUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'password' => Hash::make('Correct-horse-9'),
            'email_verified_at' => now(),
        ], $attributes))->forceFill(['account_mfa_enabled' => true]);
    }

    public function test_two_factor_cannot_be_enabled_with_an_unverified_email(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->put(route('account.two-factor.update'), ['enabled' => '1'])
            ->assertRedirect(route('account', ['tab' => 'security']))
            ->assertSessionHas('error');

        $this->assertFalse((bool) $user->fresh()->account_mfa_enabled);
    }

    public function test_two_factor_can_be_enabled_with_a_verified_email(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->put(route('account.two-factor.update'), ['enabled' => '1'])
            ->assertSessionMissing('error');

        $this->assertTrue((bool) $user->fresh()->account_mfa_enabled);
    }

    public function test_login_with_two_factor_waits_for_the_emailed_code(): void
    {
        Mail::fake();
        $user = $this->twoFactorUser();
        $user->save();

        $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'Correct-horse-9'])
            ->assertRedirect(route('login.two-factor'));
        $this->assertGuest('web');

        $code = null;
        Mail::assertSent(CustomerLoginCodeMail::class, function (CustomerLoginCodeMail $mail) use (&$code, $user) {
            $code = $mail->code;

            return $mail->hasTo($user->email);
        });

        $this->get(route('account'))->assertRedirect(route('login'));

        $this->post(route('login.two-factor.verify'), ['code' => $code])
            ->assertRedirect(route('account'));
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_wrong_code_is_rejected_and_five_wrong_codes_lock_the_attempt(): void
    {
        Mail::fake();
        $user = $this->twoFactorUser();
        $user->save();

        $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'Correct-horse-9']);
        $code = null;
        Mail::assertSent(CustomerLoginCodeMail::class, function (CustomerLoginCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->post(route('login.two-factor.verify'), ['code' => $wrong])->assertSessionHasErrors('code');
        $this->assertGuest('web');

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('login.two-factor.verify'), ['code' => $wrong]);
        }
        $this->post(route('login.two-factor.verify'), ['code' => $wrong])
            ->assertRedirect(route('login'));

        // The right code no longer works after the lockout.
        $this->post(route('login.two-factor.verify'), ['code' => $code])->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_two_factor_page_needs_a_pending_sign_in(): void
    {
        $this->get(route('login.two-factor'))->assertRedirect(route('login'));
        $this->post(route('login.two-factor.verify'), ['code' => '123456'])->assertRedirect(route('login'));
    }

    public function test_sign_in_stops_when_the_code_email_cannot_be_sent(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));
        $user = $this->twoFactorUser();
        $user->save();

        $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'Correct-horse-9'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('web');
        $this->assertNull(session('customer_2fa'));
    }

    public function test_users_without_two_factor_still_sign_in_directly(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Correct-horse-9')]);

        $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'Correct-horse-9'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_logout_all_devices_ends_other_sessions_only(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $other = User::factory()->create();
        foreach (['other-device', 'another-device'] as $id) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        }
        DB::table('sessions')->insert(['id' => 'someone-else', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()]);
        $before = $user->remember_token;

        $this->actingAs($user)->post(route('account.action'), ['action' => 'logout-devices'])
            ->assertSessionHas('success', 'Logged out from all other devices');

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->whereIn('id', ['other-device', 'another-device'])->count());
        $this->assertSame(1, DB::table('sessions')->where('id', 'someone-else')->count());
        $this->assertNotSame($before, $user->fresh()->remember_token);
    }

    public function test_password_change_ends_other_sessions_and_revokes_trusted_admin_devices(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['password' => Hash::make('Current-pass-1')]);
        DB::table('sessions')->insert(['id' => 'stolen-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('trusted_admin_devices')->insert([
            'user_id' => $user->id,
            'token_hash' => str_repeat('a', 64),
            'expires_at' => now()->addDays(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'Current-pass-1',
            'new_password' => 'Brand-new-pass-2',
            'new_password_confirmation' => 'Brand-new-pass-2',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('sessions')->where('id', 'stolen-session')->count());
        $this->assertSame(0, DB::table('trusted_admin_devices')->where('user_id', $user->id)->count());
    }

    public function test_add_payment_method_no_longer_claims_a_form_will_open(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('account.action'), ['action' => 'add-payment'])
            ->assertSessionHas('success', 'Saving payment methods is not available yet. You can pay securely at checkout.');
    }

    public function test_registration_sends_a_verification_email_without_blocking_access(): void
    {
        Notification::fake();

        $this->post(route('register.submit'), [
            'name' => 'New Traveller',
            'email' => 'new@example.test',
            'password' => 'Correct-horse-9',
            'password_confirmation' => 'Correct-horse-9',
        ])->assertRedirect(route('account'));

        $user = User::where('email', 'new@example.test')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertNull($user->email_verified_at);
        $this->actingAs($user)->get(route('account'))->assertOk()->assertSee('Resend verification email');
    }

    public function test_registration_survives_a_verification_email_failure(): void
    {
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('smtp down'));

        $this->post(route('register.submit'), [
            'name' => 'New Traveller',
            'email' => 'new2@example.test',
            'password' => 'Correct-horse-9',
            'password_confirmation' => 'Correct-horse-9',
        ])->assertRedirect(route('account'));

        $this->assertDatabaseHas('users', ['email' => 'new2@example.test']);
    }

    public function test_signed_verification_link_marks_the_email_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect(route('account', ['tab' => 'profile']));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_link_with_wrong_hash_or_signature_is_refused(): void
    {
        $user = User::factory()->unverified()->create();
        $badHash = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('other@example.test')]);
        $unsigned = route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->actingAs($user)->get($badHash)->assertForbidden();
        $this->actingAs($user)->get($unsigned)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_changing_email_resets_verification_and_turns_off_two_factor(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => now(), 'account_mfa_enabled' => true]);

        $this->actingAs($user)->put(route('account.profile.update'), [
            'username' => $user->username,
            'email' => 'changed@example.test',
        ])->assertSessionHas('success');

        $user->refresh();
        $this->assertNull($user->email_verified_at);
        $this->assertFalse((bool) $user->account_mfa_enabled);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_admin_login_does_not_reveal_that_credentials_belong_to_a_non_admin(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Correct-horse-9')]);

        $this->post(route('admin.login.submit'), ['email' => $user->email, 'password' => 'Correct-horse-9'])
            ->assertSessionHasErrors(['username' => 'The username or password is incorrect.']);
        $this->post(route('admin.login.submit'), ['email' => $user->email, 'password' => 'wrong-password-1'])
            ->assertSessionHasErrors(['username' => 'The username or password is incorrect.']);
    }

    public function test_sensitive_post_routes_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post(route('cart.promo'), ['code' => 'NOPE'])->assertStatus(302);
        }
        $this->post(route('cart.promo'), ['code' => 'NOPE'])->assertStatus(429);

        Cache::flush();
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post(route('contact.send'), [])->assertStatus(302);
        }
        $this->post(route('contact.send'), [])->assertStatus(429);
    }
}
