<?php

declare(strict_types=1);

namespace Modules\AutoServe\Application\Actions;

use App\Models\User;
use Exception;
use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\AutoServe\Domain\Models\Estimate;
use Modules\Banking\Contracts\VerifiesWalletPin;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\Domain\Enums\PaymentIntentStatus;
use Modules\Shared\Application\BaseAction;

/**
 * Biaya aktual melebihi estimasi yang ditahan: customer menyetujui dan membayar
 * selisihnya, lalu servis diselesaikan dan escrow dicairkan.
 */
class ApproveExtraChargeAction extends BaseAction
{
    public function __construct(
        private readonly PaymentGateway $paymentGateway,
        private readonly VerifiesWalletPin $verifyPinAction,
        private readonly CompleteBookingAction $completeBooking,
    ) {}

    public function execute(Booking $booking, User $customer, string $pin): Booking
    {
        return $this->transaction(function () use ($booking, $customer, $pin) {
            // Urutan lock konsisten dengan ApproveEstimateAction: booking → estimasi.
            /** @var Booking $lockedBooking */
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($lockedBooking->status !== BookingStatus::AwaitingExtraApproval->value) {
                throw new Exception('Booking ini tidak sedang menunggu persetujuan biaya tambahan.');
            }

            if ((int) $lockedBooking->customer_id !== (int) $customer->id && ! $customer->isAdmin()) {
                throw new Exception('Hanya pemilik booking yang dapat menyetujui biaya tambahan.');
            }

            $approvedEstimate = $lockedBooking->approvedEstimate();
            if ($approvedEstimate === null) {
                throw new Exception('Estimasi yang disetujui tidak ditemukan untuk booking ini.');
            }

            /** @var Estimate $lockedEstimate */
            $lockedEstimate = Estimate::query()->lockForUpdate()->findOrFail($approvedEstimate->id);

            $heldIntent = $lockedEstimate->paymentIntents()
                ->where('status', PaymentIntentStatus::HELD->value)
                ->latest()
                ->first();

            if ($heldIntent === null) {
                throw new Exception('Dana escrow estimasi tidak ditemukan.');
            }

            $heldAmount = (int) $heldIntent->amount;
            $serviceCost = (int) $lockedBooking->service_cost;
            $sparepartCost = (int) $lockedBooking->sparepart_cost;
            $finalTotal = $serviceCost + $sparepartCost;
            $extra = $finalTotal - $heldAmount;

            if ($extra <= 0) {
                throw new Exception('Tidak ada selisih biaya yang perlu dibayar.');
            }

            $walletBalance = (int) $customer->walletAccount('IDR')->cached_balance;
            if ($walletBalance < $extra) {
                $kurang = number_format($extra - $walletBalance, 0, ',', '.');
                throw new Exception("Saldo dompet kurang Rp {$kurang} untuk membayar selisih biaya.");
            }

            $this->verifyPinAction->execute($customer, $pin);

            // Bagian escrow memakai rasio biaya akhir; sisanya menjadi tagihan selisih
            $captureService = intdiv($heldAmount * $serviceCost, $finalTotal);
            $captureParts = $heldAmount - $captureService;
            $extraService = $serviceCost - $captureService;
            $extraParts = $sparepartCost - $captureParts;

            $lockedEstimate->update([
                'final_service_total' => $extraService,
                'final_parts_total' => $extraParts,
                'extra_amount' => $extra,
            ]);

            // Gateway menagih selisih (idempoten per estimasi) di savepoint sendiri.
            $extraIntent = $this->paymentGateway->charge(
                $lockedEstimate->fresh(),
                'estimate_extra_'.$lockedEstimate->uuid
            );

            $lockedEstimate->update(['extra_charge_intent_id' => $extraIntent->id]);
            $lockedBooking->transitionTo(BookingStatus::InProgress);

            return $this->completeBooking->handle($lockedBooking->fresh());
        });
    }
}
