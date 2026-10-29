<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Contracts\LedgerTransaction;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Mall\Domain\Models\PointBatch;
use Modules\Mall\Ledger\MallAccounts;

class ExpirePointsAction
{
    public function __construct(
        protected Ledger $ledger,
    ) {}

    /**
     * Kedaluwarsakan poin loyalitas yang telah melewati masa aktif secara FIFO.
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

            $mallLiabilityAccount = MallAccounts::pointsLiabilityAccount();

            // Kelompokkan per user untuk meminimalisasi jumlah transaksi buku besar
            $userBatches = $batches->groupBy('user_id');

            foreach ($userBatches as $userId => $items) {
                $user = User::find($userId);
                if (! $user) {
                    continue;
                }

                $userTotal = (int) $items->sum('points_remaining');
                if ($userTotal <= 0) {
                    continue;
                }

                // Kunci deterministik dari batch id terurut agar retry tidak men-posting ganda
                $txKey = 'pts_expire_'.$userId.'_'.implode('_', $items->pluck('id')->sort()->values()->all());

                if (LedgerTransaction::query()->where('idempotency_key', $txKey)->exists()) {
                    foreach ($items as $item) {
                        $item->update([
                            'points_remaining' => 0,
                            'is_expired' => true,
                        ]);
                    }

                    continue;
                }

                $userPointsAccount = $user->pointsAccount();
                $ptsBd = BigDecimal::of($userTotal);

                // Reversal: Debit kewajiban mall (-pts), Kredit akun aset/user (+pts) (K-1)
                $entries = [
                    PostingEntryDTO::forAccount($userPointsAccount->id, 'PTS', $ptsBd),
                    PostingEntryDTO::forAccount($mallLiabilityAccount->id, 'PTS', $ptsBd->negated()),
                ];

                $dto = new PostingDTO(
                    type: TransactionType::LOYALTY_EXPIRE->value,
                    description: "Kedaluwarsa {$userTotal} PTS untuk Pengguna #{$userId}",
                    idempotencyKey: $txKey,
                    entries: $entries,
                    referenceType: PointBatch::class,
                    referenceId: null,
                    meta: [
                        'user_id' => $userId,
                        'expired_points' => $userTotal,
                        'batch_ids' => $items->pluck('id')->toArray(),
                    ],
                    createdBy: 1,
                    postedAt: now(),
                );

                $this->ledger->post($dto);

                foreach ($items as $item) {
                    $item->update([
                        'points_remaining' => 0,
                        'is_expired' => true,
                    ]);
                }

                $totalExpiredPoints += $userTotal;
            }
        });

        return $totalExpiredPoints;
    }
}
