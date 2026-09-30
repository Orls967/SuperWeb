<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Exceptions\CreditLimitExceededException;
use Modules\Logistics\Domain\Exceptions\InvalidQuoteException;
use Modules\Logistics\Domain\Models\Package;
use Modules\Logistics\Domain\Models\Quote;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipperAccount;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;

class BookPostpaidShipmentAction
{
    /**
     * Book a postpaid shipment subject to B2B credit limit verification.
     *
     * @param  array{street: string, city: string, postal_code?: string, province?: string}  $consigneeAddress
     */
    public function execute(
        User $shipper,
        Quote $quote,
        string $consigneeName,
        string $consigneePhone,
        array $consigneeAddress
    ): Shipment {
        // 1. Verify B2B Account and Credit Limit
        $account = ShipperAccount::where('shipper_id', $shipper->id)
            ->where('is_active', true)
            ->first();

        if (! $account) {
            throw new DomainException("Pengirim #{$shipper->id} belum memiliki akun B2B pascabayar (postpaid) yang aktif.");
        }

        $outstanding = $account->calculateOutstandingBalance();
        $newAmount = $quote->total_amount_idr;

        if (! $account->canAccommodate($newAmount)) {
            throw CreditLimitExceededException::forShipper(
                $shipper->id,
                $outstanding,
                $newAmount,
                $account->credit_limit_idr
            );
        }

        // 2. Validate quote
        if ($quote->shipper_id !== $shipper->id) {
            throw InvalidQuoteException::tampered();
        }

        if ($quote->is_booked) {
            throw InvalidQuoteException::alreadyBooked();
        }

        if ($quote->isExpired()) {
            throw InvalidQuoteException::expired();
        }

        if (! $quote->verifyHash()) {
            throw InvalidQuoteException::tampered();
        }

        // 3. Create Shipment and Packages
        return DB::transaction(function () use (
            $shipper,
            $quote,
            $consigneeName,
            $consigneePhone,
            $consigneeAddress,
            $newAmount
        ) {
            $trackingNumber = TrackingNumber::generate();
            $chargeableWeightGrams = (int) round(((float) $quote->chargeable_weight_kg) * 1000);

            $shipment = Shipment::create([
                'tracking_number' => $trackingNumber,
                'shipper_id' => $shipper->id,
                'consignee_name' => $consigneeName,
                'consignee_phone' => $consigneePhone,
                'consignee_address' => $consigneeAddress,
                'origin_location_id' => $quote->origin_location_id,
                'destination_location_id' => $quote->destination_location_id,
                'service_level' => $quote->service_level,
                'mode' => $quote->mode,
                'payment_terms' => PaymentTerms::Postpaid,
                'status' => ShipmentStatus::Booked,
                'total_chargeable_weight_g' => $chargeableWeightGrams,
                'total_amount_idr' => $newAmount,
                'quote_id' => $quote->id,
                'booked_at' => now(),
            ]);

            $packagesPayload = $quote->packages_payload ?? [];
            foreach ($packagesPayload as $pkg) {
                Package::create([
                    'shipment_id' => $shipment->id,
                    'weight_g' => $pkg['weight_g'] ?? 0,
                    'length_mm' => $pkg['length_mm'] ?? 0,
                    'width_mm' => $pkg['width_mm'] ?? 0,
                    'height_mm' => $pkg['height_mm'] ?? 0,
                    'description' => $pkg['description'] ?? 'Barang Kiriman B2B',
                    'hs_code' => $pkg['hs_code'] ?? null,
                    'dg_un_number' => $pkg['dg_un_number'] ?? null,
                    'dg_class' => $pkg['dg_class'] ?? null,
                    'temp_min_c10' => $pkg['temp_min_c10'] ?? null,
                    'temp_max_c10' => $pkg['temp_max_c10'] ?? null,
                ]);
            }

            $quote->is_booked = true;
            $quote->save();

            return $shipment;
        });
    }
}
