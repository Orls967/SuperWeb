<?php

declare(strict_types=1);

namespace Modules\Egy\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Egy\Application\Actions\CalculateCbamBorderAdjustmentAction;
use Modules\Egy\Application\Actions\MatchCarbonOrdersAction;
use Modules\Egy\Application\Actions\MintRenewableEnergyCertificateAction;
use Modules\Egy\Application\Actions\RetireRecCertificateAction;
use Modules\Egy\Application\Actions\SettleEsgMarketplaceTradeAction;
use Modules\Egy\Domain\Models\CarbonCreditBatch;
use Modules\Egy\Domain\Models\CarbonOrder;
use Modules\Egy\Domain\Models\CarbonProject;
use Modules\Egy\Domain\Models\EsgMarketplaceListing;
use Modules\Egy\Domain\Models\GenerationAsset;

final class EgyT1CarbonEsgSeeder extends Seeder
{
    public function run(): void
    {
        $ledgerService = app(LedgerService::class);
        $matchAction = app(MatchCarbonOrdersAction::class);
        $mintRecAction = app(MintRenewableEnergyCertificateAction::class);
        $retireRecAction = app(RetireRecCertificateAction::class);
        $cbamAction = app(CalculateCbamBorderAdjustmentAction::class);
        $esgTradeAction = app(SettleEsgMarketplaceTradeAction::class);

        // 1. Proyek Karbon
        $project1 = CarbonProject::firstOrCreate(
            ['project_code' => 'PRJ-FOR-01'],
            [
                'project_name' => 'Proyek Konservasi & Reforestasi Rimba Tropis Kawasan',
                'project_type' => 'forestry_afforestation',
                'standard_registry' => 'SRN_PPI',
                'registry_code' => 'SRN-PPI-2024-001',
                'developer_party_id' => 1,
                'total_certified_credits_kg_co2e' => 100_000_000, // 100.000 ton
                'available_credits_kg_co2e' => 80_000_000,
                'retired_credits_kg_co2e' => 20_000_000,
                'status' => 'active',
            ]
        );

        $project2 = CarbonProject::firstOrCreate(
            ['project_code' => 'PRJ-SOLAR-01'],
            [
                'project_name' => 'Solar Farm Cirata Ground-Mounted 50MWp',
                'project_type' => 'solar_farm',
                'standard_registry' => 'VERRA_VCS',
                'registry_code' => 'VCS-2025-089',
                'developer_party_id' => 1,
                'total_certified_credits_kg_co2e' => 50_000_000,
                'available_credits_kg_co2e' => 50_000_000,
                'retired_credits_kg_co2e' => 0,
                'status' => 'active',
            ]
        );

        // 2. Batch Kredit Karbon
        CarbonCreditBatch::firstOrCreate(
            ['batch_number' => 'BATCH-FOR-2025-01'],
            [
                'carbon_project_id' => $project1->id,
                'vintage_year' => '2025',
                'initial_quantity_kg_co2e' => 50_000_000,
                'remaining_quantity_kg_co2e' => 45_000_000,
                'issued_price_minor_per_ton' => 350_000, // Rp 350.000 / ton
                'verification_date' => '2025-06-01',
                'expiration_date' => '2030-12-31',
                'verifier_entity' => 'PT Sucofindo International Certification',
                'certificate_hash' => hash('sha256', 'CERT-BATCH-FOR-2025-01'),
                'status' => 'available',
            ]
        );

        CarbonCreditBatch::firstOrCreate(
            ['batch_number' => 'BATCH-SLR-2026-01'],
            [
                'carbon_project_id' => $project2->id,
                'vintage_year' => '2026',
                'initial_quantity_kg_co2e' => 20_000_000,
                'remaining_quantity_kg_co2e' => 20_000_000,
                'issued_price_minor_per_ton' => 320_000,
                'verification_date' => '2026-01-15',
                'expiration_date' => '2031-12-31',
                'verifier_entity' => 'TUV Rheinland Carbon Verification',
                'certificate_hash' => hash('sha256', 'CERT-BATCH-SLR-2026-01'),
                'status' => 'available',
            ]
        );

        // 3. Initial Orderbook Bids & Asks
        // Asks (Penjual kredit)
        CarbonOrder::firstOrCreate(
            ['order_number' => 'ORD-SELL-T1-01'],
            [
                'party_id' => 1, // Developer
                'side' => 'SELL',
                'vintage_year' => '2025',
                'quantity_kg_co2e' => 10_000, // 10 ton
                'filled_kg_co2e' => 0,
                'limit_price_minor_per_ton' => 340_000,
                'status' => 'open',
            ]
        );
        CarbonOrder::firstOrCreate(
            ['order_number' => 'ORD-SELL-T1-02'],
            [
                'party_id' => 1,
                'side' => 'SELL',
                'vintage_year' => '2026',
                'quantity_kg_co2e' => 15_000,
                'filled_kg_co2e' => 0,
                'limit_price_minor_per_ton' => 360_000,
                'status' => 'open',
            ]
        );

        // Bids (Pembeli kredit)
        CarbonOrder::firstOrCreate(
            ['order_number' => 'ORD-BUY-T1-01'],
            [
                'party_id' => 10, // Mall Tenant Holding
                'side' => 'BUY',
                'vintage_year' => '2025',
                'quantity_kg_co2e' => 6_000,
                'filled_kg_co2e' => 0,
                'limit_price_minor_per_ton' => 350_000, // Matching against ORD-SELL-T1-01 @ 340.000
                'status' => 'open',
            ]
        );
        CarbonOrder::firstOrCreate(
            ['order_number' => 'ORD-BUY-T1-02'],
            [
                'party_id' => 12, // Hotel Grand Convention
                'side' => 'BUY',
                'vintage_year' => '2026',
                'quantity_kg_co2e' => 4_000,
                'filled_kg_co2e' => 0,
                'limit_price_minor_per_ton' => 345_000,
                'status' => 'open',
            ]
        );

        // Jalankan orderbook matching
        $matchAction->execute('SEEDER-T1');

        // 4. Minting & Retiring REC
        $solarAsset = GenerationAsset::where('fuel_type', 'SOLAR_PV')->first();
        if ($solarAsset !== null) {
            $rec1 = $mintRecAction->execute(
                generationAssetId: (int) $solarAsset->id,
                sourceType: 'solar_pv',
                periodMonth: '2026-11',
                vintageYear: '2026',
                energyWh: 100_000_000_000, // 100 MWh
                ownerPartyId: 1,
                initialTradingPriceMinor: 40_000
            );

            // Pensiunkan sebagian untuk klaim green lease
            $retireRecAction->execute(
                recCertificateId: (int) $rec1->id,
                beneficiaryName: 'Penyewa Mall Nusantara Blok A',
                retirementReason: 'Pemenuhan Standar Green Lease 100% Energi Terbarukan',
                claimantPartyId: 10
            );
        }

        // 5. Deklarasi Ekspor CBAM
        $cbamAction->execute(
            exportContractNumber: 'CTR-EXP-EU-991',
            containerNumber: 'MSCU-908123',
            hsCode: '7208-39-00', // Hot rolled coil steel
            exporterPartyId: 2, // Pabrik Baja Kawasan
            productTonnageKg: 25_000, // 25 ton
            specificEmissionFactorBps: 2200, // 2200 kg CO2e / ton
            euBenchmarkFactorBps: 1300, // 1300 kg CO2e / ton
            carbonPriceEurMinorPerTon: 6800, // €68.00 / ton
            eurToIdrExchangeRateMinor: 1_720_000, // Rp 17.200 per EUR
            idempotencyKey: 'SEEDER-CBAM-CTR-EXP-EU-991'
        );

        // 6. ESG Marketplace Listings & Orders
        $listing1 = EsgMarketplaceListing::firstOrCreate(
            ['listing_code' => 'LST-REC-BUNDLE-01'],
            [
                'title' => 'Bundel Sertifikat Listrik Hijau Solar 50 MWh',
                'category' => 'rec_bundle',
                'description' => 'Paket atribut energi terbarukan bersertifikat I-REC untuk tenant komersial dan hotel.',
                'seller_party_id' => 1,
                'price_minor_per_unit' => 2_000_000, // Rp 2.000.000 / MWh
                'unit_label' => 'mwh',
                'available_quantity' => 100,
                'sold_quantity' => 0,
                'status' => 'active',
            ]
        );

        $listing2 = EsgMarketplaceListing::firstOrCreate(
            ['listing_code' => 'LST-RECYCLED-FLAKE-01'],
            [
                'title' => 'Pelet Plastik Daur Ulang Flake rPET Grade A',
                'category' => 'recycled_raw_material',
                'description' => 'Material sirkular sekunder hasil fasilitas pengolahan limbah WTE kawasan terakreditasi.',
                'seller_party_id' => 1,
                'price_minor_per_unit' => 14_000, // Rp 14.000 / kg
                'unit_label' => 'kg',
                'available_quantity' => 5000,
                'sold_quantity' => 0,
                'status' => 'active',
            ]
        );

        $esgTradeAction->execute(
            listingId: (int) $listing1->id,
            buyerPartyId: 10,
            purchaseQuantity: 5,
            idempotencyKey: 'SEEDER-ESG-BUY-01'
        );
    }
}
