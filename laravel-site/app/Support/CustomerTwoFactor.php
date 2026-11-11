<?php

namespace App\Support;

use App\Mail\CustomerLoginCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Email one-time codes for customers who turned on two-factor sign-in.
 * Codes are stored only as keyed hashes, expire after ten minutes, and
 * allow a limited number of wrong attempts.
 */
final class CustomerTwoFactor
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public static function issue(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Send first so a delivery failure never leaves a code the user cannot receive.
        Mail::to($user->email)->send(new CustomerLoginCodeMail($code, self::TTL_MINUTES));

        Cache::put(self::key($user), [
            'hash' => self::hash($code),
            'attempts' => 0,
        ], now()->addMinutes(self::TTL_MINUTES));
    }

    /** @return 'ok'|'invalid'|'expired'|'locked' */
    public static function check(User $user, string $code): string
    {
        $entry = Cache::get(self::key($user));
        if (! is_array($entry)) {
            return 'expired';
        }

        if (($entry['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            self::forget($user);

            return 'locked';
        }

        if (hash_equals((string) $entry['hash'], self::hash($code))) {
            self::forget($user);

            return 'ok';
        }

        $entry['attempts'] = ($entry['attempts'] ?? 0) + 1;
        if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
            self::forget($user);

            return 'locked';
        }
        Cache::put(self::key($user), $entry, now()->addMinutes(self::TTL_MINUTES));

        return 'invalid';
    }

    public static function forget(User $user): void
    {
        Cache::forget(self::key($user));
    }

    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, 1);

        return $visible.str_repeat('*', max(2, mb_strlen($local) - 1)).($domain !== '' ? '@'.$domain : '');
    }

    private static function key(User $user): string
    {
        return 'customer_2fa:'.$user->id;
    }

    private static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
