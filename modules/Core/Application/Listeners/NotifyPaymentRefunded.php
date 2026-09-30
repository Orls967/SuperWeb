<?php

declare(strict_types=1);

namespace Modules\Core\Application\Listeners;

use Modules\Core\Application\Services\ActivityLogger;
use Modules\Core\Application\Services\NotificationService;
use Modules\Payment\Domain\Events\PaymentRefunded;

class NotifyPaymentRefunded
{
    public function __construct(
        private NotificationService $notifications,
        private ActivityLogger $activity,
    ) {}

    public function handle(PaymentRefunded $event): void
    {
        $intent = $event->intent;
        $userId = $intent->payer_id;

        if (! $userId) {
            return;
        }

        $amount = number_format((float) $intent->amount, 0, ',', '.');

        $this->notifications->send(
            userId: $userId,
            type: 'payment_refunded',
            title: 'Refund Diterima 💰',
            body: "Refund sebesar Rp {$amount} telah dikembalikan ke saldo Anda.",
            icon: 'info',
            meta: [
                'intent_id' => $intent->id,
                'amount' => $intent->amount,
            ],
        );

        $this->activity->log(
            module: 'payment',
            event: 'payment_refunded',
            description: "Refund Rp {$amount} dikembalikan ke saldo",
            userId: $userId,
            subject: $intent,
        );
    }
}
