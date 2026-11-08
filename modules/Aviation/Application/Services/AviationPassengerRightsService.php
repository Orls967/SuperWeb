<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Algorithms\PassengerCareObligationCalculator;
use Modules\Aviation\Domain\Models\AviationCareVoucher;
use Modules\Aviation\Domain\Models\AviationDisruptionCase;
use Modules\Aviation\Domain\Models\AviationDisruptionPassenger;
use Modules\Aviation\Domain\StateMachines\DisruptionCaseStateMachine;

class AviationPassengerRightsService
{
    public function __construct(
        protected PassengerCareObligationCalculator $calculator,
        protected DisruptionCaseStateMachine $stateMachine
    ) {}

    /**
     * Dapatkan hak kepedulian yang berlaku untuk penumpang tiket tertentu pada kasus disrupsi.
     */
    public function getPassengerRightsDetail(int $disruptionPassengerId): array
    {
        $pax = AviationDisruptionPassenger::with(['disruptionCase', 'careVouchers'])->findOrFail($disruptionPassengerId);
        $case = $pax->disruptionCase;

        $eval = $this->calculator->evaluateObligation($case->delay_minutes, $case->incident_category);

        return [
            'passenger_name' => $pax->passenger_name,
            'flight_id' => $case->flight_id,
            'delay_minutes' => $case->delay_minutes,
            'incident_category' => $case->incident_category,
            'regulatory_category' => $eval['category'],
            'duty_of_care_required' => $eval['duty_of_care_required'],
            'cash_compensation_amount_idr' => (int) round($eval['cash_compensation_amount_minor_unit'] / 100),
            'hotel_allocated' => $pax->hotel_reservation_ref !== null,
            'hotel_ref' => $pax->hotel_reservation_ref,
            'vouchers' => $pax->careVouchers->map(fn (AviationCareVoucher $v) => [
                'code' => $v->voucher_code,
                'type' => $v->voucher_type,
                'amount_idr' => (int) round($v->nominal_amount_minor_unit / 100),
                'is_redeemed' => $v->is_redeemed,
                'expires_at' => $v->expires_at->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * Ajukan sanggahan resmi penumpang atas klasifikasi disrupsi atau penolakan santunan tunai.
     */
    public function lodgePassengerDispute(int $disruptionCaseId, int $disruptionPassengerId, string $disputeReason): array
    {
        return DB::transaction(function () use ($disruptionCaseId, $disruptionPassengerId, $disputeReason) {
            $case = AviationDisruptionCase::lockForUpdate()->findOrFail($disruptionCaseId);
            $pax = AviationDisruptionPassenger::findOrFail($disruptionPassengerId);

            if ($this->stateMachine->canTransition($case->status, DisruptionCaseStateMachine::STATUS_DISPUTED)) {
                $case->status = $this->stateMachine->transition($case->status, DisruptionCaseStateMachine::STATUS_DISPUTED);
                $case->save();
            }

            $pax->reaccommodation_status = 'disputed';
            $pax->save();

            $disputeTicket = sprintf('DSP-%s-%d', date('Ymd'), $pax->id);

            return [
                'dispute_ticket' => $disputeTicket,
                'case_id' => $case->id,
                'passenger_id' => $pax->id,
                'status' => 'under_investigation',
                'reason' => $disputeReason,
            ];
        });
    }
}
