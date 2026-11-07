<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Domain\Models\HtlTravelPass;
use Modules\Hotel\Domain\Models\HtlTravelPassPointBatch;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use RuntimeException;

final class HotelGamificationChallengeService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * @return array{
     *     reward_id: string,
     *     travel_pass_id: string,
     *     quest_code: string,
     *     quest_name: string,
     *     bonus_points_awarded: int,
     *     badge_unlocked: string,
     *     awarded_at: string
     * }
     */
    public function awardQuestCompletionBonus(
        string $travelPassId,
        string $questCode,
        string $questName,
        int $bonusPoints,
        string $badgeTitle = 'Explorer of Archipelago'
    ): array {
        if ($bonusPoints <= 0) {
            throw new RuntimeException("Bonus poin quest harus bernilai positif: {$bonusPoints}");
        }

        return DB::transaction(function () use (
            $travelPassId,
            $questCode,
            $questName,
            $bonusPoints,
            $badgeTitle
        ) {
            $pass = HtlTravelPass::where('id', $travelPassId)->lockForUpdate()->firstOrFail();

            $batch = HtlTravelPassPointBatch::create([
                'travel_pass_id' => $pass->id,
                'batch_number' => 'QUEST-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(2)),
                'originating_line' => 'HOTEL_GAMIFICATION_QUEST',
                'initial_pts' => $bonusPoints,
                'remaining_pts' => $bonusPoints,
                'liability_per_pt_idr' => 100,
                'total_liability_idr' => $bonusPoints * 100,
                'state' => 'ACTIVE',
                'reference_code' => "QUEST-{$questCode}",
                'earned_at' => now(),
                'expires_at' => now()->addYears(2),
            ]);

            $pass->increment('pts_balance', $bonusPoints);
            $pass->refreshTier();

            // Append badge to metadata
            $meta = $pass->metadata ?? [];
            $badges = $meta['badges'] ?? [];
            $badges[] = [
                'badge' => $badgeTitle,
                'quest_code' => $questCode,
                'unlocked_at' => now()->toIso8601String(),
            ];
            $meta['badges'] = $badges;
            $pass->update(['metadata' => $meta]);

            $rewardId = 'QUEST-RWD-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(3));
            $liabilityIdr = $bonusPoints * 100;

            // Ledger posting
            $this->ledgerService->post(new PostingDTO(
                type: HotelTransactionTypes::TRAVEL_PASS_PTS_EARNED->value,
                description: "Gamification quest bonus: {$questName} ({$questCode}) for {$pass->pass_number}",
                idempotencyKey: "HTL-QUEST-{$rewardId}",
                entries: Posting::lines()
                    ->debit('htl:loyalty_program_expense:IDR', $liabilityIdr)
                    ->credit('htl:loyalty_points_liability:IDR', $liabilityIdr)
                    ->toEntries('IDR'),
                referenceType: 'GAMIFICATION_QUEST',
                referenceId: $rewardId,
            ));

            return [
                'reward_id' => $rewardId,
                'travel_pass_id' => $pass->id,
                'quest_code' => $questCode,
                'quest_name' => $questName,
                'bonus_points_awarded' => $bonusPoints,
                'badge_unlocked' => $badgeTitle,
                'awarded_at' => now()->toIso8601String(),
            ];
        });
    }
}
