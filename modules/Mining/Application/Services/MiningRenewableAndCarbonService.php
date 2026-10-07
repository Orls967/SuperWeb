<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Mining\Domain\Models\CarbonCreditIssuance;
use Modules\Mining\Domain\Models\GreenMineralSale;
use Modules\Mining\Domain\Models\RenewableEnergyBilling;
use RuntimeException;

class MiningRenewableAndCarbonService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function billIntercompanyRenewableEnergy(array $params): RenewableEnergyBilling
    {
        $kwh = (float) $params['energy_kwh_delivered'];
        $ratePerKwh = (int) $params['rate_per_kwh_minor'];
        $totalAmount = (int) round($kwh * $ratePerKwh);

        // Grid emission factor: approx 0.82 kg CO2 per kWh avoided
        $avoidedScope1Tons = ($kwh * 0.82) / 1000.0;

        $billingNumber = 'REN-BILL-'.strtoupper(Str::random(8));

        return DB::transaction(function () use ($params, $kwh, $ratePerKwh, $totalAmount, $avoidedScope1Tons, $billingNumber) {
            $tx = $this->ledgerService->post(new PostingDTO(
                type: 'MINING_INTERCOMPANY_RENEWABLE_POWER',
                description: "Intercompany clean energy billing from {$params['producer_site_id']} to {$params['consumer_entity_id']}",
                idempotencyKey: 'MIN-REN-'.$billingNumber,
                entries: [
                    PostingEntryDTO::forCode('min:intercompany_receivable:IDR', 'IDR', $totalAmount),
                    PostingEntryDTO::forCode('min:renewable_power_revenue:IDR', 'IDR', -$totalAmount),
                ],
                referenceType: 'RENEWABLE_ENERGY_BILLING',
                referenceId: $billingNumber,
            ));

            return RenewableEnergyBilling::create([
                'id' => (string) Str::uuid(),
                'billing_number' => $billingNumber,
                'producer_site_id' => $params['producer_site_id'],
                'consumer_entity_id' => $params['consumer_entity_id'],
                'energy_kwh_delivered' => $kwh,
                'rate_per_kwh_minor' => $ratePerKwh,
                'total_amount_minor' => $totalAmount,
                'scope1_avoided_tons_co2' => round($avoidedScope1Tons, 4),
                'ledger_transaction_id' => $tx->id,
            ]);
        });
    }

    public function issueCarbonCredits(array $params): CarbonCreditIssuance
    {
        $verifiedNdvi = (float) $params['verified_ndvi_score'];
        $verifiedCarbonTons = (float) $params['verified_carbon_tons'];
        $requestedCredits = (float) $params['requested_credits_tons'];

        if ($requestedCredits > $verifiedCarbonTons) {
            throw new RuntimeException("Carbon issuance over-allocation: requested credits ({$requestedCredits}t) exceed verified carbon sequestration ({$verifiedCarbonTons}t).");
        }

        if ($verifiedNdvi < 0.40) {
            throw new RuntimeException("ARR reforestation canopy density verification failed (NDVI {$verifiedNdvi} < 0.40 threshold).");
        }

        $projectCode = $params['project_code'] ?? 'CARB-'.strtoupper(Str::random(8));
        $serial = 'REG-IDX-'.date('Y').'-'.strtoupper(Str::random(10));

        return CarbonCreditIssuance::create([
            'id' => (string) Str::uuid(),
            'project_code' => $projectCode,
            'site_id' => $params['site_id'],
            'project_type' => $params['project_type'],
            'verified_ndvi_score' => $verifiedNdvi,
            'verified_carbon_tons' => $verifiedCarbonTons,
            'issued_credits_tons' => $requestedCredits,
            'registry_serial_number' => $serial,
            'status' => 'ISSUED',
        ]);
    }

    public function executeGreenMineralSale(array $params): GreenMineralSale
    {
        $tonnage = (float) $params['tonnage'];
        $basePrice = (int) $params['base_price_minor'];
        $premiumAdder = (int) $params['green_premium_adder_minor'];
        $totalSettled = $basePrice + $premiumAdder;

        $contractNumber = $params['contract_number'] ?? 'GMIN-'.strtoupper(Str::random(8));
        $dppHash = hash('sha256', "DPP:{$contractNumber}:{$params['commodity']}:{$tonnage}");

        return DB::transaction(function () use ($params, $tonnage, $basePrice, $premiumAdder, $totalSettled, $contractNumber, $dppHash) {
            $tx = $this->ledgerService->post(new PostingDTO(
                type: 'MINING_GREEN_MINERAL_SETTLEMENT',
                description: "Green mineral settlement with DPP traceability for {$contractNumber}",
                idempotencyKey: 'MIN-GRN-'.$contractNumber,
                entries: [
                    PostingEntryDTO::forCode('min:trade_receivable:IDR', 'IDR', $totalSettled),
                    PostingEntryDTO::forCode('min:base_metal_sales_revenue:IDR', 'IDR', -$basePrice),
                    PostingEntryDTO::forCode('min:green_premium_revenue:IDR', 'IDR', -$premiumAdder),
                ],
                referenceType: 'GREEN_MINERAL_SALE',
                referenceId: $contractNumber,
            ));

            return GreenMineralSale::create([
                'id' => (string) Str::uuid(),
                'contract_number' => $contractNumber,
                'buyer_party_id' => $params['buyer_party_id'],
                'commodity' => $params['commodity'],
                'tonnage' => $tonnage,
                'dpp_passport_hash' => $dppHash,
                'base_price_minor' => $basePrice,
                'green_premium_adder_minor' => $premiumAdder,
                'total_settled_minor' => $totalSettled,
                'ledger_transaction_id' => $tx->id,
            ]);
        });
    }
}
