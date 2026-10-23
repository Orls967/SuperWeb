<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Actions;

use InvalidArgumentException;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;

class CreateMirroredIntercompanyTransactionAction
{
    /**
     * @param array{
     *     selling_entity: string,
     *     buying_entity: string,
     *     description: string,
     *     amount_idr: int,
     *     currency?: string,
     *     sales_invoice_ref?: string,
     *     purchase_bill_ref?: string,
     *     transaction_code?: string
     * } $data
     */
    public function execute(array $data): IntercompanyTransaction
    {
        if ($data['selling_entity'] === $data['buying_entity']) {
            throw new InvalidArgumentException('Selling entity and buying entity cannot be the same entity.');
        }

        if ($data['amount_idr'] <= 0) {
            throw new InvalidArgumentException('Intercompany transaction amount must be greater than zero.');
        }

        $code = $data['transaction_code'] ?? 'IC-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $salesRef = $data['sales_invoice_ref'] ?? 'INV-IC-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $billRef = $data['purchase_bill_ref'] ?? 'BILL-IC-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        return IntercompanyTransaction::create([
            'transaction_code' => $code,
            'selling_entity' => $data['selling_entity'],
            'buying_entity' => $data['buying_entity'],
            'description' => $data['description'],
            'currency' => strtoupper($data['currency'] ?? 'IDR'),
            'amount_idr' => $data['amount_idr'],
            'sales_invoice_ref' => $salesRef,
            'purchase_bill_ref' => $billRef,
            'status' => 'matched',
        ]);
    }
}
