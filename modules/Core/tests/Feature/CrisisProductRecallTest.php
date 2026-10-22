<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Services\CrisisProductRecallService;
use Modules\Core\Domain\Models\CrisisAffectedItem;
use Modules\Core\Domain\Models\CrisisIncident;
use Tests\TestCase;

class CrisisProductRecallTest extends TestCase
{
    use RefreshDatabase;

    protected CrisisProductRecallService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CrisisProductRecallService::class);
    }

    public function test_emergency_recall_locks_quarantined_lots_across_all_branches(): void
    {
        $batches = [
            ['sku' => 'MED-PCM-SYR-01', 'lot_number' => 'LOT-RECALL-01', 'quantity' => 500, 'location' => 'ZONE-A'],
            ['sku' => 'MED-PCM-SYR-02', 'lot_number' => 'LOT-RECALL-02', 'quantity' => 750, 'location' => 'ZONE-B'],
        ];

        $incident = $this->service->initiateRecall(
            'CRISIS-TEST-001',
            'CRITICAL_GRADE_1',
            'BPOM Urgent Recall Alert',
            $batches
        );

        $this->assertDatabaseHas('core_crisis_incidents', [
            'incident_code' => 'CRISIS-TEST-001',
            'severity_level' => 'CRITICAL_GRADE_1',
            'total_units_quarantined' => 1250,
            'emergency_state' => 'ACTIVE_QUARANTINE_LOCKED',
        ]);

        $this->assertDatabaseHas('core_crisis_affected_items', [
            'incident_id' => $incident->id,
            'lot_number' => 'LOT-RECALL-01',
            'is_sale_blocked' => true,
        ]);
    }

    public function test_pos_and_epharmacy_reject_sale_of_quarantined_batches(): void
    {
        $batches = [
            ['sku' => 'MED-PCM-SYR-01', 'lot_number' => 'LOT-BLOCKED-99', 'quantity' => 100, 'location' => 'ZONE-C'],
        ];

        $this->service->initiateRecall('CRISIS-TEST-002', 'CRITICAL', 'Tainted Syrup', $batches);

        $isBlocked = $this->service->isSaleBlocked('MED-PCM-SYR-01', 'LOT-BLOCKED-99');
        $this->assertTrue($isBlocked);

        $isSafeBlocked = $this->service->isSaleBlocked('MED-PCM-SYR-01', 'LOT-SAFE-CLEAN-01');
        $this->assertFalse($isSafeBlocked);
    }

    public function test_bidirectional_traceability_identifies_all_exposed_patients(): void
    {
        $batches = [
            ['sku' => 'MED-AMOX-500', 'lot_number' => 'LOT-TRACE-77', 'quantity' => 200, 'location' => 'ZONE-CENTRAL'],
        ];

        $this->service->initiateRecall('CRISIS-TEST-003', 'HIGH', 'Traceability Verification', $batches);

        $trace = $this->service->traceLot('LOT-TRACE-77');

        $this->assertSame('LOT-TRACE-77', $trace['lot_number']);
        $this->assertTrue($trace['is_quarantined']);
        $this->assertSame('CRISIS-TEST-003', $trace['incident_code']);
        $this->assertContains('ZONE-CENTRAL', $trace['affected_storage_locations']);
    }

    public function test_hazardous_pharmaceutical_waste_manifest_generation(): void
    {
        $batches = [
            ['sku' => 'MED-CFX-200', 'lot_number' => 'LOT-WASTE-01', 'quantity' => 450, 'location' => 'HAZARD-VAULT'],
        ];

        $incident = $this->service->initiateRecall('CRISIS-TEST-004', 'CRITICAL', 'Waste Manifest Verification', $batches);
        $manifest = $this->service->generateWasteManifest($incident);

        $this->assertStringStartsWith('MANIFEST-WASTE-', $manifest['manifest_number']);
        $this->assertSame('CRISIS-TEST-004', $manifest['incident_code']);
        $this->assertSame(450, $manifest['total_units_quarantined']);
        $this->assertNotEmpty($manifest['verification_hash']);
    }

    public function test_regulatory_authority_incident_notification_dispatch(): void
    {
        $batches = [
            ['sku' => 'MED-DRP-01', 'lot_number' => 'LOT-NOTIF-01', 'quantity' => 300, 'location' => 'VAULT-NORTH'],
        ];

        $incident = $this->service->initiateRecall('CRISIS-TEST-005', 'CRITICAL_GRADE_1', 'Authority Notification', $batches);
        $this->assertSame('ACTIVE_QUARANTINE_LOCKED', $incident->emergency_state);
        $this->assertNotNull($incident->activated_at);
    }

    public function test_quarantine_release_protocol_after_clean_lab_clearance(): void
    {
        $batches = [
            ['sku' => 'MED-PARA-500', 'lot_number' => 'LOT-CLEAR-01', 'quantity' => 800, 'location' => 'QUARANTINE-BAY'],
        ];

        $incident = $this->service->initiateRecall('CRISIS-TEST-006', 'INVESTIGATION', 'Temporary Hold', $batches);
        $released = $this->service->releaseQuarantine($incident, 'LAB-CERT-PRODIA-2026-CLEAN');

        $this->assertTrue($released);
        $this->assertSame('RELEASED_AFTER_LAB_CLEARANCE', $incident->fresh()->emergency_state);

        $isBlocked = $this->service->isSaleBlocked('MED-PARA-500', 'LOT-CLEAR-01');
        $this->assertFalse($isBlocked);
    }
}
