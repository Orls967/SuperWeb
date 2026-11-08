<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Sagas;

use Illuminate\Support\Facades\DB;
use Modules\Aviation\Contracts\SeatInventoryReserver;
use Modules\Aviation\Domain\Models\AviationSagaInstance;

/**
 * Saga Pemesanan Penumpang Terdistribusi dengan Kompensasi Otomatis (T-4).
 * Alur: HOLD_SEAT -> VERIFY_KYC -> PROCESS_PAYMENT -> CONFIRM_TICKETS.
 * Kompensasi: Jika verifikasi identitas atau pembayaran gagal -> RELEASE_HOLD.
 */
class PassengerBookingSaga
{
    public function __construct(
        private readonly SeatInventoryReserver $reserver
    ) {
    }

    /**
     * @param int $flightId
     * @param string $cabin
     * @param int $seats
     * @param list<int> $passengerPartyIds
     * @param string $correlationId
     * @param bool $simulatePaymentFailure
     * @return array{saga_id: int, status: string, tickets: list<int>}
     */
    public function run(
        int $flightId,
        string $cabin,
        int $seats,
        array $passengerPartyIds,
        string $correlationId,
        bool $simulatePaymentFailure = false
    ): array {
        return DB::transaction(function () use ($flightId, $cabin, $seats, $passengerPartyIds, $correlationId, $simulatePaymentFailure) {
            $saga = AviationSagaInstance::firstOrCreate(
                ['correlation_id' => $correlationId],
                [
                    'saga_type' => 'PASSENGER_BOOKING',
                    'current_step' => 'STARTED',
                    'status' => 'started',
                    'payload_json' => [
                        'flight_id' => $flightId,
                        'cabin' => $cabin,
                        'seats' => $seats,
                        'parties' => $passengerPartyIds,
                    ],
                    'log_json' => [],
                ]
            );

            // Step 1: Hold Kursi
            $holdData = $this->reserver->hold($flightId, $cabin, $seats, "hold:{$correlationId}");
            $holdId = $holdData['hold_id'];
            $saga->current_step = 'SEATS_HELD';
            $saga->log_json = array_merge($saga->log_json ?? [], [
                ['step' => 'SEATS_HELD', 'hold_id' => $holdId, 'timestamp' => now()->toIso8601String()]
            ]);
            $saga->save();

            // Step 2: Verifikasi Identitas & Sanksi (Party KYC)
            $isSanctioned = in_array(99999, $passengerPartyIds, true); // Skenario sanksi
            if ($isSanctioned) {
                // Kompensasi: Lepas hold
                $this->reserver->release($holdId, "rel:sanction:{$correlationId}");
                $saga->current_step = 'COMPENSATED_KYC_REJECTED';
                $saga->status = 'compensated';
                $saga->log_json = array_merge($saga->log_json ?? [], [
                    ['step' => 'COMPENSATED', 'reason' => 'SANCTIONED_PARTY', 'timestamp' => now()->toIso8601String()]
                ]);
                $saga->save();

                throw new \RuntimeException('Pemesanan dibatalkan: Penumpang berada dalam daftar sanksi kepatuhan.');
            }

            // Step 3: Proses Pembayaran
            if ($simulatePaymentFailure) {
                // Kompensasi: Lepas hold
                $this->reserver->release($holdId, "rel:pay_failed:{$correlationId}");
                $saga->current_step = 'COMPENSATED_PAYMENT_FAILED';
                $saga->status = 'compensated';
                $saga->log_json = array_merge($saga->log_json ?? [], [
                    ['step' => 'COMPENSATED', 'reason' => 'PAYMENT_FAILED', 'timestamp' => now()->toIso8601String()]
                ]);
                $saga->save();

                throw new \RuntimeException('Pemesanan dibatalkan: Otorisasi pembayaran gateway ditolak.');
            }

            // Step 4: Konfirmasi Tiket
            $ticketIds = $this->reserver->confirm($holdId, $passengerPartyIds, "confirm:{$correlationId}");

            $saga->current_step = 'COMPLETED';
            $saga->status = 'completed';
            $saga->log_json = array_merge($saga->log_json ?? [], [
                ['step' => 'COMPLETED', 'tickets' => $ticketIds, 'timestamp' => now()->toIso8601String()]
            ]);
            $saga->save();

            return [
                'saga_id' => $saga->id,
                'status' => 'completed',
                'tickets' => $ticketIds,
            ];
        });
    }
}

