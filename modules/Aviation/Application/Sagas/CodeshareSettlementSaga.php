<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Sagas;

use DomainException;
use Illuminate\Support\Facades\Log;
use Modules\Aviation\Actions\SettleCodeshareProrationAction;
use Modules\Aviation\Domain\Models\AviationCodeshareAgreement;
use Modules\Aviation\Domain\Models\AviationTicket;

class CodeshareSettlementSaga
{
    public function __construct(
        protected SettleCodeshareProrationAction $prorationAction
    ) {}

    /**
     * Jalankan saga penyelesaian kliring tiket codeshare multi-langkah dengan kompensasi otomatis.
     *
     * @param  int  $agreementId  ID perjanjian codeshare
     * @param  int  $ticketId  ID tiket penumpang
     * @param  int  $tpmDistance  Jarak tempuh dalam mil laut
     * @return array{saga_id: string, status: string, details: array<string, mixed>}
     */
    public function execute(int $agreementId, int $ticketId, int $tpmDistance): array
    {
        $sagaId = sprintf('SAGA-CS-%s-%s', date('Ymd'), strtoupper(substr(uniqid('', true), -6)));
        $completedSteps = [];

        try {
            // Langkah 1: Validasi Persyaratan Tiket dan Status Kupon
            $ticket = AviationTicket::findOrFail($ticketId);
            if (! in_array($ticket->status, ['confirmed', 'checked_in', 'boarded'], true)) {
                throw new DomainException(sprintf('Kupon tiket [%s] berstatus [%s] tidak memenuhi syarat kliring.', $ticket->ticket_number, $ticket->status));
            }
            $completedSteps[] = 'STEP_1_TICKET_VALIDATION';

            // Langkah 2: Periksa Legalitas Perjanjian Kerjasama Codeshare
            $agreement = AviationCodeshareAgreement::findOrFail($agreementId);
            if (! $agreement->isActive()) {
                throw new DomainException(sprintf('Perjanjian codeshare [%s] sedang tidak aktif.', $agreement->agreement_code));
            }
            $completedSteps[] = 'STEP_2_AGREEMENT_VALIDATION';

            // Langkah 3: Eksekusi Prorasi Pembagian Pendapatan Finansial
            $settlementResult = $this->prorationAction->execute($agreementId, $ticketId, $tpmDistance);
            $completedSteps[] = 'STEP_3_PRORATION_SETTLEMENT';

            // Langkah 4: Tandai Tiket sebagai Selesai Diklarifikasi
            $ticket->status = 'checked_in';
            $ticket->save();
            $completedSteps[] = 'STEP_4_TICKET_SETTLED';

            return [
                'saga_id' => $sagaId,
                'status' => 'COMPLETED',
                'details' => $settlementResult,
            ];
        } catch (\Throwable $e) {
            Log::error('CodeshareSettlementSaga gagal, memulai kompensasi', [
                'saga_id' => $sagaId,
                'error' => $e->getMessage(),
                'completed_steps' => $completedSteps,
            ]);

            $this->compensate($sagaId, $completedSteps, $ticketId);

            throw new DomainException(sprintf('Saga kliring codeshare gagal [%s]: %s', $sagaId, $e->getMessage()), 0, $e);
        }
    }

    /**
     * Kompensasi balik untuk langkah-langkah yang sempat berhasil.
     */
    protected function compensate(string $sagaId, array $completedSteps, int $ticketId): void
    {
        if (in_array('STEP_4_TICKET_SETTLED', $completedSteps, true)) {
            $ticket = AviationTicket::find($ticketId);
            if ($ticket !== null) {
                $ticket->status = 'confirmed';
                $ticket->save();
            }
        }

        Log::info('Kompensasi CodeshareSettlementSaga berhasil diselesaikan', [
            'saga_id' => $sagaId,
            'ticket_id' => $ticketId,
            'compensated_steps' => $completedSteps,
        ]);
    }
}
