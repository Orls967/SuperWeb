<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Egy\Domain\Models\CbamDeclaration;
use Modules\Egy\Ledger\EgyTransactionTypes;
use Modules\Shared\Domain\Algorithms\Utility\CbamEmissionsTariff;

/**
 * Menghitung liabilitas tarif karbon perbatasan Uni Eropa (EU CBAM) dan membukukan kewajiban pajak ke ledger.
 */
final class CalculateCbamBorderAdjustmentAction
{
    public function __construct(
        private readonly CbamEmissionsTariff $cbamCalculator,
        private readonly LedgerService $ledgerService,
    ) {}

    public function execute(
        string $exportContractNumber,
        string $containerNumber,
        string $hsCode,
        int $exporterPartyId,
        int $productTonnageKg,
        int $specificEmissionFactorBps,
        int $euBenchmarkFactorBps,
        int $carbonPriceEurMinorPerTon,
        int $eurToIdrExchangeRateMinor,
        string $idempotencyKey,
    ): CbamDeclaration {
        $declarationNumber = sprintf('CBAM-%s-%s', $containerNumber, str_replace('-', '', $hsCode));

        $existing = CbamDeclaration::where('declaration_number', $declarationNumber)->first();
        if ($existing !== null) {
            return $existing;
        }

        $result = $this->cbamCalculator->calculate(
            productTonnageKg: $productTonnageKg,
            specificEmissionFactorKgCo2ePerTon: $specificEmissionFactorBps,
            euBenchmarkFactorKgCo2ePerTon: $euBenchmarkFactorBps,
            freeAllowancePercentageBps: 2000, // 20% alokasi bebas masa transisi
            carbonPriceEurMinorPerTon: $carbonPriceEurMinorPerTon,
            eurToIdrExchangeRateMinor: $eurToIdrExchangeRateMinor
        );

        $ledgerTxId = null;
        if ($result->liabilityIdr > 0) {
            $taxExpenseAccount = 'egy:cbam_tax_expense:IDR';
            $customsPayableAccount = 'ap:egy:cbam_customs_authority:IDR';

            $postingLines = Posting::lines()
                ->debit($taxExpenseAccount, $result->liabilityIdr)
                ->credit($customsPayableAccount, $result->liabilityIdr);

            $tx = $this->ledgerService->post(new PostingDTO(
                type: EgyTransactionTypes::EGY_GRID_BILLING->value,
                description: sprintf('Pencadangan Liabilitas Pajak CBAM Ekspor Kontainer %s', $containerNumber),
                idempotencyKey: $idempotencyKey,
                entries: $postingLines->toEntries('IDR'),
                referenceType: 'CBAM_DECLARATION',
                referenceId: $declarationNumber,
            ));
            $ledgerTxId = $tx->id;
        }

        return CbamDeclaration::create([
            'declaration_number' => $declarationNumber,
            'export_contract_number' => $exportContractNumber,
            'container_number' => $containerNumber,
            'hs_code' => $hsCode,
            'country_destination' => 'EU',
            'exporter_party_id' => $exporterPartyId,
            'product_tonnage_kg' => $productTonnageKg,
            'specific_emission_factor_bps' => $specificEmissionFactorBps,
            'eu_benchmark_factor_bps' => $euBenchmarkFactorBps,
            'gross_embedded_emissions_kg_co2e' => $result->grossEmbeddedEmissionsKgCo2e,
            'net_payable_emissions_kg_co2e' => $result->netPayableEmissionsKgCo2e,
            'carbon_price_eur_minor_per_ton' => $carbonPriceEurMinorPerTon,
            'total_cbam_liability_eur_minor' => $result->liabilityEurMinor,
            'total_cbam_liability_idr' => $result->liabilityIdr,
            'ledger_transaction_id' => $ledgerTxId,
            'status' => 'declared',
            'certificate_hash' => $result->certificateHash,
        ]);
    }
}
