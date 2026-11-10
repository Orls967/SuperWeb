<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Treasury\Application\Services\TreasuryLedgerPoster;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\CashPool;
use Modules\Treasury\Domain\Models\CashPoolSweep;
use Modules\Treasury\Ledger\EntityAccountFactory;
use Psr\Clock\ClockInterface;

class ExecuteNotionalCashPoolSweepAction
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
     * Execute physical or notional cash pool zero-balance / target-balance sweep.
     *
     * @return array{
     *     cash_pool: CashPool,
     *     swept_amount_idr: int,
     *     sweep_direction: string,
     *     sweep_record: ?CashPoolSweep
     * }
     */
    public function execute(
        CashPool $cashPool,
        ?int $currentSubAccountBalanceIdr = null,
        ?int $userId = null
    ): array {
        return DB::transaction(function () use ($cashPool, $currentSubAccountBalanceIdr, $userId) {
            $now = $this->now();

            $header = BankAccount::lockForUpdate()->find($cashPool->header_account_id);
            $sub = BankAccount::lockForUpdate()->find($cashPool->sub_account_id);

            if ($header === null || $sub === null) {
                throw new InvalidArgumentException('Rekening header atau sub account cash pool tidak valid.');
            }

            $subBalance = $currentSubAccountBalanceIdr ?? (int) $sub->balance;
            $headerBalance = (int) $header->balance;
            $targetBalance = (int) $cashPool->target_balance_idr;

            $variance = $subBalance - $targetBalance;

            if ($variance === 0) {
                return [
                    'cash_pool' => $cashPool->fresh(),
                    'swept_amount_idr' => 0,
                    'sweep_direction' => 'balanced_no_sweep',
                    'sweep_record' => null,
                ];
            }

            $sweptAmountIdr = abs($variance);
            $direction = $variance > 0 ? 'sub_to_header' : 'header_to_sub';

            $headerBefore = $headerBalance;
            $subBefore = $subBalance;

            if ($direction === 'sub_to_header') {
                if ($subBalance < $sweptAmountIdr) {
                    throw new InvalidArgumentException("Saldo rekening anak ({$subBalance}) tidak mencukupi untuk sweep {$sweptAmountIdr}.");
                }
                $subAfter = $subBefore - $sweptAmountIdr;
                $headerAfter = $headerBefore + $sweptAmountIdr;
            } else {
                if ($headerBalance < $sweptAmountIdr) {
                    throw new InvalidArgumentException("Saldo rekening induk ({$headerBalance}) tidak mencukupi untuk top-up {$sweptAmountIdr}.");
                }
                $headerAfter = $headerBefore - $sweptAmountIdr;
                $subAfter = $subBefore + $sweptAmountIdr;
            }

            // Update saldo rekening
            $sub->update(['balance' => $subAfter]);
            $header->update(['balance' => $headerAfter]);

            $cashPool->update([
                'last_swept_amount_idr' => $sweptAmountIdr,
                'last_swept_at' => $now,
            ]);

            // Catat riwayat sweep
            /** @var CashPoolSweep $sweep */
            $sweep = CashPoolSweep::create([
                'pool_id' => $cashPool->id,
                'sweep_date' => $now->toDateString(),
                'swept_amount_idr' => $sweptAmountIdr,
                'direction' => $direction,
                'header_balance_before' => $headerBefore,
                'header_balance_after' => $headerAfter,
                'sub_balance_before' => $subBefore,
                'sub_balance_after' => $subAfter,
                'created_by' => $userId,
            ]);

            // Posting ledger penyapuan kas multi-entitas
            if ($this->ledgerPoster !== null && $sweptAmountIdr > 0) {
                $headerAccCode = "trs:bank_operational:{$header->entity_id}:{$header->currency}";
                $subAccCode = "trs:bank_operational:{$sub->entity_id}:{$sub->currency}";
                $clearingAccCode = EntityAccountFactory::forPoolSweepClearing($sub->entity_id, $sub->currency);

                $idempotencyKey = "IDEM:TRS:POOL_SWEEP:{$sweep->id}";
                $tx = $this->ledgerPoster->postCashPoolSweep(
                    poolName: $cashPool->pool_name,
                    amountIdr: $sweptAmountIdr,
                    headerAccountCode: $headerAccCode,
                    subAccountCode: $subAccCode,
                    clearingAccountCode: $clearingAccCode,
                    direction: $direction,
                    idempotencyKey: $idempotencyKey,
                    poolId: $cashPool->id,
                    userId: $userId
                );
                $sweep->update(['ledger_tx_id' => $tx->id]);
            }

            return [
                'cash_pool' => $cashPool->fresh(),
                'swept_amount_idr' => $sweptAmountIdr,
                'sweep_direction' => $direction === 'sub_to_header' ? 'upstream_sweep_to_header' : 'downstream_topup_from_header',
                'sweep_record' => $sweep->fresh(),
            ];
        });
    }
}
