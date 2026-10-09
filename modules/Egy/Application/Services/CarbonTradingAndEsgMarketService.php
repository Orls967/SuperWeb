<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Egy\Domain\Models\CarbonCreditOrder;
use Modules\Egy\Domain\Models\CbamCertificate;
use Modules\Egy\Domain\Models\GreenLeaseDiscount;
use Modules\Egy\Domain\Models\RenewableEnergyCertificate;
use RuntimeException;

class CarbonTradingAndEsgMarketService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function tradeCarbonCredits(array $params): CarbonCreditOrder
    {
        $tons = (float) $params['carbon_credits_tons'];
        $pricePerTon = (int) $params['price_per_ton_minor'];
        $totalValue = (int) round($tons * $pricePerTon);

        $orderCode = 'CARB-TRD-'.strtoupper(Str::random(8));

        return DB::transaction(function () use ($params, $tons, $pricePerTon, $totalValue, $orderCode) {
            $tx = $this->ledgerService->post(new PostingDTO(
                type: 'ENERGY_CARBON_MARKET_TRADE',
                description: "Carbon credit order {$orderCode} from {$params['seller_entity_id']} to {$params['buyer_entity_id']}",
                idempotencyKey: 'EGY-CRB-'.$orderCode,
                entries: [
                    PostingEntryDTO::forCode('egy:carbon_credit_inventory:IDR', 'IDR', $totalValue),
                    PostingEntryDTO::forCode('egy:carbon_credit_sales:IDR', 'IDR', -$totalValue),
                ],
                referenceType: 'CARBON_ORDER',
                referenceId: $orderCode,
            ));

            return CarbonCreditOrder::create([
                'id' => (string) Str::uuid(),
                'order_code' => $orderCode,
                'seller_entity_id' => $params['seller_entity_id'],
                'buyer_entity_id' => $params['buyer_entity_id'],
                'vintage_year' => $params['vintage_year'],
                'carbon_credits_tons' => $tons,
                'price_per_ton_minor' => $pricePerTon,
                'total_value_minor' => $totalValue,
                'status' => 'SETTLED',
                'ledger_transaction_id' => $tx->id,
            ]);
        });
    }

    public function issueRenewableEnergyCertificate(string $generationAssetId, string $ownerEntityId, float $mwh): RenewableEnergyCertificate
    {
        return RenewableEnergyCertificate::create([
            'id' => (string) Str::uuid(),
            'certificate_serial' => 'REC-'.date('Y').'-'.strtoupper(Str::random(10)),
            'generation_asset_id' => $generationAssetId,
            'owner_entity_id' => $ownerEntityId,
            'energy_mwh' => $mwh,
            'is_retired' => false,
        ]);
    }

    public function retireRenewableEnergyCertificate(string $certificateId, string $propertyId): RenewableEnergyCertificate
    {
        $rec = RenewableEnergyCertificate::findOrFail($certificateId);

        if ($rec->is_retired) {
            throw new RuntimeException("REC {$rec->certificate_serial} is already retired by {$rec->retired_by_property_id}. Double-counting prevented.");
        }

        $rec->update([
            'is_retired' => true,
            'retired_by_property_id' => $propertyId,
            'retired_at' => Carbon::now(),
        ]);

        return $rec;
    }

    public function issueCbamCertificate(array $params): CbamCertificate
    {
        $emissionsTons = (float) $params['embedded_emissions_tons_co2'];
        $pricePerTon = (int) $params['cbam_price_per_ton_minor'];
        $totalFee = (int) round($emissionsTons * $pricePerTon);

        $certNum = 'CBAM-'.date('Y').'-'.strtoupper(Str::random(8));

        return DB::transaction(function () use ($params, $emissionsTons, $pricePerTon, $totalFee, $certNum) {
            $tx = $this->ledgerService->post(new PostingDTO(
                type: 'ENERGY_CBAM_TARIFF_SETTLEMENT',
                description: "Cross-border carbon adjustment fee for container {$params['container_id']}",
                idempotencyKey: 'EGY-CBAM-'.$certNum,
                entries: [
                    PostingEntryDTO::forCode('egy:cbam_receivable:IDR', 'IDR', $totalFee),
                    PostingEntryDTO::forCode('egy:cbam_revenue:IDR', 'IDR', -$totalFee),
                ],
                referenceType: 'CBAM_CERTIFICATE',
                referenceId: $certNum,
            ));

            return CbamCertificate::create([
                'id' => (string) Str::uuid(),
                'cbam_certificate_number' => $certNum,
                'exporter_entity_id' => $params['exporter_entity_id'],
                'container_id' => $params['container_id'],
                'embedded_emissions_tons_co2' => $emissionsTons,
                'cbam_price_per_ton_minor' => $pricePerTon,
                'total_cbam_fee_minor' => $totalFee,
                'billed_to_party_id' => $params['billed_to_party_id'],
                'ledger_transaction_id' => $tx->id,
            ]);
        });
    }

    public function applyGreenLeaseDiscount(string $tenantPropertyId, int $esgScore, int $grossUtilityChargeMinor): GreenLeaseDiscount
    {
        // ESG score threshold: >= 80 qualifies for 10% discount, >= 65 for 5%, else 0%
        $discountPct = match (true) {
            $esgScore >= 80 => 10.0,
            $esgScore >= 65 => 5.0,
            default => 0.0,
        };

        $discountAmount = (int) round($grossUtilityChargeMinor * ($discountPct / 100.0));
        $netCharge = $grossUtilityChargeMinor - $discountAmount;
        $discountCode = 'GRN-DSC-'.strtoupper(Str::random(8));

        return DB::transaction(function () use ($tenantPropertyId, $esgScore, $grossUtilityChargeMinor, $discountPct, $discountAmount, $netCharge, $discountCode) {
            $tx = null;
            if ($discountAmount > 0) {
                $tx = $this->ledgerService->post(new PostingDTO(
                    type: 'ENERGY_GREEN_LEASE_DISCOUNT_ACCRUAL',
                    description: "Green lease ESG discount for tenant {$tenantPropertyId}",
                    idempotencyKey: 'EGY-DSC-'.$discountCode,
                    entries: [
                        PostingEntryDTO::forCode('egy:green_incentive_contra_revenue:IDR', 'IDR', $discountAmount),
                        PostingEntryDTO::forCode('egy:utility_receivable:IDR', 'IDR', -$discountAmount),
                    ],
                    referenceType: 'GREEN_LEASE_DISCOUNT',
                    referenceId: $discountCode,
                ));
            }

            return GreenLeaseDiscount::create([
                'id' => (string) Str::uuid(),
                'discount_code' => $discountCode,
                'tenant_property_id' => $tenantPropertyId,
                'esg_score' => $esgScore,
                'discount_rate_pct' => $discountPct,
                'gross_utility_charge_minor' => $grossUtilityChargeMinor,
                'green_discount_amount_minor' => $discountAmount,
                'net_utility_charge_minor' => $netCharge,
                'ledger_transaction_id' => $tx?->id,
            ]);
        });
    }
}
