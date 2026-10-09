<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\EndToEndOrderServiceOrchestrationService;
use Tests\TestCase;

class EndToEndOrderServiceOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    protected EndToEndOrderServiceOrchestrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EndToEndOrderServiceOrchestrationService::class);
    }

    public function test_bundle_orchestration_full_success_flow(): void
    {
        // 409.1 Create cross-line bundle order (Hotel + Car Rental + Venue)
        $bundle = $this->service->createBundleOrder(
            bundleCode: 'BDL-VIP-2026-001',
            customerId: 'CUST-8812',
            totalAmount: 15000000.00,
            components: [
                ['type' => 'hotel', 'reference' => 'HTL-RES-01', 'amount' => 5000000.00],
                ['type' => 'auto_rental', 'reference' => 'CAR-RES-01', 'amount' => 3000000.00],
                ['type' => 'venue', 'reference' => 'VEN-RES-01', 'amount' => 7000000.00],
            ]
        );

        $this->assertEquals('BDL-VIP-2026-001', $bundle->bundle_order_code);
        $this->assertEquals('pending', $bundle->orchestration_state);

        // Process orchestration all success
        $fulfilled = $this->service->processOrchestration('BDL-VIP-2026-001', [
            'HTL-RES-01' => ['success' => true],
            'CAR-RES-01' => ['success' => true],
            'VEN-RES-01' => ['success' => true],
        ]);

        $this->assertEquals('fulfilled', $fulfilled->orchestration_state);

        // 409.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['orphan_reservations']);
    }

    public function test_partial_failure_converges_to_clean_rollback_without_orphans(): void
    {
        // 409.1 & 409.3 Partial failure scenario
        $this->service->createBundleOrder(
            bundleCode: 'BDL-ERR-2026-002',
            customerId: 'CUST-9912',
            totalAmount: 10000000.00,
            components: [
                ['type' => 'flight', 'reference' => 'FLT-RES-02', 'amount' => 6000000.00],
                ['type' => 'hotel', 'reference' => 'HTL-RES-02', 'amount' => 4000000.00],
            ]
        );

        // Flight succeeds, but Hotel fails
        $rolledBack = $this->service->processOrchestration('BDL-ERR-2026-002', [
            'FLT-RES-02' => ['success' => true],
            'HTL-RES-02' => ['success' => false, 'failure_reason' => 'Room inventory unavailable'],
        ]);

        $this->assertEquals('rolled_back', $rolledBack->orchestration_state);

        // Verify Flight reservation was compensated/refunded (no orphan reservation)
        $flightComp = DB::table('ops_orchestration_components')->where('reservation_reference', 'FLT-RES-02')->first();
        $this->assertEquals('refunded', $flightComp->status);

        // 409.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['orphan_reservations']);
    }
}
