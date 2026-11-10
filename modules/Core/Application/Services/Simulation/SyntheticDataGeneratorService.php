<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Domain\Algorithms\Simulation\StreamSeedAndFingerprint;

/**
 * Generator data sintetis terisolasi menggunakan stream sub-seed MINSTD deterministik.
 * Menghasilkan populasi data uji (pasien rumah sakit, tamu hotel, peti kemas logistik).
 */
final class SyntheticDataGeneratorService
{
    public function __construct(
        private readonly StreamSeedAndFingerprint $streamRng,
    ) {}

    /**
     * @return array<int, array{code: string, patient_name: string, illness_severity: int, estimated_days: int}>
     */
    public function generateHospitalAdmissions(int $baseSeed, int $day, int $count = 10): array
    {
        $streamSeed = $this->streamRng->split($baseSeed, 'hospital_admissions');
        // LCG step berdasarkan hari
        $daySeed = ($streamSeed + ($day * 1337)) % 2147483647;

        $patients = [];
        $curr = $daySeed;

        for ($i = 1; $i <= $count; $i++) {
            $curr = ($curr * 48271) % 2147483647;
            $severity = ($curr % 5) + 1; // 1 s/d 5
            $days = ($severity * 2) + ($curr % 3);

            $patients[] = [
                'code' => sprintf('ADM-%03d-%04d', $day, $i),
                'patient_name' => "Pasien Sintetis #{$day}-{$i}",
                'illness_severity' => $severity,
                'estimated_days' => max(1, $days),
            ];
        }

        return $patients;
    }

    /**
     * @return array<int, array{reservation_code: string, guest_name: string, room_type: string, length_of_stay: int}>
     */
    public function generateHotelBookings(int $baseSeed, int $day, int $count = 8): array
    {
        $streamSeed = $this->streamRng->split($baseSeed, 'hotel_bookings');
        $daySeed = ($streamSeed + ($day * 2048)) % 2147483647;

        $bookings = [];
        $curr = $daySeed;
        $roomTypes = ['standard', 'deluxe', 'suite', 'executive'];

        for ($i = 1; $i <= $count; $i++) {
            $curr = ($curr * 48271) % 2147483647;
            $typeIdx = $curr % 4;
            $stay = ($curr % 7) + 1;

            $bookings[] = [
                'reservation_code' => sprintf('HTL-%03d-%04d', $day, $i),
                'guest_name' => "Tamu Kamar #{$day}-{$i}",
                'room_type' => $roomTypes[$typeIdx],
                'length_of_stay' => $stay,
            ];
        }

        return $bookings;
    }

    /**
     * @return array<int, array{container_number: string, iso_type: string, free_days: int, terminal_lane: string}>
     */
    public function generateContainerArrivals(int $baseSeed, int $day, int $count = 12): array
    {
        $streamSeed = $this->streamRng->split($baseSeed, 'logistics_containers');
        $daySeed = ($streamSeed + ($day * 4096)) % 2147483647;

        $containers = [];
        $curr = $daySeed;
        $types = ['20GP', '40GP', '40HC', '20RF'];

        for ($i = 1; $i <= $count; $i++) {
            $curr = ($curr * 48271) % 2147483647;
            $tIdx = $curr % 4;
            $lane = chr(65 + ($curr % 6)).'-'.(($curr % 20) + 1);

            $containers[] = [
                'container_number' => sprintf('CONT-%03d%04d-X', $day, $i),
                'iso_type' => $types[$tIdx],
                'free_days' => 5,
                'terminal_lane' => $lane,
            ];
        }

        return $containers;
    }
}
