<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Enums\WarehouseReceiptStatus;
use Modules\Mining\Domain\Models\MiningMetalsWarehouseReceipt;
use Modules\Mining\Domain\Models\MiningSmelterQualityAssay;
use Modules\Shared\Domain\Algorithms\MetalsTrading\WarehouseReceiptDigitalCollateralHaircutModel;
use Modules\Shared\Domain\Algorithms\MetalsTrading\WarehouseReceiptDigitalCollateralHaircutVerifier;

final class IssueMetalsWarehouseReceiptAction
{
    public function __construct(
        private readonly WarehouseReceiptDigitalCollateralHaircutModel $haircutModel,
        private readonly WarehouseReceiptDigitalCollateralHaircutVerifier $haircutVerifier
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(
        MiningSmelterQualityAssay $assay,
        float $certifiedWeightTons,
        array $attributes = []
    ): MiningMetalsWarehouseReceipt {
        if ($certifiedWeightTons <= 0.0) {
            throw new InvalidArgumentException('Berat tersertifikasi (certifiedWeightTons) harus lebih besar dari 0.');
        }

        $receiptNumber = $attributes['receipt_number'] ?? ('SRG-NPI-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -6)));
        $unitPriceIdr = (int) ($attributes['unit_market_price_idr_per_ton'] ?? 245000000);

        // Evaluasi Haircut Kolateral M12
        $haircutResult = $this->haircutModel->calculateHaircut(
            metalCommodity: $assay->metal_commodity,
            certifiedWeightMetricTons: $certifiedWeightTons,
            unitMarketPriceIdrPerTon: $unitPriceIdr,
            historicalVolatility30DayPct: 18.0,
            storageAgeDays: 5,
            assayVarianceTolerancePct: 0.2,
            bankLtvCapPct: 80.0
        );

        $this->haircutVerifier->verify($haircutResult);

        return MiningMetalsWarehouseReceipt::create([
            'assay_id' => $assay->id,
            'receipt_number' => $receiptNumber,
            'warehouse_code' => $attributes['warehouse_code'] ?? 'WMS-BAPPEBTI-01',
            'warehouse_operator_name' => $attributes['warehouse_operator_name'] ?? 'PT Kliring Berjangka Indonesia Logistik',
            'metal_commodity' => $assay->metal_commodity,
            'certified_weight_metric_tons' => $certifiedWeightTons,
            'bundle_or_ingot_count' => (int) round($certifiedWeightTons * 25),
            'storage_location_rack' => $attributes['storage_location_rack'] ?? 'ZONE-A-RACK-05',
            'estimated_market_value_idr' => $haircutResult->grossCollateralValueIdr,
            'haircut_percentage' => $haircutResult->totalHaircutPct,
            'max_financing_limit_idr' => $haircutResult->maxFinancingCreditLimitIdr,
            'pledged_bank_name' => $attributes['pledged_bank_name'] ?? null,
            'scf_facility_reference' => $attributes['scf_facility_reference'] ?? null,
            'issued_at' => now()->toDateString(),
            'expires_at' => now()->addMonths(6)->toDateString(),
            'digital_seal_hash' => $haircutResult->digitalSealSignature,
            'hmac_signature' => hash('sha256', "SRG:{$receiptNumber}:{$haircutResult->maxFinancingCreditLimitIdr}"),
            'status' => WarehouseReceiptStatus::ISSUED->value,
            'metadata' => [
                'haircut_breakdown' => $haircutResult->riskFactorBreakdown,
                'ltv_percentage' => $haircutResult->effectiveLtvPct,
            ],
        ]);
    }
}
