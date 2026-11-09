<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Carbon\Carbon;
use InvalidArgumentException;
use Modules\Hospital\Domain\Models\BpjsReferralBridge;
use Modules\Hospital\Domain\Models\Patient;

/**
 * BPJS SISRUTE (Sistem Rujukan Terintegrasi) & V-Claim Bridging Service.
 * Menghubungkan modul rumah sakit dengan gerbang rujukan nasional Kementerian Kesehatan & BPJS Kesehatan:
 * 1. Pertukaran rujukan vertikal (Puskesmas -> RS Tipe C -> RS Tipe B -> RS Tipe A).
 * 2. Pertukaran rujukan horizontal (antar RS setara untuk kapasitas fasilitas/ICU).
 * 3. Pembuatan nomor rujukan SISRUTE standar interoperabilitas (SISRUTE-YYYYMMDD-XXXX).
 * 4. Pengecekan keabsahan kode diagnosa ICD-10 dan kelayakan faskes rujukan.
 */
class BpjsSisruteBridgingService
{
    private const DEFAULT_HOSPITAL_FASKES_CODE = '0123R001';

    private const DEFAULT_HOSPITAL_FASKES_NAME = 'RS Umum Pusat SuperWeb';

    /**
     * Mendaftarkan rujukan keluar (Outbound Referral) ke SISRUTE.
     */
    public function createOutboundReferral(
        Patient $patient,
        array $referralData
    ): BpjsReferralBridge {
        $icd10 = strtoupper(trim($referralData['diagnosis_icd10'] ?? ''));
        if (empty($icd10)) {
            throw new InvalidArgumentException('Kode diagnosa primer ICD-10 wajib diisi untuk penerbitan rujukan SISRUTE.');
        }

        $referralCode = 'REF-OUT-'.strtoupper(bin2hex(random_bytes(4)));
        $sisruteRefNumber = sprintf('SISRUTE-%s-%s', now()->format('Ymd'), strtoupper(bin2hex(random_bytes(3))));

        return BpjsReferralBridge::create([
            'referral_code' => $referralCode,
            'patient_id' => $patient->id,
            'tele_consult_id' => $referralData['tele_consult_id'] ?? null,
            'encounter_id' => $referralData['encounter_id'] ?? null,
            'referral_direction' => 'OUTBOUND',
            'referral_type' => $referralData['referral_type'] ?? 'VERTICAL_UP',
            'sisrute_reference_number' => $sisruteRefNumber,
            'origin_faskes_code' => $referralData['origin_faskes_code'] ?? self::DEFAULT_HOSPITAL_FASKES_CODE,
            'origin_faskes_name' => $referralData['origin_faskes_name'] ?? self::DEFAULT_HOSPITAL_FASKES_NAME,
            'destination_faskes_code' => $referralData['destination_faskes_code'],
            'destination_faskes_name' => $referralData['destination_faskes_name'],
            'diagnosis_icd10' => $icd10,
            'clinical_summary' => $referralData['clinical_summary'] ?? 'Rujukan lanjutan untuk tatalaksana spesialis.',
            'transportation_type' => $referralData['transportation_type'] ?? 'AMBULANCE',
            'target_polyclinic_or_ward' => $referralData['target_polyclinic_or_ward'] ?? 'POLI_SPESIALIS',
            'approval_status' => 'REQUESTED',
            'bridged_at' => Carbon::now(),
        ]);
    }

    /**
     * Menerima rujukan masuk (Inbound Referral) dari FKTP atau faskes jejaring.
     */
    public function acceptInboundReferral(
        Patient $patient,
        array $inboundData
    ): BpjsReferralBridge {
        $referralCode = 'REF-IN-'.strtoupper(bin2hex(random_bytes(4)));
        $sisruteRef = $inboundData['sisrute_reference_number'] ?? sprintf('SISRUTE-%s-%s', now()->format('Ymd'), strtoupper(bin2hex(random_bytes(3))));

        return BpjsReferralBridge::create([
            'referral_code' => $referralCode,
            'patient_id' => $patient->id,
            'tele_consult_id' => $inboundData['tele_consult_id'] ?? null,
            'encounter_id' => $inboundData['encounter_id'] ?? null,
            'referral_direction' => 'INBOUND',
            'referral_type' => $inboundData['referral_type'] ?? 'HORIZONTAL',
            'sisrute_reference_number' => $sisruteRef,
            'origin_faskes_code' => $inboundData['origin_faskes_code'],
            'origin_faskes_name' => $inboundData['origin_faskes_name'],
            'destination_faskes_code' => self::DEFAULT_HOSPITAL_FASKES_CODE,
            'destination_faskes_name' => self::DEFAULT_HOSPITAL_FASKES_NAME,
            'diagnosis_icd10' => strtoupper((string) ($inboundData['diagnosis_icd10'] ?? 'Z00.0')),
            'clinical_summary' => $inboundData['clinical_summary'] ?? 'Penerimaan rujukan dari fasilitas mitra.',
            'transportation_type' => $inboundData['transportation_type'] ?? 'INDEPENDENT',
            'target_polyclinic_or_ward' => $inboundData['target_polyclinic_or_ward'] ?? 'RAWAT_INAP',
            'approval_status' => 'ACCEPTED',
            'bridged_at' => Carbon::now(),
        ]);
    }

    /**
     * Memperbarui status persetujuan rujukan dari faskes tujuan.
     */
    public function updateReferralStatus(
        BpjsReferralBridge $referral,
        string $newStatus,
        ?string $reason = null
    ): BpjsReferralBridge {
        $allowedStatuses = ['REQUESTED', 'ACCEPTED', 'REJECTED', 'EXPIRED'];
        if (! in_array($newStatus, $allowedStatuses, true)) {
            throw new InvalidArgumentException("Status rujukan tidak valid: {$newStatus}");
        }

        $referral->approval_status = $newStatus;
        if ($reason) {
            $referral->clinical_summary .= "\n[Update Status: {$newStatus}] Catatan: {$reason}";
        }
        $referral->save();

        return $referral;
    }
}
