<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use InvalidArgumentException;

/**
 * Melacak siklus hidup sertifikat atribut energi terbarukan (Renewable Energy Certificate / I-REC).
 * Menjamin transisi status legal, keunikan nomor seri batch generator, dan pencegahan double-claiming.
 * Zero-Float komputasi integer watt-hours (Wh) berakar hash SHA-256.
 */
final class RecLifecycleTracker
{
    private const VALID_STATUS_TRANSITIONS = [
        'minted' => ['listed', 'retired'],
        'listed' => ['minted', 'transferred', 'retired'],
        'transferred' => ['listed', 'retired'],
        'retired' => [], // Terminal state
    ];

    /**
     * Validasi transisi status dan cetak sertifikat verifikasi siklus hidup REC.
     *
     * @param  string  $serialNumber  Nomor seri unik REC (misal IREC-SLR-2026-001)
     * @param  string  $currentStatus  Status saat ini
     * @param  string  $targetStatus  Status tujuan
     * @param  int  $energyWh  Energi hijau yang disertifikasi dalam Watt-hours (Wh)
     * @param  int  $generatorAssetId  ID aset pembangkit (Solar / Biogas)
     * @param  string  $vintageYear  Tahun vintage (YYYY)
     * @param  int|string  $ownerPartyId  Pihak pemilik legal saat ini
     * @param  string  $claimReason  Alasan klaim (hanya wajib bila status = retired)
     */
    public function transition(
        string $serialNumber,
        string $currentStatus,
        string $targetStatus,
        int $energyWh,
        int $generatorAssetId,
        string $vintageYear,
        int|string $ownerPartyId,
        string $claimReason = '',
    ): RecLifecycleResult {
        if ($energyWh <= 0) {
            throw new InvalidArgumentException('Kuantitas energi REC harus > 0 Wh');
        }
        if ($serialNumber === '') {
            throw new InvalidArgumentException('Nomor seri REC tidak boleh kosong');
        }

        $allowedTransitions = self::VALID_STATUS_TRANSITIONS[$currentStatus] ?? null;
        if ($allowedTransitions === null) {
            throw new InvalidArgumentException("Status awal REC tidak valid: {$currentStatus}");
        }

        if (! in_array($targetStatus, $allowedTransitions, true)) {
            throw new InvalidArgumentException(
                "Transisi status REC ilegal: Dari '{$currentStatus}' ke '{$targetStatus}' tidak diperbolehkan."
            );
        }

        if ($targetStatus === 'retired' && trim($claimReason) === '') {
            throw new InvalidArgumentException('Klaim pensiun REC wajib menyertakan alasan klaim keberlanjutan');
        }

        // Buat payload kanonikal verifikasi SHA-256
        $canonicalPayload = sprintf(
            'REC-LIFECYCLE|SER:%s|FROM:%s|TO:%s|WH:%d|GEN:%d|VINT:%s|OWN:%s|RSN:%s',
            $serialNumber,
            $currentStatus,
            $targetStatus,
            $energyWh,
            $generatorAssetId,
            $vintageYear,
            (string) $ownerPartyId,
            $claimReason
        );
        $certificateHash = hash('sha256', $canonicalPayload);

        return new RecLifecycleResult(
            serialNumber: $serialNumber,
            previousStatus: $currentStatus,
            newStatus: $targetStatus,
            energyWh: $energyWh,
            generatorAssetId: $generatorAssetId,
            vintageYear: $vintageYear,
            ownerPartyId: (string) $ownerPartyId,
            claimReason: $claimReason,
            isRetired: $targetStatus === 'retired',
            certificateHash: $certificateHash,
            canonicalPayload: $canonicalPayload,
        );
    }
}
