<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\InsuranceSyariahSecuritiesFinanceService;
use Tests\TestCase;

class InsuranceSyariahSecuritiesFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected InsuranceSyariahSecuritiesFinanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InsuranceSyariahSecuritiesFinanceService::class);
    }

    public function test_insurance_reserve_syariah_akad_and_securities_flow(): void
    {
        // 440.1 Healthy insurance reserve review
        $pool = $this->service->reviewInsuranceReserve(
            poolCode: 'POOL-FLEET-MOTOR-2026',
            writtenPremium: 25000000000.00,
            requiredActuarialReserve: 8000000000.00,
            currentAllocatedReserve: 9500000000.00
        );

        $this->assertEquals('POOL-FLEET-MOTOR-2026', $pool->policy_pool_code);
        $this->assertFalse((bool) $pool->under_reserve_flagged);

        // 440.2 Register Syariah mudharabah agreement expiring in 15 days
        $akad = $this->service->registerSyariahAkad('AKD-MUDH-01', 'mudharabah', now()->addDays(15)->toDateString());
        $this->assertEquals('active', $akad->status);

        // 440.6 Trigger automated reminder
        $remindersCount = $this->service->triggerAkadRenewalReminders();
        $this->assertEquals(1, $remindersCount);

        // 440.3 & 440.4 Digital securities register reconciliation
        $sec = $this->service->reconcileSecuritiesRegister(
            tokenCode: 'SEC-SUKUK-2026-A',
            issuedUnits: 1000000.0000,
            distributedSum: 1000000.0000
        );

        $this->assertTrue((bool) $sec->is_register_balanced);

        // 440.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_under_reserve_topup_and_imbalanced_securities_edge_cases(): void
    {
        // 440.5 Edge case: Under-reserve detected requires top-up approval
        $underPool = $this->service->reviewInsuranceReserve(
            poolCode: 'POOL-DEFICIT-01',
            writtenPremium: 10000000000.00,
            requiredActuarialReserve: 5000000000.00,
            currentAllocatedReserve: 3500000000.00 // Deficit of 1.5B
        );

        $this->assertTrue((bool) $underPool->under_reserve_flagged);

        // Top up reserve by 2.0B -> Total allocated becomes 5.5B (> 5.0B required)
        $topped = $this->service->topUpInsuranceReserve('POOL-DEFICIT-01', 2000000000.00);
        $this->assertFalse((bool) $topped->under_reserve_flagged);
        $this->assertTrue((bool) $topped->reserve_top_up_approved);

        // 440.4 Imbalanced securities register is blocked
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Securities register imbalanced');

        $this->service->reconcileSecuritiesRegister(
            tokenCode: 'SEC-IMBALANCED',
            issuedUnits: 500000.0000,
            distributedSum: 499990.0000 // Discrepancy!
        );
    }
}
