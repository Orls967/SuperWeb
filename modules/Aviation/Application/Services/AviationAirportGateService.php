<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use InvalidArgumentException;
use Modules\Aviation\Domain\Models\AviationFlight;

class AviationAirportGateService
{
    /**
     * Alokasi gerbang keberangkatan (aerobridge vs remote apron) berdasarkan klasifikasi bentang sayap ICAO.
     *
     * @return array{
     *     assigned_gate: string,
     *     gate_type: string,
     *     shuttle_bus_required: bool,
     *     icao_category: string,
     *     ground_power_available: bool,
     *     notes: string
     * }
     */
    public function assignDepartureGate(int $flightId, string $preferredGate = 'GATE-A1'): array
    {
        $flight = AviationFlight::with(['aircraft', 'originAirport'])->findOrFail($flightId);
        $aircraft = $flight->aircraft;

        if (! $aircraft) {
            throw new InvalidArgumentException("Penerbangan {$flight->flight_number} belum memiliki armada untuk penugasan gerbang.");
        }

        // Klasifikasi Kategori Bentang Sayap ICAO (Code A s.d. F)
        $icaoCategory = match ($aircraft->icao_type) {
            'A333', 'B77W' => 'CODE_E', // Widebody (bentang sayap 52-65 meter)
            'B738', 'A320' => 'CODE_C', // Narrowbody (bentang sayap 24-36 meter)
            'ATR72' => 'CODE_B',        // Turboprop kedaerahan (15-24 meter)
            default => 'CODE_C',
        };

        // Pesawat Widebody (Code E) membutuhkan gerbang garbarata ganda (Dual Aerobridge)
        if ($icaoCategory === 'CODE_E') {
            return [
                'assigned_gate' => 'GATE-E2-DUAL-AEROBRIDGE',
                'gate_type' => 'CONTACT_GATE_HEAVY',
                'shuttle_bus_required' => false,
                'icao_category' => $icaoCategory,
                'ground_power_available' => true,
                'notes' => 'Garbarata ganda diaktifkan untuk debarkasi/embarkasi cepat kabin Bisnis dan Ekonomi.',
            ];
        }

        // Pesawat Regional Turboprop (Code B) menggunakan Remote Stand dengan Shuttle Bus
        if ($icaoCategory === 'CODE_B') {
            return [
                'assigned_gate' => 'APRON-REMOTE-STAND-12',
                'gate_type' => 'REMOTE_APRON',
                'shuttle_bus_required' => true,
                'icao_category' => $icaoCategory,
                'ground_power_available' => false,
                'notes' => 'Boarding menggunakan bus apron bandara karena elevasi pintu pesawat tidak kompatibel dengan belalai garbarata.',
            ];
        }

        // Pesawat Narrowbody (Code C)
        return [
            'assigned_gate' => $preferredGate,
            'gate_type' => 'CONTACT_GATE_STANDARD',
            'shuttle_bus_required' => false,
            'icao_category' => $icaoCategory,
            'ground_power_available' => true,
            'notes' => 'Alokasi gerbang garbarata standar terkonfirmasi.',
        ];
    }
}
