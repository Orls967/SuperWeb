<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Exceptions\ClaimException;
use Modules\Logistics\Domain\Models\Claim;
use Modules\Logistics\Domain\Models\Shipment;

class CreateClaimAction
{
    /** Status resi yang layak per jenis klaim. */
    private const ELIGIBLE = [
        'damage' => [ShipmentStatus::Delivered, ShipmentStatus::Exception, ShipmentStatus::Returned],
        'loss' => [ShipmentStatus::Lost],
        'delay' => [ShipmentStatus::Delivered],
    ];

    /**
     * Alur 11 (langkah 1): pembuat membuat draft klaim. Pembuat adalah pemilik resi (shipper) atau staf.
     */
    public function execute(User $creator, Shipment $shipment, string $type, int $claimedAmountIdr, string $description): Claim
    {
        $isStaff = $creator->isAdmin() || $creator->isLogisticsAdmin() || $creator->isDispatcher();
        if (! $isStaff && $creator->id !== $shipment->shipper_id) {
            throw ClaimException::forbidden('membuat klaim untuk resi ini');
        }

        if (! in_array($type, Claim::TYPES, true)) {
            throw new ClaimException('Jenis klaim tidak dikenal.');
        }

        if (! in_array($shipment->status, self::ELIGIBLE[$type], true)) {
            throw ClaimException::ineligibleShipment($shipment->tracking_number, $type, $shipment->status->label());
        }

        $window = (int) config('logistics.claim_window_days', 14);
        $eventAt = $shipment->delivered_at ?? $shipment->updated_at;
        if ($eventAt && $eventAt->copy()->addDays($window)->isPast()) {
            throw ClaimException::windowExpired($window);
        }

        $cap = $this->capFor($shipment, $type);
        if ($claimedAmountIdr <= 0 || $claimedAmountIdr > $cap) {
            throw ClaimException::exceedsCap($claimedAmountIdr, $cap);
        }

        try {
            return DB::transaction(function () use ($creator, $shipment, $type, $claimedAmountIdr, $description, $cap) {
                $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();

                if (Claim::where('shipment_id', $shipment->id)->whereNotNull('active_key')->exists()) {
                    throw ClaimException::duplicate($shipment->tracking_number);
                }

                $claim = Claim::create([
                    'claim_number' => 'TMP',
                    'shipment_id' => $shipment->id,
                    'claim_type' => $type,
                    'insured' => (bool) $shipment->insured,
                    'claimed_amount_idr' => $claimedAmountIdr,
                    'cap_amount_idr' => $cap,
                    'description' => mb_substr($description, 0, 1000),
                    'status' => Claim::STATUS_DRAFT,
                    'active_key' => 'shipment:'.$shipment->id,
                    'created_by' => $creator->id,
                ]);

                $claim->update(['claim_number' => sprintf('CLM-%s-%05d', now()->format('Ym'), $claim->id)]);

                return $claim;
            });
        } catch (UniqueConstraintViolationException) {
            throw ClaimException::duplicate($shipment->tracking_number);
        }
    }

    public function capFor(Shipment $shipment, string $type): int
    {
        $freight = (int) $shipment->total_amount_idr;
        $declared = (int) $shipment->declared_value_idr;

        if ($type === 'delay') {
            return $freight;
        }

        if ($shipment->insured) {
            if ($declared <= 0) {
                throw ClaimException::noBasis();
            }

            return $declared;
        }

        $cap = $freight * (int) config('logistics.claim_uninsured_multiplier', 10);

        return $declared > 0 ? min($cap, $declared) : $cap;
    }
}
