<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CredentialValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_accepts_six_character_password_with_allowed_symbols_and_rejects_invalid_passwords(): void
    {
        foreach ([
            ['Ab1', 'Password must be at least 6 characters.'],
            ['abcdef', 'Password must contain at least one letter and one number, and use only letters, numbers, or ~!@#$%^&*() with no spaces.'],
            ['123456', 'Password must contain at least one letter and one number, and use only letters, numbers, or ~!@#$%^&*() with no spaces.'],
            ['Abc123_', 'Password must contain at least one letter and one number, and use only letters, numbers, or ~!@#$%^&*() with no spaces.'],
            ['Abc 123', 'Password must contain at least one letter and one number, and use only letters, numbers, or ~!@#$%^&*() with no spaces.'],
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

        $this->post(route('register.submit'), [
            'name' => 'Credential Traveller',
            'email' => 'valid@example.test',
            'password' => 'A~!@#1',
            'password_confirmation' => 'A~!@#1',
        ])->assertRedirect(route('account'));
    }

    public function test_password_reset_and_change_enforce_the_same_password_rules(): void
    {
        $this->from(route('password.reset', ['token' => 'reset-token']))
            ->post(route('password.update'), [
                'token' => 'reset-token',
                'email' => 'person@example.test',
                'password' => 'NewPass_1',
                'password_confirmation' => 'NewPass_1',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('password');

        $user = User::factory()->create(['password' => Hash::make('Current123')]);
        $this->actingAs($user)->from(route('account', ['tab' => 'security']))
            ->put(route('account.password.update'), [
                'current_password' => 'Current123',
                'new_password' => 'new pass1',
                'new_password_confirmation' => 'new pass1',
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
