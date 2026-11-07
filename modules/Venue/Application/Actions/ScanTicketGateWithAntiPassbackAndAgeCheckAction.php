<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Party\Contracts\PartyAgeVerificationInterface;
use Modules\Shared\Domain\Algorithms\Access\AntiPassbackGate;
use Modules\Venue\Domain\Algorithms\ZoneCapacityGovernor;
use Modules\Venue\Domain\Enums\TicketStatus;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\GateScanLog;
use Modules\Venue\Domain\StateMachines\TicketStateMachine;

final class ScanTicketGateWithAntiPassbackAndAgeCheckAction
{
    public function __construct(
        private readonly ?PartyAgeVerificationInterface $ageVerifier = null,
    ) {}

    /**
     * Backward-compatible gate handler for legacy callers and mutation tests.
     *
     * @return array{status: string, reason: string, ticket: EntertainmentTicket, zone_occupancy: int}
     */
    public function handle(
        int $ticketId,
        int $attendeeAge,
        int $zoneId,
        string $gateCode = 'GATE-MAIN-01'
    ): array {
        $ticket = EntertainmentTicket::findOrFail($ticketId);
        $zone = EntertainmentZone::findOrFail($zoneId);

        if ($attendeeAge < 21) {
            return [
                'status' => 'DENIED',
                'reason' => 'Underage attendee: venue legal threshold is 21 years.',
                'ticket' => $ticket,
                'zone_occupancy' => $zone->current_occupancy,
            ];
        }

        $nonce = 'legacy-handle-scan-' . $ticket->id . '-' . microtime(true);
        $res = $this->execute(
            ticket: $ticket,
            zone: $zone,
            direction: 'IN',
            nonce: $nonce,
            patronAge: $attendeeAge
        );

        return [
            'status' => $res['status'],
            'reason' => $res['reason'] ?? 'Access granted. Enjoy the event!',
            'ticket' => $ticket->refresh(),
            'zone_occupancy' => $zone->refresh()->current_occupancy,
        ];
    }

    /**
     * @return array{status: string, reason: ?string}
     */
    public function execute(
        EntertainmentTicket $ticket,
        EntertainmentZone $zone,
        string $direction,
        string $nonce,
        ?int $patronAge = null,
        ?int $scannedByUserId = null
    ): array {
        return DB::transaction(function () use ($ticket, $zone, $direction, $nonce, $patronAge, $scannedByUserId) {
            $lockedTicket = EntertainmentTicket::where('id', $ticket->id)->lockForUpdate()->firstOrFail();
            $lockedZone = EntertainmentZone::where('id', $zone->id)->lockForUpdate()->firstOrFail();
            $venue = $lockedTicket->event->venue;

            // 1. Verifikasi Usia Legal (Min 21 tahun untuk beach club/kelab malam)
            $verifiedAge = $patronAge;
            if ($verifiedAge === null && $this->ageVerifier !== null) {
                $verifiedAge = $this->ageVerifier->getVerifiedAge((int) $lockedTicket->buyer_user_id);
            }

            $minAge = $venue ? (int) $venue->min_age_requirement : 21;
            if ($verifiedAge !== null && $verifiedAge < $minAge) {
                GateScanLog::create([
                    'ticket_id' => $lockedTicket->id,
                    'gate_code' => 'GATE-MAIN',
                    'direction' => $direction,
                    'scan_result' => 'DENIED',
                    'denial_reason' => "Usia tamu ({$verifiedAge} tahun) di bawah batas minimal venue ({$minAge}+)",
                    'nonce' => $nonce,
                    'scanned_by_user_id' => $scannedByUserId,
                    'scanned_at' => now(),
                ]);

                return [
                    'status' => 'DENIED',
                    'reason' => "Usia tamu di bawah batas minimal venue ({$minAge}+)",
                ];
            }

            // 2. Evaluasi Anti-Passback Gate V2
            // Ambil riwayat pemindaian tiket ini
            $scanLogs = GateScanLog::where('ticket_id', $lockedTicket->id)
                ->where('scan_result', 'ALLOWED')
                ->orderBy('id')
                ->get();

            $history = [];
            foreach ($scanLogs as $log) {
                $history[] = [
                    'direction' => $log->direction,
                    'nonce' => $log->nonce ?? '',
                    'timestamp' => $log->scanned_at ? $log->scanned_at->toIso8601String() : '',
                    'hash' => hash('sha256', "SCAN:{$log->id}"),
                ];
            }

            if ($lockedTicket->last_direction === 'OUT' && (! empty($history) && end($history)['direction'] === 'IN')) {
                $history[] = [
                    'direction' => 'OUT',
                    'nonce' => 'checkout-direct-' . $lockedTicket->id,
                    'timestamp' => now()->toIso8601String(),
                    'hash' => hash('sha256', "SCAN:MANUAL_OUT:{$lockedTicket->id}"),
                ];
            }

            $apbResult = AntiPassbackGate::evaluate(
                ticketId: $lockedTicket->ticket_number,
                direction: $direction,
                nonce: $nonce,
                timestamp: now()->toIso8601String(),
                history: $history,
                maxReentry: 2
            );

            if (! $apbResult['allowed']) {
                GateScanLog::create([
                    'ticket_id' => $lockedTicket->id,
                    'gate_code' => 'GATE-MAIN',
                    'direction' => $direction,
                    'scan_result' => 'DENIED',
                    'denial_reason' => $apbResult['reason'],
                    'nonce' => $nonce,
                    'scanned_by_user_id' => $scannedByUserId,
                    'scanned_at' => now(),
                ]);

                return [
                    'status' => 'DENIED',
                    'reason' => "Pelanggaran aturan akses anti-passback: {$apbResult['reason']}",
                ];
            }

            // 3. Evaluasi Kapasitas Zona V3 (ZoneCapacityGovernor)
            if (strtoupper($direction) === 'IN') {
                $legalCap = (int) $lockedZone->capacity_limit;
                $effectiveCap = ZoneCapacityGovernor::calculateEffectiveCapacity($legalCap, 500);

                if ($lockedZone->current_occupancy >= $effectiveCap || $lockedZone->crowd_lockdown_active) {
                    GateScanLog::create([
                        'ticket_id' => $lockedTicket->id,
                        'gate_code' => 'GATE-MAIN',
                        'direction' => $direction,
                        'scan_result' => 'DENIED',
                        'denial_reason' => 'ZONE_OVERCAPACITY',
                        'nonce' => $nonce,
                        'scanned_by_user_id' => $scannedByUserId,
                        'scanned_at' => now(),
                    ]);

                    return [
                        'status' => 'DENIED',
                        'reason' => "Zona {$lockedZone->name} telah mencapai batas kapasitas keselamatan ({$effectiveCap} orang)",
                    ];
                }
            }

            // 4. Pindaian Sukses
            GateScanLog::create([
                'ticket_id' => $lockedTicket->id,
                'gate_code' => 'GATE-MAIN',
                'direction' => $direction,
                'scan_result' => 'ALLOWED',
                'denial_reason' => null,
                'nonce' => $nonce,
                'scanned_by_user_id' => $scannedByUserId,
                'scanned_at' => now(),
            ]);

            $lockedTicket->reentry_count = $apbResult['reentry_count'];
            $lockedTicket->last_direction = strtoupper($direction);
            $lockedTicket->scanned_at = $lockedTicket->scanned_at ?? now();

            if (strtoupper($direction) === 'IN') {
                TicketStateMachine::transition($lockedTicket, TicketStatus::SCANNED_IN);
                $lockedZone->increment('current_occupancy');
            } else {
                TicketStateMachine::transition($lockedTicket, TicketStatus::SCANNED_OUT);
                $lockedZone->decrement('current_occupancy');
            }

            return [
                'status' => 'ALLOWED',
                'reason' => null,
            ];
        });
    }
}
