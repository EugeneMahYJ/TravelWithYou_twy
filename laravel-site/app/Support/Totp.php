<?php

namespace App\Support;

class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(): string
    {
        return substr(self::encodeBase32(random_bytes(20)), 0, 32);
    }

    public static function verify(string $secret, string $code, ?int $time = null): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $time ??= time();
        foreach ([-1, 0, 1] as $window) {
            if (hash_equals(self::codeForTime($secret, $time + ($window * 30)), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function codeForTime(string $secret, int $time): string
    {
        $counter = intdiv($time, 30);
        $message = pack('N2', 0, $counter);
        $hash = hash_hmac('sha1', $message, self::decodeBase32($secret), true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    public static function provisioningUri(string $email, string $secret): string
    {
        $label = rawurlencode('Travel With You:'.$email);

        return "otpauth://totp/{$label}?secret={$secret}&issuer=Travel%20With%20You&algorithm=SHA1&digits=6&period=30";
    }

    private static function encodeBase32(string $data): string
    {
        $bits = '';
        foreach (unpack('C*', $data) as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) === 5) {
                $encoded .= self::ALPHABET[bindec($chunk)];
            }
        }

        return $encoded;
    }

    private static function decodeBase32(string $encoded): string
    {
        $encoded = strtoupper(rtrim($encoded, '='));
        $bits = '';
        foreach (str_split($encoded) as $character) {
            $value = strpos(self::ALPHABET, $character);
            if ($value === false) {
                throw new \InvalidArgumentException('Invalid base32 TOTP secret.');
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }

        return $decoded;
    }
}
