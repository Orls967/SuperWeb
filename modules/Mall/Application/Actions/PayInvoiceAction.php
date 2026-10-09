<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use App\Models\User;
use InvalidArgumentException;
use Modules\Banking\Contracts\VerifiesWalletPin;
use Modules\Mall\Domain\Models\Invoice;

class PayInvoiceAction
{
    public function __construct(
        protected AllocatePaymentAction $allocatePaymentAction,
        protected VerifiesWalletPin $verifyPinAction,
    ) {}

    /**
     * Pembayaran manual via portal tenant.
     *
     * PIN diverifikasi lewat mekanisme dompet Banking (hash + lockout 5x gagal),
     * satu-satunya sumber kebenaran PIN di platform ini.
     *
     * @throws InvalidArgumentException
     */
    public function execute(Invoice $invoice, int $amount, string $pin, User $user, ?string $idempotencyKey = null): Invoice
    {
        // Validasi hak akses (hanya pemilik tenant atau admin/staff yang berhak)
        $tenantUserId = $invoice->tenant?->user_id;
        if ($tenantUserId !== $user->id && ! $user->isMallAdmin() && ! $user->isAdmin()) {
            throw new InvalidArgumentException('Anda tidak memiliki otoritas untuk membayar tagihan tenant ini.');
        }

        $this->verifyPinAction->execute($user, $pin);

        return $this->allocatePaymentAction->execute(
            $invoice,
            $amount,
            source: 'portal',
            idempotencyKey: $idempotencyKey
        );
    }
}
