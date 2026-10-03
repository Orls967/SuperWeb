<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

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
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Mall\Domain\Enums\VoucherStatus;
use Modules\Mall\Domain\Models\Voucher;

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

            $breakageRevenueAccount = LedgerAccount::firstOrCreate(
                ['code' => 'revenue:mall:voucher_breakage:IDR'],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Pendapatan Breakage Voucher Kedaluwarsa',
                    'asset_code' => 'IDR',
                    'kind' => AccountKind::REVENUE->value,
                    'allow_negative' => false,
                    'cached_balance' => '0',
                    'is_frozen' => false,
                ]
            );

            $totalBreakage = (int) $vouchers->sum('nominal_value');

            // Kunci deterministik dari voucher id terurut agar retry tidak men-posting ganda
            $txKey = 'vch_breakage_'.implode('_', $vouchers->pluck('id')->sort()->values()->all());

            if ($totalBreakage > 0 && ! LedgerTransaction::query()->where('idempotency_key', $txKey)->exists()) {
                $valBd = BigDecimal::of($totalBreakage);

                // Tutup liabilitas voucher (-idr), Akui pendapatan breakage (+idr)
                $entries = [
                    PostingEntryDTO::forAccount($mallVoucherLiability->id, 'IDR', $valBd->negated()),
                    PostingEntryDTO::forAccount($breakageRevenueAccount->id, 'IDR', $valBd),
                ];

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
