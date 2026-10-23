<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\DTOs\MobileSyncEventDto;
use Modules\Core\Application\Services\Mobile\MobileOfflineSyncEngineService;
use Modules\Core\Domain\Models\CoreMobileSyncEvent;

uses(RefreshDatabase::class);

test('resolves sync conflicts using last write wins with timestamp guard and skips duplicates', function (): void {
    /** @var MobileOfflineSyncEngineService $service */
    $service = app(MobileOfflineSyncEngineService::class);

    $deviceUuid = 'DEV-TEST-SYNC-001';
    $refId = 'REF-ORDER-991';

    // Event 1: Initial event at T=10:00
    $event1 = new MobileSyncEventDto(
        idempotencyKey: 'mob:sync:DEV-001:evt-001',
        deviceUuid: $deviceUuid,
        roleScope: 'operator',
        eventType: 'MFG_OUTPUT_RECORDED',
        payload: ['reference_id' => $refId, 'output_units' => 100, 'operator_name' => 'Operator A'],
        occurredOfflineAt: new DateTimeImmutable('2026-10-18 10:00:00')
    );

    $batchResult1 = $service->processBatch([$event1]);

    expect($batchResult1['applied'])->toBe(1)
        ->and($batchResult1['skipped_duplicates'])->toBe(0)
        ->and($batchResult1['results'][0]['status'])->toBe('APPLIED');

    // Event 2: Duplicate submission with identical idempotency key (simulating retry on spotty signal)
    $batchResult2 = $service->processBatch([$event1]);

    expect($batchResult2['applied'])->toBe(0)
        ->and($batchResult2['skipped_duplicates'])->toBe(1)
        ->and($batchResult2['results'][0]['status'])->toBe('SKIPPED_DUPLICATE');

    // Event 3: Newer event at T=11:00 for same entity -> APPLIED
    $event3 = new MobileSyncEventDto(
        idempotencyKey: 'mob:sync:DEV-001:evt-003',
        deviceUuid: $deviceUuid,
        roleScope: 'operator',
        eventType: 'MFG_OUTPUT_RECORDED',
        payload: ['reference_id' => $refId, 'output_units' => 250, 'operator_name' => 'Operator A'],
        occurredOfflineAt: new DateTimeImmutable('2026-10-18 11:00:00')
    );

    $batchResult3 = $service->processBatch([$event3]);
    expect($batchResult3['applied'])->toBe(1)
        ->and($batchResult3['results'][0]['status'])->toBe('APPLIED');

    // Event 4: Delayed stale event from another device for same entity at T=09:30 -> Guarded via LWW-TG
    $event4 = new MobileSyncEventDto(
        idempotencyKey: 'mob:sync:DEV-002:evt-004',
        deviceUuid: 'DEV-TEST-SYNC-002',
        roleScope: 'operator',
        eventType: 'MFG_OUTPUT_RECORDED',
        payload: ['reference_id' => $refId, 'output_units' => 50, 'operator_name' => 'Operator B'],
        occurredOfflineAt: new DateTimeImmutable('2026-10-18 09:30:00')
    );

    $batchResult4 = $service->processBatch([$event4]);
    expect($batchResult4['applied'])->toBe(0)
        ->and($batchResult4['resolved_conflicts'])->toBe(1)
        ->and($batchResult4['results'][0]['status'])->toBe('RESOLVED_LWW');

    // Database assertions
    expect(CoreMobileSyncEvent::where('device_uuid', $deviceUuid)->count())->toBe(2);
});
