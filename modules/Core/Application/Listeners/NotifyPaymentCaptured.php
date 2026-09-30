<?php

declare(strict_types=1);

namespace Modules\Core\Application\Listeners;

use Modules\Core\Application\Services\ActivityLogger;
use Modules\Core\Application\Services\NotificationService;
use Modules\Payment\Domain\Events\PaymentCaptured;

class NotifyPaymentCaptured
{
    public function __construct(
        private NotificationService $notifications,
        private ActivityLogger $activity,
    ) {}

    public function handle(PaymentCaptured $event): void
    {
        $intent = $event->intent;
        $userId = $intent->payer_id;

        if (! $userId) {
            return;
        }

        $amount = number_format((float) $intent->amount, 0, ',', '.');

        $this->notifications->send(
            userId: $userId,
            type: 'payment_captured',
            title: 'Pembayaran Berhasil ✅',
            body: "Pembayaran sebesar Rp {$amount} telah berhasil diproses.",
            icon: 'success',
            meta: [
                'intent_id' => $intent->id,
                'amount' => $intent->amount,
            ],
        );

        $this->activity->log(
            module: 'payment',
            event: 'payment_captured',
            description: "Pembayaran Rp {$amount} berhasil diproses",
            userId: $userId,
            subject: $intent,
            properties: [
                'amount' => $intent->amount,
                'gateway' => $intent->gateway ?? 'wallet',
            ],
        );
    }
}
