<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use InvalidArgumentException;
use Modules\Shared\Domain\Algorithms\Utility\RecLifecycleCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Utility\RecLifecycleResult;
use Modules\Shared\Domain\Algorithms\Utility\RecLifecycleTracker;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RecLifecycleTrackerTest extends TestCase
{
    public function test_rec_lifecycle_transition_and_retirement(): void
    {
        $tracker = new RecLifecycleTracker;
        $verifier = new RecLifecycleCertificateVerifier;

        // 1. Transisi dari minted ke listed
        $res1 = $tracker->transition(
            serialNumber: 'IREC-SLR-2026-0001',
            currentStatus: 'minted',
            targetStatus: 'listed',
            energyWh: 1_000_000_000, // 1 MWh
            generatorAssetId: 10,
            vintageYear: '2026',
            ownerPartyId: 'PARTY-SOLAR-01'
        );

        $this->assertSame('listed', $res1->newStatus);
        $this->assertFalse($res1->isRetired);
        $this->assertTrue($verifier->verify($res1));

        // 2. Transisi dari listed ke retired (klaim green lease)
        $res2 = $tracker->transition(
            serialNumber: 'IREC-SLR-2026-0001',
            currentStatus: 'listed',
            targetStatus: 'retired',
            energyWh: 1_000_000_000,
            generatorAssetId: 10,
            vintageYear: '2026',
            ownerPartyId: 'PARTY-MALL-TENANT-88',
            claimReason: 'Klaim Penggunaan 100% Listrik Surya Hijau Tenant Mall Blok A'
        );

        $this->assertSame('retired', $res2->newStatus);
        $this->assertTrue($res2->isRetired);
        $this->assertTrue($verifier->verify($res2));
    }

    public function test_illegal_transition_throws_exception(): void
    {
        $tracker = new RecLifecycleTracker;

        // Mencoba mengaktifkan kembali REC yang sudah retired
        $this->expectException(InvalidArgumentException::class);
        $tracker->transition(
            serialNumber: 'IREC-SLR-2026-0001',
            currentStatus: 'retired',
            targetStatus: 'minted',
            energyWh: 1_000_000_000,
            generatorAssetId: 10,
            vintageYear: '2026',
            ownerPartyId: 'PARTY-01'
        );
    }

    public function test_tampered_rec_hash_fails(): void
    {
        $tracker = new RecLifecycleTracker;
        $verifier = new RecLifecycleCertificateVerifier;

        $res = $tracker->transition(
            'IREC-01',
            'minted',
            'listed',
            500_000,
            1,
            '2026',
            'P1'
        );

        $tampered = new RecLifecycleResult(
            serialNumber: $res->serialNumber,
            previousStatus: $res->previousStatus,
            newStatus: $res->newStatus,
            energyWh: $res->energyWh,
            generatorAssetId: $res->generatorAssetId,
            vintageYear: $res->vintageYear,
            ownerPartyId: $res->ownerPartyId,
            claimReason: $res->claimReason,
            isRetired: $res->isRetired,
            certificateHash: 'tampered-hash',
            canonicalPayload: $res->canonicalPayload,
        );

        $this->expectException(RuntimeException::class);
        $verifier->verify($tampered);
    }
}
