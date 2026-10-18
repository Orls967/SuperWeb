<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Dtos\EpodSubmissionDto;
use Modules\Core\Application\Services\Mobile\EpodIntegrityVerifierService;
use Modules\Core\Application\Services\Mobile\MobileOfflineSyncEngineService;
use Modules\Core\Domain\Models\CoreMobileEpodRecord;

uses(RefreshDatabase::class);

test('validates digital signature sha256 hash and flags tampered epod records', function (): void {
    /** @var MobileOfflineSyncEngineService $syncService */
    $syncService = app(MobileOfflineSyncEngineService::class);
    /** @var EpodIntegrityVerifierService $verifierService */
    $verifierService = app(EpodIntegrityVerifierService::class);

    $deliveredAt = new DateTimeImmutable('2026-10-18 14:30:00');

    $dto = new EpodSubmissionDto(
        epodCode: 'EPOD-20261018-001',
        shipmentRef: 'SHP-JKT-SBY-8812',
        driverRef: 'DRV-AGUS-01',
        recipientName: 'Siti Rahmawati',
        recipientRole: 'Warehouse Supervisor',
        signaturePayloadB64: 'data:image/svg+xml;base64,PHN2Zz48cGF0aCBkPSJNMTAgMTBMMjAgMjAiLz48L3N2Zz4=',
        photoDataB64: 'data:image/jpeg;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
        latitude: -6.2088,
        longitude: 106.8456,
        deliveredOfflineAt: $deliveredAt
    );

    // 1. Submit e-POD
    $epod = $syncService->submitEpod($dto);

    expect($epod->epod_code)->toBe('EPOD-20261018-001')
        ->and($epod->is_tampered)->toBeFalse()
        ->and(strlen($epod->signature_hash))->toBe(64);

    // 2. Verify signature integrity -> PASS
    $isValid = $verifierService->verifySignature($epod);
    expect($isValid)->toBeTrue();

    // 3. Simulate tamper: modify recipient name without updating hash
    $epod->recipient_name = 'Pemalsu Tanda Tangan';
    $isTampered = $verifierService->verifySignature($epod);
    expect($isTampered)->toBeFalse();

    // 4. Flag tamper on record
    $verifierService->flagTampering('EPOD-20261018-001');
    $fresh = CoreMobileEpodRecord::where('epod_code', 'EPOD-20261018-001')->first();
    expect($fresh->is_tampered)->toBeTrue();
});
