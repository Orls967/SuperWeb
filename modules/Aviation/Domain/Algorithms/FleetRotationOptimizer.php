<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Algorithms;

/**
 * Algoritma: Fleet Rotation Optimizer.
 *
 * Mengoptimalkan rotasi fisik pesawat (tail number rotation) di sepanjang rute
 * berjadwal untuk meratakan akumulasi jam terbang (flight hours) dan siklus pendaratan
 * (flight cycles) agar tidak melebihi batas waktu inspeksi kelaikudaraan kelaikan terbang.
 */
class FleetRotationOptimizer
{
    /**
     * Tentukan penugasan armada optimal untuk rangkaian leg penerbangan harian.
     *
     * @param array<int, array{
     *     aircraft_id: int|string,
     *     reg_mark: string,
     *     hours_to_next_check: float,
     *     cycles_to_next_check: int,
     *     current_location_iata: string
     * }> $availableAircraft
     * @param array<int, array{
     *     flight_id: int|string,
     *     flight_number: string,
     *     origin_iata: string,
     *     dest_iata: string,
     *     flight_duration_hours: float
     * }> $flightLegs
     * @return array{
     *     assignments: array<int, array{flight_id: int|string, aircraft_id: int|string, reg_mark: string}>,
     *     unassigned_legs: array<int, int|string>,
     *     utilization_variance: float
     * }
     */
    public function optimizeRotation(array $availableAircraft, array $flightLegs): array
    {
        $assignments = [];
        $unassigned = [];

        // Lacak status dinamis setiap pesawat selama simulasi rotasi
        $fleetState = [];
        foreach ($availableAircraft as $ac) {
            $fleetState[$ac['aircraft_id']] = [
                'reg_mark' => $ac['reg_mark'],
                'hours_left' => (float) $ac['hours_to_next_check'],
                'cycles_left' => (int) $ac['cycles_to_next_check'],
                'location' => strtoupper($ac['current_location_iata']),
                'assigned_hours' => 0.0,
            ];
        }

        foreach ($flightLegs as $leg) {
            $origin = strtoupper($leg['origin_iata']);
            $duration = (float) $leg['flight_duration_hours'];
            $bestAcId = null;
            $bestScore = -999999.0;

            foreach ($fleetState as $acId => $state) {
                // Syarat mutlak: Lokasi pesawat harus cocok dengan bandara keberangkatan
                if ($state['location'] !== $origin) {
                    continue;
                }

                // Syarat batas perawatan: Jam dan siklus tersisa harus cukup
                if ($state['hours_left'] < $duration || $state['cycles_left'] < 1) {
                    continue;
                }

                // Skor perataan utilisasi: Prioritaskan pesawat dengan jam sebelum check yang lebih longgar
                $score = $state['hours_left'] - ($state['assigned_hours'] * 2.0);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestAcId = $acId;
                }
            }

            if ($bestAcId !== null) {
                $assignments[] = [
                    'flight_id' => $leg['flight_id'],
                    'aircraft_id' => $bestAcId,
                    'reg_mark' => $fleetState[$bestAcId]['reg_mark'],
                ];

                // Update posisi dan sisa jam
                $fleetState[$bestAcId]['location'] = strtoupper($leg['dest_iata']);
                $fleetState[$bestAcId]['hours_left'] -= $duration;
                $fleetState[$bestAcId]['cycles_left'] -= 1;
                $fleetState[$bestAcId]['assigned_hours'] += $duration;
            } else {
                $unassigned[] = $leg['flight_id'];
            }
        }

        // Hitung variansi utilisasi jam penugasan armada
        $hoursList = array_column($fleetState, 'assigned_hours');
        $meanHours = count($hoursList) > 0 ? (array_sum($hoursList) / count($hoursList)) : 0.0;
        $varianceSum = 0.0;
        foreach ($hoursList as $h) {
            $varianceSum += pow($h - $meanHours, 2);
        }
        $variance = count($hoursList) > 0 ? ($varianceSum / count($hoursList)) : 0.0;

        return [
            'assignments' => $assignments,
            'unassigned_legs' => $unassigned,
            'utilization_variance' => round($variance, 4),
        ];
    }
}
