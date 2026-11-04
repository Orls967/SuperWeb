<?php

declare(strict_types=1);

namespace Tests\Acceptance\Clearing;

use App\Models\User;
use App\Quality\Audit\Fixtures\ClearingAuditCorruptionFixture;
use Database\Seeders\ClearingCbamT1Seeder;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Crypto\Domain\Models\CryptoAsset;
use Modules\Crypto\Domain\Models\CryptoPriceTick;
use Modules\Logistics\Application\Actions\RecordShippingDocumentHashAction;
use Modules\Logistics\Contracts\CustodyVerifier;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\TradeFinance\Application\Services\Clearing\CbamDeclarationService;
use Modules\TradeFinance\Application\Services\Clearing\ClearingEscrowService;
use Modules\TradeFinance\database\seeders\TradeFinanceRbacSeeder;
use Modules\TradeFinance\Domain\Exceptions\IdempotencyConflictException;
use Modules\TradeFinance\Domain\Models\Clearing\CbamDeclaration;
use Modules\TradeFinance\Domain\Models\Clearing\ClearingEscrow;
use Modules\TradeFinance\Domain\Models\LetterOfCredit;
use Modules\TradeFinance\Ledger\ClearingSubjectAccounts;
use RuntimeException;
use Tests\TestCase;

/**
 * Acceptance Test Fase 83 — Cross-Border Clearing House, Stablecoin Escrow & CBAM Engine (KONSEP §7F Slice 1–2)
 */
class ClearingCbamAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private ClearingEscrowService $escrowService;

    private CbamDeclarationService $cbamService;

    private User $tfOfficer;

    private User $importer;

    private User $exporter;

    private User $complianceOfficer;

    private User $driver;

    private LetterOfCredit $lc;

    private CryptoAsset $usdtAsset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(TradeFinanceRbacSeeder::class);

        $this->escrowService = app(ClearingEscrowService::class);
        $this->cbamService = app(CbamDeclarationService::class);

        $this->tfOfficer = User::factory()->create(['role' => 'tf_officer']);
        $this->importer = User::factory()->create(['role' => 'importer']);
        $this->exporter = User::factory()->create(['role' => 'exporter']);
        $this->complianceOfficer = User::factory()->create(['role' => 'compliance_officer']);
        $this->driver = User::factory()->create(['role' => 'driver']);

        $this->usdtAsset = CryptoAsset::firstOrCreate(
            ['symbol' => 'USDT'],
            [
                'name' => 'Tether USD',
                'decimals' => 6,
                'is_active' => true,
            ]
        );
        CryptoPriceTick::create([
            'asset_id' => $this->usdtAsset->id,
            'price_idr' => '16000',
            'recorded_at' => now(),
        ]);

        $this->lc = LetterOfCredit::create([
            'lc_number' => 'LC-ACC-83-0001',
            'type' => 'sight',
            'issuing_bank' => 'Bank Superweb Indonesia',
            'advising_bank' => 'Rotterdam Trade Bank NV',
            'applicant_name' => 'PT Importir Nusantara',
            'beneficiary_name' => 'Global Metals BV',
            'currency' => 'USD',
            'amount_foreign' => 50_000,
            'amount_functional_idr' => 800_000_000,
            'issue_date' => '2026-11-01',
            'expiry_date' => '2027-05-01',
            'tenor_days' => 90,
            'status' => 'issued',
        ]);

        DB::table('esg_verified_product_emissions')->insert([
            'product_code' => 'HS7601-ALUMINIUM',
            'origin_country' => 'ID',
            't_co2e_per_t' => '1.850000',
            'verification_ref' => 'ESG-K7-VER-2026-001',
            'verified_at' => '2026-01-01 00:00:00',
            'effective_from' => '2026-01-01 00:00:00',
            'effective_to' => null,
            'source_label' => 'S2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tf_cbam_parameters')->insert([
            'version' => 'CBAM-S2-2026Q4',
            'price_eur_cents_per_tco2e' => 8_500,
            'eur_to_idr_rate' => 17_200,
            'label' => 'S2',
            'source_label' => 'EU ETS Reference S2',
            'effective_from' => '2026-01-01 00:00:00',
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createShipment(string $trackingNo): Shipment
    {
        $origin = Location::firstOrCreate(
            ['code' => 'HUB-ACC-BDJ'],
            [
                'name' => 'Hub Banjarmasin Acc',
                'type' => LocationType::HUB,
                'city' => 'Banjarmasin',
                'province' => 'Kalimantan Selatan',
                'country' => 'ID',
                'lat_e6' => -3300000,
                'lng_e6' => 114500000,
                'timezone' => 'Asia/Makassar',
                'min_connection_minutes' => 60,
                'is_active' => true,
            ]
        );
        $dest = Location::firstOrCreate(
            ['code' => 'HUB-ACC-RTM'],
            [
                'name' => 'Port Rotterdam Acc',
                'type' => LocationType::HUB,
                'city' => 'Rotterdam',
                'province' => 'Zuid-Holland',
                'country' => 'NL',
                'lat_e6' => 51920000,
                'lng_e6' => 4470000,
                'timezone' => 'Europe/Amsterdam',
                'min_connection_minutes' => 60,
                'is_active' => true,
            ]
        );

        return Shipment::create([
            'tracking_number' => $trackingNo,
            'shipper_id' => $this->exporter->id,
            'consignee_name' => 'PT Importir Nusantara',
            'consignee_phone' => '081234567890',
            'consignee_address' => ['street' => 'Jl. Sudirman', 'city' => 'Jakarta'],
            'origin_location_id' => $origin->id,
            'destination_location_id' => $dest->id,
            'service_level' => 'regular',
            'mode' => 'sea',
            'payment_terms' => 'prepaid',
            'status' => 'booked',
            'total_chargeable_weight_g' => 20_000_000,
            'total_amount_idr' => 50_000_000,
            'booked_at' => now(),
        ]);
    }

    /**
     * Skenario 1: Matriks RBAC rute /trade-finance/clearing dan /trade/cbam
     */
    public function test_83_1_rbac_clearing_and_cbam_routes(): void
    {
        $this->actingAs($this->tfOfficer)->get('/trade-finance/clearing')->assertOk();
        $this->actingAs($this->importer)->get('/trade-finance/clearing')->assertOk();
        $this->actingAs($this->exporter)->get('/trade-finance/clearing')->assertOk();
        $this->actingAs($this->driver)->get('/trade-finance/clearing')->assertForbidden();

        $this->actingAs($this->complianceOfficer)->get('/trade/cbam')->assertOk();
        $this->actingAs($this->tfOfficer)->get('/trade/cbam')->assertForbidden();
        $this->actingAs($this->driver)->get('/trade/cbam')->assertForbidden();
    }

    /**
     * Skenario 2: E2E Setoran USDT, Verifikasi K6 (lgx.custody.bl_verified.v1), Rilis Sekali, Revaluasi Kurs & Idempotensi
     */
    public function test_83_2_e2e_usdt_escrow_k6_custody_release_fx_revaluation_and_idempotency(): void
    {
        // Tanpa Idempotency-Key -> 422
        $this->actingAs($this->importer)
            ->post('/trade-finance/clearing/deposit', [
                'letter_of_credit_id' => $this->lc->id,
                'importer_user_id' => $this->importer->id,
                'exporter_user_id' => $this->exporter->id,
                'usdt_micro' => 10_000_000_000,
            ])
            ->assertSessionHasErrors('idempotency_key');

        // On-ramp 10.000 USDT (10_000_000_000 micro-USDT) ke importir
        $usdtMicro = 10_000_000_000;
        $this->escrowService->simulateOnRamp($this->importer->id, $usdtMicro, 'tf:acc:onramp:1');

        // Setoran pada kurs 16.000 IDR/USDT
        $escrow = $this->escrowService->deposit(
            letterOfCreditId: $this->lc->id,
            importerUserId: $this->importer->id,
            exporterUserId: $this->exporter->id,
            usdtMicro: $usdtMicro,
            idempotencyKey: 'tf:acc:deposit:1'
        );

        $this->assertSame(ClearingEscrow::STATUS_DEPOSITED, $escrow->status);
        $this->assertSame(16_000, (int) $escrow->deposit_fx_rate_idr);

        $escrowAcct = LedgerAccount::where('code', ClearingSubjectAccounts::escrow($this->lc->id))->firstOrFail();
        $this->assertSame($usdtMicro, (int) $escrowAcct->cached_balance);

        // Idempotensi setoran: key sama + payload sama -> mengembalikan record yang sama tanpa mendebit ulang
        $duplicateDeposit = $this->escrowService->deposit(
            letterOfCreditId: $this->lc->id,
            importerUserId: $this->importer->id,
            exporterUserId: $this->exporter->id,
            usdtMicro: $usdtMicro,
            idempotencyKey: 'tf:acc:deposit:1'
        );
        $this->assertSame($escrow->id, $duplicateDeposit->id);

        // Key sama + payload beda -> IdempotencyConflictException
        try {
            $this->escrowService->deposit(
                letterOfCreditId: $this->lc->id,
                importerUserId: $this->importer->id,
                exporterUserId: $this->exporter->id,
                usdtMicro: $usdtMicro + 1,
                idempotencyKey: 'tf:acc:deposit:1'
            );
            $this->fail('IdempotencyConflictException harus dilempar saat payload berbeda.');
        } catch (IdempotencyConflictException) {
            // expected
        }

        // Catat hash BL ke rantai custody Logistics
        $shipment = $this->createShipment('LGX-ACC-83-001');
        $blHash = hash('sha256', 'BL-DOC-LC-ACC-83-0001');
        app(RecordShippingDocumentHashAction::class)->execute($shipment, 'BL', $blHash, $this->tfOfficer);

        // Kurs berubah menjadi 16.500 IDR/USDT (+500 IDR/USDT * 10.000 USDT = +5.000.000 IDR)
        CryptoPriceTick::create([
            'asset_id' => $this->usdtAsset->id,
            'price_idr' => '16500',
            'recorded_at' => now()->addMinute(),
        ]);

        // Rilis escrow
        $released = $this->escrowService->release(
            escrow: $escrow,
            documentType: 'BL',
            documentHash: $blHash,
            idempotencyKey: 'tf:acc:release:1',
            actorUserId: $this->tfOfficer->id
        );

        $this->assertSame(ClearingEscrow::STATUS_RELEASED, $released->status);
        $this->assertSame(16_500, (int) $released->release_fx_rate_idr);
        $this->assertSame(5_000_000, (int) $released->fx_revaluation_idr);

        // Saldo akun escrow menjadi 0, dompet eksportir bertambah 10.000 USDT
        $this->assertSame(0, (int) $escrowAcct->fresh()->cached_balance);
        $exporterWallet = LedgerAccount::where('code', ClearingSubjectAccounts::usdtWallet($this->exporter->id))->firstOrFail();
        $this->assertSame($usdtMicro, (int) $exporterWallet->cached_balance);

        // Selisih kurs tercatat di trs:fx_gain_loss:IDR dan tf:fx_position:IDR
        $fxGainLoss = LedgerAccount::where('code', 'trs:fx_gain_loss:IDR')->firstOrFail();
        $this->assertSame(5_000_000, abs((int) $fxGainLoss->cached_balance));

        // Event lgx.custody.bl_verified.v1 terpancar ke sim_event_spine
        $this->assertDatabaseHas('sim_event_spine', [
            'event_name' => 'lgx.custody.bl_verified.v1',
            'idempotency_key' => 'lgx:custody:verified:BL:'.$blHash,
        ]);

        // Rilis ulang dengan key yang sama -> idempoten; dengan key berbeda -> ditolak
        $replayRelease = $this->escrowService->release($escrow, 'BL', $blHash, 'tf:acc:release:1', $this->tfOfficer->id);
        $this->assertSame($released->id, $replayRelease->id);

        $this->expectException(RuntimeException::class);
        $this->escrowService->release($escrow, 'BL', $blHash, 'tf:acc:release:2', $this->tfOfficer->id);
    }

    /**
     * Skenario 3: Jalur Galat — rilis tanpa hash valid / rantai custody dimanipulasi ditolak; CBAM tanpa data K7 ditolak
     */
    public function test_83_3_rejects_unverified_or_tampered_custody_chain_and_missing_k7_emission(): void
    {
        $usdtMicro = 5_000_000_000;
        $this->escrowService->simulateOnRamp($this->importer->id, $usdtMicro, 'tf:acc:onramp:err');
        $escrow = $this->escrowService->deposit(
            letterOfCreditId: $this->lc->id,
            importerUserId: $this->importer->id,
            exporterUserId: $this->exporter->id,
            usdtMicro: $usdtMicro,
            idempotencyKey: 'tf:acc:deposit:err'
        );

        // 1. Hash BL tidak pernah tercatat di Logistics -> rilis ditolak
        $unknownHash = hash('sha256', 'UNKNOWN-BL-HASH');
        try {
            $this->escrowService->release($escrow, 'BL', $unknownHash, 'tf:acc:rel:unknown', $this->tfOfficer->id);
            $this->fail('Rilis dengan hash BL tak dikenal harus ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tidak ditemukan di rantai custody', $e->getMessage());
        }

        // 2. Hash BL tercatat tetapi rantai custody dirusak (prev_hash diubah) -> K6 mengembalikan chain_valid=false -> rilis ditolak
        $shipment = $this->createShipment('LGX-ACC-83-TAMPER');
        $tamperedBlHash = hash('sha256', 'TAMPERED-BL-HASH');
        $event = app(RecordShippingDocumentHashAction::class)->execute($shipment, 'BL', $tamperedBlHash, $this->tfOfficer);

        DB::table('lgx_tracking_events')->where('id', $event->id)->update(['prev_hash' => str_repeat('f', 64)]);

        $verified = app(CustodyVerifier::class)->verifyDocumentHash('BL', $tamperedBlHash);
        $this->assertNotNull($verified);
        $this->assertFalse($verified['chain_valid']);

        try {
            $this->escrowService->release($escrow, 'BL', $tamperedBlHash, 'tf:acc:rel:tampered', $this->tfOfficer->id);
            $this->fail('Rilis dengan rantai custody rusak harus ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Rantai custody pengiriman tidak valid', $e->getMessage());
        }

        // 3. CBAM tanpa data emisi terverifikasi K7 -> ditolak
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Data emisi spesifik terverifikasi untuk HS9999-UNKNOWN/ZZ tidak ada');
        $this->cbamService->declare(
            containerNumber: 'MSCU9999991',
            productCode: 'HS9999-UNKNOWN',
            originCountry: 'ZZ',
            netMassKg: 20_000,
            idempotencyKey: 'tf:acc:cbam:missing',
            actor: $this->complianceOfficer
        );
    }

    /**
     * Skenario 4: Vektor CBAM (20.000 kg × 1,85 tCO2e/t = 37,000 tCO2e), Nomor Gapless, DocumentStore, dan Immutabilitas
     */
    public function test_83_4_cbam_vector_calculation_gapless_numbering_and_immutability(): void
    {
        $cert1 = $this->cbamService->declare(
            containerNumber: 'MSCU8300001',
            productCode: 'HS7601-ALUMINIUM',
            originCountry: 'ID',
            netMassKg: 20_000,
            idempotencyKey: 'tf:acc:cbam:vec1',
            actor: $this->complianceOfficer
        );

        // Vektor wajib: 20.000 kg (20 t) * 1,85 tCO2e/t = 37.000 milli-tCO2e = 37,000 tCO2e
        $this->assertSame(37_000, (int) $cert1->embedded_milli_tco2e);
        $this->assertSame('37.000', $cert1->embeddedTco2e());
        $this->assertStringStartsWith('CBAM/', $cert1->certificate_number);
        $this->assertStringEndsWith('/000001', $cert1->certificate_number);

        // Sertifikat ke-2 menghasilkan nomor urut gapless berikutnya (/000002)
        $cert2 = $this->cbamService->declare(
            containerNumber: 'MSCU8300002',
            productCode: 'HS7601-ALUMINIUM',
            originCountry: 'ID',
            netMassKg: 10_000,
            idempotencyKey: 'tf:acc:cbam:vec2',
            actor: $this->complianceOfficer
        );
        $this->assertStringEndsWith('/000002', $cert2->certificate_number);
        $this->assertSame(18_500, (int) $cert2->embedded_milli_tco2e);

        // Tersimpan di DocumentStoreInterface (core_documents)
        $this->assertDatabaseHas('core_documents', [
            'document_type' => 'CBAM_CERT',
            'checksum_sha256' => $cert1->document_checksum,
        ]);

        // Immutabilitas: CbamDeclaration tidak boleh di-update atau di-delete
        $this->expectException(RuntimeException::class);
        $cert1->update(['net_mass_kg' => 99_000]);
    }

    /**
     * Skenario 5: Anti-IDOR — importir/eksportir hanya melihat escrow miliknya sendiri & importir tidak bisa mendanai atas nama pihak lain
     */
    public function test_83_5_anti_idor_on_clearing_escrow(): void
    {
        $otherImporter = User::factory()->create(['role' => 'importer']);
        $otherExporter = User::factory()->create(['role' => 'exporter']);

        $this->escrowService->simulateOnRamp($this->importer->id, 2_000_000_000, 'tf:acc:onramp:idor');
        $escrow = $this->escrowService->deposit(
            letterOfCreditId: $this->lc->id,
            importerUserId: $this->importer->id,
            exporterUserId: $this->exporter->id,
            usdtMicro: 2_000_000_000,
            idempotencyKey: 'tf:acc:deposit:idor'
        );

        // Pemilik (importer & exporter pada escrow) dan tf_officer dapat melihat detail
        $this->actingAs($this->importer)->get("/trade-finance/clearing/{$escrow->id}")->assertOk();
        $this->actingAs($this->exporter)->get("/trade-finance/clearing/{$escrow->id}")->assertOk();
        $this->actingAs($this->tfOfficer)->get("/trade-finance/clearing/{$escrow->id}")->assertOk();

        // Importir atau eksportir pihak lain ditolak (403)
        $this->actingAs($otherImporter)->get("/trade-finance/clearing/{$escrow->id}")->assertForbidden();
        $this->actingAs($otherExporter)->get("/trade-finance/clearing/{$escrow->id}")->assertForbidden();

        // Importir mencoba mendanai escrow menggunakan importer_user_id milik orang lain -> 403
        $this->actingAs($otherImporter)
            ->post('/trade-finance/clearing/deposit', [
                'letter_of_credit_id' => $this->lc->id,
                'importer_user_id' => $this->importer->id,
                'exporter_user_id' => $this->exporter->id,
                'usdt_micro' => 1_000_000,
                'idempotency_key' => 'tf:acc:idor:spoof',
            ])
            ->assertForbidden();
    }

    /**
     * Skenario 6: Audit Dua Sumber (clearing:audit & tf:audit) di atas skala T1 serta deteksi korupsi
     */
    public function test_83_6_audit_commands_pass_on_t1_and_fail_on_corruption(): void
    {
        $this->seed(ClearingCbamT1Seeder::class);

        $this->assertGreaterThanOrEqual(100, ClearingEscrow::count());
        $this->assertGreaterThanOrEqual(200, CbamDeclaration::count());

        $this->artisan('clearing:audit')->assertExitCode(0);
        $this->artisan('tf:audit')->assertExitCode(0);

        // Suntikkan korupsi pada salah satu sertifikat CBAM -> clearing:audit wajib gagal (exit != 0)
        $firstCert = CbamDeclaration::firstOrFail();
        DB::table('tf_cbam_declarations')
            ->where('id', $firstCert->id)
            ->update(['embedded_milli_tco2e' => (int) $firstCert->embedded_milli_tco2e + 500]);

        $this->artisan('clearing:audit')->assertExitCode(1);

        // Pulihkan sertifikat, lalu suntikkan fixture korupsi resmi ClearingAuditCorruptionFixture
        DB::table('tf_cbam_declarations')
            ->where('id', $firstCert->id)
            ->update(['embedded_milli_tco2e' => (int) $firstCert->embedded_milli_tco2e]);

        (new ClearingAuditCorruptionFixture)->corrupt();
        $this->artisan('clearing:audit')->assertExitCode(1);
    }
}
