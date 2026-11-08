<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Sagas;

use DomainException;
use Illuminate\Support\Facades\Log;
use Modules\Aviation\Actions\DispatchPassengerCareAction;
use Modules\Aviation\Actions\ProcessDisruptionCaseAction;
use Modules\Aviation\Actions\ReaccommodatePassengersAction;
use Modules\Aviation\Domain\Models\AviationCareVoucher;
use Modules\Aviation\Domain\Models\AviationDisruptionCase;
use Modules\Aviation\Domain\Models\AviationDisruptionPassenger;
use Modules\Aviation\Domain\StateMachines\DisruptionCaseStateMachine;
use Modules\Aviation\Integrations\InsuranceParametricTriggerService;

class DisruptionIrropsSaga
{
    public function __construct(
        protected ProcessDisruptionCaseAction $processCaseAction,
        protected DispatchPassengerCareAction $dispatchCareAction,
        protected ReaccommodatePassengersAction $reaccommodateAction,
        protected InsuranceParametricTriggerService $parametricService
    ) {}

    /**
     * Eksekusi alur penanganan disrupsi operasional IRROPS end-to-end.
     *
     * @param array{
     *     flight_id: int,
     *     incident_category: string,
     *     delay_minutes: int,
     *     incident_description: string,
     *     alternative_flight_id?: int|null
     * } $payload
     * @return array{saga_id: string, disruption_case_id: int, status: string, vouchers_dispatched: int, hotel_rooms: int}
     */
    public function execute(array $payload): array
    {
        $sagaId = sprintf('SAGA-IRR-%s-%s', date('Ymd'), strtoupper(substr(uniqid('', true), -6)));
        $completedSteps = [];
        $caseId = null;

        try {
            // Langkah 1: Buat Kasus Disrupsi & Daftarkan Manifes Penumpang
            $case = $this->processCaseAction->execute($payload);
            $caseId = $case->id;
            $completedSteps[] = 'STEP_1_CASE_PROCESSED';

            // Langkah 2: Terbitkan Paket Hak Kepedulian & E-Voucher PM 89
            $careResult = $this->dispatchCareAction->execute($caseId);
            $completedSteps[] = 'STEP_2_CARE_DISPATCHED';

            // Langkah 3: Susun Prioritas Penumpang & Alokasikan Hotel / Rebooking
            $reaccResult = $this->reaccommodateAction->execute(
                $caseId,
                $payload['alternative_flight_id'] ?? null
            );
            $completedSteps[] = 'STEP_3_REACCOMMODATED';

            // Langkah 4: Trigger Asuransi Parametrik Otomatis jika Memenuhi Syarat
            if ($case->delay_minutes >= 120 || $case->isForceMajeure()) {
                $triggerType = $case->isForceMajeure() ? 'VOLCANIC_ERUPTION_CLOSURE' : 'DELAY_OVER_180MIN';
                $paxList = AviationDisruptionPassenger::where('disruption_case_id', $caseId)->get();

                foreach ($paxList as $pax) {
                    $policyNumber = sprintf('POL-TRV-%d-%s', $pax->ticket_id, date('Ymd'));
                    $this->parametricService->recordFlightTrigger(
                        $caseId,
                        $policyNumber,
                        (int) $pax->id,
                        $case->flight->flight_number ?? 'FLIGHT',
                        $triggerType,
                        $case->delay_minutes,
                        100000000 // Rp1.000.000
                    );
                }
                $completedSteps[] = 'STEP_4_PARAMETRIC_INSURANCE_RECORDED';
            }

            return [
                'saga_id' => $sagaId,
                'disruption_case_id' => $caseId,
                'status' => 'COMPLETED',
                'vouchers_dispatched' => $careResult['vouchers_dispatched'],
                'hotel_rooms' => $reaccResult['hotel_reservations_count'],
            ];
        } catch (\Throwable $e) {
            Log::error('DisruptionIrropsSaga gagal, memicu kompensasi', [
                'saga_id' => $sagaId,
                'error' => $e->getMessage(),
                'case_id' => $caseId,
                'completed_steps' => $completedSteps,
            ]);

            if ($caseId !== null) {
                $this->compensate($sagaId, $completedSteps, $caseId);
            }

            throw new DomainException(sprintf('Saga IRROPS gagal [%s]: %s', $sagaId, $e->getMessage()), 0, $e);
        }
    }

    protected function compensate(string $sagaId, array $completedSteps, int $caseId): void
    {
        if (in_array('STEP_2_CARE_DISPATCHED', $completedSteps, true)) {
            // Batalkan seluruh voucher yang belum ditebus
            $paxIds = AviationDisruptionPassenger::where('disruption_case_id', $caseId)->pluck('id');
            AviationCareVoucher::whereIn('disruption_passenger_id', $paxIds)
                ->where('is_redeemed', false)
                ->delete();
        }

        $case = AviationDisruptionCase::find($caseId);
        if ($case !== null) {
            $case->status = DisruptionCaseStateMachine::STATUS_DISPUTED;
            $case->save();
        }

        Log::info('Kompensasi DisruptionIrropsSaga selesai', [
            'saga_id' => $sagaId,
            'case_id' => $caseId,
            'status' => 'DISPUTED',
        ]);
    }
}
