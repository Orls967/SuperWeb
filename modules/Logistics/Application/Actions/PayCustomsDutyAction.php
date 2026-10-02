<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Contracts\VerifiesWalletPin;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Logistics\Application\Services\LogisticsLedger;
use Modules\Logistics\Domain\Exceptions\CustomsException;
use Modules\Logistics\Domain\Models\CustomsDeclaration;

class PayCustomsDutyAction
{
    public function __construct(
        private readonly LogisticsLedger $ledger,
        private readonly VerifiesWalletPin $pinVerifier
    ) {}

    /**
     * Alur 13 (langkah 2): pemilik resi membayar bea dan pajak dari dompetnya (PIN wajib).
     * Debit dompet shipper, kredit titipan bea cukai (utang ke negara).
     */
    public function execute(User $shipper, CustomsDeclaration $declaration, string $pin): CustomsDeclaration
    {
        $this->pinVerifier->execute($shipper, $pin);

        return DB::transaction(function () use ($shipper, $declaration) {
            $declaration = CustomsDeclaration::whereKey($declaration->id)->lockForUpdate()->firstOrFail();
            $shipment = $declaration->shipment;

            if ($shipment->shipper_id !== $shipper->id) {
                throw CustomsException::forbidden('membayar bea cukai milik shipper lain');
            }

            if ($declaration->paid_at !== null) {
                return $declaration;
            }

            if ($declaration->total_duty_idr > 0) {
                $this->ledger->post(
                    type: TransactionType::LOGISTICS_CUSTOMS_DUTY,
                    description: "Pembayaran bea cukai {$declaration->declaration_number} resi {$shipment->tracking_number}",
                    idempotencyKey: "lgx:customs_pay:{$declaration->id}",
                    entries: [
                        [$shipper->walletAccount('IDR')->id, -$declaration->total_duty_idr],
                        [LogisticsLedger::CUSTOMS_DUTY_PAYABLE, $declaration->total_duty_idr],
                    ],
                    referenceType: CustomsDeclaration::class,
                    referenceId: $declaration->id,
                    createdBy: $shipper->id,
                );
            }

            $declaration->update(['paid_by' => $shipper->id, 'paid_at' => now()]);

            return $declaration;
        });
    }
}
