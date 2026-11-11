<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\EnterpriseFinance\Contracts\BudgetCommitmentPort;
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
                    "Pagu anggaran [{$budget->budget_code}] tidak aktif (status: {$budget->status}). Reservasi ditolak."
                );
            }

            // Hitung sisa pagu bebas
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

            // Catat encumbrance
            $encumbrance = BudgetEncumbrance::create([
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

            // Perbarui akumulasi komitmen di pagu anggaran
            $budget->encumbered_amount_idr = $encumbered + $request->amountMinor;
            $budget->save();

            $newAvailable = $allocated - ($budget->encumbered_amount_idr + $spent);

            // Periksa threshold alert (bila sisa bebas < 10% dari alokasi awal)
            if ($allocated > 0 && ($newAvailable / $allocated) < 0.10) {
                $thresholdPct = (int) round(($newAvailable / $allocated) * 100);
                $event = new EnterpriseBudgetThresholdExceeded(
                    budgetId: (int) $budget->id,
                    budgetCode: (string) $budget->budget_code,
                    entityCode: (string) $budget->cost_center_code,
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
     * Melikuidasi komitmen yang telah terealisasi menjadi beban aktual.
     */
    public function liquidate(string $commitmentCode, int $actualAmountMinor): BudgetCommitmentResult
    {
        if ($actualAmountMinor <= 0) {
            throw new BudgetGuardrailBreachException(
                "Nominal likuidasi komitmen harus lebih besar dari nol ({$actualAmountMinor})."
            );
        }

        return DB::transaction(function () use ($commitmentCode, $actualAmountMinor): BudgetCommitmentResult {
            /** @var BudgetEncumbrance|null $encumbrance */
            $encumbrance = BudgetEncumbrance::query()
                ->where('commitment_code', $commitmentCode)
                ->lockForUpdate()
                ->first();

            if ($encumbrance === null) {
                return BudgetCommitmentResult::failed($commitmentCode, "Komitmen anggaran '{$commitmentCode}' tidak ditemukan.");
            }

            if ($encumbrance->status !== EncumbranceStatus::RESERVED->value) {
                return BudgetCommitmentResult::failed($commitmentCode, "Komitmen '{$commitmentCode}' tidak berstatus RESERVED (status: {$encumbrance->status}).");
            }

            /** @var EnterpriseBudget $budget */
            $budget = EnterpriseBudget::query()
                ->where('id', $encumbrance->budget_id)
                ->lockForUpdate()
                ->firstOrFail();

            $reservedAmount = (int) $encumbrance->amount_minor;

            // Kurangi komitmen tertahan, tambahkan ke realisasi belanja
            $budget->encumbered_amount_idr = max(0, ((int) $budget->encumbered_amount_idr) - $reservedAmount);
            $budget->spent_amount_idr = ((int) $budget->spent_amount_idr) + $actualAmountMinor;
            $budget->save();

            $encumbrance->status = EncumbranceStatus::LIQUIDATED->value;
            $encumbrance->liquidated_amount_minor = $actualAmountMinor;
            $encumbrance->liquidated_at = $this->now();
            $encumbrance->save();

            $allocated = (int) $budget->allocated_amount_idr;
            $newAvailable = $allocated - (((int) $budget->encumbered_amount_idr) + ((int) $budget->spent_amount_idr));

            return BudgetCommitmentResult::successful(
                commitmentCode: $commitmentCode,
                amountMinor: $actualAmountMinor,
                status: EncumbranceStatus::LIQUIDATED->value,
                remainingAvailableMinor: $newAvailable
            );
        });
    }

    /**
     * Melepaskan komitmen terbuka yang dibatalkan atau ditutup sebelum realisasi penuh.
     */
    public function release(string $commitmentCode, string $reason): BudgetCommitmentResult
    {
        return DB::transaction(function () use ($commitmentCode, $reason): BudgetCommitmentResult {
            /** @var BudgetEncumbrance|null $encumbrance */
            $encumbrance = BudgetEncumbrance::query()
                ->where('commitment_code', $commitmentCode)
                ->lockForUpdate()
                ->first();

            if ($encumbrance === null) {
                return BudgetCommitmentResult::failed($commitmentCode, "Komitmen anggaran '{$commitmentCode}' tidak ditemukan.");
            }

            if ($encumbrance->status !== EncumbranceStatus::RESERVED->value) {
                return BudgetCommitmentResult::failed($commitmentCode, "Hanya komitmen RESERVED yang dapat dilepaskan.");
            }

            /** @var EnterpriseBudget $budget */
            $budget = EnterpriseBudget::query()
                ->where('id', $encumbrance->budget_id)
                ->lockForUpdate()
                ->firstOrFail();

            $reservedAmount = (int) $encumbrance->amount_minor;

            // Pulihkan pagu tertahan
            $budget->encumbered_amount_idr = max(0, ((int) $budget->encumbered_amount_idr) - $reservedAmount);
            $budget->save();

            $encumbrance->status = EncumbranceStatus::RELEASED->value;
            $encumbrance->released_at = $this->now();
            $encumbrance->reason = $reason;
            $encumbrance->save();

            $allocated = (int) $budget->allocated_amount_idr;
            $newAvailable = $allocated - (((int) $budget->encumbered_amount_idr) + ((int) $budget->spent_amount_idr));

            return BudgetCommitmentResult::successful(
                commitmentCode: $commitmentCode,
                amountMinor: $reservedAmount,
                status: EncumbranceStatus::RELEASED->value,
                remainingAvailableMinor: $newAvailable
            );
        });
    }
}

