<?php

declare(strict_types=1);

namespace Modules\Store\Application\Actions;

use Exception;
use Modules\Shared\Application\BaseAction;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Order;

/**
 * Admin memutuskan sengketa C2C: mencairkan dana ke penjual (capture)
 * atau mengembalikannya ke pembeli (release).
 */
class ResolveC2cDisputeAction extends BaseAction
{
    public function __construct(
        private readonly ConfirmC2cReceiptAction $confirmReceipt,
        private readonly CancelC2cOrderAction $cancelOrder,
    ) {}

    public function execute(Order $order, string $decision, string $note = ''): Order
    {
        if ($order->status !== OrderStatus::DISPUTED) {
            throw new Exception('Hanya pesanan berstatus sengketa yang dapat diputuskan admin.');
        }

        return match ($decision) {
            'capture' => $this->confirmReceipt->execute($order),
            'release' => $this->cancelOrder->execute(
                $order,
                'Sengketa diputuskan untuk pembeli'.($note !== '' ? ": {$note}" : '')
            ),
            default => throw new Exception("Keputusan '{$decision}' tidak dikenal. Gunakan 'capture' atau 'release'."),
        };
    }
}
