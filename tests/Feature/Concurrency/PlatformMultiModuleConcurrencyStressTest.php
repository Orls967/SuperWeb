<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformMultiModuleConcurrencyStressTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that platform:stress-concurrency executes deterministically across all 6 critical points
     * with 0 invariant violations.
     */
    public function test_platform_multi_module_concurrency_stress_harness_passes_with_zero_violations(): void
    {
        $this->artisan('platform:stress-concurrency', ['--iterations' => 10])
            ->assertExitCode(0)
            ->expectsOutputToContain('Double-Entry Ledger & Equilibrium')
            ->expectsOutputToContain('Stock Reservation & Quota Limits')
            ->expectsOutputToContain('Room-Night Pessimistic Lock & Walk Compensation')
            ->expectsOutputToContain('Clinical Allergy & Drug Safety Guard')
            ->expectsOutputToContain('Secondary Orderbook 1:1 Parity & Escrow')
            ->expectsOutputToContain('Anti-Passback, Cohort Quota & PNBP Royalty')
            ->expectsOutputToContain('Seluruh 6 titik kritis LOLOS pengujian konkurensi ekstrem');
    }
}
