<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Contracts\VerifiesWalletPin;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Exceptions\InvalidQuoteException;
use Modules\Logistics\Domain\Models\Package;
use Modules\Logistics\Domain\Models\Quote;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;
use Modules\Payment\Contracts\PaymentGateway;

class BookShipmentAction
{
    public function __construct(
        private readonly PaymentGateway $paymentGateway,
        private readonly VerifiesWalletPin $pinVerifier
    ) {}

    /**
     * Book a shipment from a valid quote with mandatory wallet PIN verification.
     *
     * @param  array{street: string, city: string, postal_code?: string, province?: string}  $consigneeAddress
     */
    public function execute(
        User $shipper,
        Quote $quote,
        string $consigneeName,
        string $consigneePhone,
        array $consigneeAddress,
        string $pin,
        ?string $idempotencyKey = null
    ): Shipment {
        // 1. Mandatory PIN verification
        $this->pinVerifier->execute($shipper, $pin);

        // 2. Quote validation
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

        // 3. Ensure system unearned freight ledger account exists
        LedgerAccount::firstOrCreate(
            ['code' => 'lgx:unearned_freight', 'asset_code' => 'IDR'],
            [
                'name' => 'Pendapatan Diterima di Muka (Unearned Freight)',
                'kind' => AccountKind::LIABILITY->value,
                'allow_negative' => true,
            ]
        );

        // 4. Create Shipment and Charge via Database Transaction
        return DB::transaction(function () use (
            $shipper,
            $quote,
            $consigneeName,
            $consigneePhone,
            $consigneeAddress,
            $idempotencyKey
        ) {
            /** @var Quote $lockedQuote */
            $lockedQuote = Quote::query()->lockForUpdate()->findOrFail($quote->getKey());

            if ($lockedQuote->is_booked) {
                throw InvalidQuoteException::alreadyBooked();
            }

            $quote = $lockedQuote;
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
                'payment_terms' => PaymentTerms::Prepaid,
                'status' => ShipmentStatus::Draft,
                'total_chargeable_weight_g' => $chargeableWeightGrams,
                'total_amount_idr' => $quote->total_amount_idr,
                'declared_value_idr' => $quote->declared_value_idr,
                'insured' => $quote->insured,
                'cod_amount_idr' => $quote->cod_amount_idr,
                'quote_id' => $quote->id,
            ]);

            // Create packages
            $packagesPayload = $quote->packages_payload ?? [];
            foreach ($packagesPayload as $pkg) {
                Package::create([
                    'shipment_id' => $shipment->id,
                    'weight_g' => $pkg['weight_g'] ?? 0,
                    'length_mm' => $pkg['length_mm'] ?? 0,
                    'width_mm' => $pkg['width_mm'] ?? 0,
                    'height_mm' => $pkg['height_mm'] ?? 0,
                    'description' => $pkg['description'] ?? 'Barang Kiriman',
                    'hs_code' => $pkg['hs_code'] ?? null,
                    'dg_un_number' => $pkg['dg_un_number'] ?? null,
                    'dg_class' => $pkg['dg_class'] ?? null,
                    'temp_min_c10' => $pkg['temp_min_c10'] ?? null,
                    'temp_max_c10' => $pkg['temp_max_c10'] ?? null,
                ]);
            }

            // Charge payment via gateway (if balance insufficient, throws InsufficientFundsException and rolls back)
            $payKey = $idempotencyKey ?: "lgx:book:{$quote->uuid}";
            $this->paymentGateway->charge($shipment, $payKey);

            // Mark quote as booked
            $quote->is_booked = true;
            $quote->save();

            $shipment->refresh();

            return $shipment;
        });
    }
}
