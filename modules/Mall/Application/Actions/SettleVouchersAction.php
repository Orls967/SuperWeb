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
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\Voucher;

class SettleVouchersAction
{
    public function __construct(
        protected Ledger $ledger,
    ) {}

    /**
     * Settle voucher mall yang telah digunakan ke saldo dompet tenant.
     *
     * @return array{
     *     settled_count: int,
     *     total_amount: int,
     *     tenants_count: int
     * }
     */
    public function execute(?int $tenantId = null): array
    {
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

        $settledCount = 0;
        $totalSettledAmount = 0;
        $tenantsCount = 0;

        DB::transaction(function () use (
            $tenantId,
            $mallVoucherLiability,
            &$settledCount,
            &$totalSettledAmount,
            &$tenantsCount
        ) {
            $query = Voucher::query()
                ->where('status', VoucherStatus::USED)
                ->whereNull('settled_at')
                ->orderBy('id');

            if ($tenantId) {
                $query->where('used_at_tenant_id', $tenantId);
            }

            // Baris voucher dikunci di dalam transaksi agar tidak ada settlement ganda
            $vouchers = $query->with('usedAtTenant')->lockForUpdate()->get();

            if ($vouchers->isEmpty()) {
                return;
            }

            $tenantsGroup = $vouchers->groupBy('used_at_tenant_id');
            $tenantsCount = $tenantsGroup->count();

            foreach ($tenantsGroup as $tId => $items) {
                $tenant = Tenant::find($tId);
                if (! $tenant) {
                    continue;
                }

                $tenantUser = User::find($tenant->user_id);
                if (! $tenantUser) {
                    continue;
                }

                $sumNominal = (int) $items->sum('nominal_value');
                if ($sumNominal <= 0) {
                    continue;
                }

                // Kunci deterministik per tenant dari voucher id terurut agar retry tidak men-posting ganda
                $settlementKey = 'vch_settle_'.$tenant->id.'_'.implode('_', $items->pluck('id')->sort()->values()->all());

                $existingSettlement = LedgerTransaction::query()
                    ->where('idempotency_key', $settlementKey)
                    ->first();

                if ($existingSettlement !== null) {
                    $replaySettlementId = $existingSettlement->meta['settlement_id'] ?? null;

                    foreach ($items as $item) {
                        $item->update([
                            'status' => VoucherStatus::SETTLED,
                            'settled_at' => now(),
                            'settlement_id' => $replaySettlementId,
                        ]);
                    }

                    $settledCount += $items->count();
                    $totalSettledAmount += $sumNominal;

                    continue;
                }

                $tenantWallet = $tenantUser->walletAccount('IDR');
                $valBd = BigDecimal::of($sumNominal);

                // Pelunasan liabilitas voucher (-idr), Tambah saldo dompet tenant (+idr)
                $entries = [
                    PostingEntryDTO::forAccount($mallVoucherLiability->id, 'IDR', $valBd->negated()),
                    PostingEntryDTO::forAccount($tenantWallet->id, 'IDR', $valBd),
                ];

                $settlementId = 'SETTLE-VCH-'.now()->format('YmdHis').'-'.Str::random(6);

                $dto = new PostingDTO(
                    type: TransactionType::VOUCHER_SETTLEMENT->value,
                    description: "Settlement {$items->count()} Voucher Mall untuk Tenant {$tenant->brand_name} (Rp ".number_format($sumNominal).')',
                    idempotencyKey: $settlementKey,
                    entries: $entries,
                    referenceType: Tenant::class,
                    referenceId: $tenant->id,
                    meta: [
                        'tenant_id' => $tenant->id,
                        'settlement_id' => $settlementId,
                        'vouchers_count' => $items->count(),
                        'total_nominal' => $sumNominal,
                    ],
                    createdBy: 1,
                    postedAt: now(),
                );

                $this->ledger->post($dto);

                foreach ($items as $item) {
                    $item->update([
                        'status' => VoucherStatus::SETTLED,
                        'settled_at' => now(),
                        'settlement_id' => $settlementId,
                    ]);
                }

                $settledCount += $items->count();
                $totalSettledAmount += $sumNominal;
            }
        });

        return [
            'settled_count' => $settledCount,
            'total_amount' => $totalSettledAmount,
            'tenants_count' => $tenantsCount,
        ];
    }
}
