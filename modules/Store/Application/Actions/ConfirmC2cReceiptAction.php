<?php

declare(strict_types=1);

namespace Modules\Store\Application\Actions;

use App\Models\User;
use Exception;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\Domain\Enums\PaymentIntentStatus;
use Modules\Shared\Application\BaseAction;
use Modules\Shared\Domain\ValueObjects\Money;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Order;

/**
 * Pembeli mengonfirmasi kendaraan diterima: dana escrow dicairkan
 * (99% ke dompet penjual, 1% fee platform) dan kepemilikan berpindah.
 */
class ConfirmC2cReceiptAction extends BaseAction
{
    public function __construct(
        private readonly PaymentGateway $paymentGateway,
    ) {}

    public function execute(Order $order, ?User $actor = null): Order
    {
        if (! $order->isC2c()) {
            throw new Exception('Pesanan ini bukan transaksi C2C.');
        }

        if ($actor !== null
            && (int) $order->user_id !== (int) $actor->id
            && ! $actor->isAdmin()) {
            throw new Exception('Hanya pembeli yang dapat mengonfirmasi penerimaan kendaraan.');
        }

        if (! in_array($order->status, [OrderStatus::AWAITING_CONFIRMATION, OrderStatus::DISPUTED], true)) {
            throw new Exception("Konfirmasi penerimaan hanya berlaku setelah kendaraan diserahkan. Status saat ini: {$order->status->label()}");
        }

        $intent = $order->paymentIntents()
            ->where('status', PaymentIntentStatus::HELD->value)
            ->latest()
            ->first();

        if ($intent === null) {
            throw new Exception('Dana escrow untuk pesanan ini tidak ditemukan.');
        }

        // Pastikan akun dompet penjual sudah ada sebelum posting ledger
        $order->loadMissing('seller');
        $order->seller?->walletAccount('IDR');

        // Capture escrow: memicu Order::onPaymentCaptured
        // (commit stok unit, transfer kepemilikan kendaraan, blok paspor)
        $this->paymentGateway->capture(
            $intent,
            Money::fromIdr($order->grand_total),
            'c2c_capture_'.$order->uuid
        );

        return $order->fresh(['items.product', 'paymentIntents']);
    }
}
