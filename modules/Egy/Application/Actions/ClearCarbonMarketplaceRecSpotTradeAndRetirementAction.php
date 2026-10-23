<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class ClearCarbonMarketplaceRecSpotTradeAndRetirementAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Clears internal carbon credit exchange trades, retires RECs, and computes CBAM/green discounts.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $tradeCode = (string) ($params['trade_code'] ?? 'CRB-'.hash('sha256', (string) ($params['order_id'] ?? 'default')));
            $buyerEntityId = (string) ($params['buyer_entity_id'] ?? 'ENT-HOTEL');
            $sellerEntityId = (string) ($params['seller_entity_id'] ?? 'ENT-SOLAR');

            $tonnesCo2 = (int) ($params['tonnes_co2'] ?? 0);
            $pricePerTonneMinor = (int) ($params['price_per_tonne_minor'] ?? 350000);
            $carbonTradeAmountMinor = $tonnesCo2 * $pricePerTonneMinor;

            // REC issuance & retirement
            $recMwh = (int) ($params['rec_mwh'] ?? 0);
            $recSerialNumber = 'REC-2026-'.hash('sha256', "REC:{$tradeCode}:{$recMwh}");
            $recRetired = (string) ($params['retirement_status'] ?? 'RETIRED');

            if ($carbonTradeAmountMinor > 0) {
                $idempotencyKey = hash('sha256', "EGY:CARBON-TRADE:{$tradeCode}:{$carbonTradeAmountMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'ENERGY_CARBON_MARKET_TRADE',
                    description: "Carbon credit spot trade {$tradeCode} from {$sellerEntityId} to {$buyerEntityId}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('egy:carbon_credit_inventory:IDR', $carbonTradeAmountMinor)
                        ->credit('egy:carbon_credit_sales:IDR', $carbonTradeAmountMinor)
                        ->toEntries('IDR'),
                    referenceType: 'EGY_CARBON_TRADE',
                    referenceId: $tradeCode,
                ));
            }

            // CBAM cross-border tariff calculation
            $exportTonnage = (int) ($params['export_tonnage'] ?? 0);
            $emissionIntensityKgPerTonne = (int) ($params['emission_intensity_kg_per_tonne'] ?? 800);
            $cbamTariffPerKgMinor = (int) ($params['cbam_tariff_per_kg_minor'] ?? 250);

            $totalEmissionKg = $exportTonnage * $emissionIntensityKgPerTonne;
            $cbamTariffMinor = $totalEmissionKg * $cbamTariffPerKgMinor;

            if ($cbamTariffMinor > 0) {
                $cbamKey = hash('sha256', "EGY:CBAM:{$tradeCode}:{$cbamTariffMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'ENERGY_CBAM_TARIFF_SETTLEMENT',
                    description: "CBAM border carbon adjustment tariff settlement for {$exportTonnage}t export",
                    idempotencyKey: $cbamKey,
                    entries: Posting::lines()
                        ->debit('egy:cbam_receivable:IDR', $cbamTariffMinor)
                        ->credit('egy:cbam_revenue:IDR', $cbamTariffMinor)
                        ->toEntries('IDR'),
                    referenceType: 'EGY_CBAM_EXPORT',
                    referenceId: $tradeCode,
                ));
            }

            // ESG-linked Green Lease Discount
            $esgScore = (int) ($params['esg_score'] ?? 70);
            $baseBillMinor = (int) ($params['base_utility_bill_minor'] ?? 10000000);
            $greenDiscountPct = $esgScore >= 80 ? 10 : 0;
            $greenDiscountMinor = (int) round(($baseBillMinor * $greenDiscountPct) / 100);

            $result = [
                'trade_code' => $tradeCode,
                'buyer_entity_id' => $buyerEntityId,
                'seller_entity_id' => $sellerEntityId,
                'tonnes_co2' => $tonnesCo2,
                'carbon_trade_amount_minor' => $carbonTradeAmountMinor,
                'rec_mwh' => $recMwh,
                'rec_serial_number' => $recSerialNumber,
                'rec_retired_status' => $recRetired,
                'cbam_tariff_minor' => $cbamTariffMinor,
                'esg_score' => $esgScore,
                'green_discount_minor' => $greenDiscountMinor,
                'net_utility_bill_minor' => $baseBillMinor - $greenDiscountMinor,
            ];

            $this->audit(
                action: 'egy.carbon.cleared_and_retired',
                newValues: [
                    'trade_code' => $tradeCode,
                    'tonnes_co2' => $tonnesCo2,
                    'trade_minor' => $carbonTradeAmountMinor,
                    'cbam_minor' => $cbamTariffMinor,
                    'green_discount_minor' => $greenDiscountMinor,
                ],
                correlationId: "egy_carbon_{$tradeCode}",
                impactType: 'carbon_marketplace_clearing'
            );

            $this->outbox(
                eventType: 'CarbonSpotTradeAndRecRetired',
                payload: [
                    'trade_code' => $tradeCode,
                    'rec_serial_number' => $recSerialNumber,
                    'tonnes_co2' => $tonnesCo2,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:CRB:{$tradeCode}")
            );

            return $result;
        });
    }
}
