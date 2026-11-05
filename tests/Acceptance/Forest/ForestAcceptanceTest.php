<?php

declare(strict_types=1);

namespace Tests\Acceptance\Forest;

use App\Models\User;
use App\Quality\Audit\Fixtures\ForestAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Forest\Application\Services\ForestConcessionService;
use Modules\Forest\Domain\Models\ForestTimberSale;
use RuntimeException;
use Tests\TestCase;

final class ForestAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => 'LedgerAccountsSeeder']);
    }

    public function test_akses_kontrol_akses_berbasis_peran_untuk_rute_kehutanan(): void
    {
        $forester = User::factory()->create(['role' => 'forester']);
        $unauthorized = User::factory()->create(['role' => 'guest_role']);

        $this->actingAs($forester)->get('/forest/plots')->assertOk();
        $this->actingAs($forester)->get('/forest/permits')->assertOk();

        $this->actingAs($unauthorized)->get('/forest/plots')->assertForbidden();
        $this->actingAs($unauthorized)->get('/forest/permits')->assertForbidden();
    }

    public function test_alur_utama_end_to_end_kuota_pohon_izin_kayu_dan_ledger(): void
    {
        $service = app(ForestConcessionService::class);

        // 1. Buat petak
        $plot = $service->createPlot([
            'plot_code' => 'PLT-FOR-001',
            'concession_name' => 'PT Rimba Lestari',
            'area_hectares' => 500,
            'annual_quota_m3' => 100, // kuota 100 m3
        ]);

        // 2. Daftarkan pohon
        $tree = $service->registerTree([
            'tree_tag' => 'TAG-001',
            'plot_id' => $plot->id,
            'species_name' => 'Meranti Merah',
            'dbh_cm' => 60,
            'height_m' => 20,
            'form_factor_pct' => 70,
        ]);
        expect($tree->calculated_volume_dm3)->toBeGreaterThan(0);

        // 3. Request izin tebang 50 m3
        $permit = $service->requestHarvestPermit([
            'permit_code' => 'PRM-FOR-001',
            'plot_id' => $plot->id,
            'authorized_volume_m3' => 50,
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
        ], requesterUserRef: 101);

        // 4. Four-eyes approval: harus disetujui approver berbeda
        $service->approveHarvestPermit($permit->id, approverUserRef: 102);
        expect($permit->fresh()->status)->toBe('approved');

        // 5. Catat tebangan kayu (2 batang dengan hash-chain gapless)
        $log1 = $service->logHarvestedTimber([
            'log_code' => 'LOG-FOR-001',
            'permit_id' => $permit->id,
            'tree_tag' => $tree->tree_tag,
            'volume_dm3' => 2500, // 2.5 m3
        ]);
        expect($log1->serial_number)->toBe(1)
            ->and($log1->prev_hash)->toBe(str_repeat('0', 64));

        $log2 = $service->logHarvestedTimber([
            'log_code' => 'LOG-FOR-002',
            'permit_id' => $permit->id,
            'tree_tag' => 'TAG-002',
            'volume_dm3' => 3000, // 3.0 m3
        ]);
        expect($log2->serial_number)->toBe(2)
            ->and($log2->prev_hash)->toBe($log1->canonical_hash);

        // 6. Penjualan kayu & posting ledger double-entry
        $sale = $service->recordTimberSale([
            'sale_code' => 'SALE-FOR-001',
            'buyer_party_ref' => 501,
            'total_volume_dm3' => 5500,
            'total_amount_idr' => 25000000,
            'forestry_levy_idr' => 2500000, // PSDH 10%
            'idempotency_key' => 'IDEMP-SALE-001',
        ]);
        expect($sale->status)->toBe('invoiced');

        // Idempotensi penjualan kayu
        $duplicate = $service->recordTimberSale([
            'sale_code' => 'SALE-FOR-001',
            'buyer_party_ref' => 501,
            'total_volume_dm3' => 5500,
            'total_amount_idr' => 25000000,
            'forestry_levy_idr' => 2500000,
            'idempotency_key' => 'IDEMP-SALE-001',
        ]);
        expect($duplicate->id)->toBe($sale->id);

        // 7. Restorasi hutan & kredit karbon
        $rest = $service->recordRestorationOutcome([
            'restoration_code' => 'REST-FOR-001',
            'plot_id' => $plot->id,
            'planted_hectares' => 10,
            'trees_planted' => 4000,
            'survival_rate_pct' => 85, // >= 75%
            'satellite_ndvi_score' => 70, // >= 60
        ]);
        expect($rest->carbon_credits_issued)->toBe(150000); // 10 Ha * 15.000 kg CO2e
    }

    public function test_aturan_bisnis_four_eyes_dan_pelanggaran_kuota(): void
    {
        $service = app(ForestConcessionService::class);

        $plot = $service->createPlot([
            'plot_code' => 'PLT-FOR-002',
            'concession_name' => 'PT Rimba Ulin',
            'area_hectares' => 100,
            'annual_quota_m3' => 30,
        ]);

        // Pelanggaran kuota: request 40 m3 padahal kuota hanya 30 m3
        expect(fn () => $service->requestHarvestPermit([
            'permit_code' => 'PRM-FOR-FAIL-QUOTA',
            'plot_id' => $plot->id,
            'authorized_volume_m3' => 40,
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
        ], requesterUserRef: 201))->toThrow(RuntimeException::class);

        // Request valid 20 m3
        $permit = $service->requestHarvestPermit([
            'permit_code' => 'PRM-FOR-002',
            'plot_id' => $plot->id,
            'authorized_volume_m3' => 20,
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
        ], requesterUserRef: 201);

        // Pelanggaran four-eyes: requester menyetujui izin sendiri
        expect(fn () => $service->approveHarvestPermit($permit->id, approverUserRef: 201))
            ->toThrow(RuntimeException::class);
    }

    public function test_idor_proteksi_data_pembeli_kayu(): void
    {
        $buyer1 = User::factory()->create(['role' => 'timber_buyer']);
        $buyer2 = User::factory()->create(['role' => 'timber_buyer']);

        ForestTimberSale::create([
            'sale_code' => 'SALE-IDOR-001',
            'buyer_party_ref' => $buyer1->id,
            'total_volume_dm3' => 1000,
            'total_amount_idr' => 5000000,
            'forestry_levy_idr' => 500000,
            'idempotency_key' => 'IDEMP-IDOR-1',
            'status' => 'invoiced',
        ]);

        $resp1 = $this->actingAs($buyer1)->get('/forest/sales');
        $resp1->assertOk()->assertSee('SALE-IDOR-001');

        $resp2 = $this->actingAs($buyer2)->get('/forest/sales');
        $resp2->assertOk()->assertDontSee('SALE-IDOR-001');
    }

    public function test_audit_dua_sumber_bersih_exit_0_dan_fixture_exit_nonzero(): void
    {
        expect(Artisan::call('for:audit'))->toBe(0);

        $fixture = new ForestAuditCorruptionFixture;
        $fixture->corrupt();

        expect(Artisan::call('for:audit'))->toBe(1);
    }
}
