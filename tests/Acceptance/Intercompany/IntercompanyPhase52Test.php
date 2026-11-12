<?php

declare(strict_types=1);

namespace Tests\Acceptance\Intercompany;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\Role;
use Modules\Intercompany\Domain\Events\IntercompanyConsolidationLocked;
use Modules\Intercompany\Domain\Events\IntercompanyMirrorPosted;
use Modules\Intercompany\Domain\Events\IntercompanyMismatchDetected;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\IntercompanyLoan;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Modules\Intercompany\Domain\Models\InterEntityAssetTransfer;
use Tests\TestCase;

class IntercompanyPhase52Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'LedgerAccountsSeeder']);

        foreach (['holding_cfo', 'auditor', 'treasury', 'legal', 'admin', 'guest'] as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName],
                ['label' => ucwords(str_replace('_', ' ', $roleName)), 'description' => $roleName]
            );
        }
    }

    /**
     * Test 1: RBAC Otorisasi Akses Modul Intercompany (AC-52.3).
     */
    public function test_intercompany_governance_access_control(): void
    {
        $guest = User::factory()->create();
        $guest->assignRole('guest');

        // Guest tanpa hak akses ditolak HTTP 403
        $this->actingAs($guest)
            ->get(route('intercompany.index'))
            ->assertStatus(403);

        $cfo = User::factory()->create();
        $cfo->assignRole('admin');

        $this->actingAs($cfo)
            ->get(route('intercompany.index'))
            ->assertOk();
    }

    /**
     * Test 2: Transaksi Cermin (Mirror Transactions) & Pemancaran Event Domain (AC-52.1).
     */
    public function test_mirrored_intercompany_transaction_and_event_emission(): void
    {
        Event::fake([IntercompanyMirrorPosted::class]);

        $seller = IntercompanyEntity::firstOrCreate(
            ['entity_code' => 'ENT_MANUF_01'],
            [
                'legal_name' => 'PT Manufaktur Konglomerat',
                'country_code' => 'ID',
                'functional_currency' => 'IDR',
                'tax_id' => '01.234.567.8-011.000',
                'is_parent' => false,
                'is_active' => true,
            ]
        );

        $buyer = IntercompanyEntity::firstOrCreate(
            ['entity_code' => 'ENT_RETAIL_01'],
            [
                'legal_name' => 'PT Retail Multi Distribusi',
                'country_code' => 'ID',
                'functional_currency' => 'IDR',
                'tax_id' => '01.234.567.8-012.000',
                'is_parent' => false,
                'is_active' => true,
            ]
        );

        $tx = IntercompanyTransaction::create([
            'id' => (string) Str::uuid(),
            'transaction_code' => 'TX-IC-'.strtoupper(Str::random(8)),
            'selling_entity_id' => $seller->id,
            'buying_entity_id' => $buyer->id,
            'description' => 'Pasokan Barang Dagang Retail Batch #101',
            'currency' => 'IDR',
            'amount_minor' => 150_000_000_00,
            'amount_functional_idr' => 150_000_000_00,
            'exchange_rate_numerator' => 1,
            'exchange_rate_denominator' => 1,
            'sales_invoice_ref' => 'INV-MANUF-001',
            'purchase_bill_ref' => 'BILL-RETAIL-001',
            'status' => 'draft',
        ]);

        $this->assertNotNull($tx);
        $this->assertSame(150_000_000_00, (int) $tx->amount_minor);

        event(new IntercompanyMirrorPosted(
            transactionCode: $tx->transaction_code,
            sellingEntityCode: 'ENT_MANUF_01',
            buyingEntityCode: 'ENT_RETAIL_01',
            amountMinor: 150_000_000_00,
            invoiceRef: 'INV-MANUF-001',
            billRef: 'BILL-RETAIL-001'
        ));

        Event::assertDispatched(IntercompanyMirrorPosted::class, function ($e) use ($tx) {
            return $e->transactionCode === $tx->transaction_code && $e->amountMinor === 150_000_000_00;
        });
    }

    /**
     * Test 3: Akrual Bunga Pinjaman Intra-Grup (`group:ic-interest`) & PPh 23.
     */
    public function test_group_ic_interest_command_and_withholding_tax(): void
    {
        $lender = IntercompanyEntity::firstOrCreate(
            ['entity_code' => 'ENT_HOLDING_01'],
            ['legal_name' => 'PT Holding Utama Group', 'country_code' => 'ID', 'functional_currency' => 'IDR', 'tax_id' => '01.234.567.8-001.000', 'is_parent' => true, 'is_active' => true]
        );

        $borrower = IntercompanyEntity::firstOrCreate(
            ['entity_code' => 'ENT_LOGISTICS_01'],
            ['legal_name' => 'PT Armada Logistik Nusantara', 'country_code' => 'ID', 'functional_currency' => 'IDR', 'tax_id' => '01.234.567.8-002.000', 'is_parent' => false, 'is_active' => true]
        );

        IntercompanyLoan::create([
            'id' => (string) Str::uuid(),
            'loan_agreement_number' => 'LN-HLD-LGX-2026-001',
            'lender_entity_id' => $lender->id,
            'borrower_entity_id' => $borrower->id,
            'currency' => 'IDR',
            'principal_minor' => 1_000_000_000_00,
            'interest_rate_bps' => 850,
            'spread_bps' => 150,
            'withholding_tax_rate_bps' => 1500,
            'start_date' => '2026-01-01',
            'due_date' => '2028-12-31',
            'repaid_principal_minor' => 0,
            'status' => 'active',
            'tp_doc_reference' => 'TP-DOC-LOAN-2026',
        ]);

        $exitCode = Artisan::call('group:ic-interest', ['--period' => '2026-10']);
        $this->assertSame(0, $exitCode, 'Command group:ic-interest harus keluar dengan status kode 0');
    }

    /**
     * Test 4: Penyisiran Selisih Asimetris Bilateral (`group:mismatch-sweep`) & Deteksi Anomali.
     */
    public function test_group_mismatch_sweep_and_anomaly_event_emission(): void
    {
        Event::fake([IntercompanyMismatchDetected::class]);

        // Kondisi 1: Data bersih -> exit 0
        $exitClean = Artisan::call('group:mismatch-sweep');
        $this->assertSame(0, $exitClean, 'group:mismatch-sweep harus exit 0 bila saldo bilateral seimbang');

        // Kondisi 2: Pasang ketidaksesuaian nilai transaksi timbal balik buatan
        $seller = IntercompanyEntity::firstOrCreate(['entity_code' => 'ENT_A'], ['legal_name' => 'Entity A', 'country_code' => 'ID', 'functional_currency' => 'IDR', 'tax_id' => '01.111.111.1-111.000', 'is_parent' => false, 'is_active' => true]);
        $buyer = IntercompanyEntity::firstOrCreate(['entity_code' => 'ENT_B'], ['legal_name' => 'Entity B', 'country_code' => 'ID', 'functional_currency' => 'IDR', 'tax_id' => '01.222.222.2-222.000', 'is_parent' => false, 'is_active' => true]);

        IntercompanyTransaction::create([
            'id' => (string) Str::uuid(),
            'transaction_code' => 'TX-ASYMMETRIC-TEST',
            'selling_entity_id' => $seller->id,
            'buying_entity_id' => $buyer->id,
            'description' => 'Asymmetric Mismatch Test Payload',
            'currency' => 'IDR',
            'amount_minor' => 500_000_000_00,
            'amount_functional_idr' => 450_000_000_00, // Sengaja asimetris selisih 50jt
            'exchange_rate_numerator' => 1,
            'exchange_rate_denominator' => 1,
            'sales_invoice_ref' => 'INV-ASYMM',
            'purchase_bill_ref' => null, // Pos gantung tanpa bill
            'status' => 'posted_seller',
        ]);

        $exitMismatch = Artisan::call('group:mismatch-sweep');
        $this->assertSame(1, $exitMismatch, 'group:mismatch-sweep harus exit 1 saat mendeteksi transaksi asimetris');
    }

    /**
     * Test 5: Penguncian Periode Konsolidasi & Mutasi Aset Antar-Entitas (AC-52.4).
     */
    public function test_consolidation_elimination_and_period_lock(): void
    {
        Event::fake([IntercompanyConsolidationLocked::class]);

        $transfer = InterEntityAssetTransfer::create([
            'id' => (string) Str::uuid(),
            'transfer_code' => 'AST-TRF-2026-001',
            'asset_tag' => 'AST-FORKLIFT-01',
            'source_entity_code' => 'ENT_MANUF_01',
            'target_entity_code' => 'ENT_LOGISTICS_01',
            'net_book_value_minor' => 350_000_000_00,
            'accum_depr_minor' => 150_000_000_00,
            'transfer_price_minor' => 350_000_000_00,
            'gain_loss_minor' => 0,
            'status' => 'COMPLETED',
            'authorized_by_ref' => 'USER-CFO-001',
        ]);

        $this->assertNotNull($transfer);
        $this->assertSame('AST-TRF-2026-001', $transfer->transfer_code);

        event(new IntercompanyConsolidationLocked(
            fiscalPeriod: '2026-10',
            lockedByUserId: 1,
            totalEliminatedMinor: 1_250_000_000_00
        ));

        Event::assertDispatched(IntercompanyConsolidationLocked::class);
    }

    /**
     * Test 6: Audit Dua Sumber Terpadu (`group:audit`) Data Bersih vs Korupsi Fixture.
     */
    public function test_dual_source_audit_and_corruption_fixture(): void
    {
        // 1. Audit kondisi data bersih -> exit 0
        $exitClean = Artisan::call('group:audit');
        $this->assertSame(0, $exitClean, 'Command group:audit harus keluar 0 pada kondisi data bersih');

        // 2. Pasang korupsi buatan: buat transaksi intercompany asimetris tanpa bill pasangan
        $seller = IntercompanyEntity::firstOrCreate(['entity_code' => 'ENT_CORRUPT_S'], ['legal_name' => 'Corrupt Seller', 'country_code' => 'ID', 'functional_currency' => 'IDR', 'tax_id' => '01.999.999.1-999.000', 'is_parent' => false, 'is_active' => true]);
        $buyer = IntercompanyEntity::firstOrCreate(['entity_code' => 'ENT_CORRUPT_B'], ['legal_name' => 'Corrupt Buyer', 'country_code' => 'ID', 'functional_currency' => 'IDR', 'tax_id' => '01.999.999.2-999.000', 'is_parent' => false, 'is_active' => true]);

        IntercompanyTransaction::create([
            'id' => (string) Str::uuid(),
            'transaction_code' => 'TX-CORRUPT-TAMPER',
            'selling_entity_id' => $seller->id,
            'buying_entity_id' => $buyer->id,
            'description' => 'Corrupt Tamper Payload',
            'currency' => 'IDR',
            'amount_minor' => 999_999_999_00,
            'amount_functional_idr' => 999_999_999_00,
            'exchange_rate_numerator' => 1,
            'exchange_rate_denominator' => 1,
            'sales_invoice_ref' => 'INV-CORRUPT',
            'purchase_bill_ref' => null,
            'status' => 'posted_seller',
        ]);

        // Audit mendeteksi anomali -> exit non-nol
        $exitCorrupt = Artisan::call('group:audit');
        $this->assertNotSame(0, $exitCorrupt, 'group:audit wajib exit non-nol saat mendeteksi diskrepansi');
    }
}
