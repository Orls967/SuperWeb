<?php

declare(strict_types=1);

namespace Modules\AutoServe\Application\Actions;

use App\Models\User;
use Exception;
use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Models\Booking;
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
        if ($booking->status !== BookingStatus::AwaitingExtraApproval->value) {
            throw new Exception('Booking ini tidak sedang menunggu persetujuan biaya tambahan.');
        }

        if ((int) $booking->customer_id !== (int) $customer->id && ! $customer->isAdmin()) {
            throw new Exception('Hanya pemilik booking yang dapat menyetujui biaya tambahan.');
        }

        $estimate = $booking->approvedEstimate();
        if ($estimate === null) {
            throw new Exception('Estimasi yang disetujui tidak ditemukan untuk booking ini.');
        }

        $heldIntent = $estimate->paymentIntents()
            ->where('status', PaymentIntentStatus::HELD->value)
            ->latest()
            ->first();

        if ($heldIntent === null) {
            throw new Exception('Dana escrow estimasi tidak ditemukan.');
        }

        $heldAmount = (int) $heldIntent->amount;
        $serviceCost = (int) $booking->service_cost;
        $sparepartCost = (int) $booking->sparepart_cost;
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

        $estimate->update([
            'final_service_total' => $extraService,
            'final_parts_total' => $extraParts,
            'extra_amount' => $extra,
        ]);

        $extraIntent = $this->paymentGateway->charge(
            $estimate->fresh(),
            'estimate_extra_'.$estimate->uuid
        );

        $estimate->update(['extra_charge_intent_id' => $extraIntent->id]);

        // Kembali ke pengerjaan lalu selesaikan: escrow dicairkan penuh
        $booking->transitionTo(BookingStatus::InProgress);

        return $this->completeBooking->handle($booking->fresh());
    }
}
