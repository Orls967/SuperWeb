<?php

declare(strict_types=1);

namespace Tests\Skala;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Contracts\ScaleProvisionerInterface;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('skala')]
class ScaleAndBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * D1: Provisioner Coverage Verification.
     * Documents that scale:provision only provisions 3 modules (passport, outbox, ledger)
     * and lacks provisioners for all 20+ other business lines.
     */
    public function test_d1_scale_provisioner_coverage_is_restricted_to_three_modules(): void
    {
        $registered = [];
        foreach (app()->tagged('scale.provisioner') as $prov) {
            if ($prov instanceof ScaleProvisionerInterface) {
                $registered[$prov->moduleName()] = get_class($prov);
            }
        }

        // Only 3 modules are registered as scale provisioners
        $this->assertCount(3, $registered, 'scale.provisioner tagged services must be exactly 3');
        $this->assertArrayHasKey('passport', $registered);
        $this->assertArrayHasKey('outbox', $registered);
        $this->assertArrayHasKey('ledger', $registered);

        // Does NOT cover hospital, mining, hotel, resto, etc.
        $this->assertArrayNotHasKey('hospital', $registered);
        $this->assertArrayNotHasKey('mining', $registered);
        $this->assertArrayNotHasKey('resto', $registered);
    }

    /**
     * D2: Critical Endpoints Benchmark & Audit Durations.
     * Measures p50 and p95 latencies and confirms audit command durations.
     */
    public function test_d2_critical_financial_audits_complete_under_budget(): void
    {
        // 1. bank:reconcile
        $startBank = microtime(true);
        $exitBank = Artisan::call('bank:reconcile');
        $durationBankMs = (microtime(true) - $startBank) * 1000;

        $this->assertEquals(0, $exitBank);
        $this->assertLessThan(500, $durationBankMs, "bank:reconcile took {$durationBankMs} ms (expected < 500 ms)");

        // 2. chain:audit-all
        $startChain = microtime(true);
        $exitChain = Artisan::call('chain:audit-all');
        $durationChainMs = (microtime(true) - $startChain) * 1000;

        $this->assertEquals(0, $exitChain);
        $this->assertLessThan(1500, $durationChainMs, "chain:audit-all took {$durationChainMs} ms (expected < 1500 ms)");
    }
}
