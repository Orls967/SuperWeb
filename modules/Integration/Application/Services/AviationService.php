<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AviationService (Fase 177 — Lini 26)
 *
 * Implements:
 *  - 177.1 Aircraft registry & airworthiness certificate expiry check (blocks flight dispatch)
 *  - 177.2 Passenger booking capacity enforcement & idempotent refund processing
 *  - 177.3 Air cargo custody manifests with dangerous goods compliance
 */
class AviationService
{
    /**
     * Register aircraft.
     */
    public function registerAircraft(string $tailNumber, string $model, int $paxCap, float $cargoCapKg, Carbon $airworthinessExpiry): object
    {
        DB::table('avi_aircrafts')->updateOrInsert(
            ['tail_number' => $tailNumber],
            [
                'model_type' => $model,
                'max_passenger_capacity' => $paxCap,
                'max_cargo_capacity_kg' => $cargoCapKg,
                'airworthiness_certificate_expiry' => $airworthinessExpiry->toDateString(),
                'is_grounded' => false,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('avi_aircrafts')->where('tail_number', $tailNumber)->first();
    }

    /**
     * Book passenger flight.
     * Enforces airworthiness certificate validity and aircraft seating capacity.
     */
    public function bookFlight(string $tailNumber, string $flightNum, int $seats, float $fareIdr): object
    {
        $aircraft = DB::table('avi_aircrafts')->where('tail_number', $tailNumber)->first();
        if (! $aircraft) {
            throw new \InvalidArgumentException("Aircraft {$tailNumber} not found.");
        }

        // Airworthiness expiry check
        if (Carbon::parse($aircraft->airworthiness_certificate_expiry)->isPast() || (bool) $aircraft->is_grounded) {
            throw new \RuntimeException("Flight dispatch blocked: Aircraft {$tailNumber} airworthiness certificate has expired or unit is grounded.");
        }

        // Capacity check
        $alreadyBooked = (int) DB::table('avi_flight_bookings')
            ->where('tail_number', $tailNumber)
            ->where('flight_number', $flightNum)
            ->where('status', 'CONFIRMED')
            ->sum('passenger_seats_booked');

        if ($alreadyBooked + $seats > (int) $aircraft->max_passenger_capacity) {
            throw new \RuntimeException("Capacity exceeded: Booking {$seats} seats exceeds available capacity (max: {$aircraft->max_passenger_capacity}).");
        }

        $code = 'BKG-AVI-'.strtoupper(Str::random(8));

        $id = DB::table('avi_flight_bookings')->insertGetId([
            'booking_code' => $code,
            'tail_number' => $tailNumber,
            'flight_number' => $flightNum,
            'passenger_seats_booked' => $seats,
            'fare_paid_idr' => $fareIdr,
            'is_refunded' => false,
            'refund_amount_idr' => 0.00,
            'status' => 'CONFIRMED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('avi_flight_bookings')->find($id);
    }

    /**
     * Process flight booking refund idempotently.
     */
    public function processRefund(string $bookingCode): object
    {
        $booking = DB::table('avi_flight_bookings')->where('booking_code', $bookingCode)->first();
        if (! $booking) {
            throw new \InvalidArgumentException("Booking {$bookingCode} not found.");
        }

        if ((bool) $booking->is_refunded) {
            return (object) $booking; // Idempotent return without duplicate refund
        }

        DB::table('avi_flight_bookings')->where('booking_code', $bookingCode)->update([
            'is_refunded' => true,
            'refund_amount_idr' => $booking->fare_paid_idr,
            'status' => 'REFUNDED',
            'updated_at' => now(),
        ]);

        return (object) DB::table('avi_flight_bookings')->where('booking_code', $bookingCode)->first();
    }

    /**
     * Manifest air cargo shipment with dangerous goods validation and custody hash.
     */
    public function manifestCargo(string $tailNumber, float $weightKg, bool $isDangerousGoods, bool $dgCertified = true): object
    {
        if ($isDangerousGoods && ! $dgCertified) {
            throw new \RuntimeException('Air cargo dispatch rejected: Dangerous goods cargo must carry valid IATA DG certification.');
        }

        $awb = 'AWB-'.strtoupper(Str::random(10));
        $hash = hash('sha256', "{$awb}:{$tailNumber}:{$weightKg}:".($isDangerousGoods ? 'DG' : 'GEN'));

        $id = DB::table('avi_cargo_manifests')->insertGetId([
            'airway_bill_code' => $awb,
            'tail_number' => $tailNumber,
            'weight_kg' => $weightKg,
            'is_dangerous_goods' => $isDangerousGoods,
            'dg_certified' => $dgCertified,
            'custody_hash' => $hash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('avi_cargo_manifests')->find($id);
    }

    /**
     * Quality audit gate (`avi:audit`).
     */
    public function audit(): array
    {
        $illegalFlights = DB::table('avi_flight_bookings as b')
            ->join('avi_aircrafts as a', 'b.tail_number', '=', 'a.tail_number')
            ->where('a.airworthiness_certificate_expiry', '<', Carbon::now()->toDateString())
            ->where('b.status', 'CONFIRMED')
            ->count();

        return [
            'status' => $illegalFlights === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_aircrafts' => DB::table('avi_aircrafts')->count(),
            'total_bookings' => DB::table('avi_flight_bookings')->count(),
            'total_cargo_manifests' => DB::table('avi_cargo_manifests')->count(),
            'discrepancy_count' => $illegalFlights,
        ];
    }
}
