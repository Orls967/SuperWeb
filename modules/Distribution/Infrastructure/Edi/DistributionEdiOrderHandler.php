<?php

declare(strict_types=1);

namespace Modules\Distribution\Infrastructure\Edi;

use Modules\Core\Contracts\CanonicalEdiDocument;
use Modules\Core\Contracts\EdiDocumentHandler;
use Modules\Core\Contracts\EdiHandleResult;
use Modules\Distribution\Domain\Models\DistOrder;
use Modules\Distribution\Domain\Models\Distributor;

class DistributionEdiOrderHandler implements EdiDocumentHandler
{
    public function moduleName(): string
    {
        return 'Distribution';
    }

    public function supports(string $messageType): bool
    {
        return in_array(strtoupper($messageType), ['ORDERS', '850'], true);
    }

    public function handle(CanonicalEdiDocument $document, string $idempotencyKey): EdiHandleResult
    {
        $existing = DistOrder::where('number', $document->documentNumber)->first();
        if ($existing !== null) {
            return EdiHandleResult::success((string) $existing->id, 'Order already existed (idempotent duplicate)');
        }

        $distributor = Distributor::first() ?? Distributor::create([
            'code' => 'DIST-EDI-01',
            'name' => 'EDI Default Distributor',
            'kind' => 'distributor',
            'tier' => 'tier_1',
            'status' => 'approved',
            'credit_limit_idr' => 1000000000,
            'credit_exposure_idr' => 0,
        ]);
        $distributorId = $distributor->id;

        $totalCents = 0;
        foreach ($document->lineItems as $item) {
            $totalCents += ($item['quantity'] * $item['unit_price']);
        }

        $order = DistOrder::create([
            'number' => $document->documentNumber ?: 'EDI-ORD-'.uniqid(),
            'distributor_id' => $distributorId,
            'status' => 'draft',
            'subtotal_idr' => $totalCents,
            'discount_idr' => 0,
            'ppn_idr' => 0,
            'total_idr' => $totalCents,
            'currency' => 'IDR',
            'shipping_address' => 'EDI Partner Warehouse',
            'requested_date' => now()->addDays(3),
            'notes' => 'Imported via EDI '.$document->standard.' control_ref: '.$document->interchangeControlRef,
        ]);

        return EdiHandleResult::success((string) $order->id, 'Draft distribution order created from EDI '.$document->messageType);
    }
}
