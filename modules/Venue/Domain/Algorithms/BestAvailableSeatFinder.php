<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

use Modules\Venue\Domain\Models\SeatClaim;

class BestAvailableSeatFinder
{
    /**
     * Menemukan blok kursi berdampingan terbaik dalam baris yang sama dengan proteksi anti-orphan.
     */
    public function findBestAvailable(
        int $eventId,
        int $tierId,
        int $quantity
    ): SeatAllocationResult {
        if ($quantity <= 0) {
            return new SeatAllocationResult([]);
        }

        // Ambil semua kursi untuk event & tier, diurutkan deterministik (PR3)
        $allSeats = SeatClaim::where('event_id', $eventId)
            ->where('tier_id', $tierId)
            ->orderBy('section_code', 'asc')
            ->orderBy('row_label', 'asc')
            ->orderBy('seat_number', 'asc')
            ->get();

        if ($allSeats->isEmpty()) {
            return new SeatAllocationResult([]);
        }

        // Kelompokkan per section dan row
        $grouped = [];
        foreach ($allSeats as $seat) {
            $key = $seat->section_code.':'.$seat->row_label;
            $grouped[$key][] = $seat;
        }

        foreach ($grouped as $rowSeats) {
            $allocation = $this->tryAllocateInRow($rowSeats, $quantity);
            if ($allocation !== null) {
                $seatData = array_map(fn ($s) => [
                    'id' => $s->id,
                    'section_code' => $s->section_code,
                    'row_label' => $s->row_label,
                    'seat_number' => $s->seat_number,
                ], $allocation);

                $cert = new SeatAllocationCertificate(
                    eventId: $eventId,
                    tierId: $tierId,
                    quantity: $quantity,
                    sectionCode: $allocation[0]->section_code,
                    rowLabel: $allocation[0]->row_label,
                    seatIds: array_column($seatData, 'id'),
                    seatNumbers: array_column($seatData, 'seat_number'),
                );

                $result = new SeatAllocationResult($seatData, $cert);
                $cert->verify($result);

                return $result;
            }
        }

        return new SeatAllocationResult([]);
    }

    /**
     * @param  list<SeatClaim>  $rowSeats
     * @return list<SeatClaim>|null
     */
    private function tryAllocateInRow(array $rowSeats, int $quantity): ?array
    {
        $numSeats = count($rowSeats);
        if ($numSeats < $quantity) {
            return null;
        }

        // Cari semua window berurutan sebesar quantity yang statusnya AVAILABLE
        for ($i = 0; $i <= $numSeats - $quantity; $i++) {
            $window = [];
            $validContiguous = true;

            for ($k = 0; $k < $quantity; $k++) {
                $seat = $rowSeats[$i + $k];
                if ($seat->status !== 'AVAILABLE') {
                    $validContiguous = false;
                    break;
                }
                if ($k > 0 && $seat->seat_number !== $rowSeats[$i + $k - 1]->seat_number + 1) {
                    $validContiguous = false;
                    break;
                }
                $window[] = $seat;
            }

            if (! $validContiguous) {
                continue;
            }

            // Bila quantity >= 2, periksa aturan anti-orphan
            if ($quantity >= 2) {
                if ($this->leavesOrphanSeat($rowSeats, $i, $quantity)) {
                    continue;
                }
            }

            return $window;
        }

        return null;
    }

    /**
     * Memeriksa apakah alokasi window [$startIdx .. $startIdx + $quantity - 1]
     * menyisakan tepat 1 kursi AVAILABLE yang terisolasi di sisi kiri atau kanan.
     *
     * @param  list<SeatClaim>  $rowSeats
     */
    private function leavesOrphanSeat(array $rowSeats, int $startIdx, int $quantity): bool
    {
        $endIdx = $startIdx + $quantity - 1;

        // Cek sisi kiri (startIdx - 1)
        if ($startIdx > 0) {
            $leftSeat = $rowSeats[$startIdx - 1];
            if ($leftSeat->status === 'AVAILABLE') {
                $isLeftIsolated = false;
                if ($startIdx - 1 === 0) {
                    $isLeftIsolated = true;
                } else {
                    $prevLeft = $rowSeats[$startIdx - 2];
                    if ($prevLeft->status !== 'AVAILABLE' || $leftSeat->seat_number !== $prevLeft->seat_number + 1) {
                        $isLeftIsolated = true;
                    }
                }
                if ($isLeftIsolated) {
                    return true;
                }
            }
        }

        // Cek sisi kanan (endIdx + 1)
        $numSeats = count($rowSeats);
        if ($endIdx < $numSeats - 1) {
            $rightSeat = $rowSeats[$endIdx + 1];
            if ($rightSeat->status === 'AVAILABLE') {
                $isRightIsolated = false;
                if ($endIdx + 1 === $numSeats - 1) {
                    $isRightIsolated = true;
                } else {
                    $nextRight = $rowSeats[$endIdx + 2];
                    if ($nextRight->status !== 'AVAILABLE' || $nextRight->seat_number !== $rightSeat->seat_number + 1) {
                        $isRightIsolated = true;
                    }
                }
                if ($isRightIsolated) {
                    return true;
                }
            }
        }

        return false;
    }
}
