<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Algorithms;

use InvalidArgumentException;

/**
 * Algoritma M2: Penugasan Truk Berdasarkan Waktu Mulai Muat Terawal (Need-Time Truck Dispatcher)
 * Disertai evaluasi Match Factor rasional deterministik.
 *
 * Kompleksitas Waktu: O(T * S) di mana T = jumlah truk, S = jumlah shovel.
 * Kompleksitas Ruang: O(T + S).
 */
final class NeedTimeTruckDispatcher
{
    public function __construct(
        private readonly ?NeedTimeTruckDispatcherCertificateVerifier $verifier = null
    ) {}

    /**
     * @param  list<array{
     *     id: string,
     *     loading_duration: int,
     *     travel_time_from_dump: int,
     *     available_at: int
     * }>  $shovels
     * @param  list<array{
     *     id: string,
     *     ready_at_dump: int
     * }>  $trucks
     * @param  array{
     *     total_fleet_trucks: int,
     *     total_fleet_shovels: int,
     *     mean_loading_time: int,
     *     mean_truck_cycle_time: int
     * }|null  $fleetFleetParams  Parameter untuk evaluasi rasional Match Factor
     */
    public function dispatch(
        array $shovels,
        array $trucks,
        int $totalAvailableTrucks,
        ?array $fleetFleetParams = null
    ): NeedTimeTruckDispatcherResult {
        if ($shovels === []) {
            throw new InvalidArgumentException('Daftar shovel tidak boleh kosong');
        }

        if (count($trucks) > $totalAvailableTrucks) {
            throw new InvalidArgumentException('Jumlah truk antre melebihi kapasitas unit tersedia');
        }

        // Status running shovel: ready_time dan idle_time akumulatif
        $shovelState = [];
        foreach ($shovels as $s) {
            $shovelState[$s['id']] = [
                'ready_at' => $s['available_at'],
                'loading_duration' => $s['loading_duration'],
                'travel_time' => $s['travel_time_from_dump'],
                'last_idle_start' => $s['available_at'],
                'total_idle_time' => 0,
            ];
        }

        $assignments = [];
        $totalWaitTime = 0;
        $totalIdleTime = 0;

        foreach ($trucks as $truck) {
            $tReady = $truck['ready_at_dump'];

            $bestShovelId = null;
            $earliestStart = PHP_INT_MAX;
            $longestIdle = -1;
            $bestCandidateInfo = null;

            foreach ($shovelState as $sId => $state) {
                $arrivalTime = $tReady + $state['travel_time'];
                $shovelReady = $state['ready_at'];
                $startTime = max($arrivalTime, $shovelReady);

                // Hitung seberapa lama shovel idle sebelum truk tiba (bila tiba lebih lambat dari shovel siap)
                $idleForThisArrival = max(0, $arrivalTime - $shovelReady);

                // Pemilihan: 1. start_loading_time terawal
                // 2. Tie-break: shovel yang menganggur lebih lama (longest idle)
                // 3. Tie-break: string ID shovel menaik
                $isBetter = false;
                if ($startTime < $earliestStart) {
                    $isBetter = true;
                } elseif ($startTime === $earliestStart) {
                    if ($idleForThisArrival > $longestIdle) {
                        $isBetter = true;
                    } elseif ($idleForThisArrival === $longestIdle) {
                        if ($bestShovelId === null || strcmp($sId, $bestShovelId) < 0) {
                            $isBetter = true;
                        }
                    }
                }

                if ($isBetter) {
                    $earliestStart = $startTime;
                    $longestIdle = $idleForThisArrival;
                    $bestShovelId = $sId;
                    $bestCandidateInfo = [
                        'arrival_time' => $arrivalTime,
                        'shovel_ready_time' => $shovelReady,
                        'travel_time' => $state['travel_time'],
                        'loading_duration' => $state['loading_duration'],
                        'truck_wait_time' => max(0, $shovelReady - $arrivalTime),
                        'shovel_idle_time' => $idleForThisArrival,
                    ];
                }
            }

            // Commit penugasan untuk truk ini
            $sState = &$shovelState[$bestShovelId];
            $finishLoading = $earliestStart + $bestCandidateInfo['loading_duration'];

            $assignments[] = [
                'truck_id' => $truck['id'],
                'shovel_id' => $bestShovelId,
                'departure_time' => $tReady,
                'travel_time' => $bestCandidateInfo['travel_time'],
                'arrival_time' => $bestCandidateInfo['arrival_time'],
                'shovel_ready_time' => $bestCandidateInfo['shovel_ready_time'],
                'start_loading_time' => $earliestStart,
                'loading_duration' => $bestCandidateInfo['loading_duration'],
                'finish_loading_time' => $finishLoading,
                'truck_wait_time' => $bestCandidateInfo['truck_wait_time'],
                'shovel_idle_time' => $bestCandidateInfo['shovel_idle_time'],
            ];

            $totalWaitTime += $bestCandidateInfo['truck_wait_time'];
            $totalIdleTime += $bestCandidateInfo['shovel_idle_time'];

            // Update status ketersediaan shovel untuk penugasan berikutnya
            $sState['ready_at'] = $finishLoading;
            $sState['total_idle_time'] += $bestCandidateInfo['shovel_idle_time'];
        }

        // Match Factor (MF): (N_truk * t_muat) / (N_shovel * t_siklus)
        $mf = $this->calculateMatchFactor($fleetFleetParams);

        $certificateData = [
            'total_active_trucks' => count($trucks),
            'total_available_trucks' => $totalAvailableTrucks,
            'assignments_count' => count($assignments),
            'total_wait_time' => $totalWaitTime,
            'total_idle_time' => $totalIdleTime,
            'match_factor' => $mf,
            'algorithm' => 'NeedTimeTruckDispatcher_v1',
        ];

        $certificateHash = hash('sha256', json_encode($certificateData, JSON_THROW_ON_ERROR));

        $result = new NeedTimeTruckDispatcherResult(
            assignments: $assignments,
            totalTruckWaitTime: $totalWaitTime,
            totalShovelIdleTime: $totalIdleTime,
            matchFactor: $mf,
            totalActiveTrucks: count($trucks),
            totalAvailableTrucks: $totalAvailableTrucks,
            certificateHash: $certificateHash,
            certificateData: $certificateData
        );

        $verifier = $this->verifier ?? new NeedTimeTruckDispatcherCertificateVerifier();
        $verifier->verify($result);

        return $result;
    }

    /**
     * Menghitung rasional Match Factor: (N_truk * t_muat) / (N_shovel * t_siklus)
     * Menggunakan penyederhanaan pecahan FPB (Greatest Common Divisor).
     */
    private function calculateMatchFactor(?array $params): array
    {
        if ($params === null) {
            return [
                'numerator' => 1,
                'denominator' => 1,
                'formatted' => '1/1',
                'is_undertrucked' => false,
                'is_overtrucked' => false,
                'recommendation' => 'Keseimbangan armada armada truk dan shovel optimal',
            ];
        }

        $num = $params['total_fleet_trucks'] * $params['mean_loading_time'];
        $den = $params['total_fleet_shovels'] * $params['mean_truck_cycle_time'];

        if ($den === 0) {
            throw new InvalidArgumentException('Penyebut Match Factor tidak boleh nol');
        }

        $gcd = $this->gcd($num, $den);
        $simplifiedNum = intdiv($num, $gcd);
        $simplifiedDen = intdiv($den, $gcd);

        $isUndertrucked = ($num < $den);
        $isOvertrucked = ($num > $den);

        $recommendation = 'Keseimbangan armada truk dan shovel optimal';
        if ($isUndertrucked) {
            $recommendation = 'Rekomendasi tambah truk (undertrucked: shovel menunggu truk)';
        } elseif ($isOvertrucked) {
            $recommendation = 'Rekomendasi kurangi truk atau tambah shovel (overtrucked: truk mengantre di shovel)';
        }

        return [
            'numerator' => $simplifiedNum,
            'denominator' => $simplifiedDen,
            'formatted' => "{$simplifiedNum}/{$simplifiedDen}",
            'is_undertrucked' => $isUndertrucked,
            'is_overtrucked' => $isOvertrucked,
            'recommendation' => $recommendation,
        ];
    }

    private function gcd(int $a, int $b): int
    {
        $a = abs($a);
        $b = abs($b);

        while ($b !== 0) {
            $t = $b;
            $b = $a % $b;
            $a = $t;
        }

        return $a ?: 1;
    }
}
