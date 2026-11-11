<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\EnterpriseFinance\Contracts\BudgetCommitmentPort;
use Modules\EnterpriseFinance\Domain\Algorithms\Encumbrance\BudgetVarianceAnalyzer;
use Modules\EnterpriseFinance\Domain\DTOs\BudgetCommitmentRequest;
use Modules\EnterpriseFinance\Domain\DTOs\BudgetCommitmentResult;
use Modules\EnterpriseFinance\Domain\Enums\EncumbranceStatus;
use Modules\EnterpriseFinance\Domain\Events\EnterpriseBudgetThresholdExceeded;
use Modules\EnterpriseFinance\Domain\Exceptions\BudgetGuardrailBreachException;
use Modules\EnterpriseFinance\Domain\Models\BudgetEncumbrance;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Modules\EnterpriseFinance\Domain\StateMachines\EnterpriseBudgetCycleStateMachine;
use Psr\Clock\ClockInterface;

class BudgetCommitmentService implements BudgetCommitmentPort
{
    public function __construct(
        protected readonly ?OutboxBusInterface $outboxBus = null,
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
     * Mencadangkan pagu anggaran (encumbrance) untuk Purchase Order / Kontrak baru.
     */
    public function reserve(BudgetCommitmentRequest $request): BudgetCommitmentResult
    {
        if ($request->amountMinor <= 0) {
            throw new BudgetGuardrailBreachException(
                "Nominal reservasi anggaran harus lebih besar dari nol ({$request->amountMinor})."
            );
        }

        return DB::transaction(function () use ($request): BudgetCommitmentResult {
            /** @var EnterpriseBudget|null $budget */
            $budget = EnterpriseBudget::query()
                ->where('id', $request->budgetId)
                ->lockForUpdate()
                ->first();

            if ($budget === null) {
                return BudgetCommitmentResult::failed($request->commitmentCode, "Mata anggaran ID #{$request->budgetId} tidak ditemukan.");
            }

            if ($budget->status !== EnterpriseBudgetCycleStateMachine::STATUS_ACTIVE) {
                throw new BudgetGuardrailBreachException(
                    "Mata anggaran [{$budget->budget_code}] tidak dalam status ACTIVE (status saat ini: {$budget->status})."
                );
            }

            $allocated = (int) $budget->allocated_amount_idr;
            $encumbered = (int) $budget->encumbered_amount_idr;
            $spent = (int) $budget->spent_amount_idr;
            $available = $allocated - ($encumbered + $spent);

            if ($request->amountMinor > $available && strtoupper((string) $budget->control_type) !== 'SOFT_STOP') {
                throw new BudgetGuardrailBreachException(
                    "Pelanggaran Hard-Stop: Permintaan reservasi Rp " . number_format($request->amountMinor / 100, 2) .
                    " melebihi sisa pagu bebas Rp " . number_format($available / 100, 2) . " pada anggaran [{$budget->budget_code}]."
                );
            }

            // Catat reservasi ke ef_budget_encumbrances
            BudgetEncumbrance::create([
                'budget_id' => $budget->id,
                'commitment_code' => $request->commitmentCode,
                'source_module' => $request->sourceModule,
                'reference_number' => $request->referenceNumber,
                'amount_minor' => $request->amountMinor,
                'liquidated_amount_minor' => 0,
                'status' => EncumbranceStatus::RESERVED->value,
                'reserved_at' => $this->now(),
                'reason' => $request->reason,
                'payload' => $request->payload,
            ]);

            // Mutasikan saldo encumbrance
            $newEncumbered = $encumbered + $request->amountMinor;
            $budget->encumbered_amount_idr = $newEncumbered;
            $budget->save();

            $newAvailable = $allocated - ($newEncumbered + $spent);

            // Deteksi ambang batas sisa pagu < 10%
            $thresholdPct = $allocated > 0 ? (int) floor(($newAvailable / $allocated) * 100) : 0;
            if ($thresholdPct <= 10) {
                $event = new EnterpriseBudgetThresholdExceeded(
                    budgetId: $budget->id,
                    budgetCode: $budget->budget_code,
                    allocatedAmountMinor: $allocated,
                    remainingAvailableMinor: $newAvailable,
                    thresholdPercent: $thresholdPct
                );

                if ($this->outboxBus !== null) {
                    $this->outboxBus->record(
                        EnterpriseBudgetThresholdExceeded::EVENT_NAME,
                        $event->toPayload(),
                        "EF:BUDGET:THRESH:{$budget->id}"
                    );
                }
            }

            return BudgetCommitmentResult::successful(
                commitmentCode: $request->commitmentCode,
                amountMinor: $request->amountMinor,
                status: EncumbranceStatus::RESERVED->value,
                remainingAvailableMinor: $newAvailable
            );
        });
    }

    /**
     * Melikuidasi komitmen terbuka saat invoice / tagihan diterima.
     */
    public function liquidate(string $commitmentCode, int $liquidatedAmountMinor): BudgetCommitmentResult
    {
        if ($liquidatedAmountMinor <= 0) {
            throw new BudgetGuardrailBreachException("Nominal likuidasi komitmen harus lebih besar dari nol.");
        }

        return DB::transaction(function () use ($commitmentCode, $liquidatedAmountMinor): BudgetCommitmentResult {
            /** @var BudgetEncumbrance|null $enc */
            $enc = BudgetEncumbrance::query()
                ->where('commitment_code', $commitmentCode)
                ->lockForUpdate()
                ->first();

            if ($enc === null) {
                return BudgetCommitmentResult::failed($commitmentCode, "Komitmen [{$commitmentCode}] tidak ditemukan.");
            }

            if ($enc->status !== EncumbranceStatus::RESERVED->value) {
                return BudgetCommitmentResult::failed($commitmentCode, "Komitmen [{$commitmentCode}] bukan dalam status RESERVED.");
            }

            /** @var EnterpriseBudget|null $budget */
            $budget = EnterpriseBudget::query()
                ->where('id', $enc->budget_id)
                ->lockForUpdate()
                ->first();

            if ($budget === null) {
                return BudgetCommitmentResult::failed($commitmentCode, "Anggaran induk tidak ditemukan.");
            }

            $currentEncumbered = (int) $budget->encumbered_amount_idr;
            $currentSpent = (int) $budget->spent_amount_idr;

            // Kurangi encumbered sebesar nilai awal reservasi (atau sebagian jika parsial)
            $releaseEnc = (int) $enc->amount_minor;
            $newEncumbered = max(0, $currentEncumbered - $releaseEnc);
            $newSpent = $currentSpent + $liquidatedAmountMinor;

            $budget->encumbered_amount_idr = $newEncumbered;
            $budget->spent_amount_idr = $newSpent;
            $budget->save();

            $enc->liquidated_amount_minor = $liquidatedAmountMinor;
            $enc->status = EncumbranceStatus::LIQUIDATED->value;
            $enc->liquidated_at = $this->now();
            $enc->save();

            $remainingAvailable = (int) $budget->allocated_amount_idr - ($newEncumbered + $newSpent);

            return BudgetCommitmentResult::successful(
                commitmentCode: $commitmentCode,
                amountMinor: $liquidatedAmountMinor,
                status: EncumbranceStatus::LIQUIDATED->value,
                remainingAvailableMinor: $remainingAvailable
            );
        });
    }

    /**
     * Melepaskan reservasi komitmen terbuka (misal akibat pembatalan PO).
     */
    public function release(string $commitmentCode, string $reason = ''): BudgetCommitmentResult
    {
        return DB::transaction(function () use ($commitmentCode, $reason): BudgetCommitmentResult {
            /** @var BudgetEncumbrance|null $enc */
            $enc = BudgetEncumbrance::query()
                ->where('commitment_code', $commitmentCode)
                ->lockForUpdate()
                ->first();

            if ($enc === null) {
                return BudgetCommitmentResult::failed($commitmentCode, "Komitmen [{$commitmentCode}] tidak ditemukan.");
            }

            if ($enc->status !== EncumbranceStatus::RESERVED->value) {
                return BudgetCommitmentResult::failed($commitmentCode, "Hanya komitmen RESERVED yang dapat dilepaskan.");
            }

            /** @var EnterpriseBudget|null $budget */
            $budget = EnterpriseBudget::query()
                ->where('id', $enc->budget_id)
                ->lockForUpdate()
                ->first();

            if ($budget === null) {
                return BudgetCommitmentResult::failed($commitmentCode, "Anggaran induk tidak ditemukan.");
            }

            $currentEncumbered = (int) $budget->encumbered_amount_idr;
            $releaseAmount = (int) $enc->amount_minor;
            $newEncumbered = max(0, $currentEncumbered - $releaseAmount);

            $budget->encumbered_amount_idr = $newEncumbered;
            $budget->save();

            $enc->status = EncumbranceStatus::RELEASED->value;
            $enc->released_at = $this->now();
            $enc->reason = $reason;
            $enc->save();

            $remainingAvailable = (int) $budget->allocated_amount_idr - ($newEncumbered + (int) $budget->spent_amount_idr);

            return BudgetCommitmentResult::successful(
                commitmentCode: $commitmentCode,
                amountMinor: $releaseAmount,
                status: EncumbranceStatus::RELEASED->value,
                remainingAvailableMinor: $remainingAvailable
            );
        });
    }

    /**
     * Menghitung agregasi hierarkis alokasi anggaran per entitas dan departemen.
     *
     * @return array<string, array{
     *     total_allocated_minor: int,
     *     total_encumbered_minor: int,
     *     total_spent_minor: int,
     *     total_available_minor: int,
     *     budget_count: int
     * }>
     */
    public function calculateDepartmentRollup(string $entityCode, int $fiscalYear): array
    {
        $budgets = EnterpriseBudget::query()
            ->where('fiscal_year', (string) $fiscalYear)
            ->get();

        $rollup = [];
        foreach ($budgets as $bgt) {
            $cc = (string) $bgt->cost_center_code;
            if (! isset($rollup[$cc])) {
                $rollup[$cc] = [
                    'total_allocated_minor' => 0,
                    'total_encumbered_minor' => 0,
                    'total_spent_minor' => 0,
                    'total_available_minor' => 0,
                    'budget_count' => 0,
                ];
            }

            $alloc = (int) $bgt->allocated_amount_idr;
            $enc = (int) $bgt->encumbered_amount_idr;
            $spent = (int) $bgt->spent_amount_idr;
            $avail = $alloc - ($enc + $spent);

            $rollup[$cc]['total_allocated_minor'] += $alloc;
            $rollup[$cc]['total_encumbered_minor'] += $enc;
            $rollup[$cc]['total_spent_minor'] += $spent;
            $rollup[$cc]['total_available_minor'] += $avail;
            $rollup[$cc]['budget_count']++;
        }

        return $rollup;
    }

    /**
     * Mengeksekusi pergeseran pagu anggaran antar mata anggaran (Budget Line Reallocation).
     */
    public function reallocateBudgetLine(
        int $sourceBudgetId,
        int $targetBudgetId,
        int $amountMinor,
        int $operatorId,
        string $justification
    ): array {
        if ($sourceBudgetId === $targetBudgetId) {
            throw new InvalidArgumentException('Mata anggaran donor dan penerima tidak boleh sama.');
        }

        return DB::transaction(function () use ($sourceBudgetId, $targetBudgetId, $amountMinor, $operatorId, $justification): array {
            /** @var EnterpriseBudget|null $source */
            $source = EnterpriseBudget::query()->where('id', $sourceBudgetId)->lockForUpdate()->firstOrFail();
            /** @var EnterpriseBudget|null $target */
            $target = EnterpriseBudget::query()->where('id', $targetBudgetId)->lockForUpdate()->firstOrFail();

            $sourceAlloc = (int) $source->allocated_amount_idr;
            $sourceCommitted = (int) $source->encumbered_amount_idr + (int) $source->spent_amount_idr;

            $feasibility = BudgetVarianceAnalyzer::evaluateReallocationFeasibility(
                $sourceAlloc,
                $sourceCommitted,
                $amountMinor
            );

            if (! $feasibility['is_transfer_feasible']) {
                throw new BudgetGuardrailBreachException(
                    "Pergeseran pagu ditolak: " . ($feasibility['rejection_reason'] ?? 'Saldo pagu donor tidak mencukupi.')
                );
            }

            $source->allocated_amount_idr = $sourceAlloc - $amountMinor;
            $source->save();

            $target->allocated_amount_idr = (int) $target->allocated_amount_idr + $amountMinor;
            $target->save();

            return [
                'success' => true,
                'source_budget_id' => $sourceBudgetId,
                'target_budget_id' => $targetBudgetId,
                'transferred_amount_minor' => $amountMinor,
                'source_available_after_minor' => $feasibility['donor_available_after_minor'],
                'target_allocated_after_minor' => (int) $target->allocated_amount_idr,
            ];
        });
    }
}
