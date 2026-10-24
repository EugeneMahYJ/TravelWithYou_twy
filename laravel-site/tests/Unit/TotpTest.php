<?php

namespace Tests\Unit;

use App\Support\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    public function test_totp_matches_rfc_6238_six_digit_sha1_vectors(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $vectors = [
            59 => '287082',
            1111111109 => '081804',
            1111111111 => '050471',
            1234567890 => '005924',
            2000000000 => '279037',
            20000000000 => '353130',
        ];

        foreach ($vectors as $time => $expected) {
            $this->assertSame($expected, Totp::codeForTime($secret, $time));
        }
    }

    public function test_verification_accepts_adjacent_time_steps(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $time = 1_700_000_000;

        foreach ([-30, 0, 30] as $offset) {
            $this->assertTrue(Totp::verify($secret, Totp::codeForTime($secret, $time + $offset), $time));
        }
    }

    public function test_setup_uri_declares_compatible_totp_parameters(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $uri = Totp::provisioningUri('admin@example.test', $secret);

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret='.$secret, $uri);
        $this->assertStringContainsString('algorithm=SHA1&digits=6&period=30', $uri);
    }
}
