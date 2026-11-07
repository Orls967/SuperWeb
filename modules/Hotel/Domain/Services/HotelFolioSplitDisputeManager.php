<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Hotel\Domain\Algorithms\FolioSplitCertificate;
use Modules\Hotel\Domain\Algorithms\FolioSplitRouter;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;
use Modules\Hotel\Domain\Models\HtlSubFolio;

class HotelFolioSplitDisputeManager
{
    public function __construct(
        private readonly FolioSplitRouter $router
    ) {}

    /**
     * Menangani sengketa (dispute) pada baris tagihan tertentu di folio,
     * melakukan pemisahan kembali dan pencatatan kompensasi ke sub-folio.
     *
     * @return array{status: string, disputed_line_id: string, reallocated_sub_folio_id: string, dispute_reason: string, processed_at: string}
     */
    public function raiseDisputeAndReallocate(
        string $folioId,
        string $lineId,
        string $disputeReason,
        string $targetPayerType,
        ?string $targetCorporateId = null
    ): array {
        $folio = HotelFolio::findOrFail($folioId);
        $line = HotelFolioLine::where('folio_id', $folioId)->where('id', $lineId)->firstOrFail();

        if ($line->amount_cents <= 0) {
            throw new InvalidArgumentException('Hanya baris dengan nilai debit positif yang dapat diajukan sengketa.');
        }

        // Cari atau buat sub-folio untuk target payer baru
        $targetSubFolio = HtlSubFolio::firstOrCreate(
            [
                'folio_id' => $folio->id,
                'payer_type' => $targetPayerType,
                'corporate_id' => $targetCorporateId,
            ],
            [
                'total_charges_idr' => 0,
                'total_payments_idr' => 0,
                'balance_idr' => 0,
                'status' => 'DISPUTED_HOLD',
            ]
        );

        $lineAmountIdr = (int) ($line->amount_cents / 100);
        $targetSubFolio->addCharge($lineAmountIdr);

        // Catat metadata penanganan sengketa pada baris folio
        $meta = $line->metadata ?? [];
        $meta['disputed'] = true;
        $meta['dispute_reason'] = $disputeReason;
        $meta['reallocated_to_sub_folio'] = $targetSubFolio->id;
        $meta['disputed_at'] = CarbonImmutable::now()->toISOString();
        $line->metadata = $meta;
        $line->save();

        return [
            'status' => 'DISPUTE_RECORDED',
            'disputed_line_id' => $line->id,
            'reallocated_sub_folio_id' => $targetSubFolio->id,
            'dispute_reason' => $disputeReason,
            'processed_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * Evaluasi ulang aturan routing folio saat terjadi perubahan master kontrak korporat.
     */
    public function reEvaluateFolioRouting(HotelFolio $folio, string $corporateId, int $corporateCreditLimit): FolioSplitCertificate
    {
        $lines = HotelFolioLine::where('folio_id', $folio->id)->get();
        $items = [];

        foreach ($lines as $line) {
            $items[] = [
                'id' => (string) $line->id,
                'category' => (string) ($line->charge_type ?? 'ROOM'),
                'amount_idr' => (int) ($line->amount_cents / 100),
                'description' => (string) ($line->description ?? 'Charge'),
            ];
        }

        $input = [
            'folio_id' => (string) $folio->id,
            'corporate_account_id' => $corporateId,
            'corporate_credit_limit_idr' => $corporateCreditLimit,
            'items' => $items,
        ];

        return $this->router->compute($input);
    }
}
