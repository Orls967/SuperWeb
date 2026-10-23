<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\CarbonCreditIssuance;
use Modules\Mining\Domain\Models\GreenMineralSale;
use Modules\Mining\Domain\Models\RenewableEnergyBilling;
use Modules\Shared\Application\BaseAction;

class DispatchMiningSolarHybridPowerAndCarbonOffsetAction extends BaseAction
{
    /**
     * Dispatch mining solar hybrid energy, bill intercompany clean power, issue carbon credits, and trace green mineral premium.
     *
     * @return array{renewable_billing: RenewableEnergyBilling, carbon_issuance: CarbonCreditIssuance, green_sale: GreenMineralSale, solar_ratio_pct: float, scope1_avoided_tons: float}
     */
    public function handle(
        string $siteId,
        float $solarGenerationKwh = 150000.0,
        float $dieselConsumptionKwh = 250000.0,
        int $transferRatePerKwhIdr = 1450,
        string $consumerEntityId = 'PLANT-SMELTER-01',
        float $reforestedAreaHectares = 250.0,
        float $verifiedNdviScore = 0.78,
        float $greenNickelTonnage = 500.0,
        int $greenPremiumAdderPerTonIdr = 15000000
    ): array {
        return DB::transaction(function () use (
            $siteId,
            $solarGenerationKwh,
            $dieselConsumptionKwh,
            $transferRatePerKwhIdr,
            $consumerEntityId,
            $reforestedAreaHectares,
            $verifiedNdviScore,
            $greenNickelTonnage,
            $greenPremiumAdderPerTonIdr
        ) {
            // 123.1 Hybrid energy dispatch & Scope 1 Decarbonization
            $totalEnergyKwh = $solarGenerationKwh + $dieselConsumptionKwh;
            $solarRatioPct = ($totalEnergyKwh > 0) ? ($solarGenerationKwh / $totalEnergyKwh) * 100.0 : 0.0;
            $scope1AvoidedTonsCo2 = round($solarGenerationKwh * 0.00085, 2);

            // 123.2 Intercompany renewable energy billing
            $totalBillingIdr = (int) round($solarGenerationKwh * $transferRatePerKwhIdr);
            $billCount = RenewableEnergyBilling::count() + 1;
            $billingNumber = sprintf('REB-%06d', $billCount);

            $billing = RenewableEnergyBilling::create([
                'id' => (string) Str::uuid(),
                'billing_number' => $billingNumber,
                'producer_site_id' => $siteId,
                'consumer_entity_id' => $consumerEntityId,
                'energy_kwh_delivered' => $solarGenerationKwh,
                'rate_per_kwh_minor' => $transferRatePerKwhIdr,
                'total_amount_minor' => $totalBillingIdr,
                'scope1_avoided_tons_co2' => $scope1AvoidedTonsCo2,
                'ledger_transaction_id' => 'TX-REB-'.strtoupper(bin2hex(random_bytes(4))),
            ]);

            // 123.3 Carbon project credit issuance (ARR Reforestation)
            $issuedCreditsTons = round($reforestedAreaHectares * $verifiedNdviScore * 10.0, 2);
            $projCount = CarbonCreditIssuance::count() + 1;
            $projectCode = sprintf('CAR-%06d', $projCount);

            $carbonIssuance = CarbonCreditIssuance::create([
                'id' => (string) Str::uuid(),
                'project_code' => $projectCode,
                'site_id' => $siteId,
                'project_type' => 'ARR_REFORESTATION',
                'verified_ndvi_score' => $verifiedNdviScore,
                'verified_carbon_tons' => $issuedCreditsTons,
                'issued_credits_tons' => $issuedCreditsTons,
                'registry_serial_number' => 'VCS-ID-'.strtoupper(bin2hex(random_bytes(5))),
                'status' => 'ISSUED',
            ]);

            // 123.5 Green mineral premium traceability
            $greenSaleCount = GreenMineralSale::count() + 1;
            $contractNumber = sprintf('GMS-%06d', $greenSaleCount);
            $totalSettledMinor = (int) round($greenNickelTonnage * $greenPremiumAdderPerTonIdr);

            $greenSale = GreenMineralSale::create([
                'id' => (string) Str::uuid(),
                'contract_number' => $contractNumber,
                'buyer_party_id' => 'BUYER-EV-BATTERY-01',
                'commodity' => 'BATTERY_GRADE_NICKEL',
                'tonnage' => $greenNickelTonnage,
                'dpp_passport_hash' => hash('sha256', "DPP-{$contractNumber}-".date('YmdHis')),
                'base_price_minor' => 250000000,
                'green_premium_adder_minor' => $greenPremiumAdderPerTonIdr,
                'total_settled_minor' => $totalSettledMinor,
                'ledger_transaction_id' => 'TX-GMS-'.strtoupper(bin2hex(random_bytes(4))),
            ]);

            $this->audit(
                action: 'mining.renewable_carbon.dispatched',
                auditable: $billing,
                oldValues: null,
                newValues: [
                    'billing_number' => $billingNumber,
                    'solar_ratio_pct' => round($solarRatioPct, 2),
                    'scope1_avoided_tons' => $scope1AvoidedTonsCo2,
                    'carbon_project' => $projectCode,
                    'issued_credits_tons' => $issuedCreditsTons,
                    'green_contract' => $contractNumber,
                ],
                correlationId: "min_reb_{$billingNumber}",
                impactType: 'decarbonization_carbon_offset'
            );

            return [
                'renewable_billing' => $billing,
                'carbon_issuance' => $carbonIssuance,
                'green_sale' => $greenSale,
                'solar_ratio_pct' => round($solarRatioPct, 2),
                'scope1_avoided_tons' => $scope1AvoidedTonsCo2,
            ];
        });
    }
}
