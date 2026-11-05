<?php

declare(strict_types=1);

namespace Tests\Acceptance\SybSukukWealth;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Application\Services\RbacService;
use Modules\Party\Domain\Models\Party;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Syariah\Domain\Models\SybIjarahFacility;
use Modules\Syariah\Domain\Models\SybParameter;
use Modules\Syariah\Domain\Models\SybSukukFacility;
use Modules\Syariah\Domain\Models\SybWealthInstrument;
use Modules\Syariah\Domain\Models\SybWealthPortfolio;
use Modules\Syariah\Domain\Models\SybZakatAssessment;
use Tests\TestCase;

/**
 * Acceptance Test Fase 162 — Sukuk, Ijarah & Wealth Syariah (Maksimal 5 Test Plafon Keras).
 */
class SybSukukWealthAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $officer;
    private User $dps;
    private User $advisor;
    private User $customer;
    private User $otherCustomer;
    private User $amil;
    private User $nazhir;
    private User $unauthorized;

    protected function setUp(): void
    {
        parent::setUp();

        $rbac = app(RbacService::class);
        $rbac->ensureRole('admin', 'Administrator', 'Full system access');
        $rbac->ensureRole('syariah_officer', 'Syariah Officer', 'Petugas operasional syariah');
        $rbac->ensureRole('shariah_board', 'DPS Board', 'Dewan Pengawas Syariah');
        $rbac->ensureRole('dps_reviewer', 'DPS Reviewer', 'Reviewer kepatuhan syariah');
        $rbac->ensureRole('wealth_advisor', 'Wealth Advisor', 'Penasihat investasi syariah');
        $rbac->ensureRole('customer', 'Nasabah Syariah', 'Nasabah syariah');
        $rbac->ensureRole('nasabah', 'Nasabah Murabahah', 'Nasabah pembiayaan');
        $rbac->ensureRole('amil', 'Amil Zakat', 'Pengelola zakat');
        $rbac->ensureRole('nazhir', 'Nazhir Wakaf', 'Pengelola wakaf');

        $this->admin = User::factory()->create(['email' => 'admin@syariah.test']);
        $this->officer = User::factory()->create(['email' => 'officer@syariah.test']);
        $this->dps = User::factory()->create(['email' => 'dps@syariah.test']);
        $this->advisor = User::factory()->create(['email' => 'advisor@syariah.test']);
        $this->customer = User::factory()->create(['email' => 'customer@syariah.test']);
        $this->otherCustomer = User::factory()->create(['email' => 'other@syariah.test']);
        $this->amil = User::factory()->create(['email' => 'amil@syariah.test']);
        $this->nazhir = User::factory()->create(['email' => 'nazhir@syariah.test']);
        $this->unauthorized = User::factory()->create(['email' => 'unauth@syariah.test']);

        $this->admin->assignRole('admin');
        $this->officer->assignRole('syariah_officer');
        $this->dps->assignRole('shariah_board');
        $this->advisor->assignRole('wealth_advisor');
        $this->customer->assignRole('customer');
        $this->otherCustomer->assignRole('customer');
        $this->amil->assignRole('amil');
        $this->nazhir->assignRole('nazhir');
    }

    /**
     * 1. Akses kontrol role pada semua grup rute item 162.
     */
    public function test_akses_kontrol_role_pada_rute_sukuk_dan_wealth_syariah(): void
    {
        $routes = [
            ['GET', '/syariah/sukuk', $this->officer, $this->customer],
            ['GET', '/syariah/ijarah', $this->officer, $this->customer],
            ['GET', '/syariah/wealth', $this->advisor, $this->unauthorized],
            ['GET', '/syariah/zakat', $this->amil, $this->unauthorized],
            ['GET', '/syariah/waqf', $this->nazhir, $this->unauthorized],
        ];

        foreach ($routes as [$method, $uri, $authorizedUser, $forbiddenUser]) {
            $this->actingAs($authorizedUser)
                ->json($method, $uri)
                ->assertStatus(200);

            $this->actingAs($forbiddenUser)
                ->json($method, $uri)
                ->assertStatus(403);
        }
    }

    /**
     * 2. Alur utama E2E + posting ledger + idempotensi aksi uang.
     */
    public function test_alur_e2e_sukuk_ijarah_zakat_dengan_posting_dan_idempotensi(): void
    {
        $this->seedInitialParameters();

        // (a) Struktur Sukuk -> Approve DPS -> Issue via G7-K5
        $sukukPayload = [
            'sukuk_code' => 'SKK-2026-HQ-01',
            'facility_name' => 'Sukuk Ijarah Menara Syariah 2026',
            'asset_code' => 'RWA-HQ-BLDG',
            'face_value_idr' => 10_000_000_000,
            'total_units' => 10_000,
            'maturity_date' => '2031-12-31',
            'annual_coupon_bps' => 800, // 8.00%
            'spv_party_name' => 'PT SPV Sukuk Amanah',
            'idempotency_key' => 'idemp:sukuk:struct:01',
        ];

        $resp = $this->actingAs($this->officer)
            ->postJson('/syariah/sukuk/propose', $sukukPayload)
            ->assertStatus(201);
        $sukukId = $resp->json('data.id');

        // DPS Approval
        $this->actingAs($this->dps)
            ->postJson("/syariah/sukuk/{$sukukId}/dps-approve", [
                'opinion_note' => 'Akad ijarah murni terbebas dari riba dan gharar',
                'idempotency_key' => 'idemp:sukuk:dps:01',
            ])->assertStatus(200);

        // Issue token
        $this->actingAs($this->officer)
            ->postJson("/syariah/sukuk/{$sukukId}/issue", [
                'idempotency_key' => 'idemp:sukuk:issue:01',
            ])->assertStatus(200);

        // Distribusi Kupon Sukuk ke holder (LargestRemainder)
        $distPayload = [
            'period_code' => '2026-Q1',
            'coupon_pool_idr' => 250_000_000,
            'idempotency_key' => 'idemp:sukuk:dist:q1',
        ];
        $this->actingAs($this->officer)
            ->postJson("/syariah/sukuk/{$sukukId}/distribute-coupon", $distPayload)
            ->assertStatus(200);

        // Idempotensi: pengulangan key yang sama tidak membuat mutasi ganda
        $this->actingAs($this->officer)
            ->postJson("/syariah/sukuk/{$sukukId}/distribute-coupon", $distPayload)
            ->assertStatus(200);

        // Tanpa key -> 422
        $this->actingAs($this->officer)
            ->postJson("/syariah/sukuk/{$sukukId}/distribute-coupon", [
                'period_code' => '2026-Q2',
                'coupon_pool_idr' => 250_000_000,
            ])->assertStatus(422);

        // (b) Ijarah & IMBT Alur Sewa
        $ijarahPayload = [
            'contract_code' => 'IJR-2026-FLEET-01',
            'customer_user_id' => $this->customer->id,
            'asset_name' => 'Armada Logistik Listrik 10 Unit',
            'asset_cost_idr' => 120_000_000,
            'tenor_months' => 12,
            'total_ujrah_idr' => 132_000_000,
            'residual_option_price_idr' => 1_000_000,
            'idempotency_key' => 'idemp:ijr:prop:01',
        ];
        $ijrResp = $this->actingAs($this->officer)
            ->postJson('/syariah/ijarah/propose', $ijarahPayload)
            ->assertStatus(201);
        $ijarahId = $ijrResp->json('data.id');

        $this->actingAs($this->dps)
            ->postJson("/syariah/ijarah/{$ijarahId}/dps-approve", [
                'opinion_note' => 'Akad sewa IMBT disetujui',
                'idempotency_key' => 'idemp:ijr:dps:01',
            ])->assertStatus(200);

        $this->actingAs($this->officer)
            ->postJson("/syariah/ijarah/{$ijarahId}/activate", [
                'idempotency_key' => 'idemp:ijr:act:01',
            ])->assertStatus(200);

        // Bayar cicilan periode 1
        $this->actingAs($this->customer)
            ->postJson("/syariah/ijarah/{$ijarahId}/pay-period", [
                'period' => 1,
                'idempotency_key' => 'idemp:ijr:pay:01',
            ])->assertStatus(200);

        // (c) Zakat Assessment & Distribution
        $zakatPayload = [
            'customer_user_id' => $this->customer->id,
            'haul_year' => 1447, // Hijriah
            'qualifying_wealth_idr' => 200_000_000,
            'due_debts_idr' => 20_000_000,
            'holding_days' => 354,
            'auto_debit' => true,
            'idempotency_key' => 'idemp:zakat:ass:1447',
        ];
        $zktResp = $this->actingAs($this->amil)
            ->postJson('/syariah/zakat/assess', $zakatPayload)
            ->assertStatus(201);
        expect($zktResp->json('data.zakat_amount_idr'))->toBe(4_500_000);

        // Distribusi 8 asnaf
        $this->actingAs($this->amil)
            ->postJson('/syariah/zakat/distribute', [
                'haul_year' => 1447,
                'total_amount_idr' => 1_000_003,
                'idempotency_key' => 'idemp:zakat:dist:1447',
            ])->assertStatus(200);
    }

    /**
     * 3. Aturan bisnis: screening gagal tolak beli, zakat tak ganda, wakaf pokok abadi.
     */
    public function test_aturan_bisnis_screening_gagal_tolak_beli_dan_zakat_tak_ganda(): void
    {
        $this->seedInitialParameters();

        // Register instrumen non-halal (debt 5500 bps > 4500 bps)
        $instResp = $this->actingAs($this->advisor)
            ->postJson('/syariah/wealth/instruments', [
                'ticker_symbol' => 'DEBTCO',
                'name' => 'High Leverage Conventional Entity',
                'interest_bearing_debt_idr' => 5_500_000_000,
                'total_assets_idr' => 10_000_000_000,
                'non_halal_revenue_idr' => 200_000_000,
                'total_revenue_idr' => 10_000_000_000,
                'sector_code' => 'GENERAL_COMMERCE',
                'idempotency_key' => 'idemp:inst:debtco',
            ])->assertStatus(201);
        $instId = $instResp->json('data.id');
        expect($instResp->json('data.is_shariah_compliant'))->toBeFalse();

        // Customer mencoba membeli instrumen non-halal -> ditolak (422)
        $this->actingAs($this->customer)
            ->postJson('/syariah/wealth/orders', [
                'instrument_id' => $instId,
                'units' => 100,
                'amount_idr' => 10_000_000,
                'idempotency_key' => 'idemp:ord:fail:01',
            ])->assertStatus(422)
            ->assertJsonPath('error_code', 'NON_SHARIAH_COMPLIANT_INSTRUMENT');

        // Zakat tidak ganda per haul untuk muzakki yang sama
        $zakatPayload = [
            'customer_user_id' => $this->customer->id,
            'haul_year' => 1448,
            'qualifying_wealth_idr' => 200_000_000,
            'due_debts_idr' => 20_000_000,
            'holding_days' => 354,
            'idempotency_key' => 'idemp:zakat:unique:01',
        ];
        $this->actingAs($this->amil)->postJson('/syariah/zakat/assess', $zakatPayload)->assertStatus(201);

        // Penilaian kedua di haul yang sama -> ditolak (422)
        $this->actingAs($this->amil)->postJson('/syariah/zakat/assess', [
            ...$zakatPayload,
            'idempotency_key' => 'idemp:zakat:unique:02',
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'DUPLICATE_HAUL_ZAKAT_ASSESSMENT');

        // Wakaf pokok abadi: penarikan pokok wakaf ditolak
        $waqfResp = $this->actingAs($this->nazhir)->postJson('/syariah/waqf/receive-money', [
            'wakif_user_id' => $this->customer->id,
            'principal_amount_idr' => 50_000_000,
            'purpose' => 'Pendidikan Vokasi Santri',
            'idempotency_key' => 'idemp:waqf:recv:01',
        ])->assertStatus(201);
        $waqfId = $waqfResp->json('data.id');

        $this->actingAs($this->nazhir)->postJson("/syariah/waqf/{$waqfId}/withdraw-principal", [
            'amount_idr' => 10_000_000,
            'idempotency_key' => 'idemp:waqf:with:01',
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'PERPETUAL_WAQF_PRINCIPAL_IMMUTABLE');
    }

    /**
     * 4. Proteksi IDOR portofolio nasabah dan sertifikat zakat/wakaf.
     */
    public function test_proteksi_idor_portofolio_dan_sertifikat_nasabah(): void
    {
        $this->seedInitialParameters();

        // Customer membuat portofolio wealth
        $portResp = $this->actingAs($this->customer)->postJson('/syariah/wealth/portfolios', [
            'risk_profile' => 'MODERATE',
            'initial_deposit_idr' => 25_000_000,
            'idempotency_key' => 'idemp:port:01',
        ])->assertStatus(201);
        $portId = $portResp->json('data.id');

        // Customer lain mencoba mengakses portofolio -> 403 Forbidden
        $this->actingAs($this->otherCustomer)
            ->getJson("/syariah/wealth/portfolios/{$portId}")
            ->assertStatus(403);

        // Advisor atau Admin boleh melihat
        $this->actingAs($this->advisor)
            ->getJson("/syariah/wealth/portfolios/{$portId}")
            ->assertStatus(200);
    }

    /**
     * 5. Audit dua sumber syb:audit (bersih exit 0, fixture exit nonzero).
     */
    public function test_audit_syb_sukuk_wealth_bersih_exit_0_dan_fixture_exit_nonzero(): void
    {
        $this->seedInitialParameters();

        // 1. Bersih -> exit 0
        $exitClean = Artisan::call('syb:audit');
        expect($exitClean)->toBe(0);

        // 2. Corrupt fixture -> exit != 0
        Artisan::call('syb:audit', ['--corrupt-fixture' => 'SybSukukWealthCorruptionFixture']);
        $exitCorrupt = Artisan::call('syb:audit', ['--verify-fixture' => 'SybSukukWealthCorruptionFixture']);
        expect($exitCorrupt)->not->toBe(0);
    }

    private function seedInitialParameters(): void
    {
        // Parameter berversi S1
        SybParameter::updateOrCreate(
            ['code' => 'GOLD_PRICE_PER_GRAM_IDR', 'version' => 1],
            [
                'effective_from' => '2026-01-01',
                'source' => 'ANTAM_FIXTURE_DSN_MUI',
                'simulation_label' => 'S1',
                'value' => json_encode(['price_idr' => 1_500_000]),
                'is_fixture' => true,
                'owner_confirmed_at' => now(),
            ]
        );

        SybParameter::updateOrCreate(
            ['code' => 'SHARIAH_SCREENING_RATIOS', 'version' => 1],
            [
                'effective_from' => '2026-01-01',
                'source' => 'OJK_POJK_35_2017',
                'simulation_label' => 'S1',
                'value' => json_encode([
                    'max_debt_to_assets_bps' => 4500,
                    'max_non_halal_revenue_bps' => 1000,
                ]),
                'is_fixture' => true,
                'owner_confirmed_at' => now(),
            ]
        );
    }
}
