<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Aviation\Actions\ProcessDisruptionCaseAction;
use Modules\Aviation\Domain\Models\AviationFlight;

class AviationFlightTelemetryIntegrationService
{
    public function __construct(
        protected ProcessDisruptionCaseAction $processDisruptionAction
    ) {}

    /**
     * Ingest data telemetri ACARS / OOOI (Out-Off-On-In) penerbangan.
     *
     * @param array{
     *     flight_number: string,
     *     aircraft_reg: string,
     *     event_type: string,
     *     timestamp: string,
     *     latitude?: float,
     *     longitude?: float,
     *     altitude_fl?: int,
     *     fuel_remaining_kg?: int,
     *     notes?: string
     * } $telemetry
     * @return array{flight_id: int, event_processed: string, delay_detected_minutes: int, disruption_case_triggered: bool}
     */
    public function ingestTelemetryEvent(array $telemetry): array
    {
        return DB::transaction(function () use ($telemetry) {
            $flight = AviationFlight::where('flight_number', $telemetry['flight_number'])
                ->latest('scheduled_departure')
                ->lockForUpdate()
                ->firstOrFail();

            $eventTime = Carbon::parse($telemetry['timestamp']);
            $eventType = strtoupper($telemetry['event_type']);
            $delayMinutes = 0;
            $triggered = false;

            if ($eventType === 'OUT') { // Pintu ditutup dan pesawat dorong mundur dari gate (Actual Off-Block)
                $schedDep = Carbon::parse($flight->scheduled_departure);
                if ($eventTime->greaterThan($schedDep)) {
                    $delayMinutes = (int) $schedDep->diffInMinutes($eventTime);
                }
                $flight->status = $delayMinutes > 15 ? 'delayed' : 'departed';
                $flight->actual_departure = $eventTime;
                $flight->save();

                // Jika delay >= 30 menit, picu orkestrasi IRROPS PM 89 secara otomatis
                if ($delayMinutes >= 30) {
                    $this->processDisruptionAction->execute([
                        'flight_id' => $flight->id,
                        'incident_category' => 'AOG_TECHNICAL',
                        'delay_minutes' => $delayMinutes,
                        'incident_description' => sprintf(
                            'Deteksi telemetri ACARS: Keterlambatan keberangkatan %d menit di gerbang (%s)',
                            $delayMinutes,
                            $telemetry['notes'] ?? 'Pushback delay'
                        ),
                        'detected_at' => $eventTime->toDateTimeString(),
                    ]);
                    $triggered = true;
                }
            } elseif ($eventType === 'IN') { // Pesawat tiba di gate tujuan dan mesin dimatikan (Actual On-Block)
                $flight->status = 'arrived';
                $flight->actual_arrival = $eventTime;
                $flight->save();
            }

            Log::info('Flight ACARS telemetry ingested', [
                'flight_number' => $flight->flight_number,
                'event_type' => $eventType,
                'timestamp' => $eventTime->toIso8601String(),
                'delay_minutes' => $delayMinutes,
            ]);

            return [
                'flight_id' => $flight->id,
                'event_processed' => $eventType,
                'delay_detected_minutes' => $delayMinutes,
                'disruption_case_triggered' => $triggered,
            ];
        });
    }

    /**
     * Evaluasi laporan NOTAM penutupan bandara dan deteksi penerbangan terdampak.
     *
     * @param  string  $airportIata  Kode IATA bandara
     * @param  string  $notamNumber  Nomor referensi NOTAM
     * @param  string  $effectiveFrom  Waktu mulai penutupan
     * @param  string  $effectiveUntil  Waktu berakhir penutupan
     * @param  string  $reason  Alasan penutupan (e.g. VOLCANIC_ASH, RUNWAY_WORK)
     * @return array{airport: string, impacted_flights_count: int, flights: array<int, string>}
     */
    public function processAirportClosureNotam(
        string $airportIata,
        string $notamNumber,
        string $effectiveFrom,
        string $effectiveUntil,
        string $reason
    ): array {
        $from = Carbon::parse($effectiveFrom);
        $until = Carbon::parse($effectiveUntil);

        $impactedFlights = AviationFlight::where(function ($q) use ($airportIata) {
            $q->whereHas('departureAirport', fn ($sq) => $sq->where('iata_code', $airportIata))
                ->orWhereHas('arrivalAirport', fn ($sq) => $sq->where('iata_code', $airportIata));
        })
            ->where(function ($q) use ($from, $until) {
                $q->whereBetween('departure_time', [$from, $until])
                    ->orWhere('departure_time', '>=', $from);
            })
            ->whereNotIn('status', ['cancelled', 'arrived'])
            ->get();

        $flightNumbers = [];
        $category = str_contains(strtoupper($reason), 'ASH') || str_contains(strtoupper($reason), 'VOLCANO')
            ? 'FORCE_MAJEURE_VOLCANIC_ERUPTION'
            : 'FORCE_MAJEURE_WEATHER';

        foreach ($impactedFlights as $f) {
            $duration = (int) $from->diffInMinutes($until);
            $this->processDisruptionAction->execute([
                'flight_id' => $f->id,
                'incident_category' => $category,
                'delay_minutes' => $duration,
                'incident_description' => sprintf('NOTAM %s: Penutupan bandara %s (%s)', $notamNumber, $airportIata, $reason),
                'detected_at' => now()->toDateTimeString(),
            ]);
            $flightNumbers[] = $f->flight_number;
        }

        return [
            'airport' => $airportIata,
            'impacted_flights_count' => count($flightNumbers),
            'flights' => $flightNumbers,
        ];
    }
}
