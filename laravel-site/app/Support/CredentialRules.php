<?php

namespace App\Support;

class CredentialRules
{
    public static function password(): array
    {
        return [
            'min:6',
            'regex:/\A(?=.*[A-Za-z])(?=.*\d)[A-Za-z0-9~!@#$%^&*()]+\z/',
        ];
    }

    public static function passwordMessages(string $field): array
    {
        return [
            $field.'.min' => 'Password must be at least 6 characters.',
            $field.'.regex' => 'Password must contain at least one letter and one number, and use only letters, numbers, or ~!@#$%^&*() with no spaces.',
        ];
    }
}
