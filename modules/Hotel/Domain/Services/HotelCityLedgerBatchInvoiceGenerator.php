<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Hotel\Domain\Models\HtlCorporateAccount;
use Modules\Hotel\Domain\Models\HtlSubFolio;

class HotelCityLedgerBatchInvoiceGenerator
{
    /**
     * Mengelompokkan seluruh sub-folio korporat yang belum ditagihkan menjadi batch faktur konsolidasi.
     *
     * @return array{batch_id: string, corporate_id: string, company_name: string, sub_folio_count: int, total_invoiced_idr: int, invoice_date: string, due_date: string}
     */
    public function generateConsolidatedInvoice(string $corporateAccountId, int $paymentTermsDays = 30): array
    {
        $corp = HtlCorporateAccount::findOrFail($corporateAccountId);
        $subFolios = HtlSubFolio::where('corporate_id', $corporateAccountId)
            ->where('status', 'OPEN')
            ->where('balance_idr', '>', 0)
            ->get();

        $totalInvoiced = (int) $subFolios->sum('balance_idr');
        $batchId = 'INV-CORP-'.date('Ym').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        $invoiceDate = CarbonImmutable::now();
        $dueDate = $invoiceDate->addDays($paymentTermsDays);

        foreach ($subFolios as $sf) {
            $sf->status = 'INVOICED';
            $sf->save();
        }

        return [
            'batch_id' => $batchId,
            'corporate_id' => $corp->id,
            'company_name' => $corp->company_name,
            'sub_folio_count' => $subFolios->count(),
            'total_invoiced_idr' => $totalInvoiced,
            'invoice_date' => $invoiceDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
        ];
    }
}
