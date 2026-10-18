<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Dtos\MobileFieldActionDto;
use Modules\Core\Application\Dtos\MobileSyncEventDto;
use Modules\Core\Application\Services\Mobile\MobileOfflineSyncEngineService;
use Modules\Core\Domain\Models\CoreMobileDevice;
use Modules\Core\Domain\Models\CoreMobileFieldAction;

uses(RefreshDatabase::class);

test('executes offline field actions across 4 operational roles deterministically', function (): void {
    /** @var MobileOfflineSyncEngineService $service */
    $service = app(MobileOfflineSyncEngineService::class);

    // 1. Operator Pabrik: Work order production log & downtime
    $operatorEvent = new MobileSyncEventDto(
        idempotencyKey: 'mob:sync:OP-01:wo-501',
        deviceUuid: 'DEV-OP-01',
        roleScope: 'operator',
        eventType: 'MFG_OUTPUT_RECORDED',
        payload: [
            'reference_id' => 'WO-MFG-501',
            'output_quantity' => 1200,
            'downtime_minutes' => 25,
            'downtime_reason' => 'Kalibrasi Sensor Press',
            'operator_name' => 'Bambang Operator',
        ],
        occurredOfflineAt: now()->subMinutes(30)
    );

    // 2. Petugas WMS: Barcode bin scanner & putaway
    $wmsEvent = new MobileSyncEventDto(
        idempotencyKey: 'mob:sync:WMS-01:put-88',
        deviceUuid: 'DEV-WMS-01',
        roleScope: 'hub_operator',
        eventType: 'WMS_PUTAWAY_CONFIRMED',
        payload: [
            'reference_id' => 'BIN-RAK-A-12',
            'sku' => 'SKU-SPAREPART-001',
            'quantity_scanned' => 50,
            'operator_name' => 'Hendra WMS',
        ],
        occurredOfflineAt: now()->subMinutes(20)
    );

    // 3. Driver Logistik: Route navigation & delivery check
    $driverAction = new MobileFieldActionDto(
        actionCode: 'ACT-DRV-ROUTE-77',
        roleScope: 'driver',
        actionType: 'ROUTE_CHECKPOINT',
        referenceId: 'TRIP-LOG-77',
        operatorName: 'Driver Joko',
        details: [
            'checkpoint' => 'Hub Cikarang',
            'fuel_liters' => 45,
            'status' => 'IN_TRANSIT',
        ]
    );

    // 4. Sales Agen Lapangan: Offline order creation & commission preview
    $agentEvent = new MobileSyncEventDto(
        idempotencyKey: 'mob:sync:AGT-01:ord-33',
        deviceUuid: 'DEV-AGT-01',
        roleScope: 'agent',
        eventType: 'AGY_OFFLINE_ORDER_CREATED',
        payload: [
            'reference_id' => 'ORD-OFFLINE-CLIENT-99',
            'customer_name' => 'PT Maju Bersama',
            'total_amount_idr' => 15000000,
            'projected_commission_idr' => 750000,
            'operator_name' => 'Sari Agen',
        ],
        occurredOfflineAt: now()->subMinutes(10)
    );

    // Execute Batch Sync
    $batchResult = $service->processBatch([$operatorEvent, $wmsEvent, $agentEvent]);
    expect($batchResult['applied'])->toBe(3);

    // Record Driver Action directly
    $driverRecord = $service->recordFieldAction($driverAction);
    expect($driverRecord->action_type)->toBe('ROUTE_CHECKPOINT');

    // Assert database representation across all 4 roles
    expect(CoreMobileFieldAction::where('role_scope', 'operator')->exists())->toBeTrue()
        ->and(CoreMobileFieldAction::where('role_scope', 'hub_operator')->exists())->toBeTrue()
        ->and(CoreMobileFieldAction::where('role_scope', 'driver')->exists())->toBeTrue()
        ->and(CoreMobileFieldAction::where('role_scope', 'agent')->exists())->toBeTrue();

    // Verify devices auto-registered
    expect(CoreMobileDevice::where('device_uuid', 'DEV-OP-01')->exists())->toBeTrue()
        ->and(CoreMobileDevice::where('device_uuid', 'DEV-WMS-01')->exists())->toBeTrue()
        ->and(CoreMobileDevice::where('device_uuid', 'DEV-AGT-01')->exists())->toBeTrue();
});
