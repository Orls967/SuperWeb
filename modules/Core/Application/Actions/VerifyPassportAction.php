<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions;

use Modules\Core\Domain\Models\Vehicle;
use Modules\Core\Domain\Models\VehicleEvent;

class VerifyPassportAction
{
    /**
     * Verify the entire hash-chain of a vehicle's digital passport
     *
     * @return array{is_valid: bool, event_count: int, broken_at_sequence: ?int, message: string}
     */
    public function execute(Vehicle $vehicle): array
    {
        $events = VehicleEvent::where('vehicle_id', $vehicle->id)
            ->orderBy('sequence')
            ->get();

        if ($events->isEmpty()) {
            return [
                'is_valid' => true,
                'event_count' => 0,
                'broken_at_sequence' => null,
                'message' => 'Belum ada event tercatat pada paspor kendaraan ini.',
            ];
        }

        $expectedPrevHash = str_repeat('0', 64);

        foreach ($events as $index => $event) {
            $expectedSequence = $index + 1;

            if ($event->sequence !== $expectedSequence) {
                return [
                    'is_valid' => false,
                    'event_count' => $events->count(),
                    'broken_at_sequence' => $event->sequence,
                    'message' => "Urutan sekuens tidak konsisten pada sequence {$event->sequence} (diharapkan {$expectedSequence}).",
                ];
            }

            if ($event->prev_hash !== $expectedPrevHash) {
                return [
                    'is_valid' => false,
                    'event_count' => $events->count(),
                    'broken_at_sequence' => $event->sequence,
                    'message' => "Previous hash tidak cocok pada sequence {$event->sequence}.",
                ];
            }

            $recalculatedHash = VehicleEvent::calculateHash(
                $event->prev_hash,
                $event->sequence,
                $event->type,
                $event->payload,
                $event->occurred_at
            );

            if ($event->hash !== $recalculatedHash) {
                return [
                    'is_valid' => false,
                    'event_count' => $events->count(),
                    'broken_at_sequence' => $event->sequence,
                    'message' => "Integritas kriptografis rusak pada sequence {$event->sequence}: hash tidak valid.",
                ];
            }

            $expectedPrevHash = $event->hash;
        }

        return [
            'is_valid' => true,
            'event_count' => $events->count(),
            'broken_at_sequence' => null,
            'message' => 'Seluruh rantai paspor kendaraan terverifikasi 100% valid.',
        ];
    }
}
