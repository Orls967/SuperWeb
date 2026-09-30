<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Exceptions\CannotCancelPickedUpShipmentException;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Payment\Domain\Enums\PaymentIntentStatus;
use Modules\Payment\Domain\Models\PaymentIntent;

class CancelShipmentAction
{
    public function __construct(
        private readonly Ledger $ledger
    ) {}

    /**
     * Cancel a shipment before it is picked up, processing refund minus cancellation fee.
     */
    public function execute(
        User $user,
        Shipment $shipment,
        ?string $reason = null,
        ?string $idempotencyKey = null
    ): Shipment {
        // 1. Authorization check
        $canCancel = $user->isAdmin()
            || $user->isLogisticsAdmin()
            || $user->isDispatcher()
            || ($user->id === $shipment->shipper_id);

        if (! $canCancel) {
            throw new DomainException('Anda tidak memiliki wewenang untuk membatalkan pengiriman ini.');
        }

        // 2. Status verification
        if ($shipment->status === ShipmentStatus::Cancelled) {
            return $shipment; // Idempotent
        }

        if (! in_array($shipment->status, [ShipmentStatus::Draft, ShipmentStatus::Booked], true)) {
            throw CannotCancelPickedUpShipmentException::forStatus($shipment->status, $shipment->tracking_number);
        }

        return DB::transaction(function () use ($user, $shipment, $reason, $idempotencyKey) {
            // 3. Process refund if prepaid and booked
            if ($shipment->payment_terms === PaymentTerms::Prepaid && $shipment->status === ShipmentStatus::Booked && $shipment->total_amount_idr > 0) {
                $configuredFee = (int) config('logistics.cancellation_fee_idr', 25_000);
                $cancellationFee = min($shipment->total_amount_idr, $configuredFee);
                $refundAmount = $shipment->total_amount_idr - $cancellationFee;

                $shipper = $shipment->shipper;
                $shipperWallet = $shipper->walletAccount('IDR');

                // Ensure ledger accounts exist
                LedgerAccount::firstOrCreate(
                    ['code' => 'lgx:unearned_freight', 'asset_code' => 'IDR'],
                    [
                        'name' => 'Pendapatan Diterima di Muka (Unearned Freight)',
                        'kind' => AccountKind::LIABILITY->value,
                        'allow_negative' => true,
                    ]
                );

                LedgerAccount::firstOrCreate(
                    ['code' => 'lgx:freight_revenue', 'asset_code' => 'IDR'],
                    [
                        'name' => 'Pendapatan Freight & Logistik',
                        'kind' => AccountKind::REVENUE->value,
                        'allow_negative' => true,
                    ]
                );

                // Build double-entry postings
                // 1) Clear full unearned freight liability: debit lgx:unearned_freight (-amount)
                $entries = [
                    PostingEntryDTO::forCode(
                        'lgx:unearned_freight',
                        'IDR',
                        BigDecimal::of($shipment->total_amount_idr)->negated()
                    ),
                ];

                // 2) Credit refund amount to shipper wallet
                if ($refundAmount > 0) {
                    $entries[] = PostingEntryDTO::forAccount(
                        $shipperWallet->id,
                        'IDR',
                        BigDecimal::of($refundAmount)
                    );
                }

                // 3) Credit cancellation fee to freight_revenue
                if ($cancellationFee > 0) {
                    $entries[] = PostingEntryDTO::forCode(
                        'lgx:freight_revenue',
                        'IDR',
                        BigDecimal::of($cancellationFee)
                    );
                }

                $txKey = $idempotencyKey ?: "lgx:cancel:{$shipment->id}";
                $dto = new PostingDTO(
                    type: TransactionType::REFUND->value,
                    description: "Pembatalan pengiriman #{$shipment->tracking_number} (biaya batal: Rp ".number_format($cancellationFee, 0, ',', '.').')'.($reason ? ": {$reason}" : ''),
                    idempotencyKey: $txKey,
                    entries: $entries,
                    referenceType: Shipment::class,
                    referenceId: $shipment->id,
                    createdBy: $user->id,
                    postedAt: now(),
                );

                $this->ledger->post($dto);

                $shipment->cancellation_fee_idr = $cancellationFee;

                // Mark any associated payment intent as refunded
                $intent = PaymentIntent::where('payable_type', $shipment->getMorphClass())
                    ->where('payable_id', $shipment->id)
                    ->where('status', PaymentIntentStatus::CAPTURED->value)
                    ->first();

                if ($intent) {
                    $intent->transitionTo(PaymentIntentStatus::REFUNDED);
                }
            }

            // 4. Update shipment status
            $shipment->transitionTo(ShipmentStatus::Cancelled);
            $shipment->cancelled_at = now();
            $shipment->save();

            return $shipment->fresh();
        });
    }
}
