<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Insurance\Domain\Models\LifePolicy;
use Modules\Insurance\Domain\Models\TakafulParticipant;

final class AntiMoneyLaunderingInsuranceService
{
    private const int HIGH_VALUE_CASH_THRESHOLD_IDR = 50000000000; // Rp 500.000.000 (50.000.000.000 sen)

    private const int RAPID_SURRENDER_DAYS_THRESHOLD = 90;

    /**
     * Memindai portofolio polis aktif untuk mendeteksi transaksi mencurigakan (AML / APUPPT).
     *
     * @return array<string, mixed>
     */
    public function scanSuspiciousTransactions(): array
    {
        $flaggedCases = [];

        // 1. Deteksi premi tunai nilai tinggi pada polis jiwa konvensional
        $highValueLifePolicies = LifePolicy::where('annual_premium_idr', '>=', self::HIGH_VALUE_CASH_THRESHOLD_IDR)->get();
        foreach ($highValueLifePolicies as $policy) {
            $flaggedCases[] = [
                'type' => 'HIGH_VALUE_PREMIUM',
                'policy_ref' => $policy->policy_number,
                'party_id' => $policy->insured_party_id,
                'amount_idr' => (int) $policy->annual_premium_idr,
                'risk_level' => 'HIGH',
                'indicator' => 'Akumulasi premi tahunan melebihi batas ambang pelaporan PPATK Rp 500.000.000.',
                'detected_at' => CarbonImmutable::now()->toIso8601String(),
            ];
        }

        // 2. Deteksi kontribusi jumbo pada asuransi syariah Takaful
        $highValueTakaful = TakafulParticipant::where('contribution_annual_idr', '>=', self::HIGH_VALUE_CASH_THRESHOLD_IDR)->get();
        foreach ($highValueTakaful as $p) {
            $flaggedCases[] = [
                'type' => 'HIGH_VALUE_TAKAFUL_CONTRIBUTION',
                'policy_ref' => $p->policy_number,
                'party_id' => $p->participant_party_id,
                'amount_idr' => (int) $p->contribution_annual_idr,
                'risk_level' => 'HIGH',
                'indicator' => 'Kontribusi tabarru dan ujrah melampaui batas ambang audit kepatuhan syariah & APUPPT.',
                'detected_at' => CarbonImmutable::now()->toIso8601String(),
            ];
        }

        // 3. Deteksi potensi rapid surrender (surrender dini dalam waktu singkat)
        $surrenderedPolicies = LifePolicy::where('status', 'surrendered')->get();
        foreach ($surrenderedPolicies as $sp) {
            if ($sp->issued_date && $sp->updated_at) {
                $issuedAt = CarbonImmutable::parse($sp->issued_date);
                $surrenderedAt = CarbonImmutable::parse($sp->updated_at);
                $holdingDays = $issuedAt->diffInDays($surrenderedAt);

                if ($holdingDays <= self::RAPID_SURRENDER_DAYS_THRESHOLD) {
                    $flaggedCases[] = [
                        'type' => 'RAPID_SURRENDER_ANOMALY',
                        'policy_ref' => $sp->policy_number,
                        'party_id' => $sp->insured_party_id,
                        'amount_idr' => (int) $sp->saving_accumulated_idr,
                        'risk_level' => 'CRITICAL',
                        'indicator' => sprintf('Polis di-surrender dalam %d hari setelah terbit (ambang batas minimum %d hari). Indikasi pencucian uang.', $holdingDays, self::RAPID_SURRENDER_DAYS_THRESHOLD),
                        'detected_at' => CarbonImmutable::now()->toIso8601String(),
                    ];
                }
            }
        }

        return [
            'scan_timestamp' => CarbonImmutable::now()->toIso8601String(),
            'total_flagged_cases' => count($flaggedCases),
            'high_risk_count' => count(array_filter($flaggedCases, fn ($c) => $c['risk_level'] === 'HIGH')),
            'critical_risk_count' => count(array_filter($flaggedCases, fn ($c) => $c['risk_level'] === 'CRITICAL')),
            'cases' => $flaggedCases,
        ];
    }
}
