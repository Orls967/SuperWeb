<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Hotel\Domain\Models\HtlTravelPass;
use Modules\Hotel\Domain\Models\HtlTravelPassPointBatch;
use RuntimeException;

final class HotelLoyaltyFamilyPoolingService
{
    public const MAX_FAMILY_MEMBERS = 6;

    public const MIN_CONTRIBUTION_POINTS = 500;

    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * @return array{
     *     pool_id: string,
     *     head_pass_id: string,
     *     pool_name: string,
     *     total_members: int,
     *     status: string,
     *     created_at: string
     * }
     */
    public function createFamilyPool(
        string $headTravelPassId,
        string $familyPoolName
    ): array {
        return DB::transaction(function () use ($headTravelPassId, $familyPoolName) {
            $head = HtlTravelPass::where('id', $headTravelPassId)->lockForUpdate()->firstOrFail();

            if (in_array($head->tier, ['SILVER'], true)) {
                throw new RuntimeException('Pembuatan Family Pool hanya dapat diinisiasi oleh anggota Gold, Platinum, atau Black Tier.');
            }

            $meta = $head->metadata ?? [];
            if (isset($meta['family_pool'])) {
                throw new RuntimeException('Anggota ini sudah memiliki Family Pool aktif.');
            }

            $poolId = 'FAMP-'.now()->format('Ymd').'-'.bin2hex(random_bytes(3));

            $meta['family_pool'] = [
                'pool_id' => $poolId,
                'name' => $familyPoolName,
                'role' => 'HEAD',
                'created_at' => now()->toIso8601String(),
                'members' => [
                    [
                        'pass_id' => $head->id,
                        'pass_number' => $head->pass_number,
                        'relationship' => 'HEAD',
                        'joined_at' => now()->toIso8601String(),
                    ],
                ],
            ];

            $head->update(['metadata' => $meta]);

            return [
                'pool_id' => $poolId,
                'head_pass_id' => $head->id,
                'pool_name' => $familyPoolName,
                'total_members' => 1,
                'status' => 'ACTIVE',
                'created_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * @return array{
     *     pool_id: string,
     *     new_member_pass_id: string,
     *     relationship: string,
     *     total_members: int,
     *     joined_at: string
     * }
     */
    public function addMemberToFamilyPool(
        string $headTravelPassId,
        string $newMemberPassId,
        string $relationship = 'SPOUSE'
    ): array {
        return DB::transaction(function () use ($headTravelPassId, $newMemberPassId, $relationship) {
            $head = HtlTravelPass::where('id', $headTravelPassId)->lockForUpdate()->firstOrFail();
            $member = HtlTravelPass::where('id', $newMemberPassId)->lockForUpdate()->firstOrFail();

            $meta = $head->metadata ?? [];
            if (! isset($meta['family_pool'])) {
                throw new RuntimeException('Family pool tidak ditemukan pada akun kepala keluarga.');
            }

            $pool = $meta['family_pool'];
            $existingMembers = $pool['members'] ?? [];

            if (count($existingMembers) >= self::MAX_FAMILY_MEMBERS) {
                throw new RuntimeException('Jumlah anggota Family Pool telah mencapai batas maksimal ('.self::MAX_FAMILY_MEMBERS.' orang).');
            }

            foreach ($existingMembers as $em) {
                if ($em['pass_id'] === $member->id) {
                    throw new RuntimeException('Anggota ini sudah terdaftar di dalam Family Pool.');
                }
            }

            $existingMembers[] = [
                'pass_id' => $member->id,
                'pass_number' => $member->pass_number,
                'relationship' => $relationship,
                'joined_at' => now()->toIso8601String(),
            ];

            $pool['members'] = $existingMembers;
            $meta['family_pool'] = $pool;
            $head->update(['metadata' => $meta]);

            // Link member to head pool
            $memberMeta = $member->metadata ?? [];
            $memberMeta['family_pool_link'] = [
                'pool_id' => $pool['pool_id'],
                'head_pass_id' => $head->id,
                'relationship' => $relationship,
            ];
            $member->update(['metadata' => $memberMeta]);

            return [
                'pool_id' => $pool['pool_id'],
                'new_member_pass_id' => $member->id,
                'relationship' => $relationship,
                'total_members' => count($existingMembers),
                'joined_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * @return array{
     *     contribution_id: string,
     *     member_pass_id: string,
     *     head_pass_id: string,
     *     points_contributed: int,
     *     contributed_at: string
     * }
     */
    public function contributePointsToPool(
        string $memberPassId,
        string $headPassId,
        int $pointsToContribute
    ): array {
        if ($pointsToContribute < self::MIN_CONTRIBUTION_POINTS) {
            throw new RuntimeException('Minimal kontribusi poin ke pool adalah '.self::MIN_CONTRIBUTION_POINTS.' PTS');
        }

        return DB::transaction(function () use ($memberPassId, $headPassId, $pointsToContribute) {
            $member = HtlTravelPass::where('id', $memberPassId)->lockForUpdate()->firstOrFail();
            $head = HtlTravelPass::where('id', $headPassId)->lockForUpdate()->firstOrFail();

            if ($member->pts_balance < $pointsToContribute) {
                throw new RuntimeException("Saldo poin anggota tidak mencukupi: Saldo {$member->pts_balance} PTS, kontribusi {$pointsToContribute} PTS");
            }

            $member->decrement('pts_balance', $pointsToContribute);

            // Re-assign batches
            $batches = HtlTravelPassPointBatch::where('travel_pass_id', $member->id)
                ->whereIn('state', ['ACTIVE', 'PARTIALLY_REDEEMED'])
                ->lockForUpdate()
                ->get();

            $remainingNeeded = $pointsToContribute;
            foreach ($batches as $batch) {
                if ($remainingNeeded <= 0) {
                    break;
                }

                $available = (int) $batch->remaining_pts;
                $deduct = min($available, $remainingNeeded);

                $batch->decrement('remaining_pts', $deduct);
                if ($batch->remaining_pts === 0) {
                    $batch->update(['state' => 'FULLY_REDEEMED']);
                }

                HtlTravelPassPointBatch::create([
                    'travel_pass_id' => $head->id,
                    'batch_number' => 'FAM-POOL-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(2)),
                    'originating_line' => 'HOTEL_FAMILY_POOL',
                    'initial_pts' => $deduct,
                    'remaining_pts' => $deduct,
                    'liability_per_pt_idr' => 100,
                    'total_liability_idr' => $deduct * 100,
                    'state' => 'ACTIVE',
                    'reference_code' => "POOL-FROM-{$member->pass_number}",
                    'earned_at' => now(),
                    'expires_at' => now()->addYears(2),
                ]);

                $remainingNeeded -= $deduct;
            }

            $head->increment('pts_balance', $pointsToContribute);
            $head->refreshTier();

            $contribId = 'FAM-CTB-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(3));

            return [
                'contribution_id' => $contribId,
                'member_pass_id' => $member->id,
                'head_pass_id' => $head->id,
                'points_contributed' => $pointsToContribute,
                'contributed_at' => now()->toIso8601String(),
            ];
        });
    }
}
