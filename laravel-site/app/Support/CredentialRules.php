<?php

namespace App\Support;

class CredentialRules
{
    public static function password(): array
    {
        return [
            'min:10',
            'max:128',
            'regex:/\A(?=.*[A-Za-z])(?=.*\d).+\z/s',
        ];
    }

    public static function passwordMessages(string $field): array
    {
        return [
            $field.'.min' => 'Password must be at least 10 characters.',
            $field.'.max' => 'Password must be 128 characters or fewer.',
            $field.'.regex' => 'Password must contain at least one letter and one number.',
        ];
    }
}
