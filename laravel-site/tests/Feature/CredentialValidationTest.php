<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CredentialValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_enforces_ten_character_password_with_a_letter_and_a_number(): void
    {
        $tooShort = 'Password must be at least 10 characters.';
        $needsBoth = 'Password must contain at least one letter and one number.';
        foreach ([
            ['Ab1', $tooShort],
            ['Abc123_', $tooShort],
            ['abcdefghijkl', $needsBoth],
            ['1234567890123', $needsBoth],
            [str_repeat('Ab1', 50), 'Password must be 128 characters or fewer.'],
        ] as $index => [$password, $message]) {
            $this->from(route('register'))
                ->post(route('register.submit'), [
                    'name' => 'Credential Traveller',
                    'email' => 'invalid-'.$index.'@example.test',
                    'password' => $password,
                    'password_confirmation' => $password,
                ])
                ->assertRedirect(route('register'))
                ->assertSessionHasErrors(['password' => $message]);
        }

        // Any characters are allowed, including spaces and symbols outside the old list.
        $this->post(route('register.submit'), [
            'name' => 'Credential Traveller',
            'email' => 'valid@example.test',
            'password' => 'correct horse 7 battery_staple',
            'password_confirmation' => 'correct horse 7 battery_staple',
        ])->assertRedirect(route('account'));
    }

    public function test_password_reset_and_change_enforce_the_same_password_rules(): void
    {
        $this->from(route('password.reset', ['token' => 'reset-token']))
            ->post(route('password.update'), [
                'token' => 'reset-token',
                'email' => 'person@example.test',
                'password' => 'short1',
                'password_confirmation' => 'short1',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('password');

        $user = User::factory()->create(['password' => Hash::make('Current123')]);
        $this->actingAs($user)->from(route('account', ['tab' => 'security']))
            ->put(route('account.password.update'), [
                'current_password' => 'Current123',
                'new_password' => 'onlylettershere',
                'new_password_confirmation' => 'onlylettershere',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('new_password');
    }

    public function test_profile_username_rejects_whitespace(): void
    {
        $user = User::factory()->create(['username' => 'before']);

        $this->actingAs($user)->put(route('account.profile.update'), [
            'username' => 'bad username',
            'email' => $user->email,
        ])->assertSessionHasErrors([
            'username' => 'Username cannot contain spaces.',
        ]);
    }
}
