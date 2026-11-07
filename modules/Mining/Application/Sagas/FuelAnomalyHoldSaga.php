<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Sagas;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\ClockInterface;
use Modules\Mining\Domain\Enums\FuelAnomalyCaseStatus;
use Modules\Mining\Domain\Models\MiningContractorPaymentHold;
use Modules\Mining\Domain\Models\MiningFuelAnomalyCase;
use Modules\Mining\Domain\Models\MiningSaga;
use Modules\Mining\Domain\StateMachines\FuelAnomalyCaseStateMachine;
use Modules\Mining\Ledger\MiningFase93LedgerPostingService;
use Modules\Shared\Domain\Algorithms\Stats\LinearBaselineRegression;

/**
 * Saga Penanganan Anomali Konsumsi Bahan Bakar & Penahanan Tagihan Kontraktor (D93-5, T-4).
 * Terintegrasi penuh dengan double-entry ledger dan State Machine 8 status.
 */
class FuelAnomalyHoldSaga
{
    public function __construct(
        private readonly LinearBaselineRegression $regression,
        private readonly LedgerService $ledgerService,
        private readonly FuelAnomalyCaseStateMachine $stateMachine,
        private readonly ClockInterface $clock,
        private readonly ?MiningFase93LedgerPostingService $postingService = null
    ) {}

    /**
     * @param  array{
     *     site_id: string,
     *     equipment_id: string,
     *     contractor_party_id: string,
     *     shift_date: string,
     *     shift_code: string,
     *     historical_ton_km_thousands: list<float|int>,
     *     historical_fuel_liters: list<float|int>,
     *     actual_ton_km_thousands: float|int,
     *     actual_fuel_liters: float|int,
     *     fuel_rate_per_liter_idr: int
     * }  $payload
     * @return array{
     *     is_anomaly: bool,
     *     excess_bps: int,
     *     case_id: string|null,
     *     held_amount_idr: int,
     *     status: string
     * }
     */
    public function execute(array $payload, ?User $actor = null): array
    {
        $regResult = $this->regression->fitAndPredict(
            xPoints: $payload['historical_ton_km_thousands'],
            yPoints: $payload['historical_fuel_liters'],
            newX: $payload['actual_ton_km_thousands'],
            actualY: $payload['actual_fuel_liters'],
            thresholdBps: 1500
        );

        if (! $regResult->isAnomaly) {
            return [
                'is_anomaly' => false,
                'excess_bps' => $regResult->excessBps,
                'case_id' => null,
                'held_amount_idr' => 0,
                'status' => 'NORMAL',
            ];
        }

        $sagaRef = "SAGA-FUEL-{$payload['equipment_id']}-{$payload['shift_date']}-{$payload['shift_code']}";

        $existingCase = MiningFuelAnomalyCase::where('equipment_id', $payload['equipment_id'])
            ->whereDate('shift_date', $payload['shift_date'])
            ->where('shift_code', $payload['shift_code'])
            ->first();

        if ($existingCase) {
            return [
                'is_anomaly' => true,
                'excess_bps' => $existingCase->excess_bps,
                'case_id' => $existingCase->id,
                'held_amount_idr' => $existingCase->held_amount_idr,
                'status' => $existingCase->status instanceof FuelAnomalyCaseStatus ? $existingCase->status->value : (string) $existingCase->status,
            ];
        }

        $heldAmountIdr = (int) round($regResult->excessY * $payload['fuel_rate_per_liter_idr']);

        $saga = MiningSaga::create([
            'saga_type' => 'FUEL_ANOMALY_HOLD',
            'saga_reference' => $sagaRef,
            'current_step' => 'STEP_1_CREATE_CASE',
            'status' => 'IN_PROGRESS',
            'payload_json' => array_merge($payload, [
                'excess_bps' => $regResult->excessBps,
                'held_amount_idr' => $heldAmountIdr,
            ]),
            'compensation_log_json' => [],
        ]);

        return DB::transaction(function () use ($payload, $regResult, $heldAmountIdr, $saga, $actor, $sagaRef) {
            $caseNumber = 'FAC-'.$this->clock->now()->format('Ymd').'-'.strtoupper(substr(md5($sagaRef), 0, 6));

            $case = MiningFuelAnomalyCase::create([
                'case_number' => $caseNumber,
                'site_id' => $payload['site_id'],
                'contractor_party_id' => $payload['contractor_party_id'],
                'equipment_id' => $payload['equipment_id'],
                'shift_date' => $payload['shift_date'],
                'shift_code' => $payload['shift_code'],
                'actual_fuel_ml' => (int) round($payload['actual_fuel_liters'] * 1000),
                'baseline_fuel_ml' => (int) round($regResult->predictedY * 1000),
                'excess_ml' => (int) round($regResult->excessY * 1000),
                'excess_bps' => $regResult->excessBps,
                'held_amount_idr' => $heldAmountIdr,
                'status' => FuelAnomalyCaseStatus::DETECTED->value,
            ]);

            $this->stateMachine->transition($case, FuelAnomalyCaseStatus::CONFIRMED, $actor ? (string) $actor->id : 'system', 'Dikonfirmasi algoritma M3');

            $saga->update([
                'current_step' => 'STEP_2_POST_LEDGER_HOLD',
            ]);

            $partyId = $payload['contractor_party_id'];
            $poster = $this->postingService ?? new MiningFase93LedgerPostingService($this->ledgerService);
            $postResult = $poster->postContractorFuelHold(
                caseId: (string) $case->id,
                caseNumber: $caseNumber,
                contractorPartyId: $partyId,
                shiftDate: $payload['shift_date'],
                heldAmountIdr: $heldAmountIdr
            );

            MiningContractorPaymentHold::create([
                'hold_reference' => "HLD-{$caseNumber}",
                'contractor_party_id' => $partyId,
                'case_id' => $case->id,
                'hold_amount_idr' => $heldAmountIdr,
                'released_amount_idr' => 0,
                'deducted_amount_idr' => 0,
                'status' => 'HELD',
                'idempotency_key' => $postResult['idempotency_key'],
            ]);

            $this->stateMachine->transition($case, FuelAnomalyCaseStatus::PAYMENT_HELD, $actor ? (string) $actor->id : 'system', 'Jurnal penahanan tagihan berhasil dibukukan');

            $saga->update([
                'status' => 'COMPLETED',
                'current_step' => 'DONE',
                'completed_at' => $this->clock->now(),
            ]);

            return [
                'is_anomaly' => true,
                'excess_bps' => $regResult->excessBps,
                'case_id' => $case->id,
                'held_amount_idr' => $heldAmountIdr,
                'status' => FuelAnomalyCaseStatus::PAYMENT_HELD->value,
            ];
        });
    }

    /**
     * Resolusi sengketa anomali BBM: Pemotongan permanen (Deduct).
     */
    public function resolveDeducted(MiningFuelAnomalyCase $case, ?User $actor = null, ?string $notes = null): void
    {
        DB::transaction(function () use ($case, $actor, $notes) {
            $this->stateMachine->transition($case, FuelAnomalyCaseStatus::RESOLVED_DEDUCTED, $actor ? (string) $actor->id : 'system', $notes ?? 'Pemotongan deviasi BBM terbukti');

            $hold = MiningContractorPaymentHold::where('case_id', $case->id)->first();
            if ($hold && $hold->status === 'HELD') {
                $holdAmount = (int) $hold->hold_amount_idr;
                $poster = $this->postingService ?? new MiningFase93LedgerPostingService($this->ledgerService);
                $poster->postContractorFuelHoldDeduction(
                    caseId: (string) $case->id,
                    caseNumber: (string) $case->case_number,
                    holdAmountIdr: $holdAmount
                );

                $hold->update([
                    'status' => 'DEDUCTED',
                    'deducted_amount_idr' => $holdAmount,
                ]);
            }
        });
    }

    /**
     * Resolusi sengketa anomali BBM: Rilis penahanan (Release).
     */
    public function resolveReleased(MiningFuelAnomalyCase $case, ?User $actor = null, ?string $notes = null): void
    {
        DB::transaction(function () use ($case, $actor, $notes) {
            $this->stateMachine->transition($case, FuelAnomalyCaseStatus::RESOLVED_RELEASED, $actor ? (string) $actor->id : 'system', $notes ?? 'Sanggahan diterima, dana dirilis');

            $hold = MiningContractorPaymentHold::where('case_id', $case->id)->first();
            if ($hold && $hold->status === 'HELD') {
                $holdAmount = (int) $hold->hold_amount_idr;
                $poster = $this->postingService ?? new MiningFase93LedgerPostingService($this->ledgerService);
                $poster->postContractorFuelHoldRelease(
                    caseId: (string) $case->id,
                    caseNumber: (string) $case->case_number,
                    contractorPartyId: (string) $case->contractor_party_id,
                    holdAmountIdr: $holdAmount
                );

                $hold->update([
                    'status' => 'RELEASED',
                    'released_amount_idr' => $holdAmount,
                ]);
            }
        });
    }
}
