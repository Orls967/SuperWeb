<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Domain\Models\AwardNightPricing;
use Modules\Hotel\Domain\Models\TravelPass;
use Modules\Hotel\Domain\Models\TravelPassPointsEntry;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class RedeemGlobalLoyaltyTravelPassAcrossPropertiesAction extends BaseAction
{
    public const PTS_VALUATION_IDR = 100; // 1 PTS = 100 IDR liability

    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * Issue, redeem, or clear cross-ecosystem points with dynamic award pricing floor.
     *
     * @return array{pass: TravelPass, entry: ?TravelPassPointsEntry, award_pricing: ?AwardNightPricing, pts_balance: int}
     */
    public function handle(
        int $userId,
        string $originatingLine = 'HOTEL',
        int $ptsToEarn = 0,
        string $redeemingLine = 'VENUE',
        int $ptsToRedeem = 0,
        ?string $propertyId = null,
        float $occupancyPercent = 75.0,
        int $baseRoomCostIdr = 1000000
    ): array {
        return DB::transaction(function () use (
            $userId,
            $originatingLine,
            $ptsToEarn,
            $redeemingLine,
            $ptsToRedeem,
            $propertyId,
            $occupancyPercent,
            $baseRoomCostIdr
        ) {
            $pass = TravelPass::where('user_id', $userId)->lockForUpdate()->first();
            if (! $pass) {
                $pass = TravelPass::create([
                    'id' => (string) Str::uuid(),
                    'pass_number' => 'TP-'.strtoupper(bin2hex(random_bytes(4))),
                    'user_id' => $userId,
                    'tier' => 'SILVER',
                    'cumulative_nights' => 0,
                    'cumulative_spend_idr' => 0,
                    'pts_balance' => 0,
                ]);
            }

            $entry = null;

            // 112.2 Earn cross-ecosystem points
            if ($ptsToEarn > 0) {
                $liabilityIdr = $ptsToEarn * self::PTS_VALUATION_IDR;
                $entryId = (string) Str::uuid();

                $entry = TravelPassPointsEntry::create([
                    'id' => $entryId,
                    'travel_pass_id' => $pass->id,
                    'transaction_type' => 'EARN',
                    'originating_line' => $originatingLine,
                    'pts_amount' => $ptsToEarn,
                    'liability_value_idr' => $liabilityIdr,
                    'reference_code' => "EARN-{$originatingLine}-".date('YmdHis'),
                ]);

                $pass->increment('pts_balance', $ptsToEarn);
                $this->evaluateTier($pass);

                $this->ledgerService->post(new PostingDTO(
                    type: 'TRAVEL_PASS_PTS_EARNED',
                    description: "Cross-ecosystem PTS issuance from {$originatingLine} for pass {$pass->pass_number}",
                    idempotencyKey: "HTL-PTS-EARN-{$entryId}",
                    entries: Posting::lines()
                        ->debit('htl:loyalty_program_expense:IDR', $liabilityIdr)
                        ->credit('htl:loyalty_points_liability:IDR', $liabilityIdr)
                        ->toEntries('IDR'),
                    referenceType: 'TRAVEL_PASS_ENTRY',
                    referenceId: $entry->id,
                ));
            }

            // 112.2 Redeem cross-ecosystem points
            if ($ptsToRedeem > 0) {
                if ($ptsToRedeem > $pass->pts_balance) {
                    throw new RuntimeException("Insufficient points: Requested {$ptsToRedeem} PTS but balance is {$pass->pts_balance} PTS");
                }

                $settlementIdr = $ptsToRedeem * self::PTS_VALUATION_IDR;
                $entryId = (string) Str::uuid();

                $entry = TravelPassPointsEntry::create([
                    'id' => $entryId,
                    'travel_pass_id' => $pass->id,
                    'transaction_type' => 'REDEEM',
                    'originating_line' => $redeemingLine,
                    'pts_amount' => $ptsToRedeem,
                    'liability_value_idr' => $settlementIdr,
                    'reference_code' => "RED-{$redeemingLine}-".date('YmdHis'),
                ]);

                $pass->decrement('pts_balance', $ptsToRedeem);

                $this->ledgerService->post(new PostingDTO(
                    type: 'TRAVEL_PASS_PTS_REDEEMED',
                    description: "Cross-ecosystem PTS redemption on {$redeemingLine} for pass {$pass->pass_number}",
                    idempotencyKey: "HTL-PTS-RED-{$entryId}",
                    entries: Posting::lines()
                        ->debit('htl:loyalty_points_liability:IDR', $settlementIdr)
                        ->credit('htl:loyalty_redemption_settlement:IDR', $settlementIdr)
                        ->toEntries('IDR'),
                    referenceType: 'TRAVEL_PASS_ENTRY',
                    referenceId: $entry->id,
                ));
            }

            // 112.4 Dynamic award pricing with 115% cost floor guardrail
            $awardPricing = null;
            if ($propertyId !== null) {
                $floorCostIdr = (int) ceil($baseRoomCostIdr * 1.15);
                $floorPts = (int) ceil($floorCostIdr / self::PTS_VALUATION_IDR);
                $baseAwardPts = (int) ceil($baseRoomCostIdr / self::PTS_VALUATION_IDR);

                $occupancyMultiplier = ($occupancyPercent >= 80.0) ? 1.35 : (($occupancyPercent >= 60.0) ? 1.15 : 1.0);
                $dynamicPts = max($floorPts, (int) round($baseAwardPts * $occupancyMultiplier));

                $awardPricing = AwardNightPricing::create([
                    'id' => (string) Str::uuid(),
                    'property_id' => $propertyId,
                    'room_type' => 'DELUXE',
                    'stay_date' => date('Y-m-d'),
                    'occupancy_rate_percent' => $occupancyPercent,
                    'base_award_pts' => $baseAwardPts,
                    'dynamic_award_pts' => $dynamicPts,
                    'minimum_floor_pts' => $floorPts,
                ]);
            }

            $this->audit(
                action: 'hotel.loyalty.points_processed',
                auditable: $pass,
                oldValues: null,
                newValues: [
                    'pass_number' => $pass->pass_number,
                    'user_id' => $userId,
                    'pts_balance' => $pass->pts_balance,
                    'tier' => $pass->tier,
                ],
                correlationId: "htl_pts_{$pass->pass_number}",
                impactType: 'global_loyalty_clearing'
            );

            return [
                'pass' => $pass,
                'entry' => $entry,
                'award_pricing' => $awardPricing,
                'pts_balance' => $pass->pts_balance,
            ];
        });
    }

    protected function evaluateTier(TravelPass $pass): void
    {
        $pts = $pass->pts_balance;
        $tier = match (true) {
            $pts >= 50000 => 'BLACK',
            $pts >= 25000 => 'PLATINUM',
            $pts >= 10000 => 'GOLD',
            default => 'SILVER',
        };

        if ($pass->tier !== $tier) {
            $pass->update(['tier' => $tier]);
        }
    }
}
