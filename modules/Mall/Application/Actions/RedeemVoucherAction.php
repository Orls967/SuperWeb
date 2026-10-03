<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Mall\Domain\Enums\VoucherStatus;
use Modules\Mall\Domain\Exceptions\InsufficientPointsException;
use Modules\Mall\Domain\Models\PointBatch;
use Modules\Mall\Domain\Models\Voucher;
use Modules\Mall\Domain\Models\VoucherTemplate;

class RedeemVoucherAction
{
    public function __construct(
        protected Ledger $ledger,
    ) {}

    /**
     * Tukarkan Duta Points dengan Voucher Belanja Mall.
     *
     * @throws InsufficientPointsException
     */
    public function execute(User $user, VoucherTemplate $template, ?string $idempotencyKey = null): Voucher
    {
        return DB::transaction(function () use ($user, $template, $idempotencyKey) {
            $batches = PointBatch::query()
                ->where('user_id', $user->id)
                ->where('is_expired', false)
                ->where('points_remaining', '>', 0)
                ->orderBy('expires_at', 'asc')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $currentPoints = (int) $batches->sum('points_remaining');

            if ($currentPoints < $template->points_required) {
                throw new InsufficientPointsException("Poin tidak mencukupi (Tersedia: {$currentPoints} PTS, Diperlukan: {$template->points_required} PTS).");
            }

            $batchIds = $batches->pluck('id')->sort()->implode('_');
            $sourceKey = $idempotencyKey ?? implode('_', [$user->id, $batchIds, $template->code, $template->points_required, $template->nominal_value]);
            $pointsKey = 'pts_redeem_'.$sourceKey;
            $voucherKey = 'vch_issue_'.$sourceKey;

            if (LedgerTransaction::query()->where('idempotency_key', $pointsKey)->exists()) {
                return Voucher::query()
                    ->where('user_id', $user->id)
                    ->where('template_id', $template->id)
                    ->latest('id')
                    ->firstOrFail();
            }
            // 1. Double-Entry Posting untuk aset PTS (Kredit akun user, Debit kewajiban mall)
            $userPointsAccount = $user->pointsAccount();

            $mallPtsLiability = LedgerAccount::firstOrCreate(
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

            $ptsBd = BigDecimal::of($template->points_required);

            $ptsEntries = [
                PostingEntryDTO::forAccount($userPointsAccount->id, 'PTS', $ptsBd->negated()),
                PostingEntryDTO::forAccount($mallPtsLiability->id, 'PTS', $ptsBd),
            ];

            $dtoPts = new PostingDTO(
                type: TransactionType::LOYALTY_REDEEM->value,
                description: "Penukaran {$template->points_required} PTS untuk Voucher {$template->title}",
                idempotencyKey: $pointsKey,
                entries: $ptsEntries,
                referenceType: VoucherTemplate::class,
                referenceId: $template->id,
                meta: [
                    'user_id' => $user->id,
                    'template_id' => $template->id,
                    'points_spent' => $template->points_required,
                ],
                createdBy: $user->id,
                postedAt: now(),
            );

            $this->ledger->post($dtoPts);

            // 2. Double-Entry Posting untuk aset IDR (Penerbitan Liabilitas Voucher Mall)
            $mallExpense = LedgerAccount::firstOrCreate(
                ['code' => 'expense:mall:loyalty:IDR'],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Beban Promosi & Loyalitas Mall',
                    'asset_code' => 'IDR',
                    'kind' => AccountKind::EXPENSE->value,
                    'allow_negative' => true,
                    'cached_balance' => '0',
                    'is_frozen' => false,
                ]
            );

            $mallVoucherLiability = LedgerAccount::firstOrCreate(
                ['code' => 'liability:mall:voucher:IDR'],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Kewajiban Voucher Belanja Mall',
                    'asset_code' => 'IDR',
                    'kind' => AccountKind::LIABILITY->value,
                    'allow_negative' => true,
                    'cached_balance' => '0',
                    'is_frozen' => false,
                ]
            );

            $valBd = BigDecimal::of($template->nominal_value);

            // Liabilitas voucher mall (+idr), Beban promosi (-idr)
            $idrEntries = [
                PostingEntryDTO::forAccount($mallExpense->id, 'IDR', $valBd->negated()),
                PostingEntryDTO::forAccount($mallVoucherLiability->id, 'IDR', $valBd),
            ];

            $dtoIdr = new PostingDTO(
                type: TransactionType::VOUCHER_ISSUE->value,
                description: "Penerbitan Liabilitas Voucher {$template->title} (Rp ".number_format($template->nominal_value).')',
                idempotencyKey: $voucherKey,
                entries: $idrEntries,
                referenceType: VoucherTemplate::class,
                referenceId: $template->id,
                meta: [
                    'user_id' => $user->id,
                    'nominal_value' => $template->nominal_value,
                ],
                createdBy: $user->id,
                postedAt: now(),
            );

            $this->ledger->post($dtoIdr);

            // 3. Potong saldo PointBatch secara FIFO (baris sudah terkunci sejak awal transaksi)
            $neededPoints = $template->points_required;

            foreach ($batches as $batch) {
                if ($neededPoints <= 0) {
                    break;
                }

                $deduct = min($neededPoints, $batch->points_remaining);
                $batch->points_remaining -= $deduct;
                if ($batch->points_remaining === 0) {
                    $batch->is_expired = true;
                }
                $batch->save();

                $neededPoints -= $deduct;
            }

            // 4. Buat kode voucher unik dan record Voucher
            $voucherCode = 'VCH-'.strtoupper(Str::random(8));

            return Voucher::create([
                'voucher_code' => $voucherCode,
                'template_id' => $template->id,
                'user_id' => $user->id,
                'tenant_id' => null,
                'nominal_value' => $template->nominal_value,
                'min_spend' => $template->min_spend,
                'points_spent' => $template->points_required,
                'status' => VoucherStatus::ACTIVE,
                'expires_at' => now()->addDays($template->validity_days),
            ]);
        });
    }
}
