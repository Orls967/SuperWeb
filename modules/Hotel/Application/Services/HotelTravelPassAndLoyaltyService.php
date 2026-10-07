<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Hotel\Domain\Models\AwardNightPricing;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\TravelPass;
use Modules\Hotel\Domain\Models\TravelPassPointsEntry;
use RuntimeException;

class HotelTravelPassAndLoyaltyService
{
    public const PTS_VALUATION_IDR = 100; // 1 PTS = 100 IDR liability

    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 112.1 Register Travel Pass
     */
    public function registerTravelPass(int $userId): TravelPass
    {
        return TravelPass::create([
            'id' => (string) Str::uuid(),
            'pass_number' => 'TP-'.strtoupper(bin2hex(random_bytes(4))),
            'user_id' => $userId,
            'tier' => 'SILVER',
            'cumulative_nights' => 0,
            'cumulative_spend_idr' => 0,
            'pts_balance' => 0,
        ]);
    }

    /**
     * 112.2 Earn Cross-Ecosystem Points and Accrue PTS Liability
     */
    public function earnPoints(
        TravelPass $pass,
        string $originatingLine,
        int $ptsAmount,
        string $referenceCode
    ): TravelPassPointsEntry {
        return DB::transaction(function () use ($pass, $originatingLine, $ptsAmount, $referenceCode) {
            $liabilityIdr = $ptsAmount * self::PTS_VALUATION_IDR;

            $entry = TravelPassPointsEntry::create([
                'id' => (string) Str::uuid(),
                'travel_pass_id' => $pass->id,
                'transaction_type' => 'EARN',
                'originating_line' => $originatingLine,
                'pts_amount' => $ptsAmount,
                'liability_value_idr' => $liabilityIdr,
                'reference_code' => $referenceCode,
            ]);

            $pass->increment('pts_balance', $ptsAmount);

            // Re-evaluate tier
            $this->evaluateTier($pass);

            // Post PTS liability into Ledger: Debit Loyalty Expense, Credit PTS Liability
            $this->ledgerService->post(new PostingDTO(
                type: 'TRAVEL_PASS_PTS_EARNED',
                description: "Cross-ecosystem PTS issuance from {$originatingLine} for pass {$pass->pass_number}",
                idempotencyKey: "HTL-PTS-EARN-{$entry->id}",
                entries: [
                    PostingEntryDTO::forCode('htl:loyalty_program_expense:IDR', 'IDR', $liabilityIdr),
                    PostingEntryDTO::forCode('htl:loyalty_points_liability:IDR', 'IDR', -$liabilityIdr),
                ],
                referenceType: 'TRAVEL_PASS_ENTRY',
                referenceId: $entry->id,
            ));

            return $entry;
        });
    }

    /**
     * 112.2 Redeem Points and Settle PTS Liability
     */
    public function redeemPoints(
        TravelPass $pass,
        string $redeemingLine,
        int $ptsAmount,
        string $referenceCode
    ): TravelPassPointsEntry {
        if ($ptsAmount > $pass->pts_balance) {
            throw new RuntimeException("Insufficient points: Requested {$ptsAmount} PTS but balance is {$pass->pts_balance} PTS");
        }

        return DB::transaction(function () use ($pass, $redeemingLine, $ptsAmount, $referenceCode) {
            $liabilityIdr = $ptsAmount * self::PTS_VALUATION_IDR;

            $entry = TravelPassPointsEntry::create([
                'id' => (string) Str::uuid(),
                'travel_pass_id' => $pass->id,
                'transaction_type' => 'REDEEM',
                'originating_line' => $redeemingLine,
                'pts_amount' => -$ptsAmount,
                'liability_value_idr' => -$liabilityIdr,
                'reference_code' => $referenceCode,
            ]);

            $pass->decrement('pts_balance', $ptsAmount);

            // Discharge PTS liability in Ledger: Debit PTS Liability, Credit Vendor/Revenue
            $this->ledgerService->post(new PostingDTO(
                type: 'TRAVEL_PASS_PTS_REDEEMED',
                description: "Cross-ecosystem PTS redemption at {$redeemingLine} for pass {$pass->pass_number}",
                idempotencyKey: "HTL-PTS-RED-{$entry->id}",
                entries: [
                    PostingEntryDTO::forCode('htl:loyalty_points_liability:IDR', 'IDR', $liabilityIdr),
                    PostingEntryDTO::forCode('htl:loyalty_redemption_settlement:IDR', 'IDR', -$liabilityIdr),
                ],
                referenceType: 'TRAVEL_PASS_ENTRY',
                referenceId: $entry->id,
            ));

            return $entry;
        });
    }

    /**
     * 112.4 Dynamic Award Room Pricing with Non-Negative Floor Guardrail
     */
    public function calculateDynamicAward(
        HotelProperty $property,
        string $roomType,
        string $stayDate,
        float $occupancyPercent,
        int $basePts = 25000,
        int $floorPts = 15000
    ): AwardNightPricing {
        // Multiplier: low occupancy (40%) -> 0.8x base, high occupancy (90%) -> 1.5x base
        $multiplier = max(0.6, min(2.0, 0.5 + ($occupancyPercent / 100.0)));
        $calculatedPts = (int) round($basePts * $multiplier);

        // Guardrail: never below minimum floor points!
        $finalPts = max($floorPts, $calculatedPts);

        return AwardNightPricing::create([
            'id' => (string) Str::uuid(),
            'property_id' => $property->id,
            'room_type' => $roomType,
            'stay_date' => $stayDate,
            'occupancy_rate_percent' => $occupancyPercent,
            'base_award_pts' => $basePts,
            'dynamic_award_pts' => $finalPts,
            'minimum_floor_pts' => $floorPts,
        ]);
    }

    protected function evaluateTier(TravelPass $pass): void
    {
        $nights = $pass->cumulative_nights;
        $spend = $pass->cumulative_spend_idr;

        if ($nights >= 50 || $spend >= 100_000_000) {
            $pass->tier = 'BLACK';
        } elseif ($nights >= 25 || $spend >= 50_000_000) {
            $pass->tier = 'PLATINUM';
        } elseif ($nights >= 10 || $spend >= 15_000_000) {
            $pass->tier = 'GOLD';
        } else {
            $pass->tier = 'SILVER';
        }

        $pass->save();
    }
}
