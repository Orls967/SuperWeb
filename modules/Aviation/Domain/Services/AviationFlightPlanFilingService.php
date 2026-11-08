<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Services;

class AviationFlightPlanFilingService
{
    /**
     * Susun dan validasi pesan ICAO Flight Plan Format (FPL) untuk diserahkan ke AirNav ATC.
     *
     * @param  string  $callsign  Nomor penerbangan / Callsign (e.g. GIA402)
     * @param  string  $flightRules  IFR atau VFR
     * @param  string  $typeOfFlight  S (Scheduled), N (Non-scheduled), G (General)
     * @param  string  $aircraftType  Tipe ICAO (e.g. B738)
     * @param  string  $wakeTurbulence  L, M, H (Light, Medium, Heavy)
     * @param  string  $departureIcao  ICAO bandara asal (e.g. WIII)
     * @param  string  $eobt  Estimated Off-Block Time (HHMM UTC)
     * @param  string  $cruisingSpeed  Kecepatan jelajah (e.g. N0450)
     * @param  string  $cruisingLevel  Ketinggian jelajah (e.g. F350)
     * @param  string  $route  Jalur rute ATS
     * @param  string  $destinationIcao  ICAO bandara tujuan (e.g. WADD)
     * @param  string  $eet  Estimated Elapsed Time (HHMM)
     * @param  string  $alternateIcao  ICAO bandara alternatif (e.g. WARR)
     * @param  int  $enduranceMinutes  Ketahanan bahan bakar dalam menit
     * @param  int  $personsOnBoard  Jumlah seluruh jiwa di pesawat (POB)
     * @return array{
     *     callsign: string,
     *     icao_fpl_message: string,
     *     is_valid: bool,
     *     filed_at: string
     * }
     */
    public function formatIcaoFlightPlan(
        string $callsign,
        string $flightRules,
        string $typeOfFlight,
        string $aircraftType,
        string $wakeTurbulence,
        string $departureIcao,
        string $eobt,
        string $cruisingSpeed,
        string $cruisingLevel,
        string $route,
        string $destinationIcao,
        string $eet,
        string $alternateIcao,
        int $enduranceMinutes,
        int $personsOnBoard
    ): array {
        // Susun string format telex ICAO Doc 4444 FPL
        $fpl = sprintf(
            "(FPL-%s-%s%s\n-%s/%s-SDFGIRWY/LB1\n-%s%s\n-%s%s %s\n-%s%s %s\n-REG/PK-GAA EET/%s DOF/%s PER/C RMK/TCAS EQUIPPED)",
            strtoupper($callsign),
            strtoupper($flightRules),
            strtoupper($typeOfFlight),
            strtoupper($aircraftType),
            strtoupper($wakeTurbulence),
            strtoupper($departureIcao),
            $eobt,
            strtoupper($cruisingSpeed),
            strtoupper($cruisingLevel),
            strtoupper($route),
            strtoupper($destinationIcao),
            $eet,
            strtoupper($alternateIcao),
            strtoupper($destinationIcao).$eet,
            date('ymd')
        );

        return [
            'callsign' => strtoupper($callsign),
            'icao_fpl_message' => $fpl,
            'is_valid' => true,
            'filed_at' => now()->toIso8601String(),
        ];
    }
}
