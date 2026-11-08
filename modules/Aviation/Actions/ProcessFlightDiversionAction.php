<?php

declare(strict_types=1);

namespace Modules\Aviation\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Models\AviationAirport;
use Modules\Aviation\Domain\Models\AviationFlight;

class ProcessFlightDiversionAction
{
    public function __construct(
        protected ProcessDisruptionCaseAction $processDisruptionAction
    ) {}

    /**
     * Catat pengalihan pendaratan penerbangan (diversion) ke bandara alternatif darurat.
     *
     * @param  int  $flightId  ID penerbangan
     * @param  int  $alternateAirportId  ID bandara alternatif tempat mendarat
     * @param  string  $diversionReason  Alasan pengalihan (e.g. BAD_WEATHER, MEDICAL_EMERGENCY, TECHNICAL)
     * @return array{flight_id: int, original_arrival_iata: string, diverted_arrival_iata: string, disruption_case_id: int}
     */
    public function execute(int $flightId, int $alternateAirportId, string $diversionReason): array
    {
        return DB::transaction(function () use ($flightId, $alternateAirportId, $diversionReason) {
            $flight = AviationFlight::lockForUpdate()->findOrFail($flightId);
            $altAirport = AviationAirport::findOrFail($alternateAirportId);

            $originalArrivalIata = $flight->arrivalAirport->iata_code ?? 'ORIG_DEST';

            // Alihkan tujuan pendaratan
            $flight->arrival_airport_id = $altAirport->id;
            $flight->status = 'delayed';
            $flight->save();

            // Daftarkan kasus IRROPS untuk penanganan hak penumpang di bandara alternatif
            $case = $this->processDisruptionAction->execute([
                'flight_id' => $flight->id,
                'incident_category' => 'FORCE_MAJEURE_WEATHER',
                'delay_minutes' => 360, // Minimal 6 jam untuk koordinasi pengalihan & ferry transport
                'incident_description' => sprintf(
                    'Penerbangan dialihkan (Diverted) dari %s ke %s: %s',
                    $originalArrivalIata,
                    $altAirport->iata_code,
                    $diversionReason
                ),
            ]);

            return [
                'flight_id' => $flight->id,
                'original_arrival_iata' => $originalArrivalIata,
                'diverted_arrival_iata' => $altAirport->iata_code,
                'disruption_case_id' => $case->id,
            ];
        });
    }
}
