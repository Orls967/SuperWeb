<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use DomainException;
use Modules\Banking\Contracts\VerifiesWalletPin;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Logistics\Domain\Models\LogisticsInvoice;
use Modules\Payment\Contracts\PaymentGateway;

class PayLogisticsInvoiceAction
{
    public function __construct(
        private readonly PaymentGateway $paymentGateway,
        private readonly VerifiesWalletPin $pinVerifier
    ) {}

    /**
     * Pay a B2B postpaid logistics invoice via shipper wallet with mandatory PIN.
     */
    public function execute(
        User $shipper,
        LogisticsInvoice $invoice,
        string $pin,
        ?string $idempotencyKey = null
    ): LogisticsInvoice {
        // 1. Mandatory PIN verification
        $this->pinVerifier->execute($shipper, $pin);

        // 2. Validate ownership and status
        if ($invoice->shipper_id !== $shipper->id) {
            throw new DomainException('Anda tidak berhak membayar tagihan invoice milik shipper lain.');
        }

        if ($invoice->status === 'paid') {
            return $invoice;
        }

        // 3. Ensure Shipper AR ledger account exists
        $arCode = "lgx:ar:{$shipper->id}";
        LedgerAccount::firstOrCreate(
            ['code' => $arCode, 'asset_code' => 'IDR'],
            [
                'name' => "Piutang B2B Shipper {$shipper->name}",
                'kind' => AccountKind::ASSET->value,
                'allow_negative' => true,
            ]
        );

        // 4. Charge shipper wallet via PaymentGateway (clearing AR balance)
        $idemKey = $idempotencyKey ?: "lgx:pay_inv:{$invoice->id}";
        $this->paymentGateway->charge($invoice, $idemKey);

        return $invoice->fresh();
    }
}
