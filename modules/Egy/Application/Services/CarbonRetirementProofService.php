<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use Modules\Egy\Domain\Models\CarbonCreditBatch;
use Modules\Egy\Domain\Models\RecCertificate;
use Modules\Egy\Domain\StateMachines\RecCertificateStateMachine;
use RuntimeException;

/**
 * Layanan Pembuktian Kriptografis Pensiun Kredit Karbon & Sertifikat REC.
 * Memastikan tidak ada klaim ganda (anti double-counting) pada transaksi ESG.
 */
final class CarbonRetirementProofService
{
    /**
     * Verifikasi keabsahan bukti pensiun permanen sertifikat REC.
     *
     * @return array{verified: bool, certificate_number: string, beneficiary: string, mwh: int, registry_proof: string}
     */
    public function verifyRecRetirement(string $certificateNumber): array
    {
        $rec = RecCertificate::where('certificate_number', $certificateNumber)->first();
        if ($rec === null) {
            throw new RuntimeException("Sertifikat REC [#{$certificateNumber}] tidak ditemukan dalam basis data.");
        }

        if ($rec->status !== RecCertificateStateMachine::STATUS_RETIRED) {
            throw new RuntimeException("Sertifikat REC [#{$certificateNumber}] belum dipensiunkan (status: {$rec->status}).");
        }

        if (empty($rec->beneficiary_entity) || empty($rec->retirement_reason)) {
            throw new RuntimeException("Metadata penerima manfaat atau alasan pensiun REC [#{$certificateNumber}] tidak lengkap.");
        }

        $canonicalProof = sprintf(
            'REC-PROOF|NUM:%s|GEN:%s|MWH:%d|BENEFICIARY:%s|REASON:%s|ORIGINAL_HASH:%s',
            $rec->certificate_number,
            $rec->generator_asset_code,
            $rec->mwh_amount,
            $rec->beneficiary_entity,
            $rec->retirement_reason,
            $rec->certificate_hash
        );

        $proofHash = hash('sha256', $canonicalProof);

        return [
            'verified' => true,
            'certificate_number' => $rec->certificate_number,
            'beneficiary' => $rec->beneficiary_entity,
            'mwh' => (int) $rec->mwh_amount,
            'registry_proof' => $proofHash,
        ];
    }

    /**
     * Verifikasi integritas batch kredit karbon terdaftar.
     *
     * @return array{verified: bool, batch_number: string, project_code: string, remaining_kg: int, total_kg: int}
     */
    public function verifyCarbonBatchIntegrity(string $batchNumber): array
    {
        $batch = CarbonCreditBatch::where('batch_number', $batchNumber)->first();
        if ($batch === null) {
            throw new RuntimeException("Batch kredit karbon [#{$batchNumber}] tidak ditemukan.");
        }

        $totalKg = (int) $batch->total_credits_kg_co2e;
        $availableKg = (int) $batch->available_credits_kg_co2e;
        $retiredKg = (int) $batch->retired_kg_co2e;

        if ($availableKg + $retiredKg !== $totalKg) {
            throw new RuntimeException("Diskrepansi integritas batch karbon [#{$batchNumber}]: available + retired !== total.");
        }

        return [
            'verified' => true,
            'batch_number' => $batch->batch_number,
            'project_code' => $batch->project->project_code,
            'remaining_kg' => $availableKg,
            'total_kg' => $totalKg,
        ];
    }
}
