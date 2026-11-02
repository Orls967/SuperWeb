<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Party\Contracts\PartyAgeVerificationInterface;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\GateScanLog;
use Modules\Venue\Domain\Services\VenueQrSigner;

class ScanTicketAtGateAction
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly VenueQrSigner $qrSigner,
        private readonly ?PartyAgeVerificationInterface $partyAgeVerification = null
    ) {}

    /**
     * @return array{status: 'ALLOWED'|'DENIED', message: string, ticket?: EntertainmentTicket, log?: GateScanLog}
     */
    public function handle(
        string $qrPayload,
        string $gateCode,
        string $direction,
        ?int $scannedByUserId,
        string $idempotencyKey
    ): array {
        $now = $this->clock->now();
        $partyAgeVerifier = $this->partyAgeVerification ?? app(PartyAgeVerificationInterface::class);

        // 1. Verifikasi tanda tangan QR HMAC
        $verified = $this->qrSigner->verifyPayload($qrPayload);
        if ($verified === null) {
            $parts = explode('.', $qrPayload);
            $ticket = isset($parts[0]) ? EntertainmentTicket::where('ticket_uuid', $parts[0])->first() : null;

            $log = GateScanLog::create([
                'ticket_id' => $ticket?->id,
                'gate_code' => $gateCode,
                'direction' => $direction,
                'scan_result' => 'DENIED',
                'denial_reason' => 'Tanda tangan QR tidak valid',
                'scanned_by_user_id' => $scannedByUserId,
                'scanned_at' => $now,
            ]);

            return [
                'status' => 'DENIED',
                'message' => 'Tanda tangan QR tidak valid',
                'log' => $log,
            ];
        }

        $ticket = EntertainmentTicket::with(['event.venue', 'tier.zone'])
            ->where('ticket_uuid', $verified['ticket_uuid'])
            ->first();

        if ($ticket === null) {
            $log = GateScanLog::create([
                'ticket_id' => null,
                'gate_code' => $gateCode,
                'direction' => $direction,
                'scan_result' => 'DENIED',
                'denial_reason' => 'Tiket tidak ditemukan di sistem',
                'scanned_by_user_id' => $scannedByUserId,
                'scanned_at' => $now,
            ]);

            return [
                'status' => 'DENIED',
                'message' => 'Tiket tidak ditemukan di sistem',
                'log' => $log,
            ];
        }

        // 2. Verifikasi Batas Usia Minimum KYC Party (V-6, Z9)
        if ($direction === 'IN') {
            $minAge = $ticket->event?->venue?->min_age_requirement ?? 0;
            if ($minAge > 0) {
                $verifiedAge = $partyAgeVerifier->getVerifiedAge((int) $ticket->buyer_user_id);
                if ($verifiedAge === null) {
                    $log = GateScanLog::create([
                        'ticket_id' => $ticket->id,
                        'gate_code' => $gateCode,
                        'direction' => $direction,
                        'scan_result' => 'DENIED',
                        'denial_reason' => 'Data usia belum terverifikasi di KYC Party',
                        'scanned_by_user_id' => $scannedByUserId,
                        'scanned_at' => $now,
                    ]);

                    return [
                        'status' => 'DENIED',
                        'message' => 'Data usia belum terverifikasi di KYC Party (V-6, Z9)',
                        'log' => $log,
                    ];
                }

                if ($verifiedAge < $minAge) {
                    $log = GateScanLog::create([
                        'ticket_id' => $ticket->id,
                        'gate_code' => $gateCode,
                        'direction' => $direction,
                        'scan_result' => 'DENIED',
                        'denial_reason' => "Usia belum memenuhi syarat minimum {$minAge} tahun",
                        'scanned_by_user_id' => $scannedByUserId,
                        'scanned_at' => $now,
                    ]);

                    return [
                        'status' => 'DENIED',
                        'message' => "Usia belum memenuhi syarat minimum {$minAge} tahun (KYC: {$verifiedAge})",
                        'log' => $log,
                    ];
                }
            }
        }

        $tier = $ticket->tier;
        $allowsReentry = (bool) ($tier?->allows_reentry ?? false);

        // 3. Status Check & Anti-Passback (V-5)
        if ($direction === 'IN') {
            if ($ticket->status === 'USED') {
                $log = GateScanLog::create([
                    'ticket_id' => $ticket->id,
                    'gate_code' => $gateCode,
                    'direction' => $direction,
                    'scan_result' => 'DENIED',
                    'denial_reason' => 'Anti-passback: tiket sudah digunakan',
                    'scanned_by_user_id' => $scannedByUserId,
                    'scanned_at' => $now,
                ]);

                return [
                    'status' => 'DENIED',
                    'message' => 'Anti-passback: tiket sudah digunakan',
                    'log' => $log,
                ];
            }

            if ($ticket->status === 'EXITED' && ! $allowsReentry) {
                $log = GateScanLog::create([
                    'ticket_id' => $ticket->id,
                    'gate_code' => $gateCode,
                    'direction' => $direction,
                    'scan_result' => 'DENIED',
                    'denial_reason' => 'Tiket tidak mengizinkan re-entry',
                    'scanned_by_user_id' => $scannedByUserId,
                    'scanned_at' => $now,
                ]);

                return [
                    'status' => 'DENIED',
                    'message' => 'Tiket tidak mengizinkan re-entry',
                    'log' => $log,
                ];
            }

            return DB::transaction(function () use ($ticket, $gateCode, $direction, $scannedByUserId, $now, $tier) {
                $ticket->update([
                    'status' => 'USED',
                    'scanned_at' => $now,
                ]);

                if ($tier?->zone_id) {
                    EntertainmentZone::where('id', $tier->zone_id)->increment('current_occupancy');
                }

                $log = GateScanLog::create([
                    'ticket_id' => $ticket->id,
                    'gate_code' => $gateCode,
                    'direction' => $direction,
                    'scan_result' => 'ALLOWED',
                    'denial_reason' => null,
                    'scanned_by_user_id' => $scannedByUserId,
                    'scanned_at' => $now,
                ]);

                return [
                    'status' => 'ALLOWED',
                    'message' => 'Akses diizinkan',
                    'ticket' => $ticket,
                    'log' => $log,
                ];
            });
        }

        // Direction: OUT
        return DB::transaction(function () use ($ticket, $gateCode, $direction, $scannedByUserId, $now, $tier, $allowsReentry) {
            if ($allowsReentry && $ticket->status === 'USED') {
                $ticket->update(['status' => 'EXITED']);
            }

            if ($tier?->zone_id) {
                EntertainmentZone::where('id', $tier->zone_id)
                    ->where('current_occupancy', '>', 0)
                    ->decrement('current_occupancy');
            }

            $log = GateScanLog::create([
                'ticket_id' => $ticket->id,
                'gate_code' => $gateCode,
                'direction' => $direction,
                'scan_result' => 'ALLOWED',
                'denial_reason' => null,
                'scanned_by_user_id' => $scannedByUserId,
                'scanned_at' => $now,
            ]);

            return [
                'status' => 'ALLOWED',
                'message' => 'Akses keluar dicatat',
                'ticket' => $ticket,
                'log' => $log,
            ];
        });
    }
}
