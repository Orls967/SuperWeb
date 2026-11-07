<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Routing;

use InvalidArgumentException;

/**
 * H3 Shared\Routing\HousekeepingRouteSequencer [Fase 91]
 *
 * Mengurutkan kamar menurut prioritas (VIP/arrival dulu), lalu kredit menurun, lalu nomor kamar menaik.
 * Tiap kamar diberikan ke petugas dengan beban terkecil (tie-break nama alfabetis menaik),
 * dengan batas kredit per petugas (overflow -> antrean).
 * Rute tiap petugas: kamar prioritas dulu, lalu nomor kamar menaik.
 */
final class HousekeepingRouteSequencer
{
    /**
     * @param  list<array{room: int, credit: int, is_priority: bool, is_vip: bool}>  $rooms
     * @param  list<string>  $staffNames
     * @param  int  $creditLimitPerStaff  Batas kredit pembersihan per petugas (mis. 240)
     * @return array{
     *     assignments: array<string, list<int>>,
     *     routes: array<string, list<int>>,
     *     workloads: array<string, int>,
     *     unassigned_queue: list<int>,
     *     certificate: HousekeepingRouteCertificate
     * }
     */
    public static function sequence(
        array $rooms,
        array $staffNames,
        int $creditLimitPerStaff = 240
    ): array {
        if (empty($staffNames)) {
            throw new InvalidArgumentException('Daftar petugas tidak boleh kosong.');
        }
        if ($creditLimitPerStaff <= 0) {
            throw new InvalidArgumentException('Batas kredit per petugas harus positif.');
        }

        // Urutkan nama petugas untuk tie-break deterministik
        sort($staffNames);

        // Map room details untuk referensi cepat
        $roomMap = [];
        foreach ($rooms as $r) {
            $roomMap[$r['room']] = $r;
        }

        // Urutkan kamar:
        // Prioritas (VIP/arrival dulu) -> kredit menurun -> nomor kamar menaik
        $sortedRooms = $rooms;
        usort($sortedRooms, function (array $a, array $b): int {
            // Cek level prioritas: VIP = 2, Priority Arrival = 1, Normal = 0
            $prioA = ($a['is_vip'] ?? false) ? 2 : (($a['is_priority'] ?? false) ? 1 : 0);
            $prioB = ($b['is_vip'] ?? false) ? 2 : (($b['is_priority'] ?? false) ? 1 : 0);

            if ($prioA !== $prioB) {
                return $prioB <=> $prioA; // Prioritas lebih tinggi di depan
            }
            if ($a['credit'] !== $b['credit']) {
                return $b['credit'] <=> $a['credit']; // Kredit menurun
            }

            return $a['room'] <=> $b['room']; // Nomor kamar menaik
        });

        $assignments = [];
        $workloads = [];
        foreach ($staffNames as $name) {
            $assignments[$name] = [];
            $workloads[$name] = 0;
        }

        $unassignedQueue = [];

        foreach ($sortedRooms as $roomItem) {
            $rNum = $roomItem['room'];
            $rCredit = $roomItem['credit'];

            // Cari petugas yang memenuhi syarat kredit <= limit dengan beban saat ini terkecil
            $chosenStaff = null;
            $minLoad = PHP_INT_MAX;

            foreach ($staffNames as $name) {
                if ($creditLimitPerStaff >= $workloads[$name] + $rCredit) {
                    if ($workloads[$name] < $minLoad) {
                        $minLoad = $workloads[$name];
                        $chosenStaff = $name;
                    }
                }
            }

            if ($chosenStaff !== null) {
                $assignments[$chosenStaff][] = $rNum;
                $workloads[$chosenStaff] += $rCredit;
            } else {
                $unassignedQueue[] = $rNum;
            }
        }

        // Susun rute per petugas: kamar prioritas dulu, lalu nomor kamar menaik
        $routes = [];
        foreach ($staffNames as $name) {
            $staffRooms = $assignments[$name];
            usort($staffRooms, function (int $roomA, int $roomB) use ($roomMap): int {
                $itemA = $roomMap[$roomA];
                $itemB = $roomMap[$roomB];
                $prioA = ($itemA['is_vip'] ?? false) || ($itemA['is_priority'] ?? false);
                $prioB = ($itemB['is_vip'] ?? false) || ($itemB['is_priority'] ?? false);

                if ($prioA !== $prioB) {
                    return $prioB ? 1 : -1; // Yang prioritas di depan
                }

                return $roomA <=> $roomB; // Nomor kamar menaik
            });
            $routes[$name] = $staffRooms;
        }

        $certificate = new HousekeepingRouteCertificate(
            assignments: $assignments,
            routes: $routes,
            workloads: $workloads,
            unassignedQueue: $unassignedQueue,
            creditLimit: $creditLimitPerStaff,
            timestamp: time()
        );

        HousekeepingRouteCertificateVerifier::verify($certificate);

        return [
            'assignments' => $assignments,
            'routes' => $routes,
            'workloads' => $workloads,
            'unassigned_queue' => $unassignedQueue,
            'certificate' => $certificate,
        ];
    }
}
