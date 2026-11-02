<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Contracts\LedgerTransaction;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Mall\Domain\Enums\VoucherStatus;
use Modules\Mall\Domain\Exceptions\InsufficientPointsException;
use Modules\Mall\Domain\Models\PointBatch;
use Modules\Mall\Domain\Models\Voucher;
use Modules\Mall\Domain\Models\VoucherTemplate;
use Modules\Mall\Ledger\MallAccounts;
use Modules\Shared\Support\Posting;

class RedeemVoucherAction
{
    public function __construct(
        protected Ledger $ledger,
    ) {}

    /**
     * Tukarkan Duta Points dengan Voucher Belanja Mall.
     *
     * @throws InsufficientPointsException
     * @throws InvalidArgumentException
     */
    public function execute(User $user, VoucherTemplate $template, ?string $idempotencyKey = null): Voucher
    {
        $rawKey = $idempotencyKey;
        if ($rawKey === null && app()->bound('request')) {
            $rawKey = request()?->header('Idempotency-Key')
                ?? request()?->input('idempotency_key')
                ?? request()?->input('request_id');
        }

        $cleanKey = is_string($rawKey) ? trim($rawKey) : '';
        if ($cleanKey === '') {
            throw new InvalidArgumentException('Idempotency key wajib diisi untuk penukaran voucher.');
        }

        $pointsKey = 'pts_redeem_'.$cleanKey;
        $voucherKey = 'vch_issue_'.$cleanKey;

        return DB::transaction(function () use ($user, $template, $pointsKey, $voucherKey) {
            if (LedgerTransaction::query()->where('idempotency_key', $pointsKey)->exists()) {
                return Voucher::query()
                    ->where('user_id', $user->id)
                    ->where('template_id', $template->id)
                    ->latest('id')
                    ->firstOrFail();
            }

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

            // 1. Double-Entry Posting untuk aset PTS (Model C: debit dompet user, kredit revenue redeemed)
            $userPointsAccount = $user->pointsAccount();
            $revenueRedeemedAccount = MallAccounts::pointsRedeemedAccount();

            $ptsEntries = Posting::lines()
                ->debit($userPointsAccount->id, $template->points_required, 'PTS')
                ->credit($revenueRedeemedAccount->id, $template->points_required, 'PTS')
                ->toEntries('PTS');

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

            // 2. Double-Entry Posting untuk aset IDR (Penerbitan Liabilitas Voucher Mall via MallAccounts helper)
            $mallExpense = MallAccounts::loyaltyExpenseAccount();
            $mallVoucherLiability = MallAccounts::voucherLiabilityAccount();

            // Beban promosi (debit), Liabilitas voucher mall (kredit)
            $idrEntries = Posting::lines()
                ->debit($mallExpense->id, $template->nominal_value, 'IDR')
                ->credit($mallVoucherLiability->id, $template->nominal_value, 'IDR')
                ->toEntries('IDR');

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
