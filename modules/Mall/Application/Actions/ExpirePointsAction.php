<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Contracts\LedgerTransaction;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Mall\Domain\Models\PointBatch;
use Modules\Mall\Ledger\MallAccounts;
use Modules\Shared\Support\Posting;

class ExpirePointsAction
{
    public function __construct(
        protected Ledger $ledger,
    ) {}

    /**
     * Kedaluwarsakan poin loyalitas yang telah melewati masa aktif secara FIFO.
     * Satu posting per batch dengan key deterministik mall:pts_expire:{batch_id} (KONSEP §A2.5).
     */
    public function execute(?Carbon $asOfDate = null): int
    {
        $asOfDate = $asOfDate ?? Carbon::now();

        $totalExpiredPoints = 0;

        DB::transaction(function () use ($asOfDate, &$totalExpiredPoints) {
            $batches = PointBatch::query()
                ->where('is_expired', false)
                ->where('expires_at', '<=', $asOfDate)
                ->where('points_remaining', '>', 0)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($batches->isEmpty()) {
                return;
            }

            $breakageAccount = MallAccounts::pointsBreakageAccount();

            foreach ($batches as $batch) {
                $user = User::find($batch->user_id);
                if (! $user) {
                    continue;
                }

                $pointsToExpire = (int) $batch->points_remaining;
                if ($pointsToExpire <= 0) {
                    continue;
                }

                // Kunci deterministik satu posting per batch (§A2.5)
                $txKey = 'mall:pts_expire:'.$batch->id;

                if (! LedgerTransaction::query()->where('idempotency_key', $txKey)->exists()) {
                    $userPointsAccount = $user->pointsAccount();

                    // Model C: debit dompet user, kredit revenue breakage PTS tanpa negated()
                    $entries = Posting::lines()
                        ->debit($userPointsAccount->id, $pointsToExpire, 'PTS')
                        ->credit($breakageAccount->id, $pointsToExpire, 'PTS')
                        ->toEntries('PTS');

                    $dto = new PostingDTO(
                        type: TransactionType::LOYALTY_EXPIRE->value,
                        description: "Kedaluwarsa {$pointsToExpire} PTS batch #{$batch->id} untuk Pengguna #{$user->id}",
                        idempotencyKey: $txKey,
                        entries: $entries,
                        referenceType: PointBatch::class,
                        referenceId: $batch->id,
                        meta: [
                            'user_id' => $user->id,
                            'batch_id' => $batch->id,
                            'expired_points' => $pointsToExpire,
                        ],
                        createdBy: 1,
                        postedAt: now(),
                    );

                    $this->ledger->post($dto);
                }

                $batch->update([
                    'points_remaining' => 0,
                    'is_expired' => true,
                ]);

                $totalExpiredPoints += $pointsToExpire;
            }
        });

        return $totalExpiredPoints;
    }
}
