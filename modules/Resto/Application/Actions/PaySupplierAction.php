<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Resto\Domain\Models\PurchaseOrder;
use Modules\Resto\Domain\Models\Supplier;

class PaySupplierAction
{
    public function __construct(
        private readonly Ledger $ledger
    ) {}

    public function handle(
        Supplier $supplier,
        int $amount,
        ?PurchaseOrder $po = null,
        ?User $payerUser = null,
        ?string $note = null
    ): LedgerTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal pembayaran supplier harus lebih besar dari 0.');
        }

        return DB::transaction(function () use ($supplier, $amount, $po, $payerUser, $note) {
            $apAccCode = "ap:supplier:{$supplier->id}:IDR";
            $clearingAccCode = 'clearing:external:IDR';

            $this->ensureLedgerAccountExists($apAccCode, "Utang Usaha Supplier #{$supplier->id} ({$supplier->name})", AccountKind::AP);
            $this->ensureLedgerAccountExists($clearingAccCode, 'Rekening Kliring Eksternal IDR', AccountKind::CLEARING);

            $amountBd = BigDecimal::of($amount);

            $trans = $this->ledger->post(new PostingDTO(
                type: TransactionType::SUPPLIER_PAYMENT->value,
                description: "Pembayaran utang dagang ke {$supplier->name}: Rp ".number_format($amount, 0, ',', '.').($po ? " (PO #{$po->number})" : '').($note ? " - {$note}" : ''),
                idempotencyKey: "resto:supplier:pay:{$supplier->id}:".Str::uuid(),
                entries: [
                    PostingEntryDTO::forCode($apAccCode, 'IDR', $amountBd),
                    PostingEntryDTO::forCode($clearingAccCode, 'IDR', $amountBd->negated()),
                ],
                referenceType: $po ? 'resto_purchase_order' : 'resto_supplier',
                referenceId: $po ? $po->id : $supplier->id,
                createdBy: $payerUser?->id
            ));

            if ($po) {
                $po->paid_amount += $amount;
                $po->save();
            }

            return $trans;
        });
    }

    private function ensureLedgerAccountExists(string $code, string $name, AccountKind $kind): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'asset_code' => 'IDR',
                'kind' => $kind->value,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
