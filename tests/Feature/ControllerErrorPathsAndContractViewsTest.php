<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Agency\Application\Services\AgencyService;
use Modules\Agency\Domain\Models\Agent;
use Modules\Agency\Domain\Models\Payout;
use Modules\Contract\Domain\Enums\ContractStatus;
use Modules\Contract\Domain\Enums\ContractType;
use Modules\Contract\Domain\Models\Contract;
use Modules\Distribution\Application\Services\DistributionService;
use Modules\Distribution\Domain\Models\ArInvoice;
use Modules\Distribution\Domain\Models\Distributor;
use Modules\Distribution\Domain\Models\Territory;
use Modules\Party\Domain\Models\LegalEntity;
use Modules\Party\Domain\Models\Party;
use Modules\Pricing\Application\Services\PricingService;
use Modules\Pricing\Domain\Models\PriceList;
use Modules\Pricing\Domain\Models\PriceOverride;
use Modules\Pricing\Domain\Models\Promotion;
use Modules\Pricing\Domain\Models\PromotionClaim;
use Tests\TestCase;

class ControllerErrorPathsAndContractViewsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    // ── Contract Views (M-R0-10 & M-R0-11) ──────────────────────────────────

    public function test_contract_clauses_create_renders_successfully(): void
    {
        $response = $this->actingAs($this->admin)->get(route('contract.clauses.create'));

        $response->assertOk();
        $response->assertSee('Tambah Klausul Baru');
    }

    public function test_contract_reports_renders_successfully_with_active_contract(): void
    {
        $le = LegalEntity::create([
            'name' => 'PT Semesta Kontrak',
            'short_name' => 'PSK',
            'entity_type' => 'company',
            'functional_currency' => 'IDR',
            'fiscal_year_start' => '01-01',
            'is_active' => true,
        ]);

        $party = Party::create([
            'type' => 'company',
            'name' => 'PT Mitra Terpercaya',
            'name_normalized' => 'pt mitra terpercaya',
            'status' => 'verified',
            'is_active' => true,
        ]);

        Contract::create([
            'legal_entity_id' => $le->id,
            'contract_number' => 'CTR-2026-TEST-001',
            'title' => 'Kontrak Layanan Terverifikasi',
            'contract_type' => ContractType::Service,
            'status' => ContractStatus::Active,
            'total_value_idr' => 500_000_000,
            'effective_date' => now()->subMonth(),
            'end_date' => now()->addYear(),
            'created_by' => $this->admin->id,
            'created_by_name' => $this->admin->name,
            'parties_data' => [
                ['party_id' => $party->id, 'role' => 'vendor', 'name' => $party->name],
            ],
        ]);

        $response = $this->actingAs($this->admin)->get(route('contract.reports'));

        $response->assertOk();
        $response->assertSee('Nilai per Jenis Kontrak');
        $response->assertSee('Eksposur per Pihak');
    }

    // ── AgencyController Error Paths (1 path) ───────────────────────────────

    public function test_agency_approve_payout_redirects_with_errors_on_domain_exception(): void
    {
        $agent = Agent::create([
            'code' => 'AG-001',
            'name' => 'Agent One',
            'kind' => 'sales_agent',
            'status' => 'active',
        ]);

        $payout = Payout::create([
            'agent_id' => $agent->id,
            'number' => 'PAY-001',
            'period' => '2026-10',
            'gross_idr' => 1_000_000,
            'withheld_tax_idr' => 25_000,
            'net_idr' => 975_000,
            'status' => 'pending',
            'created_by_user_id' => $this->admin->id,
        ]);

        $mock = Mockery::mock(AgencyService::class);
        $mock->shouldReceive('approvePayout')
            ->once()
            ->andThrow(new \InvalidArgumentException('Payout tidak dapat disetujui'));
        $this->app->instance(AgencyService::class, $mock);

        $response = $this->actingAs($this->admin)
            ->from('/agency')
            ->post(route('agency.payouts.approve', $payout));

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['payout' => 'Payout tidak dapat disetujui']);
    }

    // ── DistributionController Error Paths (7 paths) ────────────────────────

    public function test_distribution_cover_territory_redirects_with_errors_on_domain_exception(): void
    {
        $distributor = Distributor::create([
            'code' => 'DIST-001',
            'name' => 'Distributor One',
            'kind' => 'distributor',
            'status' => 'approved',
        ]);

        $territory = Territory::create([
            'code' => 'TER-001',
            'name' => 'Jawa Barat',
            'type' => 'province',
        ]);

        $mock = Mockery::mock(DistributionService::class);
        $mock->shouldReceive('coverTerritory')
            ->once()
            ->andThrow(new \InvalidArgumentException('Wilayah tumpang tindih'));
        $this->app->instance(DistributionService::class, $mock);

        $response = $this->actingAs($this->admin)
            ->from('/distribution')
            ->post(route('distribution.coverages.store'), [
                'distributor_id' => $distributor->id,
                'territory_id' => $territory->id,
                'valid_from' => '2026-01-01',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['coverage' => 'Wilayah tumpang tindih']);
    }

    public function test_distribution_submit_onboarding_redirects_with_errors_on_domain_exception(): void
    {
        $distributor = Distributor::create([
            'code' => 'DIST-002',
            'name' => 'Distributor Two',
            'kind' => 'distributor',
            'status' => 'approved',
        ]);

        $mock = Mockery::mock(DistributionService::class);
        $mock->shouldReceive('submitOnboarding')
            ->once()
            ->andThrow(new \InvalidArgumentException('Distributor bukan dalam status onboarding'));
        $this->app->instance(DistributionService::class, $mock);

        $response = $this->actingAs($this->admin)
            ->from('/distribution')
            ->post(route('distribution.onboarding.submit', $distributor));

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['onboarding' => 'Distributor bukan dalam status onboarding']);
    }

    public function test_distribution_approve_onboarding_redirects_with_errors_on_domain_exception(): void
    {
        $distributor = Distributor::create([
            'code' => 'DIST-003',
            'name' => 'Distributor Three',
            'kind' => 'distributor',
            'status' => 'onboarding',
        ]);

        $mock = Mockery::mock(DistributionService::class);
        $mock->shouldReceive('approveOnboarding')
            ->once()
            ->andThrow(new \InvalidArgumentException('Distributor belum diajukan approval'));
        $this->app->instance(DistributionService::class, $mock);

        $response = $this->actingAs($this->admin)
            ->from('/distribution')
            ->post(route('distribution.onboarding.approve', $distributor), [
                'credit_limit_idr' => 50_000_000,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['onboarding' => 'Distributor belum diajukan approval']);
    }

    public function test_distribution_transition_redirects_with_errors_on_domain_exception(): void
    {
        $distributor = Distributor::create([
            'code' => 'DIST-004',
            'name' => 'Distributor Four',
            'kind' => 'distributor',
            'status' => 'onboarding',
        ]);

        $mock = Mockery::mock(DistributionService::class);
        $mock->shouldReceive('transition')
            ->once()
            ->andThrow(new \InvalidArgumentException('Transisi status tidak valid'));
        $this->app->instance(DistributionService::class, $mock);

        $response = $this->actingAs($this->admin)
            ->from('/distribution')
            ->post(route('distribution.transition', $distributor), [
                'to' => 'terminated',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['status' => 'Transisi status tidak valid']);
    }

    public function test_distribution_store_invoice_redirects_with_errors_on_domain_exception(): void
    {
        $distributor = Distributor::create([
            'code' => 'DIST-005',
            'name' => 'Distributor Five',
            'kind' => 'distributor',
            'status' => 'suspended',
        ]);

        $mock = Mockery::mock(DistributionService::class);
        $mock->shouldReceive('issueInvoice')
            ->once()
            ->andThrow(new \InvalidArgumentException('Distributor sedang ditangguhkan'));
        $this->app->instance(DistributionService::class, $mock);

        $response = $this->actingAs($this->admin)
            ->from('/distribution')
            ->post(route('distribution.invoices.store'), [
                'distributor_id' => $distributor->id,
                'amount_idr' => 10_000_000,
                'invoice_date' => '2026-10-01',
                'due_date' => '2026-10-31',
                'source_type' => 'sales',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['invoice' => 'Distributor sedang ditangguhkan']);
    }

    public function test_distribution_store_payment_redirects_with_errors_on_domain_exception(): void
    {
        $distributor = Distributor::create([
            'code' => 'DIST-006',
            'name' => 'Distributor Six',
            'kind' => 'distributor',
            'status' => 'approved',
        ]);

        $invoice = ArInvoice::create([
            'distributor_id' => $distributor->id,
            'number' => 'INV-001',
            'amount_idr' => 5_000_000,
            'balance_idr' => 5_000_000,
            'invoice_date' => '2026-10-01',
            'due_date' => '2026-10-31',
            'source_type' => 'sales',
            'status' => 'issued',
            'created_by_user_id' => $this->admin->id,
        ]);

        $mock = Mockery::mock(DistributionService::class);
        $mock->shouldReceive('payInvoice')
            ->once()
            ->andThrow(new \InvalidArgumentException('Pembayaran melebihi sisa tagihan'));
        $this->app->instance(DistributionService::class, $mock);

        $response = $this->actingAs($this->admin)
            ->from('/distribution')
            ->post(route('distribution.invoices.pay'), [
                'invoice_id' => $invoice->id,
                'amount_idr' => 6_000_000,
                'paid_at' => '2026-10-15',
                'method' => 'transfer',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['payment' => 'Pembayaran melebihi sisa tagihan']);
    }

    public function test_distribution_store_outlet_redirects_with_errors_on_domain_exception(): void
    {
        $distributor = Distributor::create([
            'code' => 'DIST-007',
            'name' => 'Distributor Seven',
            'kind' => 'distributor',
            'status' => 'approved',
        ]);

        $mock = Mockery::mock(DistributionService::class);
        $mock->shouldReceive('addOutlet')
            ->once()
            ->andThrow(new \InvalidArgumentException('Kode outlet sudah terdaftar'));
        $this->app->instance(DistributionService::class, $mock);

        $response = $this->actingAs($this->admin)
            ->from('/distribution')
            ->post(route('distribution.outlets.store', $distributor), [
                'code' => 'OUT-001',
                'name' => 'Toko Barokah',
                'segment' => 'retail',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['outlet' => 'Kode outlet sudah terdaftar']);
    }

    // ── PricingController Error Paths (7 paths) ─────────────────────────────

    public function test_pricing_store_price_list_item_redirects_with_errors_on_domain_exception(): void
    {
        $list = PriceList::create([
            'code' => 'PL-001',
            'name' => 'Daftar Harga Umum',
            'channel' => 'general',
            'currency' => 'IDR',
            'status' => 'draft',
            'valid_from' => '2026-01-01',
            'created_by_user_id' => $this->admin->id,
        ]);

        $mock = Mockery::mock(PricingService::class);
        $mock->shouldReceive('setPriceListItem')
            ->once()
            ->andThrow(new \InvalidArgumentException('Item harga tidak valid'));
        $this->app->instance(PricingService::class, $mock);

        $response = $this->actingAs($this->admin)
            ->from('/pricing')
            ->post(route('pricing.price-lists.items.store'), [
                'price_list_id' => $list->id,
                'sku' => 'SKU-001',
                'price_idr' => 15_000,
                'min_qty' => 1,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['item' => 'Item harga tidak valid']);
    }

    public function test_pricing_activate_price_list_redirects_with_errors_on_domain_exception(): void
    {
        $list = PriceList::create([
            'code' => 'PL-002',
            'name' => 'Daftar Harga Kosong',
            'channel' => 'general',
            'currency' => 'IDR',
            'status' => 'draft',
            'valid_from' => '2026-01-01',
            'created_by_user_id' => $this->admin->id,
        ]);

        $mock = Mockery::mock(PricingService::class);
        $mock->shouldReceive('activatePriceList')
            ->once()
            ->andThrow(new \InvalidArgumentException('Price list kosong tidak dapat diaktifkan'));
        $this->app->instance(PricingService::class, $mock);

        $response = $this->actingAs($this->admin)
            ->from('/pricing')
            ->post(route('pricing.price-lists.activate', $list));

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['list' => 'Price list kosong tidak dapat diaktifkan']);
    }

    public function test_pricing_store_claim_redirects_with_errors_on_domain_exception(): void
    {
        $promo = Promotion::create([
            'code' => 'PRM-001',
            'name' => 'Promo Diskon 10%',
            'mechanic' => 'off_invoice',
            'budget_idr' => 10_000_000,
            'spent_idr' => 0,
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
            'status' => 'active',
        ]);

        $mock = Mockery::mock(PricingService::class);
        $mock->shouldReceive('submitPromotionClaim')
            ->once()
            ->andThrow(new \InvalidArgumentException('Klaim melebihi sisa anggaran'));
        $this->app->instance(PricingService::class, $mock);

        $response = $this->actingAs($this->admin)
            ->from('/pricing')
            ->post(route('pricing.promotions.claims.store'), [
                'promotion_id' => $promo->id,
                'source_ref' => 'REF-001',
                'amount_idr' => 15_000_000,
                'evidence_note' => 'Bukti klaim promo',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['claim' => 'Klaim melebihi sisa anggaran']);
    }

    public function test_pricing_validate_claim_redirects_with_errors_on_domain_exception(): void
    {
        $promo = Promotion::create([
            'code' => 'PRM-002',
            'name' => 'Promo Diskon 5%',
            'mechanic' => 'off_invoice',
            'budget_idr' => 5_000_000,
            'spent_idr' => 0,
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
            'status' => 'active',
        ]);

        $claim = PromotionClaim::create([
            'promotion_id' => $promo->id,
            'source_ref' => 'REF-002',
            'amount_idr' => 1_000_000,
            'status' => 'settled',
            'evidence_note' => 'Bukti klaim validasi',
        ]);

        $mock = Mockery::mock(PricingService::class);
        $mock->shouldReceive('approvePromotionClaim')
            ->once()
            ->andThrow(new \InvalidArgumentException('Klaim sudah diselesaikan sebelumnya'));
        $this->app->instance(PricingService::class, $mock);

        $response = $this->actingAs($this->admin)
            ->from('/pricing')
            ->post(route('pricing.claims.validate', $claim));

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['claim' => 'Klaim sudah diselesaikan sebelumnya']);
    }

    public function test_pricing_settle_claim_redirects_with_errors_on_domain_exception(): void
    {
        $promo = Promotion::create([
            'code' => 'PRM-003',
            'name' => 'Promo Diskon 15%',
            'mechanic' => 'off_invoice',
            'budget_idr' => 20_000_000,
            'spent_idr' => 0,
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
            'status' => 'active',
        ]);

        $claim = PromotionClaim::create([
            'promotion_id' => $promo->id,
            'source_ref' => 'REF-003',
            'amount_idr' => 2_000_000,
            'status' => 'submitted',
            'evidence_note' => 'Bukti klaim settle',
        ]);

        $mock = Mockery::mock(PricingService::class);
        $mock->shouldReceive('settlePromotionClaim')
            ->once()
            ->andThrow(new \InvalidArgumentException('Klaim belum disetujui untuk settlement'));
        $this->app->instance(PricingService::class, $mock);

        $response = $this->actingAs($this->admin)
            ->from('/pricing')
            ->post(route('pricing.claims.settle', $claim));

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['claim' => 'Klaim belum disetujui untuk settlement']);
    }

    public function test_pricing_store_override_redirects_with_errors_on_domain_exception(): void
    {
        $mock = Mockery::mock(PricingService::class);
        $mock->shouldReceive('requestOverride')
            ->once()
            ->andThrow(new \InvalidArgumentException('Harga melanggar batas floor price'));
        $this->app->instance(PricingService::class, $mock);

        $response = $this->actingAs($this->admin)
            ->from('/pricing')
            ->post(route('pricing.overrides.store'), [
                'sku' => 'SKU-LOW',
                'channel' => 'general',
                'proposed_price_idr' => 1_000,
                'reason' => 'Permintaan diskon khusus proyek',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['override' => 'Harga melanggar batas floor price']);
    }

    public function test_pricing_decide_override_redirects_with_errors_on_domain_exception(): void
    {
        $override = PriceOverride::create([
            'sku' => 'SKU-002',
            'channel' => 'general',
            'proposed_price_idr' => 5_000,
            'floor_price_idr' => 4_000,
            'margin_percent' => 20.0,
            'status' => 'approved',
            'reason' => 'Pengajuan override',
            'requested_by_user_id' => $this->admin->id,
        ]);

        $mock = Mockery::mock(PricingService::class);
        $mock->shouldReceive('decideOverride')
            ->once()
            ->andThrow(new \InvalidArgumentException('Override sudah diputuskan'));
        $this->app->instance(PricingService::class, $mock);

        $response = $this->actingAs($this->admin)
            ->from('/pricing')
            ->post(route('pricing.overrides.decide', $override), [
                'decision' => 'approve',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['override' => 'Override sudah diputuskan']);
    }
}
