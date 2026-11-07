<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use InvalidArgumentException;
use Modules\Shared\Domain\Algorithms\Access\AntiPassbackCertificate;
use Modules\Shared\Domain\Algorithms\Access\AntiPassbackCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Access\AntiPassbackGate;
use PHPUnit\Framework\TestCase;

final class AntiPassbackGateTest extends TestCase
{
    public function test_prompt_vectors(): void
    {
        $ticketId = 'TCK-VECTOR-001';
        $history = [];
        $maxReentry = 2;

        // 1. MASUK 20:00 ✓
        $s1 = AntiPassbackGate::evaluate($ticketId, 'IN', 'nonce-2000', '20:00', $history, $maxReentry);
        $this->assertTrue($s1['allowed']);
        $this->assertSame(0, $s1['reentry_count']);
        $history = $s1['history'];

        // 2. MASUK 20:05 ✗ passback
        $s2 = AntiPassbackGate::evaluate($ticketId, 'IN', 'nonce-2005', '20:05', $history, $maxReentry);
        $this->assertFalse($s2['allowed']);
        $this->assertSame('PASSBACK_VIOLATION', $s2['reason']);

        // 3. KELUAR 21:00 ✓
        $s3 = AntiPassbackGate::evaluate($ticketId, 'OUT', 'nonce-2100', '21:00', $history, $maxReentry);
        $this->assertTrue($s3['allowed']);
        $history = $s3['history'];

        // 4. MASUK 21:10 ✓ (re-entry 1)
        $s4 = AntiPassbackGate::evaluate($ticketId, 'IN', 'nonce-2110', '21:10', $history, $maxReentry);
        $this->assertTrue($s4['allowed']);
        $this->assertSame(1, $s4['reentry_count']);
        $history = $s4['history'];

        // 5. ulang nonce 21:10 ✗ replay
        $s5 = AntiPassbackGate::evaluate($ticketId, 'IN', 'nonce-2110', '21:10', $history, $maxReentry);
        $this->assertFalse($s5['allowed']);
        $this->assertSame('REPLAY_ATTACK', $s5['reason']);

        // 6. KELUAR 22:00 ✓
        $s6 = AntiPassbackGate::evaluate($ticketId, 'OUT', 'nonce-2200', '22:00', $history, $maxReentry);
        $this->assertTrue($s6['allowed']);
        $history = $s6['history'];

        // 7. MASUK 22:30 ✓ (re-entry 2)
        $s7 = AntiPassbackGate::evaluate($ticketId, 'IN', 'nonce-2230', '22:30', $history, $maxReentry);
        $this->assertTrue($s7['allowed']);
        $this->assertSame(2, $s7['reentry_count']);
        $history = $s7['history'];

        // 8. KELUAR 23:00 ✓
        $s8 = AntiPassbackGate::evaluate($ticketId, 'OUT', 'nonce-2300', '23:00', $history, $maxReentry);
        $this->assertTrue($s8['allowed']);
        $history = $s8['history'];

        // 9. MASUK 23:10 ✗ batas re-entry
        $s9 = AntiPassbackGate::evaluate($ticketId, 'IN', 'nonce-2310', '23:10', $history, $maxReentry);
        $this->assertFalse($s9['allowed']);
        $this->assertSame('REENTRY_LIMIT_EXCEEDED', $s9['reason']);
    }

    public function test_corrupted_certificate_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $cert = new AntiPassbackCertificate(
            ticketId: 'TCK-001',
            maxReentry: 2,
            reentryCount: 0,
            currentDirection: 'IN',
            netInMinusOut: 1,
            history: [],
            hmacSignature: 'invalid-signature'
        );

        AntiPassbackCertificateVerifier::verify($cert);
    }
}

