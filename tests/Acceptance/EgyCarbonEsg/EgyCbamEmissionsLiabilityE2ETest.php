<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyCarbonEsg;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Egy\Application\Actions\CalculateCbamBorderAdjustmentAction;
use Modules\Egy\Domain\Models\CbamDeclaration;
use Tests\TestCase;

final class EgyCbamEmissionsLiabilityE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_cbam_border_adjustment_calculation_and_ledger_posting(): void
    {
        $this->seed();

        $action = app(CalculateCbamBorderAdjustmentAction::class);

        // Ekspor 20.000 kg baja (20 ton) ke Uni Eropa.
        // Spesifik = 2.100 kg CO2e / ton, Benchmark EU = 1.300 kg CO2e / ton.
        // Gross emisi = 42.000 kg CO2e.
        // Taxable emisi = 16.000 kg CO2e.
        // Potongan kuota bebas 20% (2000 bps) = 3.200 kg -> Net payable = 12.800 kg CO2e (12.8 ton).
        // Harga karbon EU = €65,00 / ton (6500 sen).
        // Liabilitas EUR = 12.8 * 6500 = 83.200 sen (€832,00).
        // Kurs EUR = Rp 17.200 (1720000 minor per 100 sen).
        // Liabilitas IDR = (83200 * 1720000) / 100 = Rp 14.310.400.
        $declaration = $action->execute(
            exportContractNumber: 'EXP-CONTRACT-EU-777',
            containerNumber: 'CMA-CGM-12984',
            hsCode: '7208-39-00',
            exporterPartyId: 2,
            productTonnageKg: 20_000,
            specificEmissionFactorBps: 2100,
            euBenchmarkFactorBps: 1300,
            carbonPriceEurMinorPerTon: 6500,
            eurToIdrExchangeRateMinor: 1_720_000,
            idempotencyKey: 'IDEMP-CBAM-CONTAINER-12984'
        );

        $this->assertSame('declared', $declaration->status);
        $this->assertSame(20_000, $declaration->product_tonnage_kg);
        $this->assertSame(42_000, $declaration->gross_embedded_emissions_kg_co2e);
        $this->assertSame(12_800, $declaration->net_payable_emissions_kg_co2e);
        $this->assertSame(83_200, $declaration->total_cbam_liability_eur_minor);
        $this->assertSame(1_431_040_000, $declaration->total_cbam_liability_idr);
        $this->assertNotNull($declaration->ledger_transaction_id);

        // Verifikasi pembukuan kewajiban bea pabean CBAM di ledger
        $customsEntry = LedgerEntry::where('transaction_id', $declaration->ledger_transaction_id)
            ->whereHas('account', fn ($q) => $q->where('code', 'ap:egy:cbam_customs_authority:IDR'))
            ->first();

        $this->assertNotNull($customsEntry);
        $this->assertSame(1_431_040_000, (int) abs((float) $customsEntry->amount));

        // Idempoten
        $repeated = $action->execute(
            exportContractNumber: 'EXP-CONTRACT-EU-777',
            containerNumber: 'CMA-CGM-12984',
            hsCode: '7208-39-00',
            exporterPartyId: 2,
            productTonnageKg: 20_000,
            specificEmissionFactorBps: 2100,
            euBenchmarkFactorBps: 1300,
            carbonPriceEurMinorPerTon: 6500,
            eurToIdrExchangeRateMinor: 1_720_000,
            idempotencyKey: 'IDEMP-CBAM-CONTAINER-12984'
        );
        $this->assertSame($declaration->id, $repeated->id);
        $this->assertSame(1, CbamDeclaration::count());
    }
}
