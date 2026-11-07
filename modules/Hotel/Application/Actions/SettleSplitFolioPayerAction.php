<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Domain\Algorithms\FolioSplitCertificate;
use Modules\Hotel\Domain\Algorithms\FolioSplitCertificateVerifier;
use Modules\Hotel\Domain\Algorithms\FolioSplitRouter;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;
use RuntimeException;

/**
 * Tindakan Penyelesaian Sub-Folio Multi-Payer (Corporate vs Personal).
 */
final class SettleSplitFolioPayerAction
{
    public function __construct(
        private readonly ?LedgerService $ledger,
        private readonly FolioSplitRouter $router,
        private readonly FolioSplitCertificateVerifier $verifier,
    ) {}

    /**
     * @return array{folio_id: string, corporate_paid_idr: int, personal_paid_idr: int, certificate: FolioSplitCertificate, is_fully_settled: bool}
     */
    public function execute(
        string $folioId,
        string $corporateId,
        bool $corporateBillingEnabled,
        int $corporateCreditLimitIdr,
        int $personalPaymentAmountIdr,
        string $idempotencyKey
    ): array {
        $folio = HotelFolio::findOrFail($folioId);

        // Kumpulkan seluruh baris transaksi folio
        $lines = HotelFolioLine::where('folio_id', $folio->id)->get();
        $lineItems = [];
        foreach ($lines as $line) {
            $lineItems[] = [
                'line_id' => (string) $line->id,
                'category' => (string) $line->line_type,
                'amount_idr' => (int) $line->amount_idr,
            ];
        }

        // Eksekusi Algoritma H6: FolioSplitRouter
        $cert = $this->router->route(
            folioId: $folio->id,
            corporateBillingEnabled: $corporateBillingEnabled,
            corporateCreditLimitIdr: $corporateCreditLimitIdr,
            lineItems: $lineItems
        );

        if (! $this->verifier->verify($cert)) {
            throw new RuntimeException("Verifikasi sertifikat FolioSplit gagal untuk folio {$folio->id}");
        }

        // Posting Akuntansi Corporate City Ledger jika ada alokasi korporat
        if ($this->ledger && $cert->corporateAmountIdr > 0) {
            $this->ledger->post(new PostingDTO(
                type: 'HOTEL_FOLIO_SETTLEMENT',
                description: "Corporate city ledger AR transfer for company {$corporateId} folio {$folio->id}",
                idempotencyKey: "htl:folio_split:corp:{$folio->id}:{$corporateId}:{$idempotencyKey}",
                entries: Posting::lines()
                    ->debit('htl:city_ledger:IDR', $cert->corporateAmountIdr)
                    ->credit('htl:guest_ledger:IDR', $cert->corporateAmountIdr)
                    ->toEntries('IDR'),
                referenceType: 'htl_folios',
                referenceId: $folio->id,
            ));
        }

        // Posting Pembayaran Personal Tamu
        $personalPaid = min($cert->personalAmountIdr, $personalPaymentAmountIdr);
        if ($this->ledger && $personalPaid > 0) {
            $this->ledger->post(new PostingDTO(
                type: 'HOTEL_FOLIO_SETTLEMENT',
                description: "Personal settlement by guest for folio {$folio->id}",
                idempotencyKey: "htl:folio_split:pers:{$folio->id}:{$idempotencyKey}",
                entries: Posting::lines()
                    ->debit('clearing:external:IDR', $personalPaid)
                    ->credit('htl:guest_ledger:IDR', $personalPaid)
                    ->toEntries('IDR'),
                referenceType: 'htl_folios',
                referenceId: $folio->id,
            ));
        }

        $totalPaidNow = $cert->corporateAmountIdr + $personalPaid;
        $folio->increment('total_paid', $totalPaidNow);
        $totalCharges = $folio->total_room_charges + $folio->total_addon_charges;
        $remainingBalance = max(0, $totalCharges - $folio->total_paid);

        $isClosed = ($remainingBalance === 0);
        $folio->update([
            'outstanding_balance' => $remainingBalance,
            'status' => $isClosed ? 'settled' : 'open',
        ]);

        return [
            'folio_id' => $folio->id,
            'corporate_paid_idr' => $cert->corporateAmountIdr,
            'personal_paid_idr' => $personalPaid,
            'certificate' => $cert,
            'is_fully_settled' => $isClosed,
        ];
    }
}
