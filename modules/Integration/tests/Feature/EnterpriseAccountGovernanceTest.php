<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\EnterpriseAccountGovernanceService;
use Tests\TestCase;

class EnterpriseAccountGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseAccountGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseAccountGovernanceService::class);
    }

    public function test_enterprise_umbrella_agreement_and_component_orders(): void
    {
        // 420.1 & 420.2 Create umbrella agreement
        $umb = $this->service->createUmbrellaAgreement(
            code: 'UMB-CORP-TELKOM-2026',
            clientName: 'PT Telkom Indonesia Tbk',
            executiveSponsor: 'VP Enterprise Sales',
            totalValue: 5000000000.00
        );

        $this->assertEquals('UMB-CORP-TELKOM-2026', $umb->agreement_code);
        $this->assertEquals(100.00, (float) $umb->health_index_score);

        // Add component order (Logistics fleet + Cloud hosting) with matching settlement
        $this->service->addComponentOrder(
            umbrellaCode: 'UMB-CORP-TELKOM-2026',
            orderCode: 'ORD-LGX-01',
            businessLine: 'LOGISTICS',
            billingAmount: 2500000000.00,
            settlementAmount: 2500000000.00
        );

        $this->service->addComponentOrder(
            umbrellaCode: 'UMB-CORP-TELKOM-2026',
            orderCode: 'ORD-HTL-01',
            businessLine: 'HOTEL',
            billingAmount: 2500000000.00,
            settlementAmount: 2500000000.00
        );

        // 420.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_umbrella_cancellation_decouples_components_edge_case(): void
    {
        // 420.5 Edge case: umbrella cancellation manages components separately
        $this->service->createUmbrellaAgreement('UMB-CANCEL-TEST', 'Client X', 'VP Y', 100000000.00);
        $this->service->addComponentOrder('UMB-CANCEL-TEST', 'ORD-SUB-01', 'RESTO', 50000000.00, 50000000.00);

        $cancelled = $this->service->cancelUmbrellaAgreement('UMB-CANCEL-TEST');
        $this->assertEquals('cancelled', $cancelled->status);

        $comp = DB::table('crm_enterprise_component_orders')->where('component_order_code', 'ORD-SUB-01')->first();
        $this->assertEquals('managed_separately', $comp->status);
    }
}
