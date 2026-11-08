<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Enums\FlightStatus;
use Modules\Aviation\Domain\Enums\TicketStatus;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationTicket;
use Modules\Aviation\Integrations\HotelIrropsAccommodationService;

class AviationIrropsOrchestratorService
{
    public function __construct(
        private readonly HotelIrropsAccommodationService $hotelService,
        private readonly \Modules\Aviation\Application\Sagas\FlightCancellationMassRefundSaga $massRefundSaga
    ) {}

    /**
     * Evaluasi tingkat keterlambatan penerbangan dan tentukan paket kompensasi wajib regulasi PM 89/2015.
     *
     * @param int $flightId
     * @param int $delayMinutes
     * @param string $causeReason
     * @return array{
     *     category: int,
     *     compensation_type: string,
     *     cash_compensation_minor_per_pax: int,
     *     hotel_required: bool,
     *     eligible_passengers_count: int,
     *     action_taken: string
     * }
     */
    public function handleFlightDelayDisruption(int $flightId, int $delayMinutes, string $causeReason): array
    {
        $flight = AviationFlight::findOrFail($flightId);

        $category = match (true) {
            $delayMinutes >= 241 => 5, // Kategori 5: > 240 menit (uang tunai Rp 300.000)
            $delayMinutes >= 181 => 4, // Kategori 4: 181-240 menit (makanan berat + opsi pengalihan)
            $delayMinutes >= 121 => 3, // Kategori 3: 121-180 menit (makanan berat)
            $delayMinutes >= 61 => 2,  // Kategori 2: 61-120 menit (makanan ringan)
            $delayMinutes >= 30 => 1,  // Kategori 1: 30-60 menit (minuman)
            default => 0,              // Minor (< 30 menit)
        };

        $compensationType = match ($category) {
            5 => 'CASH_COMPENSATION_AND_MEALS',
            4 => 'HOT_MEAL_AND_REBOOKING_OPTION',
            3 => 'HOT_MEAL_VOUCHER',
            2 => 'SNACK_AND_BEVERAGE_VOUCHER',
            1 => 'LIGHT_REFRESHMENT_BEVERAGE',
            default => 'NO_STATUTORY_COMPENSATION',
        };

        $cashCompensationMinor = ($category === 5) ? 30000000 : 0; // Rp 300.000 dalam minor IDR

        $tickets = AviationTicket::where('flight_id', $flight->id)
            ->whereIn('status', [TicketStatus::ISSUED->value, TicketStatus::CHECKED_IN->value, TicketStatus::BOARDED->value])
            ->get();

        $hotelRequired = false;
        // Jika delay melewati tengah malam (> 4 jam dan jam > 22:00)
        $hour = (int) now()->format('H');
        if ($delayMinutes >= 240 && ($hour >= 21 || $hour <= 4)) {
            $hotelRequired = true;
            foreach ($tickets->take(5) as $ticket) {
                $this->hotelService->reserveEmergencyAccommodation($flight, $ticket);
            }
        }

        return [
            'category' => $category,
            'compensation_type' => $compensationType,
            'cash_compensation_minor_per_pax' => $cashCompensationMinor,
            'hotel_required' => $hotelRequired,
            'eligible_passengers_count' => $tickets->count(),
            'action_taken' => "Protokol PM 89/2015 Kategori {$category} diaktifkan untuk penerbangan {$flight->flight_number}.",
        ];
    }

    /**
     * Pembatalan darurat penerbangan dengan orkestrasi refund massal dan pelepasan slot.
     */
    public function cancelFlightWithMassRemediation(int $flightId, string $cancellationReason): array
    {
        $flight = AviationFlight::findOrFail($flightId);

        return $this->massRefundSaga->execute([
            'flight_id' => $flight->id,
            'cancellation_reason' => $cancellationReason,
            'idempotency_key' => 'CANCEL-IRROPS-'.$flight->flight_number.'-'.date('Ymd'),
        ]);
    }
}
