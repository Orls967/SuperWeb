<?php

declare(strict_types=1);

namespace Tests\Perilaku\R2;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Modules\Agency\Application\Services\AgencyService;
use Modules\Agency\Domain\Models\Agent;
use Modules\Agency\Domain\Models\Payout;
use Modules\Contract\Domain\Enums\ContractStatus;
use Modules\Contract\Domain\Enums\ContractType;
use Modules\Contract\Domain\Models\Contract;
use Modules\Distribution\Application\Services\DistributionService;
use Modules\Distribution\Domain\Models\Distributor;
use Modules\Distribution\Domain\Models\Territory;
use Modules\Party\Domain\Models\LegalEntity;
use Modules\Party\Domain\Models\Party;
use Modules\Pricing\Application\Services\PricingService;
use Modules\Pricing\Domain\Models\PriceList;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('perilaku')]
class R2_3Test extends TestCase
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

    public function test_klausul_1_arch_scan_a13_nol_pelanggaran_back_errors(): void
    {
        // Klausul 1: Memastikan tidak ada back()->errors() yang memicu 500
        $code = Artisan::call('arch:scan', ['--json' => true]);
        $output = Artisan::output();
        $data = json_decode($output, true);

        $this->assertEquals(0, $code, 'Klausul 1: arch:scan wajib exit 0');
        $this->assertEquals(0, $data['rules']['A13']['count'] ?? -1, 'Klausul 1: Pelanggaran A13 harus 0');
    }

    public function test_klausul_2_jalur_galat_http_pricing_agency_distribution_redirect_back_dengan_errors_bukan_500(): void
    {
        // 1. Pricing Controller Error Path
        $priceList = PriceList::create([
            'code' => 'PL-TEST-01',
            'name' => 'Daftar Harga Test',
            'currency' => 'IDR',
            'status' => 'draft',
            'valid_from' => now(),
        ]);

        $mockPricing = Mockery::mock(PricingService::class);
        $mockPricing->shouldReceive('setPriceListItem')
            ->andThrow(new \InvalidArgumentException('Item harga tidak valid'));
        $this->app->instance(PricingService::class, $mockPricing);

        $resPricing = $this->actingAs($this->admin)
            ->from('/pricing')
            ->post('/pricing/price-lists/items', [
                'price_list_id' => $priceList->id,
                'sku' => 'SKU-ERR-01',
                'price_idr' => 100_000,
                'min_qty' => 1,
            ]);

        $resPricing->assertRedirect('/pricing');
        $resPricing->assertSessionHasErrors(['item']);
        $this->assertNotEquals(500, $resPricing->getStatusCode(), 'Pricing error path tidak boleh 500');

        // 2. Agency Controller Error Path
        $agent = Agent::create([
            'code' => 'AGT-ERR-01',
            'name' => 'Agen Galat',
            'kind' => 'individual',
            'status' => 'active',
        ]);
        $payout = Payout::create([
            'agent_id' => $agent->id,
            'number' => 'PO-ERR-001',
            'period' => '2026-10',
            'gross_idr' => 500_000,
            'net_idr' => 500_000,
            'status' => 'pending',
        ]);

        $mockAgency = Mockery::mock(AgencyService::class);
        $mockAgency->shouldReceive('approvePayout')
            ->andThrow(new \InvalidArgumentException('Saldo tidak mencukupi untuk payout'));
        $this->app->instance(AgencyService::class, $mockAgency);

        $resAgency = $this->actingAs($this->admin)
            ->from('/agency')
            ->post('/agency/payouts/'.$payout->id.'/approve');

        $resAgency->assertRedirect('/agency');
        $resAgency->assertSessionHasErrors(['payout']);
        $this->assertNotEquals(500, $resAgency->getStatusCode(), 'Agency error path tidak boleh 500');

        // 3. Distribution Controller Error Path
        $distributor = Distributor::create([
            'code' => 'DST-ERR-01',
            'name' => 'Distributor Galat',
            'kind' => 'distributor',
            'status' => 'active',
            'payment_terms_days' => 30,
            'credit_limit_idr' => 10_000_000,
        ]);
        $territory = Territory::create([
            'code' => 'TRT-ERR-01',
            'name' => 'Wilayah Barat',
            'level' => 'province',
        ]);

        $mockDist = Mockery::mock(DistributionService::class);
        $mockDist->shouldReceive('coverTerritory')
            ->andThrow(new \InvalidArgumentException('Territory konflik'));
        $this->app->instance(DistributionService::class, $mockDist);

        $resDist = $this->actingAs($this->admin)
            ->from('/distribution')
            ->post('/distribution/coverages', [
                'distributor_id' => $distributor->id,
                'territory_id' => $territory->id,
                'valid_from' => '2026-01-01',
            ]);

        $resDist->assertRedirect('/distribution');
        $resDist->assertSessionHasErrors(['coverage']);
        $this->assertNotEquals(500, $resDist->getStatusCode(), 'Distribution error path tidak boleh 500');
    }

    public function test_klausul_3_view_contract_clauses_create_dan_reports_render_sukses_200(): void
    {
        // Test M-R0-10: contract.clauses.create renders ok
        $resClauses = $this->actingAs($this->admin)->get(route('contract.clauses.create'));
        $resClauses->assertOk();

        // Test M-R0-11: contract.reports renders ok
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
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'total_value' => 100_000_000,
            'currency' => 'IDR',
        ]);

        $resReports = $this->actingAs($this->admin)->get(route('contract.reports'));
        $resReports->assertOk();
    }
}
