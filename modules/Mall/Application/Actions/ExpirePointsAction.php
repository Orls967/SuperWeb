<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Mall\Domain\Models\PointBatch;

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

        $batches = PointBatch::query()
            ->where('is_expired', false)
            ->where('expires_at', '<=', $asOfDate)
            ->where('points_remaining', '>', 0)
            ->get();

        if ($batches->isEmpty()) {
            return 0;
        }

        $totalExpiredPoints = 0;

        $mallLiabilityAccount = LedgerAccount::firstOrCreate(
            ['code' => 'liability:mall:points:PTS'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Kewajiban Poin Loyalitas Duta Mall',
                'asset_code' => 'PTS',
                'kind' => AccountKind::LIABILITY->value,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );

        // Kelompokkan per user untuk meminimalisasi jumlah transaksi buku besar
        $userBatches = $batches->groupBy('user_id');

        DB::transaction(function () use ($userBatches, $mallLiabilityAccount, &$totalExpiredPoints) {
            foreach ($userBatches as $userId => $items) {
                $user = User::find($userId);
                if (! $user) {
                    continue;
                }

                $userTotal = (int) $items->sum('points_remaining');
                if ($userTotal <= 0) {
                    continue;
                }

                $userPointsAccount = $user->pointsAccount();
                $ptsBd = BigDecimal::of($userTotal);

                // Reversal: Kredit akun user (-pts), Debit kewajiban mall (+pts)
                $entries = [
                    PostingEntryDTO::forAccount($userPointsAccount->id, 'PTS', $ptsBd->negated()),
                    PostingEntryDTO::forAccount($mallLiabilityAccount->id, 'PTS', $ptsBd),
                ];

                $txKey = 'pts_expire_'.$userId.'_'.now()->format('YmdHis').'_'.Str::random(6);

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
