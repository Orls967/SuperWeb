<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Syariah\Domain\Enums\IslamicTradeStatus;
use Modules\Syariah\Ledger\SyariahAccounts;

final class SyariahTradeT1Seeder extends Seeder
{
    public function run(): void
    {
        SyariahAccounts::ensureAccountsExist();
        $now = CarbonImmutable::now();

        // 1. Seed 200 Islamic Letters of Credit & Istisna Contracts
        $lcBatch = [];
        $istisnaBatch = [];
        for ($i = 1; $i <= 200; $i++) {
            $amount = 1_000_000_000 + ($i * 10_000_000);
            $margin = (int) intdiv($amount * 20, 100);
            $lcBatch[] = [
                'id' => $i,
                'lc_number' => sprintf('LC-SYB-202611-%04d', $i),
                'applicant_party_ref' => sprintf('importer-corp-%03d', ($i % 30) + 1),
                'beneficiary_party_ref' => sprintf('manufacturer-intl-%03d', ($i % 25) + 1),
                'issuing_bank_party_ref' => 'bank-syariah-pusat',
                'amount_currency' => 'IDR',
                'amount_cents' => $amount,
                'margin_deposit_cents' => $margin,
                'status' => IslamicTradeStatus::ACTIVE->value,
                'expiry_date' => $now->addMonths(6)->toDateString(),
                'wakalah_fee_cents' => 15_000_000,
                'terms_conditions' => 'Akad Istisna manufaktur pesanan dan Wakalah bil Ujrah keagenan dokumen.',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $budget = (int) intdiv($amount * 80, 100);
            $actualCost = (int) intdiv($budget * (($i % 8) + 1), 10);
            $poc = (int) min(100, intdiv($actualCost * 100, $budget));
            $recRev = (int) intdiv($amount * $poc, 100);

            $istisnaBatch[] = [
                'id' => $i,
                'lc_id' => $i,
                'contract_number' => sprintf('IST-202611-%04d', $i),
                'manufacturer_party_ref' => sprintf('manufacturer-intl-%03d', ($i % 25) + 1),
                'specification_summary' => sprintf('Pesanan batch manufaktur mesin industri lot %d', $i),
                'total_price_cents' => $amount,
                'budgeted_cost_cents' => $budget,
                'actual_cost_incurred_cents' => $actualCost,
                'recognized_revenue_cents' => $recRev,
                'progress_percent' => $poc,
                'status' => $poc >= 100 ? IslamicTradeStatus::COMPLETED->value : IslamicTradeStatus::ACTIVE->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($lcBatch, 100) as $chunk) {
            DB::table('syb_letters_of_credit')->insertOrIgnore($chunk);
        }
        foreach (array_chunk($istisnaBatch, 100) as $chunk) {
            DB::table('syb_istisna_contracts')->insertOrIgnore($chunk);
        }

        // 2. Seed 500 Salam Trade Contracts & Parallel Hedges
        $commodities = ['CPO', 'COFFEE', 'RUBBER', 'NICKEL', 'COCOA'];
        $salamBatch = [];
        $hedgeBatch = [];
        for ($i = 1; $i <= 500; $i++) {
            $comm = $commodities[$i % 5];
            $qty = 50 + ($i * 2);
            $adv = $qty * 12_500_000;
            $salamBatch[] = [
                'id' => $i,
                'contract_number' => sprintf('SLM-202611-%04d', $i),
                'seller_party_ref' => sprintf('farmer-coop-%03d', ($i % 50) + 1),
                'buyer_party_ref' => 'holding-syariah-agri',
                'commodity_code' => $comm,
                'commodity_grade' => 'SUPER_GRADE_A',
                'quantity_unit' => 'MT',
                'quantity_ordered' => $qty,
                'quantity_delivered' => (int) intdiv($qty * (($i % 4)), 4),
                'advance_payment_cents' => $adv,
                'promised_delivery_date' => $now->addMonths(2)->toDateString(),
                'delivery_warehouse_code' => sprintf('WH-%s-%02d', $comm, ($i % 5) + 1),
                'status' => IslamicTradeStatus::ACTIVE->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($i % 2 === 0) {
                $hedgeBatch[] = [
                    'salam_id' => $i,
                    'hedge_contract_number' => sprintf('HDG-SLM-202611-%04d', $i),
                    'offtaker_party_ref' => sprintf('offtaker-corp-%02d', ($i % 15) + 1),
                    'agreed_sale_price_cents' => $qty * 14_000_000,
                    'quantity_hedged' => $qty,
                    'delivery_date' => $now->addMonths(3)->toDateString(),
                    'status' => IslamicTradeStatus::ACTIVE->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($salamBatch, 100) as $chunk) {
            DB::table('syb_salam_trade_contracts')->insertOrIgnore($chunk);
        }
        foreach (array_chunk($hedgeBatch, 100) as $chunk) {
            DB::table('syb_parallel_salam_hedges')->insertOrIgnore($chunk);
        }

        // 3. Seed 1.000 SCF Murabahah Invoices & Facilities (100 facilities)
        $facilityBatch = [];
        for ($f = 1; $f <= 100; $f++) {
            $facilityBatch[] = [
                'id' => $f,
                'facility_number' => sprintf('SCF-FAC-202611-%04d', $f),
                'buyer_party_ref' => sprintf('buyer-pt-%03d', $f),
                'supplier_party_ref' => sprintf('supplier-cv-%03d', ($f % 40) + 1),
                'limit_amount_cents' => 10_000_000_000,
                'utilized_amount_cents' => 3_000_000_000,
                'margin_rate_bps' => 750,
                'max_tenor_days' => 120,
                'status' => IslamicTradeStatus::ACTIVE->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('syb_scf_facilities')->insertOrIgnore($facilityBatch);

        // Seed 1.000 Custody Documents (Verified Qabdh)
        $docBatch = [];
        for ($d = 1; $d <= 1000; $d++) {
            $docBatch[] = [
                'id' => $d,
                'document_type' => 'BILL_OF_LADING',
                'document_number' => sprintf('BL-INTL-%04d', $d),
                'issuer_name' => 'Samudera Ocean Logistics',
                'carrier_name' => 'MV Nusantara Bahari',
                'cargo_description' => sprintf('Kontainer muatan logistik bahan baku batch %d', $d),
                'document_hash_sha256' => hash('sha256', "BL-DATA-{$d}"),
                'verified_qabdh_at' => $now->subDays($d % 10),
                'verifier_officer_ref' => 'officer-custody-inspector',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($docBatch, 100) as $chunk) {
            DB::table('syb_trade_custody_documents')->insertOrIgnore($chunk);
        }

        $invoiceBatch = [];
        for ($inv = 1; $inv <= 1000; $inv++) {
            $facId = ($inv % 100) + 1;
            $cost = 300_000_000 + ($inv * 500_000);
            $margin = (int) intdiv($cost * 750 * 90, 365 * 10000);
            $invoiceBatch[] = [
                'facility_id' => $facId,
                'invoice_number' => sprintf('SCF-INV-202611-%04d', $inv),
                'supplier_invoice_ref' => sprintf('INV-SUPP-%04d', $inv),
                'principal_cost_cents' => $cost,
                'margin_profit_cents' => $margin,
                'total_sale_price_cents' => $cost + $margin,
                'custody_document_ref' => (string) $inv,
                'qabdh_verified_at' => $now->subDays($inv % 10),
                'due_date' => $now->addDays(90)->toDateString(),
                'status' => IslamicTradeStatus::ACTIVE->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($invoiceBatch, 100) as $chunk) {
            DB::table('syb_scf_invoices')->insertOrIgnore($chunk);
        }

        // 4. Seed 300 Tawarruq FX Orders & Commodity Lots
        $lotBatch = [];
        $tawarruqBatch = [];
        for ($t = 1; $t <= 300; $t++) {
            $src = 1_550_000_000 + ($t * 10_000_000);
            $target = (int) intdiv($src * 10000, 1_550_000);
            $serial = sprintf('LOT-ICDX-ALUM-%05d', $t);
            $lotBatch[] = [
                'id' => $t,
                'lot_number' => sprintf('LOT-EXCH-%04d', $t),
                'exchange_name' => 'Bursa ICDX Syariah',
                'commodity_type' => 'METALS_ALUMINUM_GRADE_A',
                'physical_serial_number' => $serial,
                'warehouse_location' => 'WH-ROTTERDAM-SYARIAH-01',
                'unit_quantity' => 10,
                'acquisition_cost_cents' => $src,
                'lot_status' => 'TRANSFERRED_LIQUIDATED',
                'assigned_order_ref' => sprintf('TWQ-202611-%04d', $t),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $markup = (int) intdiv($src * 600 * 90, 365 * 10000);
            $deferred = $src + $markup + 2_500_000;
            $effRate = (int) intdiv($deferred * 10000, $target);

            $tawarruqBatch[] = [
                'commodity_lot_id' => $t,
                'order_number' => sprintf('TWQ-202611-%04d', $t),
                'customer_party_ref' => sprintf('corporate-client-%03d', ($t % 30) + 1),
                'source_currency' => 'IDR',
                'source_amount_cents' => $src,
                'target_currency' => 'USD',
                'target_amount_cents' => $target,
                'spot_rate_bps' => 1_550_000,
                'tenor_days' => 90,
                'markup_rate_bps' => 600,
                'markup_profit_cents' => $markup,
                'broker_fee_cents' => 2_500_000,
                'total_deferred_price_cents' => $deferred,
                'effective_rate_bps' => $effRate,
                'certificate_hash' => hash('sha256', "TWQ-CERT-{$t}"),
                'status' => IslamicTradeStatus::COMPLETED->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($lotBatch, 100) as $chunk) {
            DB::table('syb_commodity_lots')->insertOrIgnore($chunk);
        }
        foreach (array_chunk($tawarruqBatch, 100) as $chunk) {
            DB::table('syb_tawarruq_orders')->insertOrIgnore($chunk);
        }
    }
}
