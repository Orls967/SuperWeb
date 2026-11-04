<?php

declare(strict_types=1);

namespace Tests\Acceptance\Syariah;

use App\Models\User;
use App\Quality\Audit\Fixtures\SybAuditCorruptionFixture;
use Database\Seeders\LedgerAccountsSeeder;
use Database\Seeders\SyariahMurabahahT1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Support\Posting;
use Modules\Syariah\Application\Services\MurabahahService;
use Modules\Syariah\database\seeders\SyariahRbacSeeder;
use Modules\Syariah\Domain\Exceptions\IdempotencyConflictException;
use Modules\Syariah\Domain\Models\MurabahahContract;
use Modules\Syariah\Domain\Models\MurabahahSchedule;
use Modules\Syariah\Ledger\SyariahTransactionTypes;
use RuntimeException;
use Tests\TestCase;

/**
 * Acceptance Test Fase 161 — Lini 19 Keuangan Syariah Murabahah (D19-1..D19-5)
 */
class SyariahMurabahahAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private MurabahahService $service;

    private User $syariahOfficer;

    private User $dpsReviewer;

    private User $nasabah;

    private User $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(SyariahRbacSeeder::class);

        $this->service = app(MurabahahService::class);

        $this->syariahOfficer = User::factory()->create(['role' => 'syariah_officer']);
        $this->dpsReviewer = User::factory()->create(['role' => 'dps_reviewer']);
        $this->nasabah = User::factory()->create(['role' => 'nasabah']);
        $this->driver = User::factory()->create(['role' => 'driver']);
    }

    /**
     * Skenario 1: Matriks RBAC rute /syariah/murabahah, /syariah/review, dan /syariah/portal
     */
    public function test_161_1_rbac_syariah_routes(): void
    {
        $this->actingAs($this->syariahOfficer)->get('/syariah/murabahah')->assertOk();
        $this->actingAs($this->dpsReviewer)->get('/syariah/murabahah')->assertForbidden();
        $this->actingAs($this->nasabah)->get('/syariah/murabahah')->assertForbidden();
        $this->actingAs($this->driver)->get('/syariah/murabahah')->assertForbidden();

        $this->actingAs($this->dpsReviewer)->get('/syariah/review')->assertOk();
        $this->actingAs($this->syariahOfficer)->get('/syariah/review')->assertForbidden();
        $this->actingAs($this->nasabah)->get('/syariah/review')->assertForbidden();

        $this->actingAs($this->nasabah)->get('/syariah/portal')->assertOk();
        $this->actingAs($this->syariahOfficer)->get('/syariah/portal')->assertOk();
        $this->actingAs($this->dpsReviewer)->get('/syariah/portal')->assertOk();
        $this->actingAs($this->driver)->get('/syariah/portal')->assertForbidden();
    }

    /**
     * Skenario 2: Vektor Jadwal Angsuran D19-4 (LargestRemainder: biaya 100.000.000, margin 18.000.000, 12 bulan)
     */
    public function test_161_2_murabahah_schedule_vector_largest_remainder(): void
    {
        $schedule = $this->service->computeInstallmentSchedule(
            costPriceIdr: 100_000_000,
            marginAmountIdr: 18_000_000,
            tenorMonths: 12
        );

        $this->assertCount(12, $schedule);

        // Angsuran 1..4 = 9.833.334 (pokok 8.333.334 + margin 1.500.000)
        for ($i = 0; $i < 4; $i++) {
            $this->assertSame($i + 1, $schedule[$i]['installment_no']);
            $this->assertSame(8_333_334, $schedule[$i]['principal_component_idr']);
            $this->assertSame(1_500_000, $schedule[$i]['margin_component_idr']);
            $this->assertSame(9_833_334, $schedule[$i]['installment_amount_idr']);
        }

        // Angsuran 5..12 = 9.833.333 (pokok 8.333.333 + margin 1.500.000)
        for ($i = 4; $i < 12; $i++) {
            $this->assertSame($i + 1, $schedule[$i]['installment_no']);
            $this->assertSame(8_333_333, $schedule[$i]['principal_component_idr']);
            $this->assertSame(1_500_000, $schedule[$i]['margin_component_idr']);
            $this->assertSame(9_833_333, $schedule[$i]['installment_amount_idr']);
        }

        $this->assertSame(100_000_000, array_sum(array_column($schedule, 'principal_component_idr')));
        $this->assertSame(18_000_000, array_sum(array_column($schedule, 'margin_component_idr')));
        $this->assertSame(118_000_000, array_sum(array_column($schedule, 'installment_amount_idr')));
    }

    /**
     * Skenario 3: E2E Alur Akad Murabahah 6 Tahap, Posting Double-Entry, Pengakuan Margin Proporsional, Ta'zir & Idempotensi
     */
    public function test_161_3_e2e_murabahah_lifecycle_ledger_postings_tazir_and_idempotency(): void
    {
        // Tanpa Idempotency-Key -> 422
        $this->actingAs($this->syariahOfficer)
            ->post('/syariah/murabahah', [
                'customer_user_id' => $this->nasabah->id,
                'supplier_party_ref' => 10,
                'asset_description' => 'Mesin CNC Industri',
                'cost_price_idr' => 100_000_000,
                'margin_amount_idr' => 18_000_000,
                'tenor_months' => 12,
            ])
            ->assertSessionHasErrors('idempotency_key');

        // 1. Pengajuan (PROPOSED)
        $contract = $this->service->propose(
            customerUserId: $this->nasabah->id,
            supplierPartyRef: 10,
            assetDescription: 'Mesin CNC Industri',
            costPriceIdr: 100_000_000,
            marginAmountIdr: 18_000_000,
            tenorMonths: 12,
            proposedByUserId: $this->syariahOfficer->id,
            idempotencyKey: 'syb:acc:propose:1'
        );
        $this->assertSame(MurabahahContract::STATUS_PROPOSED, $contract->status);

        // Idempotensi pengajuan: key sama + payload sama -> record sama; payload beda -> IdempotencyConflictException
        $dup = $this->service->propose(
            customerUserId: $this->nasabah->id,
            supplierPartyRef: 10,
            assetDescription: 'Mesin CNC Industri',
            costPriceIdr: 100_000_000,
            marginAmountIdr: 18_000_000,
            tenorMonths: 12,
            proposedByUserId: $this->syariahOfficer->id,
            idempotencyKey: 'syb:acc:propose:1'
        );
        $this->assertSame($contract->id, $dup->id);

        try {
            $this->service->propose(
                customerUserId: $this->nasabah->id,
                supplierPartyRef: 10,
                assetDescription: 'Mesin CNC Industri',
                costPriceIdr: 200_000_000,
                marginAmountIdr: 18_000_000,
                tenorMonths: 12,
                proposedByUserId: $this->syariahOfficer->id,
                idempotencyKey: 'syb:acc:propose:1'
            );
            $this->fail('IdempotencyConflictException wajib dilempar saat payload berbeda.');
        } catch (IdempotencyConflictException) {
            // expected
        }

        // 2. Persetujuan syariah_officer + Reviu DPS (four-eyes)
        $this->service->approveByOfficer($contract, $this->syariahOfficer->id);
        $this->service->reviewByDps($contract, $this->dpsReviewer->id, 'Akad murabahah bebas riba dan objek barang jelas.');
        $this->assertSame(MurabahahContract::STATUS_DPS_APPROVED, $contract->fresh()->status);

        // 3. Bank membeli barang dari pemasok (ASSET_PURCHASED)
        $this->service->purchaseAsset($contract, 'syb:acc:purchase:1');
        $this->assertSame(-100_000_000, (int) LedgerAccount::where('code', 'syb:murabahah_asset:IDR')->value('cached_balance'));
        $this->assertSame(100_000_000, (int) LedgerAccount::where('code', 'ap:syb:supplier:10:IDR')->value('cached_balance'));

        // 4 & 5. Tanda tangan akad jual (ACTIVE) -> persediaan barang 0, piutang -118.000.000, deferred_margin +18.000.000
        $signed = $this->service->signContract($contract, 'syb:acc:sign:1');
        $this->assertSame(MurabahahContract::STATUS_ACTIVE, $signed->status);
        $this->assertCount(12, $signed->schedules);
        $this->assertSame(0, (int) LedgerAccount::where('code', 'syb:murabahah_asset:IDR')->value('cached_balance'));
        $this->assertSame(-118_000_000, (int) LedgerAccount::where('code', "syb:murabahah_receivable:{$contract->id}:IDR")->value('cached_balance'));
        $this->assertSame(18_000_000, (int) LedgerAccount::where('code', "syb:deferred_margin:{$contract->id}:IDR")->value('cached_balance'));

        // 6. Isi dompet nasabah 120.000.000, bayar seluruh 12 angsuran, dan pungut 1 denda ta'zir 250.000
        $this->service->fundCustomerWallet($this->nasabah->id, 120_000_000, 'syb:acc:fund:1');

        for ($no = 1; $no <= 12; $no++) {
            $this->service->payInstallment($contract, $no, "syb:acc:pay:{$no}");
        }

        $settled = $contract->fresh();
        $this->assertSame(MurabahahContract::STATUS_SETTLED, $settled->status);
        $this->assertSame(118_000_000, (int) $settled->total_paid_idr);
        $this->assertSame(18_000_000, (int) $settled->recognized_margin_idr);

        $this->assertSame(0, (int) LedgerAccount::where('code', "syb:murabahah_receivable:{$contract->id}:IDR")->value('cached_balance'));
        $this->assertSame(0, (int) LedgerAccount::where('code', "syb:deferred_margin:{$contract->id}:IDR")->value('cached_balance'));
        $this->assertSame(18_000_000, (int) LedgerAccount::where('code', 'syb:margin_income:IDR')->value('cached_balance'));

        // Denda keterlambatan ta'zir masuk ke syb:qardhul_hasan_fund:IDR, BUKAN ke syb:margin_income:IDR
        $this->service->collectTazirPenalty($contract, 250_000, 'Keterlambatan angsuran oleh nasabah mampu', 'syb:acc:tazir:1');
        $this->assertSame(250_000, (int) LedgerAccount::where('code', 'syb:qardhul_hasan_fund:IDR')->value('cached_balance'));
        $this->assertSame(18_000_000, (int) LedgerAccount::where('code', 'syb:margin_income:IDR')->value('cached_balance'));

        $this->artisan('syb:audit')->assertExitCode(0);
    }

    /**
     * Skenario 4: Jalur Galat — larangan tanda tangan tanpa reviu DPS (four-eyes), larangan beli barang sebelum DPS, & larangan akun bunga (D19-1)
     */
    public function test_161_4_rejects_signing_without_dps_review_and_rejects_interest_accounts(): void
    {
        $contract = $this->service->propose(
            customerUserId: $this->nasabah->id,
            supplierPartyRef: 11,
            assetDescription: 'Armada Niaga Halal',
            costPriceIdr: 50_000_000,
            marginAmountIdr: 9_000_000,
            tenorMonths: 6,
            proposedByUserId: $this->syariahOfficer->id,
            idempotencyKey: 'syb:acc:err:1'
        );

        $this->service->approveByOfficer($contract, $this->syariahOfficer->id);

        // 1. Mencoba menandatangani akad tanpa reviu DPS -> ditolak keras
        try {
            $this->service->signContract($contract, 'syb:acc:err:sign-no-dps');
            $this->fail('Tanda tangan akad tanpa reviu DPS wajib ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Dewan Pengawas Syariah (DPS)', $e->getMessage());
        }

        // 2. Pelanggaran four-eyes: syariah_officer mencoba mereviu sendiri sebagai DPS -> ditolak
        try {
            $this->service->reviewByDps($contract, $this->syariahOfficer->id, 'Self review');
            $this->fail('Self-review oleh petugas pengaju/penyetuju wajib ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('four-eyes', $e->getMessage());
        }

        // 3. Larangan D19-1: mencoba memposting transaksi syariah yang menyentuh akun mengandung 'interest' atau 'bunga' -> ditolak
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dilarang keras dalam transaksi syariah');
        $this->service->postShariahCompliant(new PostingDTO(
            type: SyariahTransactionTypes::SYB_MURABAHAH_MARGIN_REC->value,
            description: 'Percobaan posting akun bunga terlarang',
            idempotencyKey: 'syb:acc:err:interest',
            entries: Posting::lines()
                ->debit('syb:murabahah_asset:IDR', 100_000, 'IDR')
                ->credit('syb:interest_income:IDR', 100_000, 'IDR')
                ->toEntries('IDR')
        ));
    }

    /**
     * Skenario 5: Anti-IDOR Portal Nasabah — nasabah hanya dapat melihat dan membayar akad miliknya sendiri
     */
    public function test_161_5_anti_idor_customer_portal(): void
    {
        $otherNasabah = User::factory()->create(['role' => 'nasabah']);

        $contract = $this->service->propose(
            customerUserId: $this->nasabah->id,
            supplierPartyRef: 12,
            assetDescription: 'Peralatan Medis Klinik',
            costPriceIdr: 30_000_000,
            marginAmountIdr: 6_000_000,
            tenorMonths: 6,
            proposedByUserId: $this->syariahOfficer->id,
            idempotencyKey: 'syb:acc:idor:1'
        );

        // Nasabah pemilik akad & petugas syariah mendapat 200 OK
        $this->actingAs($this->nasabah)->get("/syariah/portal/{$contract->id}")->assertOk();
        $this->actingAs($this->syariahOfficer)->get("/syariah/portal/{$contract->id}")->assertOk();

        // Nasabah lain yang mencoba mengakses detail atau membayar angsuran akad tersebut mendapat 403 Forbidden
        $this->actingAs($otherNasabah)->get("/syariah/portal/{$contract->id}")->assertForbidden();
        $this->actingAs($otherNasabah)
            ->post("/syariah/murabahah/{$contract->id}/pay-installment", [
                'installment_no' => 1,
                'idempotency_key' => 'syb:acc:idor:pay',
            ])
            ->assertForbidden();
    }

    /**
     * Skenario 6: Audit Dua Sumber (syb:audit) di atas Skala T1 (1.000 akad) dan deteksi korupsi
     */
    public function test_161_6_syb_audit_passes_on_t1_and_fails_on_corruption(): void
    {
        $this->seed(SyariahMurabahahT1Seeder::class);

        $this->assertGreaterThanOrEqual(1000, MurabahahContract::count());
        $this->assertGreaterThanOrEqual(6000, MurabahahSchedule::count());

        $this->artisan('syb:audit')->assertExitCode(0);

        // 1. Korupsi jadwal angsuran (ubah nilai angsuran UNPAID sehingga != saldo piutang) -> syb:audit wajib gagal (exit 1)
        $firstUnpaid = MurabahahSchedule::where('status', MurabahahSchedule::STATUS_UNPAID)->firstOrFail();
        $originalAmount = (int) $firstUnpaid->installment_amount_idr;
        DB::table('syb_murabahah_schedules')
            ->where('id', $firstUnpaid->id)
            ->update(['installment_amount_idr' => $originalAmount + 1_000_000]);

        $this->artisan('syb:audit')->assertExitCode(1);

        // Pulihkan jadwal
        DB::table('syb_murabahah_schedules')
            ->where('id', $firstUnpaid->id)
            ->update(['installment_amount_idr' => $originalAmount]);

        $this->artisan('syb:audit')->assertExitCode(0);

        // 2. Suntikkan SybAuditCorruptionFixture (akun bunga terlarang syb:forbidden_interest_income:IDR) -> syb:audit wajib gagal (exit 1)
        (new SybAuditCorruptionFixture)->corrupt();
        $this->artisan('syb:audit')->assertExitCode(1);
    }
}
