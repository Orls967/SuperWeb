<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Services;

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
use Modules\Mall\Application\Actions\ClaimReceiptPointsAction;
use Modules\Mall\Application\Actions\UseVoucherAction;
use Modules\Mall\Contracts\LoyaltyLedger;
use Modules\Mall\Domain\Exceptions\DuplicateReceiptClaimException;
use Modules\Mall\Domain\Exceptions\InsufficientPointsException;
use Modules\Mall\Domain\Exceptions\InvalidVoucherException;
use Modules\Mall\Domain\Models\PointBatch;
use Modules\Mall\Domain\Models\Tenant;

class MallLoyaltyLedgerService implements LoyaltyLedger
{
    public function __construct(
        protected Ledger $ledger,
        protected ClaimReceiptPointsAction $claimReceiptAction,
        protected UseVoucherAction $useVoucherAction,
    ) {}

    public function getPointsBalance(User $user): int
    {
        return $user->pointsBalance();
    }

    public function awardPoints(
        User $user,
        int $spendAmount,
        string $receiptNumber,
        ?string $tenantExternalRef = null,
        ?User $processor = null
    ): int {
        if ($spendAmount <= 0) {
            return 0;
        }

        $tenantId = null;
        if ($tenantExternalRef) {
            $tenant = Tenant::query()->where('external_ref', $tenantExternalRef)->first();
            $tenantId = $tenant?->id;
        }

        try {
            $claim = $this->claimReceiptAction->execute(
                user: $user,
                receiptNumber: $receiptNumber,
                receiptAmount: $spendAmount,
                receiptDate: now(),
                tenantId: $tenantId,
                processor: $processor
            );

            return (int) $claim->points_earned;
        } catch (DuplicateReceiptClaimException) {
            return 0;
        }
    }

    public function applyVoucher(
        string $voucherCode,
        string $tenantExternalRef,
        int $spendAmount,
        ?string $transactionRef = null
    ): int {
        $tenant = Tenant::query()->where('external_ref', $tenantExternalRef)->first();
        if (! $tenant) {
            throw new InvalidVoucherException("Tenant dengan referensi {$tenantExternalRef} tidak ditemukan.");
        }

        $voucher = $this->useVoucherAction->execute(
            voucherCode: $voucherCode,
            tenant: $tenant,
            transactionAmount: $spendAmount,
            transactionRef: $transactionRef
        );

        return (int) ($voucher->nominal_value ?? 0);
    }

    public function redeemPointsForDiscount(
        User $user,
        int $pointsToRedeem,
        string $description,
        ?string $referenceId = null
    ): int {
        if ($pointsToRedeem <= 0) {
            return 0;
        }

        $currentBalance = $this->getPointsBalance($user);
        if ($currentBalance < $pointsToRedeem) {
            throw new InsufficientPointsException(
                "Saldo poin ({$currentBalance} PTS) tidak mencukupi untuk penukaran {$pointsToRedeem} PTS."
            );
        }

        // Rasio: 1 PTS = Rp 100
        $discountAmountIdr = $pointsToRedeem * 100;

        return DB::transaction(function () use ($user, $pointsToRedeem, $discountAmountIdr, $description, $referenceId) {
            // 1. Kurangi batch poin FIFO
            $batches = PointBatch::query()
                ->where('user_id', $user->id)
                ->where('is_expired', false)
                ->where('points_remaining', '>', 0)
                ->orderBy('earned_at', 'asc')
                ->lockForUpdate()
                ->get();

            $needed = $pointsToRedeem;
            foreach ($batches as $batch) {
                if ($needed <= 0) {
                    break;
                }
                $take = min($needed, (int) $batch->points_remaining);
                $batch->points_remaining -= $take;
                $batch->save();
                $needed -= $take;
            }

            // 2. Double-Entry Posting untuk PTS
            $userPointsAccount = $user->pointsAccount();
            $mallPointsLiability = LedgerAccount::firstOrCreate(
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

            $ptsBd = BigDecimal::of($pointsToRedeem);
            $ptsEntries = [
                PostingEntryDTO::forAccount($userPointsAccount->id, 'PTS', $ptsBd->negated()),
                PostingEntryDTO::forAccount($mallPointsLiability->id, 'PTS', $ptsBd),
            ];

            $this->ledger->post(new PostingDTO(
                type: TransactionType::LOYALTY_REDEEM->value,
                description: $description." ({$pointsToRedeem} PTS)",
                idempotencyKey: 'pts_redeem_disc_'.Str::uuid(),
                entries: $ptsEntries,
                referenceType: 'points_discount',
                referenceId: $referenceId ? (int) $referenceId : null,
                createdBy: $user->id,
                postedAt: now(),
            ));

            // 3. Double-Entry Moneter untuk Diskon IDR
            $expenseAccount = LedgerAccount::firstOrCreate(
                ['code' => 'expense:mall:loyalty:IDR'],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Beban Promosi Loyalitas Duta Mall',
                    'asset_code' => 'IDR',
                    'kind' => AccountKind::EXPENSE->value,
                    'allow_negative' => true,
                    'cached_balance' => '0',
                    'is_frozen' => false,
                ]
            );

            $voucherLiabilityAccount = LedgerAccount::firstOrCreate(
                ['code' => 'liability:mall:voucher:IDR'],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Kewajiban Voucher & Diskon Mall',
                    'asset_code' => 'IDR',
                    'kind' => AccountKind::LIABILITY->value,
                    'allow_negative' => true,
                    'cached_balance' => '0',
                    'is_frozen' => false,
                ]
            );

            $idrBd = BigDecimal::of($discountAmountIdr);
            $idrEntries = [
                PostingEntryDTO::forAccount($expenseAccount->id, 'IDR', $idrBd->negated()),
                PostingEntryDTO::forAccount($voucherLiabilityAccount->id, 'IDR', $idrBd),
            ];

            $this->ledger->post(new PostingDTO(
                type: TransactionType::VOUCHER_ISSUE->value,
                description: "Subsidi diskon poin loyalty: {$description} (Rp ".number_format($discountAmountIdr).')',
                idempotencyKey: 'idr_pts_disc_'.Str::uuid(),
                entries: $idrEntries,
                referenceType: 'points_discount_idr',
                referenceId: $referenceId ? (int) $referenceId : null,
                createdBy: $user->id,
                postedAt: now(),
            ));

            return $discountAmountIdr;
        });
    }
}
