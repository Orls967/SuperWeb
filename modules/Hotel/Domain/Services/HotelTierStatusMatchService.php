<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Hotel\Domain\Models\HtlTravelPass;

final class HotelTierStatusMatchService
{
    /**
     * @return array{
     *     match_request_id: string,
     *     travel_pass_id: string,
     *     competitor_program: string,
     *     competitor_tier: string,
     *     granted_tier: string,
     *     trial_expiry: string,
     *     status: string
     * }
     */
    public function grantStatusMatchChallenge(
        string $travelPassId,
        string $competitorProgram,
        string $competitorTier,
        string $proofDocumentReference,
        string $reviewedByStaffReference
    ): array {
        return DB::transaction(function () use (
            $travelPassId,
            $competitorProgram,
            $competitorTier,
            $proofDocumentReference,
            $reviewedByStaffReference
        ) {
            $pass = HtlTravelPass::where('id', $travelPassId)->lockForUpdate()->firstOrFail();

            // Map competitor tier to equivalent internal tier
            $grantedTier = match (strtoupper($competitorTier)) {
                'DIAMOND', 'TITANIUM', 'AMBASSADOR', 'BLACK' => 'BLACK',
                'PLATINUM', 'GOLD_ELITE' => 'PLATINUM',
                'GOLD', 'SILVER_PLUS' => 'GOLD',
                default => 'SILVER',
            };

            $trialPeriodDays = 90; // 90 days challenge window
            $trialExpiry = now()->addDays($trialPeriodDays);

            $currentTier = $pass->tier;
            $pass->update([
                'tier' => $grantedTier,
                'metadata' => array_merge($pass->metadata ?? [], [
                    'status_match' => [
                        'competitor_program' => $competitorProgram,
                        'competitor_tier' => $competitorTier,
                        'previous_tier' => $currentTier,
                        'proof_document' => $proofDocumentReference,
                        'approved_by' => $reviewedByStaffReference,
                        'challenge_start' => now()->toIso8601String(),
                        'challenge_expiry' => $trialExpiry->toIso8601String(),
                        'required_nights_to_retain' => $grantedTier === 'BLACK' ? 15 : 8,
                    ],
                ]),
            ]);

            $requestId = 'SM-'.now()->format('Ymd').'-'.bin2hex(random_bytes(3));

            return [
                'match_request_id' => $requestId,
                'travel_pass_id' => $pass->id,
                'competitor_program' => $competitorProgram,
                'competitor_tier' => $competitorTier,
                'granted_tier' => $grantedTier,
                'trial_expiry' => $trialExpiry->toIso8601String(),
                'status' => 'APPROVED_IN_CHALLENGE',
            ];
        });
    }
}
