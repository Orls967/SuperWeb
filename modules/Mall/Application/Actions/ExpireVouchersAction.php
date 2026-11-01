<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Contracts\LedgerTransaction;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Mall\Domain\Enums\VoucherStatus;
use Modules\Mall\Domain\Models\Voucher;
use Modules\Mall\Ledger\MallAccounts;
use Modules\Shared\Support\Posting;

class ExpireVouchersAction
{
    public function __construct(
        protected Ledger $ledger,
    ) {}

    /**
     * Kedaluwarsakan voucher yang melewati masa aktif dan akui sebagai pendapatan breakage.
     */
    public function execute(?Carbon $asOfDate = null): int
    {
        $asOfDate = $asOfDate ?? Carbon::now();

        $expiredCount = 0;

        DB::transaction(function () use ($asOfDate, &$expiredCount) {
            $vouchers = Voucher::query()
                ->where('status', VoucherStatus::ACTIVE)
                ->where('expires_at', '<=', $asOfDate)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($vouchers->isEmpty()) {
                return;
            }

            $mallVoucherLiability = MallAccounts::voucherLiabilityAccount();
            $breakageRevenueAccount = MallAccounts::voucherBreakageRevenueAccount();

            $totalBreakage = (int) $vouchers->sum('nominal_value');

            // Kunci deterministik dari voucher id terurut agar retry tidak men-posting ganda
            $txKey = 'vch_breakage_'.implode('_', $vouchers->pluck('id')->sort()->values()->all());

            if ($totalBreakage > 0 && ! LedgerTransaction::query()->where('idempotency_key', $txKey)->exists()) {
                // Tutup liabilitas voucher (debit), Akui pendapatan breakage (kredit) via Posting helper
                $entries = Posting::lines()
                    ->debit($mallVoucherLiability->id, $totalBreakage, 'IDR')
                    ->credit($breakageRevenueAccount->id, $totalBreakage, 'IDR')
                    ->toEntries('IDR');

                $dto = new PostingDTO(
                    type: TransactionType::VOUCHER_BREAKAGE->value,
                    description: "Breakage {$vouchers->count()} Voucher Kedaluwarsa (Total Rp ".number_format($totalBreakage).')',
                    idempotencyKey: $txKey,
                    entries: $entries,
                    referenceType: Voucher::class,
                    referenceId: null,
                    meta: [
                        'count' => $vouchers->count(),
                        'total_nominal' => $totalBreakage,
                        'voucher_ids' => $vouchers->pluck('id')->toArray(),
                    ],
                    createdBy: 1,
                    postedAt: now(),
                );

                $this->ledger->post($dto);
            }

            foreach ($vouchers as $voucher) {
                $voucher->update(['status' => VoucherStatus::EXPIRED]);
                $expiredCount++;
            }
        });

        return $expiredCount;
    }
}
