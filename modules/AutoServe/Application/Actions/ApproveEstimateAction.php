<?php

declare(strict_types=1);

namespace Modules\AutoServe\Application\Actions;

use App\Models\User;
use Exception;
use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Enums\EstimateStatus;
use Modules\AutoServe\Domain\Models\Estimate;
use Modules\Banking\Contracts\VerifiesWalletPin;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Shared\Application\BaseAction;
use Modules\Shared\Domain\ValueObjects\Money;

/**
 * Customer menyetujui estimasi: dana sebesar total estimasi ditahan di escrow,
 * lalu booking masuk pengerjaan (atau menunggu sparepart bila stok kurang).
 */
class ApproveEstimateAction extends BaseAction
{
    public function __construct(
        private readonly PaymentGateway $paymentGateway,
        private readonly VerifiesWalletPin $verifyPinAction,
        private readonly BackorderPartsAction $backorderParts,
    ) {}

    public function execute(Estimate $estimate, User $customer, string $pin, ?string $idempotencyKey = null): Estimate
    {
        $estimate->loadMissing('booking');
        $booking = $estimate->booking;

        if ($booking === null) {
            throw new Exception('Estimasi tidak terhubung dengan booking manapun.');
        }

        if ((int) $booking->customer_id !== (int) $customer->id && ! $customer->isAdmin()) {
            throw new Exception('Hanya pemilik booking yang dapat menyetujui estimasi ini.');
        }

        if ($estimate->status !== EstimateStatus::Sent) {
            throw new Exception("Estimasi berstatus {$estimate->status->label()} tidak dapat disetujui.");
        }

        $walletBalance = (int) $customer->walletAccount('IDR')->cached_balance;
        if ($walletBalance < $estimate->total) {
            $kurang = number_format($estimate->total - $walletBalance, 0, ',', '.');
            throw new Exception("Saldo dompet kurang Rp {$kurang}. Silakan top up terlebih dahulu sebelum menyetujui estimasi.");
        }

        $this->verifyPinAction->execute($customer, $pin);

        $key = $idempotencyKey ?? ('estimate_hold_'.$estimate->uuid);

        // Tahan dana sebesar estimasi (tanpa expiry: dilepas/dicapture oleh alur servis)
        $intent = $this->paymentGateway->hold($estimate, Money::fromIdr($estimate->total), $key);

        $estimate->transitionTo(EstimateStatus::Approved);
        $estimate->update([
            'approved_at' => now(),
            'payment_intent_id' => $intent->id,
        ]);

        // Booking langsung masuk pengerjaan (lewati konfirmasi bila masih pending)
        if ($booking->isPending()) {
            $booking->transitionTo(BookingStatus::Confirmed);
        }

        if (! $booking->isInProgress()) {
            $booking->transitionTo(BookingStatus::InProgress);
        }

        // Sparepart kurang stok → pesan backorder internal, booking menunggu sparepart
        $shortages = $this->backorderParts->shortages($estimate);

        if ($shortages !== []) {
            $this->backorderParts->execute($estimate, $shortages);
            $booking->transitionTo(BookingStatus::WaitingParts);
        }

        return $estimate->fresh(['booking', 'paymentIntents']);
    }
}
