<?php

declare(strict_types=1);

namespace Tests\Acceptance\VmiC2m;

use App\Models\User;
use App\Quality\Audit\Fixtures\VmiAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Application\Services\RbacService;
use Modules\Manufacturing\Domain\Models\C2m\C2mCustomOrder;
use Modules\Manufacturing\Domain\Models\C2m\VmiReplenishment;
use Modules\Pricing\Application\Services\PriceLocker;
use Modules\Procurement\Domain\Models\VmiAgreement;
use Tests\TestCase;

/**
 * Acceptance test Fase 82: Vendor-Managed Inventory (VMI) & Consumer-to-Manufacturer (C2M).
 * Maksimal 6 skenario acceptance v3 (Plafon keras protokol G6).
 */
final class VmiC2mAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private User $supplierUser;

    private User $otherSupplierUser;

    private User $customerUser;

    private User $plannerUser;

    private User $procurementManagerUser;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $rbac = app(RbacService::class);
        $rbac->ensureRole('supplier', 'Pemasok VMI');
        $rbac->ensureRole('customer', 'Pelanggan');
        $rbac->ensureRole('planner', 'Perencana Produksi');
        $rbac->ensureRole('procurement_manager', 'Manajer Pengadaan');
        $rbac->ensureRole('guest_viewer', 'Tamu Tanpa Hak');

        $this->supplierUser = User::factory()->create(['email' => 'supplier1@superweb.test']);
        $rbac->assignRole($this->supplierUser, 'supplier');

        $this->otherSupplierUser = User::factory()->create(['email' => 'supplier2@superweb.test']);
        $rbac->assignRole($this->otherSupplierUser, 'supplier');

        $this->customerUser = User::factory()->create(['email' => 'customer@superweb.test']);
        $rbac->assignRole($this->customerUser, 'customer');

        $this->plannerUser = User::factory()->create(['email' => 'planner@superweb.test']);
        $rbac->assignRole($this->plannerUser, 'planner');

        $this->procurementManagerUser = User::factory()->create(['email' => 'proc.mgr@superweb.test']);
        $rbac->assignRole($this->procurementManagerUser, 'procurement_manager');

        $this->unauthorizedUser = User::factory()->create(['email' => 'unauth@superweb.test']);
        $rbac->assignRole($this->unauthorizedUser, 'guest_viewer');
    }

    /**
     * Test 1: RBAC Access Control untuk rute VMI dan C2M (role berhak vs tak berhak 403).
     */
    public function test_rbac_access_control_vmi_and_c2m_routes(): void
    {
        // 1. Supplier portal
        $this->actingAs($this->supplierUser)->get('/portal/vmi')->assertStatus(200);
        $this->actingAs($this->unauthorizedUser)->get('/portal/vmi')->assertStatus(403);

        // 2. Customer C2M store
        $this->actingAs($this->customerUser)->get('/store/c2m')->assertStatus(200);
        $this->actingAs($this->unauthorizedUser)->get('/store/c2m')->assertStatus(403);

        // 3. Manufacturing C2M dashboard
        $this->actingAs($this->plannerUser)->get('/manufacturing/c2m')->assertStatus(200);
        $this->actingAs($this->unauthorizedUser)->get('/manufacturing/c2m')->assertStatus(403);
    }

    /**
     * Test 2: Alur VMI end-to-end, kebijakan (s, S), auto-PO <= plafon 15jt, posting ledger, dan idempotensi scan-reorder.
     */
    public function test_vmi_e2e_reorder_policy_auto_po_and_idempotency(): void
    {
        // Kontrak VMI aktif untuk supplier 1 dengan plafon Rp 15.000.000
        $agreement = VmiAgreement::create([
            'agreement_code' => 'AGR-VMI-001',
            'supplier_id' => $this->supplierUser->id,
            'sku' => 'SKU-TABLE-LEG-01',
            'reorder_point' => 579,
            'reorder_qty' => 950,
            'unit_cost_idr' => 12_000,
            'contract_plafond_idr' => 15_000_000,
            'status' => 'ACTIVE',
        ]);

        // Parameter vektor D82-1: d=120, sigma=30, L=4, z=1.645, review=7, stok=500
        // Expected: SS=99, ROP=579, S=1419, net=500 -> raw qty=919 -> pack 50 -> order qty=950. Total = Rp 11.400.000
        $payload = [
            'supplier_id' => $this->supplierUser->id,
            'sku' => 'SKU-TABLE-LEG-01',
            'mean_daily_demand' => 120,
            'demand_std_dev' => 30,
            'lead_time_days' => 4,
            'service_level_bps' => 9500, // z = 1.645
            'review_period_days' => 7,
            'on_hand' => 500,
            'on_order' => 0,
            'backorder' => 0,
            'pack_size' => 50,
            'unit_cost_idr' => 12_000,
        ];

        // 1. Eksekusi trigger VMI
        $response = $this->actingAs($this->supplierUser)
            ->post('/portal/vmi/trigger', $payload);
        $response->assertSessionHasNoErrors();

        // Verifikasi VMI replenishment terbentuk sebagai AUTO_ORDERED karena 11.400.000 <= 15.000.000
        $replenishment = VmiReplenishment::where('sku', 'SKU-TABLE-LEG-01')->firstOrFail();
        $this->assertSame('AUTO_ORDERED', $replenishment->status);
        $this->assertSame(950, $replenishment->suggested_reorder_qty);
        $this->assertSame(11_400_000, $replenishment->total_estimated_idr);

        // Verifikasi komitmen anggaran tercatat di ledger
        $ledgerKey = "VMI-COMM-{$replenishment->vmi_code}";
        $this->assertDatabaseHas('bank_ledger_transactions', [
            'idempotency_key' => $ledgerKey,
        ]);

        // 2. Eksekusi ulang scan-reorder via command pada hari yang sama (idempotensi mutlak)
        $exitCode = Artisan::call('vmi:scan-reorder', [
            '--sku' => 'SKU-TABLE-LEG-01',
            '--supplier' => $this->supplierUser->id,
        ]);
        $this->assertSame(0, $exitCode);

        // Harus tetap tepat 1 record di database
        $count = VmiReplenishment::where('sku', 'SKU-TABLE-LEG-01')->count();
        $this->assertSame(1, $count);
    }

    /**
     * Test 3: VMI nilai melebihi plafon kontrak membutuhkan approval procurement_manager sebelum PO aktif.
     */
    public function test_vmi_contract_plafond_and_procurement_approval(): void
    {
        // Kontrak dengan plafon hanya Rp 10.000.000
        $agreement = VmiAgreement::create([
            'agreement_code' => 'AGR-VMI-002',
            'supplier_id' => $this->supplierUser->id,
            'sku' => 'SKU-HEAVY-STEEL-02',
            'reorder_point' => 579,
            'reorder_qty' => 950,
            'unit_cost_idr' => 12_000,
            'contract_plafond_idr' => 10_000_000, // Plafon lebih rendah dari estimasi order 11.4jt
            'status' => 'ACTIVE',
        ]);

        $payload = [
            'supplier_id' => $this->supplierUser->id,
            'sku' => 'SKU-HEAVY-STEEL-02',
            'mean_daily_demand' => 120,
            'demand_std_dev' => 30,
            'lead_time_days' => 4,
            'service_level_bps' => 9500,
            'review_period_days' => 7,
            'on_hand' => 500,
            'pack_size' => 50,
            'unit_cost_idr' => 12_000,
        ];

        $this->actingAs($this->supplierUser)->post('/portal/vmi/trigger', $payload);

        $replenishment = VmiReplenishment::where('sku', 'SKU-HEAVY-STEEL-02')->firstOrFail();
        $this->assertSame('PENDING_APPROVAL', $replenishment->status);

        // Supplier tidak boleh approve sendiri (403)
        $this->actingAs($this->supplierUser)
            ->post("/portal/vmi/{$replenishment->id}/approve")
            ->assertStatus(403);

        // Procurement manager berhak menyetujui
        $this->actingAs($this->procurementManagerUser)
            ->post("/portal/vmi/{$replenishment->id}/approve")
            ->assertRedirect();

        $replenishment->refresh();
        $this->assertSame('APPROVED', $replenishment->status);
        $this->assertDatabaseHas('bank_ledger_transactions', [
            'idempotency_key' => "VMI-COMM-{$replenishment->vmi_code}",
        ]);
    }

    /**
     * Test 4: Proteksi Anti-IDOR pada portal VMI pemasok (hanya melihat SKU miliknya).
     */
    public function test_vmi_supplier_anti_idor_scope(): void
    {
        // Supplier 1 memiliki kontrak SKU A
        VmiAgreement::create([
            'agreement_code' => 'AGR-VMI-A',
            'supplier_id' => $this->supplierUser->id,
            'sku' => 'SKU-SUPP-1-ONLY',
            'reorder_point' => 100,
            'reorder_qty' => 200,
            'unit_cost_idr' => 5_000,
            'contract_plafond_idr' => 50_000_000,
            'status' => 'ACTIVE',
        ]);

        // Supplier 2 memiliki kontrak SKU B
        VmiAgreement::create([
            'agreement_code' => 'AGR-VMI-B',
            'supplier_id' => $this->otherSupplierUser->id,
            'sku' => 'SKU-SUPP-2-ONLY',
            'reorder_point' => 100,
            'reorder_qty' => 200,
            'unit_cost_idr' => 5_000,
            'contract_plafond_idr' => 50_000_000,
            'status' => 'ACTIVE',
        ]);

        // Supplier 1 hanya melihat SKU miliknya di portal
        $response1 = $this->actingAs($this->supplierUser)->get('/portal/vmi');
        $response1->assertSee('SKU-SUPP-1-ONLY');
        $response1->assertDontSee('SKU-SUPP-2-ONLY');

        // Supplier 1 mencoba trigger reorder untuk SKU supplier 2 -> Ditolak 403 Forbidden
        $this->actingAs($this->supplierUser)
            ->post('/portal/vmi/trigger', [
                'supplier_id' => $this->supplierUser->id,
                'sku' => 'SKU-SUPP-2-ONLY',
                'mean_daily_demand' => 10,
                'demand_std_dev' => 2,
                'lead_time_days' => 3,
                'on_hand' => 20,
            ])
            ->assertStatus(403);
    }

    /**
     * Test 5: C2M Konfigurator CSP - validasi AC-3, penolakan 422 QuickXplain minimal, dan order valid dengan PriceLocker.
     */
    public function test_c2m_csp_validation_422_and_valid_order_with_pricelocker(): void
    {
        // 1. Pilihan meja inkonsisten vektor prompt 1: (W=120, D=80, M=kaca, L=3)
        // Batasan: M=kaca -> W<=100. Konflik minimal = [W=120, M=kaca]
        $res1 = $this->actingAs($this->customerUser)->postJson('/store/c2m/configure', [
            'base_model' => 'CUSTOM_DESK_MODULAR',
            'width' => 120,
            'depth' => 80,
            'material' => 'kaca',
            'drawers' => 3,
        ]);
        $res1->assertStatus(422);
        $res1->assertJsonFragment(['minimal_conflict' => ['width' => 120, 'material' => 'kaca']]);

        // 2. Pilihan meja inkonsisten vektor prompt 2: (W=60, D=60, M=kayu, L=4)
        // Batasan: W >= D + 20 (60 < 80). Konflik minimal = [W=60, D=60]
        $res2 = $this->actingAs($this->customerUser)->postJson('/store/c2m/configure', [
            'base_model' => 'CUSTOM_DESK_MODULAR',
            'width' => 60,
            'depth' => 60,
            'material' => 'kayu',
            'drawers' => 4,
        ]);
        $res2->assertStatus(422);
        $res2->assertJsonFragment(['minimal_conflict' => ['width' => 60, 'depth' => 60]]);

        // 3. Pilihan meja valid: (W=100, D=60, M=kayu, L=4)
        $res3 = $this->actingAs($this->customerUser)->postJson('/store/c2m/configure', [
            'base_model' => 'CUSTOM_DESK_MODULAR',
            'width' => 100,
            'depth' => 60,
            'material' => 'kayu',
            'drawers' => 4,
            'raw_material_cost_idr' => 1_500_000,
        ]);
        $res3->assertStatus(200);

        $order = C2mCustomOrder::where('base_model', 'CUSTOM_DESK_MODULAR')->firstOrFail();
        $this->assertNotNull($order->c2m_code);
        $this->assertSame('DESIGN_VALIDATED', $order->status);
        $this->assertGreaterThan(0, $order->final_price_idr);

        // Verifikasi BOM & routing turunan dihasilkan
        $this->assertDatabaseHas('mfg_c2m_bom_nodes', [
            'c2m_custom_order_id' => $order->id,
            'material' => 'kayu',
        ]);

        // Verifikasi harga dikunci di PriceLocker (pric_frozen_quotes)
        $this->assertDatabaseHas('pric_frozen_quotes', [
            'quote_hash' => hash('sha256', "C2M:{$order->c2m_code}:{$order->final_price_idr}"),
            'sku' => "C2M-{$order->c2m_code}",
            'frozen_price_idr' => $order->final_price_idr,
        ]);
    }

    /**
     * Test 6: Audit dua sumber VMI & C2M (data bersih exit 0 + fixture korupsi exit non-0).
     */
    public function test_vmi_audit_clean_and_corruption_fixture(): void
    {
        // 1. Data bersih harus exit 0
        $exitClean = Artisan::call('vmi:audit');
        $this->assertSame(0, $exitClean);

        // 2. Terapkan fixture korupsi (auto-order over plafond tanpa approval)
        $fixture = new VmiAuditCorruptionFixture;
        $issueCode = $fixture->corrupt();
        $this->assertSame('vmi.auto_order_over_plafond', $issueCode);

        // 3. Command audit harus exit FAILURE (non-0)
        $exitCorrupt = Artisan::call('vmi:audit');
        $this->assertNotSame(0, $exitCorrupt);
    }
}
