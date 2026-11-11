<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\EnterpriseFinance\Domain\Exceptions\BudgetGuardrailBreachException;
use Modules\EnterpriseFinance\Domain\Models\BudgetHistory;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Psr\Clock\ClockInterface;

/**
 * Mesin Pemindahan Alokasi Anggaran Antar Cost Center (54.1).
 * Mengelola realokasi pagu antar departemen dengan validasi ketersediaan dana sumber,
 * pencatatan mutasi berimbang (zero-sum reallocation), dan audit trail lengkap.
 */
class BudgetTransferEngine
{
    public function __construct(
        protected readonly ?ClockInterface $clock = null
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Memindahkan pagu alokasi anggaran dari departemen sumber ke departemen penerima.
     *
     * @return array{
     *     transfer_reference: string,
     *     source_budget_code: string,
     *     target_budget_code: string,
     *     amount_idr: int,
     *     source_new_allocated_idr: int,
     *     target_new_allocated_idr: int,
     *     executed_at: string
     * }
     */
    public function transferBudget(
        EnterpriseBudget $sourceBudget,
        EnterpriseBudget $targetBudget,
        int $amountIdr,
        int $operatorId,
        string $justification
    ): array {
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Nominal pemindahan anggaran harus lebih besar dari nol.');
        }

        if ($sourceBudget->id === $targetBudget->id) {
            throw new InvalidArgumentException('Departemen anggaran sumber dan tujuan tidak boleh sama.');
        }

        if ($sourceBudget->fiscal_year !== $targetBudget->fiscal_year) {
            throw new InvalidArgumentException('Pemindahan anggaran hanya diizinkan pada tahun fiskal yang sama.');
        }

        if ($sourceBudget->status === 'FROZEN' || $targetBudget->status === 'FROZEN') {
            throw new BudgetGuardrailBreachException('Pemindahan anggaran dilarang karena salah satu anggaran dalam status DIBEKUKAN (FROZEN).');
        }

        return DB::transaction(function () use ($sourceBudget, $targetBudget, $amountIdr, $operatorId, $justification) {
            // Lock kedua baris anggaran secara pesimistik
            $source = EnterpriseBudget::where('id', $sourceBudget->id)->lockForUpdate()->firstOrFail();
            $target = EnterpriseBudget::where('id', $targetBudget->id)->lockForUpdate()->firstOrFail();

            $sourceAvailable = $source->allocated_amount_idr - ($source->encumbered_amount_idr + $source->spent_amount_idr);
            if ($amountIdr > $sourceAvailable) {
                throw new BudgetGuardrailBreachException(
                    "Pagu tersedia departemen sumber [{$source->budget_code}] (Rp {$sourceAvailable}) tidak mencukupi untuk pemindahan Rp {$amountIdr}."
                );
            }

            // Realokasi berimbang (Zero-Sum Reallocation)
            $source->allocated_amount_idr -= $amountIdr;
            $source->save();

            $target->allocated_amount_idr += $amountIdr;
            $target->save();

            $ref = 'TRF-BGT-'.$source->fiscal_year.'-'.bin2hex(random_bytes(4));

            BudgetHistory::create([
                'budget_id' => $source->id,
                'from_status' => $source->status ?? 'ACTIVE',
                'to_status' => $source->status ?? 'ACTIVE',
                'reason' => "Transfer keluar ke [{$target->budget_code}]: {$justification} (Ref: {$ref})",
                'operator_id' => $operatorId,
            ]);

            BudgetHistory::create([
                'budget_id' => $target->id,
                'from_status' => $target->status ?? 'ACTIVE',
                'to_status' => $target->status ?? 'ACTIVE',
                'reason' => "Transfer masuk dari [{$source->budget_code}]: {$justification} (Ref: {$ref})",
                'operator_id' => $operatorId,
            ]);

            return [
                'transfer_reference' => $ref,
                'source_budget_code' => $source->budget_code,
                'target_budget_code' => $target->budget_code,
                'amount_idr' => $amountIdr,
                'source_new_allocated_idr' => $source->allocated_amount_idr,
                'target_new_allocated_idr' => $target->allocated_amount_idr,
                'executed_at' => $this->now()->toIso8601String(),
            ];
        });
    }

    /**
     * Mengkalkulasi total pagu bersih yang dipindahkan dalam suatu tahun fiskal.
     */
    public function getTransferSummary(string $fiscalYear): array
    {
        $histories = BudgetHistory::whereHas('budget', function ($q) use ($fiscalYear) {
            $q->where('fiscal_year', $fiscalYear);
        })->where('reason', 'like', 'Transfer%')->get();

        return [
            'fiscal_year' => $fiscalYear,
            'total_transfers_recorded' => $histories->count(),
            'last_transfer_at' => $histories->last()?->created_at?->toIso8601String(),
        ];
    }
}
