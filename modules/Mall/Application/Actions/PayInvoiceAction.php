<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use App\Models\User;
use InvalidArgumentException;
use Modules\Mall\Domain\Models\Invoice;

class PayInvoiceAction
{
    public function __construct(
        protected AllocatePaymentAction $allocatePaymentAction,
    ) {}

    /**
     * Pembayaran manual via portal tenant (memerlukan PIN transaksi dompet).
     *
     * @throws InvalidArgumentException
     */
    public function execute(Invoice $invoice, int $amount, string $pin, User $user): Invoice
    {
        // Validasi PIN transaksi
        if (! $user->verifyPin($pin)) {
            throw new InvalidArgumentException('PIN dompet yang Anda masukkan salah.');
        }

        // Validasi hak akses (hanya pemilik tenant atau admin/staff yang berhak)
        $tenantUserId = $invoice->tenant?->user_id;
        if ($tenantUserId !== $user->id && ! $user->isMallAdmin() && ! $user->isAdmin()) {
            throw new InvalidArgumentException('Anda tidak memiliki otoritas untuk membayar tagihan tenant ini.');
        }

        return $this->allocatePaymentAction->execute(
            $invoice,
            $amount,
            source: 'portal'
        );
    }
}
