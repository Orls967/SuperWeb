<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Venue\Domain\Algorithms\ZoneCapacityGovernor;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\ZoneRelocationLog;
use Modules\Venue\Ledger\VenueTransactionTypes;

class PartialWeatherZoneRelocationService
{
    public function __construct(
        private readonly ?Ledger $ledger = null
    ) {}

    /**
     * Memindahkan pemegang tiket dari zona terbuka yang ditutup cuaca buruk ke zona tertutup
     * atau mengeksekusi pengembalian dana parsial secara adil.
     *
     * @return array{
     *     relocated_count: int,
     *     refunded_count: int,
     *     total_refund_idr: int,
     *     target_zone_occupancy: int
     * }
     */
    public function relocatePatronsFromClosedZone(
        EntertainmentEvent $event,
        EntertainmentZone $closedZone,
        EntertainmentZone $targetIndoorZone,
        int $partialRefundBps = 2000 // 20% kompensasi jika zona downgrade
    ): array {
        return DB::transaction(function () use ($event, $closedZone, $targetIndoorZone, $partialRefundBps) {
            $lockedClosedZone = EntertainmentZone::where('id', $closedZone->id)->lockForUpdate()->firstOrFail();
            $lockedTargetZone = EntertainmentZone::where('id', $targetIndoorZone->id)->lockForUpdate()->firstOrFail();

            $targetEffectiveCap = ZoneCapacityGovernor::calculateEffectiveCapacity((int) $lockedTargetZone->capacity_limit, 500);

            // Cari tiket yang berada di zona tertutup atau berstatus scanned_in di zona tersebut
            $tickets = EntertainmentTicket::where('event_id', $event->id)
                ->whereIn('status', ['issued', 'scanned_in'])
                ->get();

            $relocatedCount = 0;
            $refundedCount = 0;
            $totalRefundIdr = 0;

            foreach ($tickets as $ticket) {
                $canFit = ($lockedTargetZone->current_occupancy < $targetEffectiveCap);

                if ($canFit) {
                    $refundPortion = (int) round(($ticket->price_paid_idr * $partialRefundBps) / 10000);
                    $relocatedCount++;
                    $lockedTargetZone->current_occupancy += 1;

                    ZoneRelocationLog::create([
                        'relocation_code' => 'RELOC-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
                        'event_id' => $event->id,
                        'from_zone_id' => $lockedClosedZone->id,
                        'to_zone_id' => $lockedTargetZone->id,
                        'ticket_id' => $ticket->id,
                        'reason' => 'Penutupan zona terbuka akibat cuaca hujan/angin kencang',
                        'refund_amount_idr' => $refundPortion,
                        'status' => 'completed',
                    ]);

                    if ($refundPortion > 0 && $this->ledger !== null) {
                        $totalRefundIdr += $refundPortion;
                        $this->ledger->post(new PostingDTO(
                            type: VenueTransactionTypes::VENUE_TICKET_REFUND->value,
                            description: "Partial weather compensation refund for ticket {$ticket->ticket_number}",
                            idempotencyKey: "ven:weather_refund:{$ticket->id}",
                            entries: Posting::lines()
                                ->debit('ven:ticket_unearned:IDR', $refundPortion)
                                ->credit('ven:ticket_clearing:IDR', $refundPortion)
                                ->toEntries('IDR'),
                            referenceType: 'VENUE_TICKET',
                            referenceId: (string) $ticket->id
                        ));
                    }
                } else {
                    // Zona tujuan penuh -> refund penuh
                    $refundedCount++;
                    $fullRefund = (int) $ticket->price_paid_idr;
                    $totalRefundIdr += $fullRefund;
                    $ticket->status = 'refunded';
                    $ticket->save();

                    if ($this->ledger !== null) {
                        $this->ledger->post(new PostingDTO(
                            type: VenueTransactionTypes::VENUE_TICKET_REFUND->value,
                            description: "Full weather refund due to indoor capacity exhaustion for ticket {$ticket->ticket_number}",
                            idempotencyKey: "ven:full_weather_refund:{$ticket->id}",
                            entries: Posting::lines()
                                ->debit('ven:ticket_unearned:IDR', $fullRefund)
                                ->credit('ven:ticket_clearing:IDR', $fullRefund)
                                ->toEntries('IDR'),
                            referenceType: 'VENUE_TICKET',
                            referenceId: (string) $ticket->id
                        ));
                    }
                }
            }

            $lockedClosedZone->current_occupancy = 0;
            $lockedClosedZone->crowd_lockdown_active = true;
            $lockedClosedZone->save();
            $lockedTargetZone->save();

            return [
                'relocated_count' => $relocatedCount,
                'refunded_count' => $refundedCount,
                'total_refund_idr' => $totalRefundIdr,
                'target_zone_occupancy' => $lockedTargetZone->current_occupancy,
            ];
        });
    }
}
