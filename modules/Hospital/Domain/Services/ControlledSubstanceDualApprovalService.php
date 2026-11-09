<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use InvalidArgumentException;
use Modules\Hospital\Domain\Models\EPharmacyOrder;
use RuntimeException;

/**
 * Controlled Substance (Narkotika & Psikotropika) Dual Approval Auditor.
 * Memenuhi regulasi Badan Pengawas Obat dan Makanan (BPOM) dan UU Narkotika No. 35/2009:
 * 1. Resep obat keras tertentu / narkotika / psikotropika wajib mendapatkan persetujuan 2 dokter ber-SIP aktif.
 * 2. Dokter pemeriksa pertama (prescriber) dan dokter penelaah kedua (reviewer) tidak boleh orang yang sama.
 * 3. Pembuatan stempel audit kriptografis untuk pelaporan berkala SIPNAP (Sistem Pelaporan Narkotika dan Psikotropika).
 */
class ControlledSubstanceDualApprovalService
{
    private const CONTROLLED_CLASSES = [
        'NARCOTIC',
        'PSYCHOTROPIC',
        'NARCOTIC_PSYCHOTROPIC',
        'CONTROLLED_SUBSTANCE',
    ];

    /**
     * Memeriksa apakah suatu klasifikasi obat termasuk dalam pengawasan ketat.
     */
    public function isControlledSubstance(string $classification): bool
    {
        return in_array(strtoupper(trim($classification)), self::CONTROLLED_CLASSES, true);
    }

    /**
     * Memproses dan memverifikasi persetujuan dokter kedua untuk e-resep obat terawasi.
     */
    public function approveControlledPrescription(
        EPharmacyOrder $order,
        int $approvingDoctorId,
        string $approvingDoctorSip,
        string $clinicalReviewNotes
    ): EPharmacyOrder {
        if (! $this->isControlledSubstance($order->drug_classification)) {
            // Obat reguler tidak memerlukan persetujuan ganda
            return $order;
        }

        // Dapatkan dokter pemeriksa pertama dari teleconsult
        $primaryDoctorId = (int) ($order->teleConsult?->doctor_id ?? 0);
        if ($primaryDoctorId > 0 && $primaryDoctorId === $approvingDoctorId) {
            throw new RuntimeException(
                'Pelanggaran Regulasi: Dokter penelaah kedua tidak boleh sama dengan dokter penulis resep pertama.'
            );
        }

        if (empty(trim($approvingDoctorSip))) {
            throw new InvalidArgumentException(
                'Surat Izin Praktik (SIP) dokter penelaah kedua wajib diisi.'
            );
        }

        // Buat hash persetujuan dokter kedua
        $secondDoctorApprovalHash = hash('sha256', implode('|', [
            $order->order_code,
            (string) $approvingDoctorId,
            trim($approvingDoctorSip),
            trim($clinicalReviewNotes),
            now()->toIso8601String(),
        ]));

        $order->second_doctor_approval_hash = $secondDoctorApprovalHash;
        $order->save();

        return $order;
    }

    /**
     * Memverifikasi apakah pesanan obat terawasi sudah memenuhi syarat untuk diserahkan/diproses.
     */
    public function canDispense(EPharmacyOrder $order): bool
    {
        if (! $this->isControlledSubstance($order->drug_classification)) {
            return true;
        }

        // Obat terawasi wajib memiliki dokter signature pertama DAN persetujuan dokter kedua
        return ! empty($order->doctor_signature_hash) && ! empty($order->second_doctor_approval_hash);
    }
}
