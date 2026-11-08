<?php

declare(strict_types=1);

namespace Modules\Aviation\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Algorithms\DisruptedPassengerReaccommodator;
use Modules\Aviation\Domain\Models\AviationDisruptionCase;
use Modules\Aviation\Domain\Models\AviationDisruptionPassenger;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\StateMachines\DisruptionCaseStateMachine;
use Modules\Aviation\Integrations\HotelIrropsRoomReservationService;

class ReaccommodatePassengersAction
{
    public function __construct(
        protected DisruptedPassengerReaccommodator $reaccommodator,
        protected DisruptionCaseStateMachine $stateMachine,
        protected HotelIrropsRoomReservationService $hotelService
    ) {}

    /**
     * Susun prioritas re-akomodasi penumpang dan alokasikan kamar hotel bila diperlukan.
     *
     * @return array{case_id: int, passengers_processed: int, hotel_reservations_count: int, status: string}
     */
    public function execute(int $disruptionCaseId, ?int $alternativeFlightId = null): array
    {
        return DB::transaction(function () use ($disruptionCaseId, $alternativeFlightId) {
            $case = AviationDisruptionCase::lockForUpdate()->findOrFail($disruptionCaseId);

            if ($this->stateMachine->canTransition($case->status, DisruptionCaseStateMachine::STATUS_REACCOMMODATING)) {
                $case->status = $this->stateMachine->transition($case->status, DisruptionCaseStateMachine::STATUS_REACCOMMODATING);
                $case->save();
            }

            $passengers = AviationDisruptionPassenger::where('disruption_case_id', $case->id)->get();
            if ($passengers->isEmpty()) {
                return [
                    'case_id' => $case->id,
                    'passengers_processed' => 0,
                    'hotel_reservations_count' => 0,
                    'status' => $case->status,
                ];
            }

            // Siapkan payload untuk algoritma prioritisasi A7
            $rawPayload = [];
            foreach ($passengers as $pax) {
                $rawPayload[] = [
                    'id' => $pax->id,
                    'name' => $pax->passenger_name,
                    'tier' => $pax->loyalty_tier,
                    'cabin' => 'economy',
                    'has_connection' => false,
                    'special' => $pax->special_service_request,
                    'booking_time' => (int) $pax->created_at->timestamp,
                ];
            }

            $prioritized = $this->reaccommodator->prioritizePassengers($rawPayload);
            $prioritizedMap = collect($prioritized)->keyBy('id');

            // Simpan skor prioritas kembali ke database
            foreach ($passengers as $pax) {
                if ($prioritizedMap->has($pax->id)) {
                    $pax->priority_score = (int) $prioritizedMap->get($pax->id)['priority_score'];
                    $pax->save();
                }
            }

            $hotelCount = 0;
            // Jika kasus membutuhkan penginapan hotel (Kategori 6 atau delay ekstrem)
            if ($case->hotel_required) {
                $destinationCity = $case->flight->arrivalAirport->city ?? 'Kota Tujuan';
                $hotelRes = $this->hotelService->reserveRoomsForIrrops(
                    count($passengers),
                    $destinationCity,
                    $case->case_number
                );

                $hotelRef = $hotelRes['reservation_id'] ?? ('HTL-RES-'.$case->id);
                foreach ($passengers as $pax) {
                    $pax->hotel_reservation_ref = $hotelRef;
                    $pax->save();
                    $hotelCount++;
                }
            }

            // Jika ada penerbangan alternatif, tetapkan rebooking
            if ($alternativeFlightId !== null) {
                $altFlight = AviationFlight::findOrFail($alternativeFlightId);
                foreach ($passengers as $pax) {
                    $pax->new_flight_id = $altFlight->id;
                    $pax->reaccommodation_status = 'confirmed';
                    $pax->save();
                }

                if ($this->stateMachine->canTransition($case->status, DisruptionCaseStateMachine::STATUS_REBOOKED)) {
                    $case->status = $this->stateMachine->transition($case->status, DisruptionCaseStateMachine::STATUS_REBOOKED);
                    $case->save();
                }
            }

            return [
                'case_id' => $case->id,
                'passengers_processed' => count($passengers),
                'hotel_reservations_count' => $hotelCount,
                'status' => $case->status,
            ];
        });
    }
}
