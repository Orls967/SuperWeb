<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\TicketResale;
use Modules\Venue\Domain\StateMachines\TicketResaleStateMachine;

final class SecondaryTicketTransferVerificationService
{
    /**
     * Verifikasi integritas kriptografis tiket sebelum dialihkan ke pembeli baru.
     *
     * @return array{
     *   is_valid: bool,
     *   ticket_id: int,
     *   original_hash: string,
     *   rotated_hash: string,
     *   verification_audit_trail: string,
     *   reason: ?string
     * }
     */
    public function verifyAndRotateBarcodeToken(EntertainmentTicket $ticket, TicketResale $resale, int $newBuyerUserId): array
    {
        if ($ticket->id !== $resale->ticket_id) {
            return [
                'is_valid' => false,
                'ticket_id' => $ticket->id,
                'original_hash' => (string) $ticket->ticket_hash,
                'rotated_hash' => '',
                'verification_audit_trail' => 'MISMATCH_TICKET_RESALE_ID',
                'reason' => 'Ticket ID tidak cocok dengan listing resale yang ditargetkan.',
            ];
        }

        if ($ticket->status !== 'VALID' && $ticket->status !== 'ISSUED') {
            return [
                'is_valid' => false,
                'ticket_id' => $ticket->id,
                'original_hash' => (string) $ticket->ticket_hash,
                'rotated_hash' => '',
                'verification_audit_trail' => 'INVALID_TICKET_LIFECYCLE_STATUS',
                'reason' => "Status tiket ({$ticket->status}) tidak mengizinkan pemindahtanganan di pasar sekunder.",
            ];
        }

        // Cek riwayat transfer sebelumnya
        $pastSettledTransfers = TicketResale::query()
            ->where('ticket_id', $ticket->id)
            ->where('status', TicketResaleStateMachine::STATUS_SETTLED)
            ->where('id', '!=', $resale->id)
            ->count();

        if ($pastSettledTransfers > 0) {
            return [
                'is_valid' => false,
                'ticket_id' => $ticket->id,
                'original_hash' => (string) $ticket->ticket_hash,
                'rotated_hash' => '',
                'verification_audit_trail' => 'SINGLE_TRANSFER_RULE_VIOLATION',
                'reason' => 'Pelanggaran aturan single transfer: tiket sekunder dilarang dijual kembali untuk kedua kalinya.',
            ];
        }

        // Generate rotated barcode hash token baru yang unik
        $entropy = sprintf(
            'VENUE-SEC-ROTATED|T:%d|R:%d|B:%d|TIME:%d|SALT:%s',
            $ticket->id,
            $resale->id,
            $newBuyerUserId,
            time(),
            bin2hex(random_bytes(16))
        );
        $newRotatedHash = hash('sha256', $entropy);

        $auditTrail = sprintf(
            'SUCCESSFUL_TRANSFER_AUDIT[Seller:%d->Buyer:%d|Price:%d|RotatedHash:%s]',
            $resale->seller_user_id,
            $newBuyerUserId,
            $resale->resale_price_idr,
            substr($newRotatedHash, 0, 12)
        );

        return [
            'is_valid' => true,
            'ticket_id' => $ticket->id,
            'original_hash' => (string) $ticket->ticket_hash,
            'rotated_hash' => $newRotatedHash,
            'verification_audit_trail' => $auditTrail,
            'reason' => null,
        ];
    }
}
