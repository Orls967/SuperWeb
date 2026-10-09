<?php

declare(strict_types=1);

namespace Modules\AutoServe\Application\Actions;

use App\Models\User;
use Exception;
use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Enums\EstimateStatus;
use Modules\AutoServe\Domain\Models\Booking;
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

        return $this->transaction(function () use ($booking, $estimate, $key) {
            // Urutan lock konsisten dengan ApproveExtraChargeAction: booking → estimasi.
            /** @var Booking $lockedBooking */
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            /** @var Estimate $lockedEstimate */
            $lockedEstimate = Estimate::query()->lockForUpdate()->findOrFail($estimate->id);

            if ($lockedEstimate->status !== EstimateStatus::Sent) {
                throw new Exception("Estimasi berstatus {$lockedEstimate->status->label()} tidak dapat disetujui.");
            }

            // Gateway menahan dana (menyimpan intent + posting ledger) dalam
            // savepoint tersendiri; key deterministik membuat retry idempoten.
            $intent = $this->paymentGateway->hold($lockedEstimate, Money::fromIdr($lockedEstimate->total), $key);

            $lockedEstimate->transitionTo(EstimateStatus::Approved);
            $lockedEstimate->update([
                'approved_at' => now(),
                'payment_intent_id' => $intent->id,
            ]);

            // Booking langsung masuk pengerjaan (lewati konfirmasi bila masih pending)
            if ($lockedBooking->isPending()) {
                $lockedBooking->transitionTo(BookingStatus::Confirmed);
            }

            if (! $lockedBooking->isInProgress() && ! $lockedBooking->isWaitingParts()) {
                $lockedBooking->transitionTo(BookingStatus::InProgress);
            }

            // Sparepart kurang stok → pesan backorder internal, booking menunggu sparepart
            $shortages = $this->backorderParts->shortages($lockedEstimate);

            if ($shortages !== []) {
                $this->backorderParts->execute($lockedEstimate, $shortages);
                $lockedBooking->transitionTo(BookingStatus::WaitingParts);
            }

            return $lockedEstimate->fresh(['booking', 'paymentIntents']);
        });
    }
}
