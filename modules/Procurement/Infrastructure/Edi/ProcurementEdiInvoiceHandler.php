<?php

declare(strict_types=1);

namespace Modules\Procurement\Infrastructure\Edi;

use Modules\Core\Contracts\CanonicalEdiDocument;
use Modules\Core\Contracts\EdiDocumentHandler;
use Modules\Core\Contracts\EdiHandleResult;
use Modules\Procurement\Domain\Models\SupplierInvoice;
use Modules\Supplier\Contracts\Supplier;

class ProcurementEdiInvoiceHandler implements EdiDocumentHandler
{
    public function moduleName(): string
    {
        return 'Procurement';
    }

    public function supports(string $messageType): bool
    {
        return in_array(strtoupper($messageType), ['INVOIC', '810'], true);
    }

    public function handle(CanonicalEdiDocument $document, string $idempotencyKey): EdiHandleResult
    {
        $existing = SupplierInvoice::where('number', $document->documentNumber)->first();
        if ($existing !== null) {
            return EdiHandleResult::success((string) $existing->id, 'Supplier invoice already exists');
        }

        $totalCents = 0;
        foreach ($document->lineItems as $item) {
            $totalCents += ($item['quantity'] * $item['unit_price']);
        }

        $supplier = \Modules\Supplier\Contracts\Supplier::first();
        if (! $supplier) {
            $supplier = \Modules\Supplier\Contracts\Supplier::create([
                'name' => 'EDI Default Supplier',
            ]);
        }
        $supplierId = $supplier->id;

        $invoice = SupplierInvoice::create([
            'number' => $document->documentNumber ?: 'EDI-INV-' . uniqid(),
            'supplier_id' => $supplierId,
            'po_id' => null,
            'status' => 'draft',
            'invoice_amount_idr' => $totalCents,
            'matched_amount_idr' => 0,
            'variance_idr' => 0,
            'price_tolerance_pct' => 0,
            'qty_tolerance_pct' => 0,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'currency' => 'IDR',
            'notes' => 'Ingested via EDI INVOIC interchange ' . $document->interchangeControlRef . ' (No ledger entry created).',
        ]);

        return EdiHandleResult::success((string) $invoice->id, 'Draft supplier invoice created without ledger posting');
    }
}

