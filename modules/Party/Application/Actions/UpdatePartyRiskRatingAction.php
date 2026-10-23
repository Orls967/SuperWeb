<?php

declare(strict_types=1);

namespace Modules\Party\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Party\Domain\Enums\PartyStatus;
use Modules\Party\Domain\Enums\RiskTier;
use Modules\Party\Domain\Models\CreditProfile;
use Modules\Party\Domain\Models\Party;
use Modules\Shared\Application\BaseAction;

class UpdatePartyRiskRatingAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): CreditProfile
    {
        return DB::transaction(function () use ($params) {
            $party = Party::findOrFail($params['party_id']);
            $tier = $params['risk_tier'] instanceof RiskTier
                ? $params['risk_tier']
                : RiskTier::from((string) $params['risk_tier']);

            $score = (int) ($params['internal_score'] ?? 500);

            $profile = CreditProfile::firstOrNew(['party_id' => $party->id]);
            $profile->risk_tier = $tier;
            $profile->internal_score = $score;
            $profile->last_scored_at = Carbon::now();

            if (isset($params['credit_limit_idr'])) {
                $profile->credit_limit_idr = (int) $params['credit_limit_idr'];
            }
            if (isset($params['exposure_breakdown'])) {
                $profile->exposure_breakdown = (array) $params['exposure_breakdown'];
            }

            $profile->save();

            if ($tier === RiskTier::Blacklisted && $party->status->canTransitionTo(PartyStatus::Blacklisted)) {
                $party->update(['status' => PartyStatus::Blacklisted]);
            }

            $this->audit(
                action: 'party_risk_rating_updated',
                auditable: $profile,
                newValues: ['party_id' => $party->id, 'risk_tier' => $tier->value, 'score' => $score],
                impactType: 'credit_risk',
            );

            return $profile;
        });
    }
}
