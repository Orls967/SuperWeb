<?php

declare(strict_types=1);

namespace Modules\Aviation\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Algorithms\PassengerCareObligationCalculator;
use Modules\Aviation\Domain\Models\AviationDisruptionCase;
use Modules\Aviation\Domain\Models\AviationDisruptionPassenger;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationTicket;
use Modules\Aviation\Domain\StateMachines\DisruptionCaseStateMachine;

class ProcessDisruptionCaseAction
{
    public function __construct(
        protected PassengerCareObligationCalculator $calculator,
        protected DisruptionCaseStateMachine $stateMachine
    ) {}

    /**
     * Daftarkan dan proses kasus insiden disrupsi operasional.
     *
     * @param array{
     *     flight_id: int,
     *     incident_category: string,
     *     delay_minutes: int,
     *     incident_description: string,
     *     detected_at?: string
     * } $data
     */
    public function execute(array $data): AviationDisruptionCase
    {
        return DB::transaction(function () use ($data) {
            $flight = AviationFlight::lockForUpdate()->findOrFail($data['flight_id']);
            $delayMinutes = (int) $data['delay_minutes'];
            $category = $data['incident_category'];

            $eval = $this->calculator->evaluateObligation($delayMinutes, $category);

            $caseNumber = sprintf('IRR-%s-%s-%s', date('Ymd'), $flight->flight_number, substr(uniqid('', true), -4));

            $disruptionCase = AviationDisruptionCase::create([
                'flight_id' => $flight->id,
                'case_number' => $caseNumber,
                'incident_category' => $category,
                'delay_minutes' => $delayMinutes,
                'status' => DisruptionCaseStateMachine::STATUS_DETECTED,
                'regulatory_category' => $eval['category'],
                'duty_of_care_required' => $eval['duty_of_care_required'],
                'cash_compensation_required' => $eval['cash_compensation_required'],
                'hotel_required' => $eval['hotel_required'],
                'incident_description' => $data['incident_description'],
                'detected_at' => $data['detected_at'] ?? Carbon::now(),
            ]);

            // Transisikan status ke CLASSIFIED
            $disruptionCase->status = $this->stateMachine->transition(
                $disruptionCase->status,
                DisruptionCaseStateMachine::STATUS_CLASSIFIED
            );
            $disruptionCase->save();

            // Jika Kategori 6 (pembatalan) atau delay sangat parah, update status flight
            if ($eval['category'] === 'CAT6_CANCELLED') {
                $flight->status = 'cancelled';
                $flight->save();
            } elseif ($delayMinutes >= 60 && $flight->status === 'scheduled') {
                $flight->status = 'delayed';
                $flight->save();
            }

            // Daftarkan manifest seluruh penumpang tiket aktif
            $tickets = AviationTicket::where('flight_id', $flight->id)
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->get();

            foreach ($tickets as $ticket) {
                AviationDisruptionPassenger::create([
                    'disruption_case_id' => $disruptionCase->id,
                    'ticket_id' => $ticket->id,
                    'passenger_name' => $ticket->passenger_name,
                    'priority_score' => 0, // Akan dihitung oleh ReaccommodatePassengersAction
                    'loyalty_tier' => 'classic',
                    'special_service_request' => null,
                    'reaccommodation_status' => 'pending',
                ]);
            }

            return $disruptionCase;
        });
    }
}
