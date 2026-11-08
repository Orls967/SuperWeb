<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Algorithms;

/**
 * Algoritma A7: Disrupted Passenger Reaccommodator.
 *
 * Mengurutkan manifes penumpang terdampak disrupsi penerbangan ke dalam antrean
 * berbobot multi-kriteria untuk alokasi kapasitas penerbangan pengganti dan akomodasi hotel.
 */
class DisruptedPassengerReaccommodator
{
    /**
     * Prioritaskan penumpang berdasarkan skor multi-kriteria deterministik.
     *
     * @param array<int, array{
     *     id: int|string,
     *     name: string,
     *     tier: string,
     *     cabin: string,
     *     has_connection: bool,
     *     special: ?string,
     *     booking_time?: int
     * }> $passengers
     * @return array<int, array{
     *     id: int|string,
     *     name: string,
     *     priority_score: int,
     *     tier: string,
     *     cabin: string,
     *     has_connection: bool,
     *     special: ?string,
     *     booking_time: int
     * }>
     */
    public function prioritizePassengers(array $passengers): array
    {
        $scored = [];

        foreach ($passengers as $p) {
            $score = 0;

            // 1. Kebutuhan Khusus (Special Service Request) - Prioritas Tertinggi Kemanusiaan
            $special = strtoupper((string) ($p['special'] ?? ''));
            if ($special === 'UMNR') {
                $score += 20000; // Anak tanpa pendamping
            } elseif (in_array($special, ['WCHR', 'WCHS', 'WCHC', 'MEDA'], true)) {
                $score += 15000; // Penumpang disabilitas / medis
            } elseif ($special === 'INFANT') {
                $score += 10000; // Bayi / keluarga
            }

            // 2. Status Tier Program Loyalitas
            $tier = strtolower((string) ($p['tier'] ?? 'classic'));
            $score += match ($tier) {
                'platinum' => 10000,
                'gold' => 5000,
                'silver' => 2500,
                default => 500,
            };

            // 3. Kelas Kabin
            $cabin = strtolower((string) ($p['cabin'] ?? 'economy'));
            $score += match ($cabin) {
                'first' => 4000,
                'business' => 3000,
                'premium_economy' => 1500,
                default => 500,
            };

            // 4. Koneksi Penerbangan Lanjutan yang Kritis
            if (! empty($p['has_connection'])) {
                $score += 8000;
            }

            $bookingTime = (int) ($p['booking_time'] ?? 0);

            $scored[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'priority_score' => $score,
                'tier' => $tier,
                'cabin' => $cabin,
                'has_connection' => (bool) ($p['has_connection'] ?? false),
                'special' => $p['special'] ?? null,
                'booking_time' => $bookingTime,
            ];
        }

        // Urutkan menurun berdasarkan skor prioritas; jika imbang gunakan FIFO booking_time
        usort($scored, function ($a, $b) {
            if ($b['priority_score'] !== $a['priority_score']) {
                return $b['priority_score'] <=> $a['priority_score'];
            }

            return $a['booking_time'] <=> $b['booking_time'];
        });

        return $scored;
    }

    /**
     * Hitung kebutuhan alokasi kamar hotel berdasarkan daftar penumpang terdampak.
     *
     * @param  array<int, array{id: int|string, is_family?: bool, family_group_id?: string|null}>  $passengers
     * @return array{total_passengers: int, single_rooms: int, twin_rooms: int, total_rooms: int}
     */
    public function calculateHotelRoomRequirements(array $passengers): array
    {
        $totalPassengers = count($passengers);
        $familyGroups = [];
        $soloCount = 0;

        foreach ($passengers as $p) {
            $groupId = $p['family_group_id'] ?? null;
            if (! empty($p['is_family']) && $groupId !== null) {
                $familyGroups[$groupId] = ($familyGroups[$groupId] ?? 0) + 1;
            } else {
                $soloCount++;
            }
        }

        $twinRooms = 0;
        foreach ($familyGroups as $count) {
            $twinRooms += (int) ceil($count / 2.0);
        }

        $singleRooms = $soloCount;
        $totalRooms = $singleRooms + $twinRooms;

        return [
            'total_passengers' => $totalPassengers,
            'single_rooms' => $singleRooms,
            'twin_rooms' => $twinRooms,
            'total_rooms' => $totalRooms,
        ];
    }
}
