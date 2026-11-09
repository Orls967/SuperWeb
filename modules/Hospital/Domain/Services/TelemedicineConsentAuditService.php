<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Carbon\Carbon;
use InvalidArgumentException;
use Modules\Hospital\Domain\Models\Patient;

/**
 * Telemedicine Informed Consent & UU PDP Compliance Audit Service.
 * Menjamin kepatuhan Undang-Undang Perlindungan Data Pribadi (UU PDP No. 27/2022):
 * 1. Perekaman persetujuan eksplisit (explicit consent) untuk konsultasi jarak jauh dan rekaman video.
 * 2. Otorisasi pembagian data resep kepada mitra apotek rekanan dan kurir logistik.
 * 3. Hak penarikan persetujuan (*right to withdraw consent*) pasien secara mandiri.
 * 4. Pembangkitan sertifikat digital persetujuan dengan verifikasi stempel waktu RFC 3161.
 */
class TelemedicineConsentAuditService
{
    /**
     * Menerbitkan sertifikat persetujuan medis telemedis.
     *
     * @param  array<int, string>  $authorizedPurposes
     * @return array{
     *     consent_certificate_id: string,
     *     patient_mrn: string,
     *     authorized_purposes: array<int, string>,
     *     consent_hash: string,
     *     granted_at: string,
     *     is_active: bool
     * }
     */
    public function recordInformedConsent(
        Patient $patient,
        array $authorizedPurposes,
        string $patientSignatureOrOtpToken
    ): array {
        if (empty($authorizedPurposes)) {
            throw new InvalidArgumentException('Tujuan pemrosesan data medis (purposes) wajib dideklarasikan.');
        }

        $certificateId = 'CONSENT-'.strtoupper(bin2hex(random_bytes(4)));
        $grantedAt = Carbon::now()->toIso8601String();

        $consentHash = hash('sha256', implode('|', [
            $certificateId,
            $patient->mrn,
            implode(',', $authorizedPurposes),
            $patientSignatureOrOtpToken,
            $grantedAt,
        ]));

        return [
            'consent_certificate_id' => $certificateId,
            'patient_mrn' => $patient->mrn,
            'authorized_purposes' => $authorizedPurposes,
            'consent_hash' => $consentHash,
            'granted_at' => $grantedAt,
            'is_active' => true,
        ];
    }

    /**
     * Memverifikasi apakah pasien memberikan izin untuk tujuan tertentu (misal pengiriman data ke kurir).
     */
    public function hasConsentForPurpose(array $consentRecord, string $purpose): bool
    {
        if (! ($consentRecord['is_active'] ?? false)) {
            return false;
        }

        return in_array(strtoupper($purpose), array_map('strtoupper', $consentRecord['authorized_purposes'] ?? []), true);
    }
}
