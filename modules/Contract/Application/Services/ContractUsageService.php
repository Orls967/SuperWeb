<?php

declare(strict_types=1);

namespace Modules\Contract\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\UsageLedger;

/**
 * Rekonsiliasi pemakaian plafon kontrak (29.5).
 *
 * Sumber transaksi riil (PO, penjualan, pengiriman, billing sewa, royalti)
 * dicatat ke `ctr_usage_ledger` secara idempoten (unique source_type+source_id).
 * `ctr_contracts.used_value_idr` adalah cache agregat yang dihitung ulang dari
 * ledger sehingga `bank:reconcile`-style invarian tetap terjaga (Σ ledger = cache).
 */
class ContractUsageService
{
    /**
     * Catat pemakaian plafon dari satu transaksi sumber.
     *
     * @return bool true bila baris baru tercatat (bukan replay)
     */
    public function recordUsage(
        Contract|string $contract,
        string $sourceType,
        int $sourceId,
        int $amountIdr,
        ?string $note = null
    ): bool {
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Nilai pemakaian plafon harus lebih besar dari 0.');
        }

        $contractId = $contract instanceof Contract ? $contract->id : $contract;

        return DB::transaction(function () use ($contractId, $sourceType, $sourceId, $amountIdr, $note) {
            $created = UsageLedger::recordOnce($contractId, $sourceType, $sourceId, $amountIdr, $note);

            if ($created) {
                $this->recomputeCache($contractId);
            }

            return $created;
        });
    }

    /**
     * Hitung ulang agregat used_value_idr dari ledger (idempoten, selalu nol konflik).
     */
    public function recomputeCache(string $contractId): int
    {
        return DB::transaction(function () use ($contractId) {
            $total = (int) UsageLedger::where('contract_id', $contractId)->sum('amount_idr');

            DB::table('ctr_contracts')
                ->where('id', $contractId)
                ->update(['used_value_idr' => $total]);

            return $total;
        });
    }

    /**
     * Status plafon: OK (<80%), WARNING (>=80%), EXCEEDED (>=100%).
     *
     * @return array{used_idr: int, total_idr: int, remaining_idr: int, percent: float, status: string}
     */
    public function utilization(Contract $contract): array
    {
        $used = (int) $contract->used_value_idr;
        $total = (int) $contract->total_value_idr;

        if ($total <= 0) {
            return [
                'used_idr' => $used,
                'total_idr' => $total,
                'remaining_idr' => 0,
                'percent' => 100.0,
                'status' => 'exceeded',
            ];
        }

        $percent = round($used * 100 / $total, 2);

        $status = match (true) {
            $used >= $total => 'exceeded',
            $used >= (int) floor($total * 0.8) => 'warning',
            default => 'ok',
        };

        return [
            'used_idr' => $used,
            'total_idr' => $total,
            'remaining_idr' => max(0, $total - $used),
            'percent' => $percent,
            'status' => $status,
        ];
    }

    /**
     * Kontrak dengan pemakaian melewati ambang early warning (80%/100%).
     *
     * @param  int  $thresholdPercent  80 untuk early warning, 100 untuk exceeded.
     * @return array<int, Contract>
     */
    public function contractsOverThreshold(int $thresholdPercent = 80): array
    {
        $contracts = Contract::query()
            ->whereIn('status', ['active', 'signed', 'suspended'])
            ->where('total_value_idr', '>', 0)
            ->get();

        return $contracts->filter(function (Contract $c) use ($thresholdPercent): bool {
            $util = $this->utilization($c);

            return $util['percent'] >= $thresholdPercent;
        })->values()->all();
    }
}
