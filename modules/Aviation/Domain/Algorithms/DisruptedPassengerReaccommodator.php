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
        $engine = new \Modules\Shared\Domain\Algorithms\Aviation\DisruptedPassengerReaccommodator();
        $result = $engine->prioritize($passengers);

        return $result->orderedPassengers;
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
