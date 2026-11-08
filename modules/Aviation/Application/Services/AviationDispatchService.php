<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Application\Exceptions\AirworthinessFailedException;
use Modules\Aviation\Domain\Enums\FlightStatus;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationWeightBalanceRecord;
use Modules\Shared\Domain\Algorithms\Aviation\AirworthinessDispatchGate;
use Modules\Shared\Domain\Algorithms\Aviation\LoadAndBalanceChecker;

class AviationDispatchService
{
    public function __construct(
        private readonly AirworthinessDispatchGate $airworthinessGate,
        private readonly LoadAndBalanceChecker $loadAndBalanceChecker
    ) {}

    /**
     * Memverifikasi gerbang pelepasan kelaikan terbang (A2) dan perhitungan W&B (A3).
     *
     * @param array{
     *     dry_operating_weight_kg: int,
     *     passenger_count: int,
     *     average_passenger_weight_kg?: int,
     *     cargo_weight_kg: int,
     *     fuel_takeoff_kg: int,
     *     fuel_trip_burn_kg: int,
     *     cargo_center_of_gravity_mac_bps: int
     * } $wbPayload
     * @return array{flight: AviationFlight, airworthiness_passed: bool, wb_passed: bool, details: array}
     *
     * @throws AirworthinessFailedException
     */
    public function evaluateAndDispatchFlight(int $flightId, array $wbPayload): array
    {
        $flight = AviationFlight::with(['aircraft', 'originAirport', 'destinationAirport'])->findOrFail($flightId);
        $aircraft = $flight->aircraft;

        if (! $aircraft) {
            throw new AirworthinessFailedException("Penerbangan {$flight->flight_number} belum memiliki alokasi pesawat.");
        }

        // 1. Evaluasi Kelaikan Udara A2
        $estimatedHours = (int) ceil(($flight->route->scheduled_duration_minutes ?? 120) / 60);
        $awResult = $this->airworthinessGate->verify([
            'aircraft_status' => $aircraft->status,
            'airworthiness_expires_at' => $aircraft->airworthiness_expires_at,
            'flight_date' => Carbon::parse($flight->departure_time)->toDateString(),
            'hours_since_last_a_check' => $aircraft->hours_since_last_a_check,
            'a_check_interval_hours' => $aircraft->a_check_interval_hours,
            'flight_estimated_hours' => $estimatedHours,
            'cycles_since_last_c_check' => $aircraft->cycles_since_last_c_check,
            'c_check_interval_cycles' => $aircraft->c_check_interval_cycles,
            'open_mel_count' => $aircraft->open_mel_items_count,
            'max_allowed_mel_items' => 3,
        ]);

        if (! $awResult['eligible_for_dispatch']) {
            throw new AirworthinessFailedException("Pesawat {$aircraft->registration_mark} gagal lolos uji kelaikan udara A2: ".implode(', ', $awResult['reasons']));
        }

        // 2. Evaluasi Weight & Balance A3
        $wbResult = $this->loadAndBalanceChecker->calculate([
            'operating_empty_weight_kg' => $wbPayload['dry_operating_weight_kg'] ?? $aircraft->operating_empty_weight_kg,
            'passenger_count' => $wbPayload['passenger_count'],
            'avg_passenger_weight_kg' => $wbPayload['average_passenger_weight_kg'] ?? 80,
            'cargo_weight_kg' => $wbPayload['cargo_weight_kg'],
            'takeoff_fuel_kg' => $wbPayload['fuel_takeoff_kg'],
            'trip_burn_fuel_kg' => $wbPayload['fuel_trip_burn_kg'],
            'max_takeoff_weight_kg' => $aircraft->max_takeoff_weight_kg,
            'max_landing_weight_kg' => $aircraft->max_landing_weight_kg,
            'max_zero_fuel_weight_kg' => (int) ($aircraft->max_takeoff_weight_kg * 0.85),
            'cg_limits_mac_bps' => [
                'forward' => 1500,
                'aft' => 3500,
            ],
            'estimated_cg_mac_bps' => $wbPayload['cargo_center_of_gravity_mac_bps'] ?? 2500,
        ]);

        if (! $wbResult['within_limits']) {
            throw new AirworthinessFailedException("Penerbangan {$flight->flight_number} melanggar batasan berat dan titik gravitasi (W&B): ".implode(', ', $wbResult['violations']));
        }

        // 3. Simpan Rekam Jejak Dispatch W&B
        DB::transaction(function () use ($flight, $wbResult, $wbPayload) {
            AviationWeightBalanceRecord::updateOrCreate(
                ['flight_id' => $flight->id],
                [
                    'zero_fuel_weight_kg' => $wbResult['zero_fuel_weight_kg'],
                    'takeoff_weight_kg' => $wbResult['takeoff_weight_kg'],
                    'landing_weight_kg' => $wbResult['landing_weight_kg'],
                    'takeoff_cg_mac_bps' => $wbResult['takeoff_cg_mac_bps'],
                    'within_envelope' => true,
                    'dispatcher_party_id' => auth()->id() ?? 1,
                    'notes' => 'Lolos otomatis sistem dispatch terintegrasi',
                ]
            );

            $flight->fuel_burn_kg = $wbPayload['fuel_trip_burn_kg'];
            $flight->save();

            $flight->transitionTo(FlightStatus::AIRWORTHY_CLEARED, 'DISPATCH_GATES_PASSED');
        });

        return [
            'flight' => $flight->fresh(),
            'airworthiness_passed' => true,
            'wb_passed' => true,
            'details' => [
                'tow_kg' => $wbResult['takeoff_weight_kg'],
                'law_kg' => $wbResult['landing_weight_kg'],
                'cg_bps' => $wbResult['takeoff_cg_mac_bps'],
            ],
        ];
    }
}
