<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Enums\TicketStatus;
use Modules\Aviation\Domain\Exceptions\IdempotencyConflictException;
use Modules\Aviation\Domain\Models\AviationSeatHold;
use Modules\Aviation\Domain\Models\AviationSeatInventory;
use Modules\Aviation\Domain\Models\AviationTicket;
use Modules\Aviation\Ledger\AviationLedgerPostingService;

class BookPassengerTicketAction
{
    public function __construct(
        private readonly AviationLedgerPostingService $ledgerService
    ) {
    }

    /**
     * @param int $holdId
     * @param list<int> $passengerPartyIds
     * @param list<string> $passengerNames
     * @param string $idempotencyKey
     * @return list<AviationTicket>
     */
    public function execute(
        int $holdId,
        array $passengerPartyIds,
        array $passengerNames,
        string $idempotencyKey
    ): array {
        return DB::transaction(function () use ($holdId, $passengerPartyIds, $passengerNames, $idempotencyKey) {
            $hold = AviationSeatHold::findOrFail($holdId);
            if ($hold->is_released) {
                throw new \RuntimeException("Seat hold #{$holdId} sudah dilepas/kedaluwarsa.");
            }

            // Jika sudah dikonfirmasi, kembalikan tiket yang terdaftar untuk idempotensi
            if ($hold->is_confirmed) {
                return AviationTicket::where('seat_hold_id', $holdId)->get()->all();
            }

            $inventory = AviationSeatInventory::where('flight_id', $hold->flight_id)
                ->where('cabin', $hold->cabin)
                ->lockForUpdate()
                ->firstOrFail();

            $count = $hold->seats_count;
            $inventory->held_seats = max(0, $inventory->held_seats - $count);
            $inventory->booked_seats += $count;
            $inventory->save();

            $hold->is_confirmed = true;
            $hold->save();

            $tickets = [];
            $perTicketFare = (int) floor($hold->fare_minor / max(1, $count));
            $taxMinor = (int) floor($perTicketFare * 0.1); // 10% tax bandara
            $totalTicketFare = $perTicketFare + $taxMinor;

            for ($i = 0; $i < $count; $i++) {
                $partyId = $passengerPartyIds[$i] ?? null;
                $paxName = $passengerNames[$i] ?? "Penumpang {$i}";
                $ticketNum = sprintf('TKT-%d-%d-%04d', $hold->flight_id, $hold->id, $i + 1);

                $ticket = AviationTicket::create([
                    'ticket_number' => $ticketNum,
                    'flight_id' => $hold->flight_id,
                    'seat_hold_id' => $hold->id,
                    'passenger_party_id' => $partyId,
                    'cabin' => $hold->cabin,
                    'seat_assignment' => sprintf('%d%s', rand(1, 30), $hold->cabin === 'J' ? 'A' : 'C'),
                    'fare_basis' => 'STD',
                    'base_fare_minor' => $perTicketFare,
                    'tax_minor' => $taxMinor,
                    'total_fare_minor' => $totalTicketFare,
                    'currency' => $hold->currency,
                    'status' => TicketStatus::PAID->value,
                    'is_baggage_checked' => false,
                    'baggage_weight_g' => 0,
                    'pii_name_encrypted' => encrypt($paxName),
                    'pii_blind_index' => hash_hmac('sha256', $paxName, 'secret_salt'),
                ]);

                // Terbitkan tiket (status ISSUED)
                $ticket->transitionTo(TicketStatus::ISSUED, 'PAYMENT_CONFIRMED');

                // Jurnal unearned
                $ledgerKey = "avi:sale:{$ticket->id}:{$idempotencyKey}";
                $this->ledgerService->postTicketPayment($ticket, $ledgerKey);

                $tickets[] = $ticket;
            }

            return $tickets;
        });
    }
}

