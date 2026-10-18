<?php

declare(strict_types=1);

use App\Quality\Audit\Fixtures\MobileAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Dtos\EpodSubmissionDto;
use Modules\Core\Application\Dtos\MobileSyncEventDto;
use Modules\Core\Application\Services\Mobile\MobileOfflineSyncEngineService;
use Modules\Core\Domain\Models\CoreMobileEpodRecord;

uses(RefreshDatabase::class);

test('mobile:audit succeeds on clean state and catches tampered epod hashes', function (): void {
    /** @var MobileOfflineSyncEngineService $syncService */
    $syncService = app(MobileOfflineSyncEngineService::class);

    // 1. Seed valid data
    $syncService->processBatch([
        new MobileSyncEventDto(
            idempotencyKey: 'mob:sync:DEV-SEED:001',
            deviceUuid: 'DEV-SEED-01',
            roleScope: 'operator',
            eventType: 'MFG_OUTPUT_RECORDED',
            payload: ['units' => 100],
            occurredOfflineAt: now()
        ),
    ]);

    $syncService->submitEpod(new EpodSubmissionDto(
        epodCode: 'EPOD-VALID-001',
        shipmentRef: 'SHP-SEED-001',
        driverRef: 'DRV-SEED-01',
        recipientName: 'Valid Recipient',
        recipientRole: 'Manager',
        signaturePayloadB64: 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=',
        photoDataB64: 'data:image/jpeg;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
        latitude: -6.2,
        longitude: 106.8,
        deliveredOfflineAt: new DateTimeImmutable('2026-10-18 12:00:00')
    ));

    // Clean audit run -> exit code 0
    $this->artisan('mobile:audit')
        ->expectsOutputToContain('mobile:audit PASSED')
        ->assertExitCode(0);

    // 2. Corrupt e-POD via fixture
    $fixture = new MobileAuditCorruptionFixture;
    $expectedMessage = $fixture->corrupt();

    // Corrupted audit run -> exit code 1
    $this->artisan('mobile:audit')
        ->expectsOutputToContain('mobile:audit FAILED')
        ->assertExitCode(1);

    // 3. Test tampered flag detection
    CoreMobileEpodRecord::where('epod_code', 'like', 'EPOD-CORRUPT-%')->delete();
    $validEpod = CoreMobileEpodRecord::where('epod_code', 'EPOD-VALID-001')->first();
    $validEpod->update(['is_tampered' => true]);

    $this->artisan('mobile:audit')
        ->expectsOutputToContain('Tampered e-POD record detected')
        ->assertExitCode(1);
});
