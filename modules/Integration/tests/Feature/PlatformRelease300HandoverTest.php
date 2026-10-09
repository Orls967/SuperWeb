<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\PlatformRelease300HandoverService;
use Tests\TestCase;

class PlatformRelease300HandoverTest extends TestCase
{
    use RefreshDatabase;

    protected PlatformRelease300HandoverService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PlatformRelease300HandoverService::class);
    }

    public function test_universal_subledger_reconciliation_enforces_zero_variance(): void
    {
        // 1. Balanced subledger debit $5,000,000 vs credit $5,000,000 => variance $0 (300.2)
        $balanced = $this->service->reconcileDomainLedger(
            domainLine: 'CARBON_CREDITS',
            debitSumUsd: 5000000.0,
            creditSumUsd: 5000000.0
        );
        $this->assertTrue((bool) $balanced->is_reconciled);
        $this->assertEquals(0.00, (float) $balanced->net_variance_usd);

        // 2. Unbalanced subledger variance strictly rejected (300.2)
        try {
            $this->service->reconcileDomainLedger('ZAKAT_WAKAF', 100000.0, 99500.0);
            $this->fail('Expected exception for unbalanced subledger reconciliation');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('unexplained net variance', $e->getMessage());
        }
    }

    public function test_30_lines_super_health_check_and_hash_chain_verification(): void
    {
        // Super health-check covering all 30 business lines and hash chains (300.3 & 300.4)
        $health = $this->service->verify30LinesIntegrity(allChainsValid: true, allLinesHealthy: true);
        $this->assertEquals('HEALTHY', $health['status']);
        $this->assertEquals(30, $health['lines_monitored_count']);
        $this->assertTrue($health['all_hash_chains_valid']);
        $this->assertTrue($health['all_lines_healthy']);
    }

    public function test_final_release300_handover_signoff_gate(): void
    {
        // 1. Incomplete handover criteria fails sign-off gate (300.8)
        try {
            $this->service->executeReleaseHandoverSignoff(
                signoffCode: 'SIGNOFF-FAIL-01',
                executiveArchitectId: 'CHIEF_ARCHITECT_01',
                all30LinesHealthy: true,
                allHashChainsValid: true,
                totalLedgerVarianceUsd: 15.00, // Non-zero variance!
                drFailoverProven: true
            );
            $this->fail('Expected exception for non-zero variance during signoff');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Handover sign-off rejected: Criteria incomplete', $e->getMessage());
        }

        // 2. Full criteria satisfied signs off release tag v300-30-lines-complete (300.6, 300.7, 300.8)
        $signoff = $this->service->executeReleaseHandoverSignoff(
            signoffCode: 'SIGNOFF-RELEASE-V300-FINAL',
            executiveArchitectId: 'CHIEF_ARCHITECT_01',
            all30LinesHealthy: true,
            allHashChainsValid: true,
            totalLedgerVarianceUsd: 0.00,
            drFailoverProven: true
        );
        $this->assertTrue((bool) $signoff->is_handover_complete);
        $this->assertEquals('v300-30-lines-complete', $signoff->release_tag);
    }

    public function test_platform_handover_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->reconcileDomainLedger('POINTS', 1000.0, 1000.0);
        $this->service->executeReleaseHandoverSignoff('SIG-AUD', 'LEAD', true, true, 0.0, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: subledger with non-zero variance
        DB::table('platform_30lines_ledger_reconciliations')->insert([
            'ledger_domain_line' => 'UNBALANCED_SUBLEDGER',
            'debit_sum_usd' => 500.0,
            'credit_sum_usd' => 450.0,
            'net_variance_usd' => 50.0, // Discrepancy!
            'is_reconciled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
