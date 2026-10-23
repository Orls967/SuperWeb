<?php

declare(strict_types=1);

namespace Tests\Feature\Chaos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformChaosAndTamperResilienceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that platform:chaos-verify successfully injects sabotages across modules,
     * proves that all audit commands detect tampering (exit != 0), and confirms
     * 100% green recovery (exit 0) once healed.
     */
    public function test_platform_chaos_and_tamper_resilience_detects_and_recovers_all_sabotages(): void
    {
        $this->artisan('platform:chaos-verify')
            ->assertExitCode(0)
            ->expectsOutputToContain('Memulai Platform-Wide Chaos & Cryptographic Tamper Simulator')
            ->expectsOutputToContain('bank:reconcile')
            ->expectsOutputToContain('hsp:audit')
            ->expectsOutputToContain('edu:audit')
            ->expectsOutputToContain('egy:audit')
            ->expectsOutputToContain('med:audit')
            ->expectsOutputToContain('ret:audit')
            ->expectsOutputToContain('tlx:audit')
            ->expectsOutputToContain('100% Sabotase Terdeteksi & Seluruh Perintah Audit Kembali Hijau');
    }
}
