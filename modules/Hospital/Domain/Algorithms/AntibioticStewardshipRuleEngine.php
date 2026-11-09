<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Algorithms;

final class AntibioticStewardshipRuleEngine
{
    /**
     * Memvalidasi peresepan antimikroba rumah sakit berdasarkan klasifikasi AWaRe WHO
     * (Access, Watch, Reserve) dan aturan kepatuhan Program Pengendalian Resistensi Antimikroba (PPRA).
     *
     * @param array{
     *     antibiotic_code: string,
     *     antibiotic_name: string,
     *     indication_type: string, // PROPHYLAXIS_SURGICAL, EMPIRIC, DEFINITIVE
     *     duration_days: int,
     *     culture_sensitivity_tested: bool,
     *     ppra_committee_approval: bool
     * } $prescription
     * @return array{
     *     aware_category: string, // ACCESS, WATCH, RESERVE
     *     is_prescription_allowed: bool,
     *     requires_prior_authorization: bool,
     *     blocking_reasons: list<string>,
     *     stewardship_audit_flags: list<string>,
     *     certificate: array{
     *         algorithm: string,
     *         version: string,
     *         input_hash: string,
     *         signature: string
     *     }
     * }
     */
    public function evaluate(array $prescription): array
    {
        $code = strtoupper((string) ($prescription['antibiotic_code'] ?? ''));
        $indication = strtoupper((string) ($prescription['indication_type'] ?? 'EMPIRIC'));
        $duration = (int) ($prescription['duration_days'] ?? 1);
        $cultureTested = ! empty($prescription['culture_sensitivity_tested']);
        $ppraApproved = ! empty($prescription['ppra_committee_approval']);

        $blockingReasons = [];
        $auditFlags = [];

        // Penentuan Kategori AWaRe
        $awareCategory = match (true) {
            in_array($code, ['COLISTIN', 'LINEZOLID', 'MEROPENEM_VABORBACTAM', 'TIGECYCLINE', 'CEFTAZIDIME_AVIBACTAM']) => 'RESERVE',
            in_array($code, ['MEROPENEM', 'VANCOMYCIN', 'CEFTRIAXONE', 'CIPROFLOXACIN', 'LEVOFLOXACIN', 'CEFOTAXIME']) => 'WATCH',
            default => 'ACCESS', // Amoxicillin, Ampicillin, Cefazolin, Metronidazole, Gentamicin
        };

        // Aturan Kategori RESERVE: Wajib Persetujuan PPRA & Bukti Kultur
        if ($awareCategory === 'RESERVE') {
            if (! $ppraApproved) {
                $blockingReasons[] = "Antibiotik kategori RESERVE ({$code}) wajib memperoleh persetujuan tertulis Komite PPRA.";
            }
            if (! $cultureTested) {
                $blockingReasons[] = 'Antibiotik kategori RESERVE wajib didukung hasil uji kepekaan kultur mikroorganisme.';
            }
            $auditFlags[] = 'HIGH_PRIORITY_ANTIMICROBIAL_STEWARDSHIP_AUDIT';
        }

        // Aturan Profilaksis Bedah: Maksimal 24 Jam
        if ($indication === 'PROPHYLAXIS_SURGICAL') {
            if ($duration > 1) {
                $blockingReasons[] = "Profilaksis bedah tidak boleh melebihi 24 jam (durasi diminta: {$duration} hari).";
            }
            if ($awareCategory === 'RESERVE') {
                $blockingReasons[] = 'Antibiotik kategori RESERVE dilarang digunakan untuk profilaksis bedah rutin.';
            }
        }

        // Aturan WATCH Terapi Empiris: Re-evaluasi 72 jam
        if ($awareCategory === 'WATCH' && $duration > 3 && ! $cultureTested) {
            $auditFlags[] = '72_HOUR_ANTIBIOTIC_TIME_OUT_EVALUATION_REQUIRED';
        }

        $isAllowed = count($blockingReasons) === 0;
        $requiresAuth = ($awareCategory === 'RESERVE') || ($awareCategory === 'WATCH' && $duration > 7);

        $inputSerialized = json_encode($prescription, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $inputHash = hash('sha256', $inputSerialized);
        $sigPayload = "ASRE-V1:{$code}:{$awareCategory}:".($isAllowed ? '1' : '0').":{$inputHash}";
        $signature = hash_hmac('sha256', $sigPayload, 'HSP_SECRET_ANTIBIOTIC_STEWARDSHIP_2026');

        return [
            'aware_category' => $awareCategory,
            'is_prescription_allowed' => $isAllowed,
            'requires_prior_authorization' => $requiresAuth,
            'blocking_reasons' => $blockingReasons,
            'stewardship_audit_flags' => $auditFlags,
            'certificate' => [
                'algorithm' => 'AntibioticStewardshipRuleEngine',
                'version' => '1.0.0',
                'input_hash' => $inputHash,
                'signature' => $signature,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $originalPrescription
     */
    public function verifyCertificate(array $result, array $originalPrescription): bool
    {
        $inputSerialized = json_encode($originalPrescription, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $inputHash = hash('sha256', $inputSerialized);

        $cert = $result['certificate'] ?? [];
        if (($cert['input_hash'] ?? '') !== $inputHash) {
            return false;
        }

        $code = strtoupper((string) ($originalPrescription['antibiotic_code'] ?? ''));
        $cat = $result['aware_category'] ?? '';
        $allowed = ! empty($result['is_prescription_allowed']) ? '1' : '0';

        $expectedSigPayload = "ASRE-V1:{$code}:{$cat}:{$allowed}:{$inputHash}";
        $expectedSig = hash_hmac('sha256', $expectedSigPayload, 'HSP_SECRET_ANTIBIOTIC_STEWARDSHIP_2026');

        return hash_equals($expectedSig, (string) ($cert['signature'] ?? ''));
    }
}
