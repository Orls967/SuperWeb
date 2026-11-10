<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Treasury\Application\Services\TreasuryLedgerPoster;
use Modules\Treasury\Domain\Exceptions\PoolSweepNegativeBalanceException;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\CashPool;
use Modules\Treasury\Domain\Models\CashPoolSweep;
use Modules\Treasury\Ledger\EntityAccountFactory;
use Psr\Clock\ClockInterface;

class ExecuteCashPoolSweepAction
{
    public function __construct(
        protected readonly ?TreasuryLedgerPoster $ledgerPoster = null,
        protected readonly ?ClockInterface $clock = null,
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Menjalankan sweep zero-balancing atau target-balance cash pooling.
     * Mengembalikan nominal yang berhasil disapu ke header account.
     */
    public function execute(CashPool $cashPool, ?int $userId = null): int
    {
        return DB::transaction(function () use ($cashPool, $userId) {
            $now = $this->now();

            $sub = BankAccount::lockForUpdate()->find($cashPool->sub_account_id);
            $header = BankAccount::lockForUpdate()->find($cashPool->header_account_id);

            if ($sub === null || $header === null) {
                throw new InvalidArgumentException('Rekening header atau sub account cash pool tidak valid.');
            }

            $subBalance = (int) $sub->balance;
            $headerBalance = (int) $header->balance;
            $target = (int) $cashPool->target_balance_idr;

            $excess = $subBalance - $target;

            if ($excess <= 0) {
                return 0;
            }

            // Guard: sweep tidak boleh membuat saldo sub account negatif
            if ($subBalance - $excess < 0) {
                throw new PoolSweepNegativeBalanceException('Penyapuan kas ditolak: saldo anak tidak mencukupi dan akan bersaldo negatif.');
            }

            $sub->decrement('balance', $excess);
            $header->increment('balance', $excess);

            $cashPool->update([
                'last_swept_amount_idr' => $excess,
                'last_swept_at' => $now,
            ]);

            /** @var CashPoolSweep $sweep */
            $sweep = CashPoolSweep::create([
                'pool_id' => $cashPool->id,
                'sweep_date' => $now->toDateString(),
                'swept_amount_idr' => $excess,
                'direction' => 'sub_to_header',
                'header_balance_before' => $headerBalance,
                'header_balance_after' => $headerBalance + $excess,
                'sub_balance_before' => $subBalance,
                'sub_balance_after' => $subBalance - $excess,
                'created_by' => $userId,
            ]);

            if ($this->ledgerPoster !== null) {
                $headerAccCode = "trs:bank_operational:{$header->entity_id}:{$header->currency}";
                $subAccCode = "trs:bank_operational:{$sub->entity_id}:{$sub->currency}";
                $clearingAccCode = EntityAccountFactory::forPoolSweepClearing($sub->entity_id, $sub->currency);

                $idempotencyKey = "IDEM:TRS:POOL_SWEEP:{$sweep->id}";
                $tx = $this->ledgerPoster->postCashPoolSweep(
                    poolName: $cashPool->pool_name,
                    amountIdr: $excess,
                    headerAccountCode: $headerAccCode,
                    subAccountCode: $subAccCode,
                    clearingAccountCode: $clearingAccCode,
                    direction: 'sub_to_header',
                    idempotencyKey: $idempotencyKey,
                    poolId: $cashPool->id,
                    userId: $userId
                );
                $sweep->update(['ledger_tx_id' => $tx->id]);
            }

            return $excess;
        });
    }
}
