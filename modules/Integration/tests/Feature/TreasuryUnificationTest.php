<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\TreasuryUnificationService;
use Tests\TestCase;

/**
 * Fase 187 — Integrasi 30 Lini B: Payment, Settlement & Treasury Unification Tests
 *
 * Covers:
 *  (a) unified payment across 30 lines is idempotent
 *  (b) intercompany netting clears gross flows correctly
 *  (c) pooling cannot make source account balance negative
 *  (d) treasury:audit = 0 discrepancy
 */
class TreasuryUnificationTest extends TestCase
{
    use RefreshDatabase;

    protected TreasuryUnificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TreasuryUnificationService::class);
    }

    /**
     * (a) Payment idempotency across 30 lines.
     */
    public function test_unified_payment_idempotent(): void
    {
        $key = 'TX-UNI-CROSS-LINE-20261008-001';

        // 1. Initial payment
        $p1 = $this->service->processPayment($key, 'L26', 'QRIS', 1500000.0);
        $this->assertSame('SETTLED', $p1->status);
        $this->assertEquals(1500000.00, (float) $p1->gross_amount_idr);

        // 2. Duplicate payment submission returns existing record
        $p2 = $this->service->processPayment($key, 'L26', 'QRIS', 1500000.0);
        $this->assertSame($p1->transaction_key, $p2->transaction_key);

        $count = DB::table('pay_unified_transactions')->count();
        $this->assertSame(1, $count);
    }

    /**
     * (b) Intercompany netting calculation.
     */
    public function test_intercompany_netting_clearing(): void
    {
        // Entity A has receivables of Rp 1,000,000,000 and payables of Rp 700,000,000 to Entity B
        // Net settlement = Rp 300,000,000 to be paid by Entity B
        $netting = $this->service->executeIntercompanyNetting('ENT-HOSPITALITY', 'ENT-AGRI', 1000000000.0, 700000000.0);

        $this->assertEquals(300000000.00, (float) $netting->netted_settlement_idr);
        $this->assertSame('ENT-AGRI', $netting->settling_entity);
    }

    /**
     * (c) Cash pool sweep cannot drive balance negative.
     */
    public function test_cash_pool_sweep_invariant_no_negative_balance(): void
    {
        // 1. Valid sweep: Rp 400M from Rp 500M balance -> Rp 100M remains -> SUCCESS
        $sweep = $this->service->executePoolSweep('ACC-OP-01', 'ACC-POOL-CENTRAL', 400000000.0, 500000000.0);
        $this->assertEquals(100000000.00, (float) $sweep->source_balance_after_idr);

        // 2. Over-draft sweep: Rp 600M from Rp 500M balance -> Exception
        try {
            $this->service->executePoolSweep('ACC-OP-01', 'ACC-POOL-CENTRAL', 600000000.0, 500000000.0);
            $this->fail('Expected exception for negative cash balance after sweep.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Balance cannot be negative', $e->getMessage());
        }
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_treasury_unification_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
