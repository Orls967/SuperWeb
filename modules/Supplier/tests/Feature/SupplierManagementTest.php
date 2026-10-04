<?php

declare(strict_types=1);

namespace Tests\Feature\Supplier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Core\Contracts\DocumentStoreInterface;
use Modules\Core\Database\Seeders\RbacSeeder;
use Modules\Party\database\seeders\PartySeeder;
use Modules\Party\Domain\Models\LegalEntity;
use Modules\Supplier\Application\Services\SupplierService;
use Modules\Supplier\Domain\Enums\SupplierStatus;
use Modules\Supplier\Domain\Models\Supplier;
use Modules\Supplier\Domain\Models\SupplierCertification;
use Modules\Supplier\Domain\Models\SupplierItem;
use Modules\Supplier\Domain\Models\SupplierPriceTier;
use Modules\Supplier\Domain\Models\SupplierRiskFlag;
use Modules\Supplier\Domain\Models\SupplierScorecard;
use Modules\Supplier\Domain\Models\SupplierStatusHistory;
use Modules\Supplier\Http\Controllers\SupplierPortalController;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    private SupplierService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // RBAC + platform seed ensures permission/numbering/approval tables exist.
        $this->seed(RbacSeeder::class);
        $this->seed(PartySeeder::class);
        $this->seed(BankingSeeder::class);

        $this->service = app(SupplierService::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    // ── 32.1 Supplier profile ───────────────────────────────────────────

    public function test_register_supplier_generates_gapless_code_and_candidate_status(): void
    {
        $supplier = $this->service->register([
            'name' => 'PT Pemasok Uji',
            'kind' => 'producer',
            'lead_time_days' => 12,
            'payment_terms_days' => 45,
            'capabilities' => ['bahan baku', 'pangan beku'],
            'factory_locations' => ['Bekasi'],
        ], $this->admin);

        $this->assertStringStartsWith('SUP/', $supplier->code);
        $this->assertSame(SupplierStatus::Candidate, $supplier->status);
        $this->assertSame(12, $supplier->lead_time_days);
        $this->assertSame(['bahan baku', 'pangan beku'], $supplier->capabilities);
    }

    public function test_supplier_directory_route_is_available_to_procurement_but_not_customer(): void
    {
        $procurement = User::factory()->create(['role' => 'procurement']);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($procurement)->get(route('supplier.index'))->assertOk();
        $this->actingAs($customer)->get(route('supplier.index'))->assertForbidden();
    }

    // ── 32.2 Qualification + approval status machine ────────────────────

    public function test_qualification_uses_approval_engine_and_pass_sets_pass_result(): void
    {
        $supplier = $this->service->register(['name' => 'PT Kualifikasi', 'kind' => 'supplier'], $this->admin);

        $qualification = $this->service->submitQualification(
            supplier: $supplier,
            type: 'site_audit',
            answers: ['facility' => 'ok'],
            scores: ['quality' => 85, 'safety' => 90],
            assessor: $this->admin,
            notes: 'audit fixture',
        );

        $this->assertSame(88, $qualification->total_score);
        $this->assertSame('pass', $qualification->result);
        $this->assertNotNull($qualification->approval_id);
        $this->assertSame('pending', $qualification->approval_status);

        $this->service->approveQualification($qualification, $this->admin);

        $this->assertSame('approved', $qualification->fresh()->approval_status);
        $this->assertSame(SupplierStatus::Approved, $supplier->fresh()->status);
    }

    public function test_supplier_status_transition_requires_valid_state_and_reason(): void
    {
        $supplier = $this->service->register(['name' => 'PT Status', 'kind' => 'supplier'], $this->admin);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->transition($supplier, SupplierStatus::Preferred, 'loncat status', $this->admin);
    }

    public function test_disqualified_supplier_can_only_reenter_as_candidate(): void
    {
        $supplier = $this->service->register(['name' => 'PT Re-Onboarding', 'kind' => 'supplier'], $this->admin);
        $approved = $this->service->transition($supplier, SupplierStatus::Approved, 'lulus audit', $this->admin);
        $disqualified = $this->service->transition($approved, SupplierStatus::Disqualified, 'pelanggaran mutu', $this->admin);
        $candidate = $this->service->transition($disqualified, SupplierStatus::Candidate, 're-onboarding', $this->admin);

        $this->assertSame(SupplierStatus::Candidate, $candidate->status);
        $this->assertSame(3, SupplierStatusHistory::where('supplier_id', $supplier->id)->count());
    }

    // ── 32.3 Price tiers / MOQ / valid period ────────────────────────────

    public function test_resolve_price_uses_highest_applicable_tier_and_moq(): void
    {
        $supplier = $this->service->register(['name' => 'PT Harga', 'kind' => 'supplier'], $this->admin);
        $item = SupplierItem::create([
            'supplier_id' => $supplier->id, 'supplier_sku' => 'SKU-1', 'name' => 'Tepung',
            'unit' => 'kg', 'moq' => 10, 'lead_time_days' => 3, 'currency' => 'IDR', 'is_active' => true,
        ]);

        SupplierPriceTier::create([
            'item_id' => $item->id, 'min_qty' => 10, 'max_qty' => 49,
            'unit_price' => '10000.0000', 'currency' => 'IDR',
            'valid_from' => now()->subDay()->toDateString(), 'valid_to' => now()->addMonth()->toDateString(), 'is_active' => true,
        ]);
        SupplierPriceTier::create([
            'item_id' => $item->id, 'min_qty' => 50, 'max_qty' => null,
            'unit_price' => '9000.0000', 'currency' => 'IDR',
            'valid_from' => now()->subDay()->toDateString(), 'valid_to' => null, 'is_active' => true,
        ]);

        $this->assertEqualsWithDelta(10000.0, (float) $this->service->resolvePrice($item, 20)['unit_price'], 0.0001);
        $this->assertEqualsWithDelta(9000.0, (float) $this->service->resolvePrice($item, 60)['unit_price'], 0.0001);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->resolvePrice($item, 5);
    }

    public function test_expired_tier_is_not_selected(): void
    {
        $supplier = $this->service->register(['name' => 'PT Expired Price', 'kind' => 'supplier'], $this->admin);
        $item = SupplierItem::create([
            'supplier_id' => $supplier->id, 'supplier_sku' => 'SKU-X', 'name' => 'Beras', 'moq' => 1,
            'currency' => 'IDR', 'is_active' => true,
        ]);
        SupplierPriceTier::create([
            'item_id' => $item->id, 'min_qty' => 1, 'unit_price' => '12000.0000', 'currency' => 'IDR',
            'valid_from' => now()->subYear()->toDateString(), 'valid_to' => now()->subDay()->toDateString(), 'is_active' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->resolvePrice($item, 10);
    }

    // ── 32.4 Contract framework price beats catalog price ──────────────

    public function test_contract_tier_beats_catalog_tier(): void
    {
        $supplier = $this->service->register(['name' => 'PT Kontrak', 'kind' => 'supplier'], $this->admin);
        $item = SupplierItem::create([
            'supplier_id' => $supplier->id, 'supplier_sku' => 'SKU-C', 'name' => 'Cabe merah',
            'moq' => 1, 'currency' => 'IDR', 'is_active' => true,
        ]);

        // Katalog: 10.000
        SupplierPriceTier::create([
            'item_id' => $item->id, 'min_qty' => 1, 'unit_price' => '10000.0000', 'currency' => 'IDR',
            'valid_from' => now()->subDay()->toDateString(), 'valid_to' => null, 'is_active' => true,
        ]);

        $legalEntity = LegalEntity::firstOrCreate(
            ['name' => 'PT Uji Kontrak Harga'],
            ['short_name' => 'UKH', 'entity_type' => 'company', 'functional_currency' => 'IDR', 'fiscal_year_start' => '01-01', 'is_active' => true],
        );

        $contractId = (string) Str::uuid();
        DB::table('ctr_contracts')->insert([
            'id' => $contractId, 'contract_number' => 'CTR/TEST/2026-00001', 'title' => 'Kontrak Kerangka Cabe',
            'contract_type' => 'purchase', 'status' => 'active', 'legal_entity_id' => $legalEntity->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Kontrak: 8.500
        SupplierPriceTier::create([
            'item_id' => $item->id, 'contract_id' => $contractId, 'min_qty' => 1,
            'unit_price' => '8500.0000', 'currency' => 'IDR',
            'valid_from' => now()->subDay()->toDateString(), 'valid_to' => null, 'is_active' => true,
        ]);

        // Tanpa konteks kontrak → harga katalog.
        $catalog = $this->service->resolvePrice($item, 5);
        $this->assertEqualsWithDelta(10000.0, (float) $catalog['unit_price'], 0.0001);
        $this->assertSame('catalog', $catalog['source']);

        // Dengan konteks kontrak → harga kontrak mengalahkan (32.4).
        $contractPrice = $this->service->resolvePrice($item, 5, ['contract_id' => $contractId]);
        $this->assertEqualsWithDelta(8500.0, (float) $contractPrice['unit_price'], 0.0001);
        $this->assertStringStartsWith('contract:', $contractPrice['source']);
    }

    // ── 32.6 Scorecard and corrective action ─────────────────────────────

    public function test_scorecard_calculates_metrics_and_action_threshold(): void
    {
        $supplier = $this->service->register(['name' => 'PT Score', 'kind' => 'supplier'], $this->admin);

        $card = $this->service->saveScorecard($supplier, '2026-10', [
            'otd_percent' => 90,
            'reject_percent' => 10,
            'price_index' => 1.15,
            'response_days' => 5,
        ], 'scorecard fixture');

        $this->assertSame(90, $card->otd_score);
        $this->assertSame(90, $card->quality_score);
        $this->assertSame(85, $card->price_score);
        $this->assertSame(75, $card->responsiveness_score);
        $this->assertSame(85, $card->overall_score);
        $this->assertSame('none', $card->action);

        // Threshold action at low overall score.
        $low = $this->service->score('2026-10', ['otd_percent' => 20, 'reject_percent' => 60, 'price_index' => 2, 'response_days' => 15]);
        $this->assertSame('scar', $low['action']);
    }

    public function test_scorecard_upsert_is_idempotent_per_supplier_period(): void
    {
        $supplier = $this->service->register(['name' => 'PT Score Replay', 'kind' => 'supplier'], $this->admin);
        $this->service->saveScorecard($supplier, '2026-10', ['otd_percent' => 90]);
        $this->service->saveScorecard($supplier, '2026-10', ['otd_percent' => 80]);

        $this->assertSame(1, SupplierScorecard::where('supplier_id', $supplier->id)->where('period', '2026-10')->count());
        $this->assertSame(80, SupplierScorecard::where('supplier_id', $supplier->id)->where('period', '2026-10')->value('otd_score'));
    }

    // ── 32.5 Supplier portal (ASN, docs) + IDOR safety ──────────────────

    public function test_supplier_portal_requires_supplier_role(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->get(route('supplier.portal.home'))->assertForbidden();
    }

    public function test_portal_owner_resolution_is_isolated_per_user(): void
    {
        $supplierA = $this->service->register(['name' => 'PT Pemasok A', 'kind' => 'supplier'], $this->admin);
        $supplierB = $this->service->register(['name' => 'PT Pemasok B', 'kind' => 'supplier'], $this->admin);

        $userA = User::factory()->create(['role' => 'supplier']);
        $userB = User::factory()->create(['role' => 'supplier']);

        $supplierA->update(['owner_user_id' => $userA->id]);
        $supplierB->update(['owner_user_id' => $userB->id]);

        $controller = new SupplierPortalController(
            app(DocumentStoreInterface::class),
            app(DocumentNumberingInterface::class),
        );

        $reflection = new \ReflectionMethod($controller, 'ownSupplier');
        $reflection->setAccessible(true);

        $requestA = new Request;
        $requestA->setUserResolver(fn () => $userA);

        $requestB = new Request;
        $requestB->setUserResolver(fn () => $userB);

        $resolvedA = $reflection->invoke($controller, $requestA);
        $resolvedB = $reflection->invoke($controller, $requestB);

        $this->assertSame($supplierA->id, $resolvedA->id, 'Pemasok A hanya boleh melihat pemasoknya sendiri.');
        $this->assertSame($supplierB->id, $resolvedB->id, 'Pemasok B hanya boleh melihat pemasoknya sendiri.');
        $this->assertNotSame($resolvedA->id, $resolvedB->id);
    }

    public function test_asn_creation_and_ship_transition(): void
    {
        $user = User::factory()->create(['role' => 'supplier']);
        $supplier = $this->service->register(['name' => 'PT ASN', 'kind' => 'supplier'], $this->admin);
        $supplier->update(['owner_user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('supplier.portal.asn.store'), [
            'ship_date' => now()->toDateString(),
            'expected_arrival' => now()->addDays(3)->toDateString(),
            'lines_json' => json_encode([['sku' => 'ASN-1', 'qty' => 5]]),
        ]);
        $response->assertSessionHas('success');

        $asn = $supplier->asns()->firstOrFail();
        $this->assertSame('draft', $asn->status);
        $this->assertCount(1, $asn->lines);

        $this->actingAs($user)->post(route('supplier.portal.asn.ship', $asn))->assertSessionHas('success');
        $this->assertSame('shipped', $asn->fresh()->status);
    }

    public function test_document_upload_uses_document_store_with_checksum(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'supplier']);
        $supplier = $this->service->register(['name' => 'PT Dokumen', 'kind' => 'supplier'], $this->admin);
        $supplier->update(['owner_user_id' => $user->id]);

        $file = UploadedFile::fake()->create('coa.pdf', 50, 'application/pdf');

        $this->actingAs($user)->post(route('supplier.portal.documents.store'), [
            'file' => $file, 'kind' => 'coa', 'label' => 'COA Batch 1',
        ])->assertSessionHas('success');

        $doc = $supplier->documents()->firstOrFail();
        $this->assertSame('coa', $doc->kind);
        $this->assertNotNull($doc->document_id);
    }

    // ── 32.7 Risk flags (sanctions / certificates / concentration) ──────

    public function test_flag_risk_is_idempotent_and_can_be_resolved(): void
    {
        $supplier = $this->service->register(['name' => 'PT Risk', 'kind' => 'supplier'], $this->admin);
        $flag1 = $this->service->flag($supplier, 'single_source', 'medium', 'only provider', ['sku' => 'X']);
        $flag2 = $this->service->flag($supplier, 'single_source', 'medium', 'only provider', ['sku' => 'X']);

        $this->assertSame($flag1->id, $flag2->id);
        $this->assertSame(1, SupplierRiskFlag::where('supplier_id', $supplier->id)->where('is_open', true)->count());

        $resolved = $this->service->resolveFlag($flag1, 'Diversified');
        $this->assertFalse($resolved->is_open);
        $this->assertNotNull($resolved->resolved_at);
    }

    public function test_expiring_certificate_opens_risk_flag(): void
    {
        $supplier = $this->service->register(['name' => 'PT Sertifikasi', 'kind' => 'supplier'], $this->admin);
        SupplierCertification::create([
            'supplier_id' => $supplier->id, 'type' => 'halal', 'number' => 'HAL-1',
            'expires_at' => now()->addDays(20)->toDateString(), 'is_active' => true,
        ]);

        $flags = $this->service->scanRisks();
        $this->assertNotEmpty($flags);
        $this->assertSame(1, SupplierRiskFlag::where('supplier_id', $supplier->id)->where('type', 'expiring_cert')->count());
    }
}
