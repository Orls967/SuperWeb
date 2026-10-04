<?php

declare(strict_types=1);

namespace Modules\Party\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Party\Domain\Enums\RiskTier;
use Modules\Party\Domain\Models\CreditProfile;
use Modules\Party\Domain\Models\Party;

/**
 * CreditScoringService — simulated internal credit scoring.
 * Score 0-100, risk tier derived from score + exposure ratio.
 */
class CreditScoringService
{
    /**
     * Recalculate score and exposure for a party.
     * Aggregates exposure from Logistics (lgx_shipper_accounts), Mall (mall_leases), etc.
     */
    public function score(Party $party): CreditProfile
    {
        $profile = $party->creditProfile ?? CreditProfile::create([
            'party_id' => $party->id,
            'credit_limit_idr' => 0,
            'internal_score' => 50,
            'risk_tier' => RiskTier::Medium->value,
        ]);

        return DB::transaction(function () use ($party, $profile) {
            $profile->lockForUpdate();

            $breakdown = $this->gatherExposure($party);
            $totalExposure = array_sum($breakdown);

            // Score factors:
            // - KYB verified: +20
            // - Sanctions clear: +15
            // - Active roles: +5 per role (max 20)
            // - KYC documents approved: +5 per doc (max 20)
            // - Exposure vs limit ratio: -30 if > 80%
            $score = 50;
            if ($party->kyb_status->value === 'verified') {
                $score += 20;
            }

            $latestCheck = $party->latestSanctionCheck();
            if ($latestCheck && $latestCheck->isClear()) {
                $score += 15;
            }

            $activeRoles = $party->roles()->where('is_active', true)->count();
            $score += min(20, $activeRoles * 5);

            $approvedDocs = $party->kycDocuments()->where('status', 'approved')->count();
            $score += min(20, $approvedDocs * 5);

            // Deduct for high exposure
            $utilization = $profile->credit_limit_idr > 0
                ? ($totalExposure / $profile->credit_limit_idr) * 100
                : 0;
            if ($utilization > 80) {
                $score -= 30;
            }

            $score = max(0, min(100, $score));

            $tier = match (true) {
                $party->status->value === 'blacklisted' => RiskTier::Blacklisted,
                $score >= 75 => RiskTier::Low,
                $score >= 50 => RiskTier::Medium,
                default => RiskTier::High,
            };

            $profile->update([
                'internal_score' => $score,
                'risk_tier' => $tier->value,
                'current_exposure_idr' => $totalExposure,
                'exposure_breakdown' => $breakdown,
                'last_scored_at' => now(),
            ]);

            return $profile->fresh();
        });
    }

    /**
     * Gather exposure from linked modules (via party_id backlinks).
     *
     * @return array<string, int>
     */
    private function gatherExposure(Party $party): array
    {
        $breakdown = [];

        // Logistics shipper account outstanding
        $lgxExposure = DB::table('lgx_shipper_accounts')
            ->where('party_id', $party->id)
            ->sum('current_balance_idr');
        if ($lgxExposure > 0) {
            $breakdown['logistics'] = (int) $lgxExposure;
        }

        // Mall tenant outstanding invoices
        $mallExposure = DB::table('mall_invoices')
            ->join('mall_tenants', 'mall_invoices.tenant_id', '=', 'mall_tenants.id')
            ->where('mall_tenants.party_id', $party->id)
            ->whereIn('mall_invoices.status', ['issued', 'partially_paid', 'overdue'])
            ->sum('mall_invoices.total_amount');
        if ($mallExposure > 0) {
            $breakdown['mall'] = (int) $mallExposure;
        }

        return $breakdown;
    }
}
