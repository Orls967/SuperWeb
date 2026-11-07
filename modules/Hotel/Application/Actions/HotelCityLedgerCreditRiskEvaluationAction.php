<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\HtlCorporateAccount;

class HotelCityLedgerCreditRiskEvaluationAction
{
    /**
     * Mengevaluasi profil risiko kredit akun korporat berdasarkan rasio penyerapan kredit dan riwayat keterlambatan.
     *
     * @return array{corporate_id: string, company_name: string, utilization_pct: float, risk_tier: string, recommended_credit_limit_idr: int, action_required: string}
     */
    public function execute(string $corporateAccountId): array
    {
        $corp = HtlCorporateAccount::findOrFail($corporateAccountId);
        $creditLimit = (int) $corp->credit_limit_idr;
        $outstanding = (int) $corp->current_outstanding_idr;

        $utilizationPct = $creditLimit > 0 ? round(($outstanding / $creditLimit) * 100, 2) : 0;

        $riskTier = 'LOW';
        $recommendedLimit = $creditLimit;
        $action = 'MAINTAIN_CURRENT_TERMS';

        if ($utilizationPct > 90.0) {
            $riskTier = 'CRITICAL';
            $action = 'SUSPEND_NEW_BOOKINGS';
        } elseif ($utilizationPct > 75.0) {
            $riskTier = 'ELEVATED';
            $action = 'REQUEST_INTERIM_PAYMENT';
        } elseif ($utilizationPct < 30.0 && $outstanding > 0) {
            $riskTier = 'PRIME_EXCELLENT';
            $recommendedLimit = (int) round($creditLimit * 1.25); // Naikkan 25%
            $action = 'OFFER_CREDIT_EXPANSION';
        }

        return [
            'corporate_id' => $corp->id,
            'company_name' => $corp->company_name,
            'utilization_pct' => $utilizationPct,
            'risk_tier' => $riskTier,
            'recommended_credit_limit_idr' => $recommendedLimit,
            'action_required' => $action,
        ];
    }
}
