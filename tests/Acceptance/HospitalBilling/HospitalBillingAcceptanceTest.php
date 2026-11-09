<?php

declare(strict_types=1);

namespace Tests\Acceptance\HospitalBilling;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Domain\Models\RbacRole;
use Modules\Hospital\Application\DTOs\FolioItemDTO;
use Modules\Hospital\Application\Sagas\HospitalBillingReconciliationSaga;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Models\BillingEpisode;
use Modules\Hospital\Domain\Models\ColdChainTelemetry;
use Modules\Hospital\Domain\Models\Encounter;
use Modules\Hospital\Domain\Models\FolioItem;
use Modules\Hospital\Domain\Models\GuaranteeLetter;
use Modules\Hospital\Domain\Models\InaCbgTariff;
use Modules\Hospital\Domain\Models\MedFridgeBreach;
use Modules\Hospital\Domain\Models\MedicalWasteManifest;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\PharmacyBranchLot;
use Modules\Hospital\Domain\Services\ClaimAgingAnalysisService;
use Modules\Hospital\Domain\Services\ColdChainMonitoringService;
use Modules\Hospital\Domain\Services\CoordinationOfBenefitsWaterfallService;
use Modules\Hospital\Domain\Services\HospitalBillingCalculationService;
use Modules\Hospital\Domain\Services\InaCbgGroupingService;
use Modules\Hospital\Domain\Services\MedicalWasteManifestService;
use Modules\Insurance\Contracts\CashlessGuaranteeRequester;
use Tests\TestCase;

class HospitalBillingAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private User $financeUser;
    private User $unauthorizedUser;
    private Patient $patient;
    private Encounter $encounter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\LedgerAccountsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Modules\\Hospital\\database\\seeders\\HospitalDemoSeeder']);
        $this->artisan('db:seed', ['--class' => 'Modules\\Hospital\\database\\seeders\\Phase88Seeder']);

        // Siapkan user dengan role terotorisasi
        $this->financeUser = User::factory()->create([
            'role' => 'hospital_finance',
        ]);

        $this->unauthorizedUser = User::factory()->create([
            'role' => 'auditor',
        ]);

        // Buat data master dasar
        $this->patient = Patient::first() ?? Patient::create([
            'mrn' => 'MRN-'.strtoupper(uniqid()),
            'name' => 'Budi Santoso',
            'birth_date' => '1985-05-15',
            'gender' => 'MALE',
            'blood_type' => 'O_POSITIVE',
            'bpjs_card_no' => '0001234567890',
            'is_bpjs_active' => true,
            'status' => 'ACTIVE',
        ]);

        $this->encounter = Encounter::first() ?? Encounter::create([
            'encounter_code' => 'ENC-'.strtoupper(uniqid()),
            'patient_id' => $this->patient->id,
            'encounter_type' => 'INPATIENT',
            'admitted_at' => now(),
            'status' => 'IN_PROGRESS',
        ]);
    }

    /**
     * Skenario 1: RBAC Route Access untuk Modul Billing & Operasional.
     */
    public function test_1_rbac_route_access_for_billing_and_operations(): void
    {
        // 1. Unauthenticated user harus diredirect (302)
        $respUnauth = $this->postJson('/hospital/billing/episodes', [
            'encounter_id' => $this->encounter->id,
        ]);
        $this->assertTrue(in_array($respUnauth->status(), [302, 401], true));

        // 2. Unauthorized role harus ditolak (403)
        $respForbidden = $this->actingAs($this->unauthorizedUser)->postJson('/hospital/billing/episodes', [
            'encounter_id' => $this->encounter->id,
        ]);
        $respForbidden->assertStatus(403);

        // 3. Authorized finance role berhasil membuat billing episode (201)
        $respOk = $this->actingAs($this->financeUser)->postJson('/hospital/billing/episodes', [
            'encounter_id' => $this->encounter->id,
            'initial_deposit_idr' => 2_500_000,
        ]);
        $respOk->assertStatus(201);
        $this->assertDatabaseHas('hsp_billing_episodes', [
            'encounter_id' => $this->encounter->id,
            'escrow_deposit_idr' => 2_500_000,
            'status' => 'OPEN',
        ]);
    }

    /**
     * Skenario 2: End-to-End Folio Aggregation & Mathematical Invariant.
     */
    public function test_2_folio_aggregation_and_mathematical_integrity(): void
    {
        $episode = BillingEpisode::create([
            'episode_code' => 'EP-TEST-'.uniqid(),
            'encounter_id' => $this->encounter->id,
            'escrow_deposit_idr' => 5_000_000,
            'total_charges_idr' => 0,
            'status' => 'OPEN',
        ]);

        $calc = app(HospitalBillingCalculationService::class);

        // Tambah Kamar Rawat
        $calc->addItemToEpisode($episode, new FolioItemDTO(
            category: 'BED_DAY',
            description: 'Kamar Rawat Inap Kelas 1 (4 hari)',
            quantity: 4,
            unitPriceIdr: 750_000,
            subtotalIdr: 3_000_000
        ));

        // Tambah Tindakan Bedah
        $calc->addItemToEpisode($episode, new FolioItemDTO(
            category: 'PROCEDURE',
            description: 'Tindakan Percutaneous Coronary Intervention (PCI)',
            quantity: 1,
            unitPriceIdr: 12_000_000,
            subtotalIdr: 12_000_000
        ));

        // Tambah Farmasi
        $calc->addItemToEpisode($episode, new FolioItemDTO(
            category: 'PHARMACY',
            description: 'Obat Antiplatelet & Antikoagulan Cold-Chain',
            quantity: 3,
            unitPriceIdr: 500_000,
            subtotalIdr: 1_500_000
        ));

        $episode->refresh();
        $this->assertSame(16_500_000, $episode->total_charges_idr);
        $this->assertTrue($calc->verifyFolioIntegrity($episode));
    }

    /**
     * Skenario 3: INA-CBGs Grouping & Perhitungan Selisih Naik Kelas (Permenkes 3/2023).
     */
    public function test_3_ina_cbg_grouping_and_class_upgrade_excess(): void
    {
        $groupingService = app(InaCbgGroupingService::class);

        // Simulasi Kasus Serangan Jantung I21 dengan Komorbiditas (Severity II)
        $dto = $groupingService->groupAndCalculateTariff(
            primaryDiagnosisCode: 'I21.0',
            secondaryDiagnosisCodes: ['I50.9', 'E11.9'],
            procedureCodes: ['36.06'],
            entitledClass: 'CLASS_2',
            actualClass: 'VIP',
            hospitalType: 'B'
        );

        $this->assertSame('II', $dto->severityLevel);
        $this->assertTrue($dto->isTopUpCapped);
        // Naik ke VIP selisih maksimal 75% dari tarif kelas hak (Kelas 2)
        $this->assertGreaterThan(0, $dto->classUpgradeExcessIdr);
        $this->assertLessThanOrEqual($dto->actualClassTariffIdr, $dto->classUpgradeExcessIdr);
    }

    /**
     * Skenario 4: Waterfall Coordination of Benefits (BPJS -> Asuransi K31 -> Pasien OOP).
     */
    public function test_4_waterfall_coordination_of_benefits_with_k31(): void
    {
        $episode = BillingEpisode::create([
            'episode_code' => 'EP-WATERFALL-'.uniqid(),
            'encounter_id' => $this->encounter->id,
            'escrow_deposit_idr' => 3_000_000,
            'total_charges_idr' => 20_000_000,
            'status' => 'OPEN',
        ]);

        // Buat Folio Items
        FolioItem::create([
            'billing_episode_id' => $episode->id,
            'item_category' => 'PROCEDURE',
            'description' => 'Paket Perawatan Jantung Komprehensif',
            'quantity' => 1,
            'unit_price_idr' => 20_000_000,
            'subtotal_idr' => 20_000_000,
        ]);

        $saga = app(HospitalBillingReconciliationSaga::class);

        $breakdown = $saga->execute([
            'billing_episode_id' => $episode->id,
            'primary_icd10' => 'I21.0',
            'secondary_icd10' => ['I50.0'],
            'procedures_icd9' => ['36.06'],
            'entitled_class' => 'CLASS_1',
            'actual_class' => 'CLASS_1',
            'private_insurance_policy_no' => 'POL-PRUDENTIAL-12345',
            'guarantee_letter_no' => 'GL-PRU-998877',
        ]);

        $episode->refresh();
        $this->assertSame('SETTLED', $episode->status);
        $this->assertGreaterThan(0, $breakdown->bpjsCoveredAmountIdr);

        // Verifikasi konservasi nilai: Total Gross == BPJS + Asuransi Swasta + Pasien Share
        $totalCovered = $breakdown->bpjsCoveredAmountIdr + $breakdown->privateInsuranceCoveredAmountIdr + $breakdown->patientOutOfPocketIdr;
        $this->assertSame($breakdown->totalGrossChargesIdr, $totalCovered);

        // Verifikasi entri waterfall di database
        $this->assertDatabaseHas('hsp_claim_waterfalls', [
            'billing_episode_id' => $episode->id,
            'payer_type' => 'BPJS',
        ]);
    }

    /**
     * Skenario 5: Telemetri Cold-Chain, Deteksi Thermal Excursion, & Karantina Stok Farmasi.
     */
    public function test_5_cold_chain_thermal_excursion_and_quarantine_workflow(): void
    {
        $coldChainService = app(ColdChainMonitoringService::class);
        $fridgeCode = 'FRIDGE-CHILLER-TEST-01';

        $branch = \Modules\Hospital\Domain\Models\PharmacyBranch::firstOrCreate(
            ['branch_code' => 'PB-TEST-01'],
            ['name' => 'Instalasi Farmasi RS', 'type' => 'HOSPITAL', 'city' => 'Jakarta', 'is_active' => true]
        );

        // Buat stok obat dingin simulasi
        PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'DRUG-VACCINE-01',
            'drug_name' => 'Vaksin Hepatitis B Rekombinan',
            'lot_number' => 'LOT-VAC-2026-A',
            'expiry_date' => now()->addYear(),
            'quantity' => 50,
            'fridge_unit_code' => $fridgeCode,
            'is_quarantined' => false,
        ]);

        // Catat suhu normal (4.5°C) -> Status harus NORMAL
        $normalLog = $coldChainService->recordAndEvaluateTelemetry(
            sensorDeviceCode: 'SENSOR-TEST-A',
            fridgeUnitCode: $fridgeCode,
            storageZone: 'CHILLER_2_TO_8',
            temperatureC: 4.5
        );
        $this->assertFalse($normalLog->isExcursion);
        $this->assertSame('NORMAL', $normalLog->severity);

        // Catat suhu rusak kritis (14.8°C) -> Harus picu karantina otomatis
        $criticalLog = $coldChainService->recordAndEvaluateTelemetry(
            sensorDeviceCode: 'SENSOR-TEST-A',
            fridgeUnitCode: $fridgeCode,
            storageZone: 'CHILLER_2_TO_8',
            temperatureC: 14.8
        );
        $this->assertTrue($criticalLog->isExcursion);
        $this->assertSame('CRITICAL', $criticalLog->severity);
        $this->assertTrue($criticalLog->requiresImmediateQuarantine);

        // Verifikasi stok obat di lemari pendingin tersebut otomatis menjadi QUARANTINED
        $this->assertDatabaseHas('hsp_branch_inventory_lots', [
            'fridge_unit_code' => $fridgeCode,
            'is_quarantined' => true,
        ]);

        $this->assertDatabaseHas('hsp_med_fridge_breaches', [
            'fridge_unit_code' => $fridgeCode,
            'status' => 'QUARANTINED',
        ]);

        // Uji pembebasan dari karantina setelah validasi stabilitas
        $breach = MedFridgeBreach::where('fridge_unit_code', $fridgeCode)->latest('id')->firstOrFail();
        $coldChainService->releaseFromQuarantine($breach, 'PROTOCOL-STABILITY-LAB-PASS-88');

        $this->assertDatabaseHas('hsp_branch_inventory_lots', [
            'fridge_unit_code' => $fridgeCode,
            'is_quarantined' => false,
        ]);
    }

    /**
     * Skenario 6: Rantai Kustodi Festronik Limbah Medis B3 (SHA-256 Chain of Custody & Tamper Detection).
     */
    public function test_6_medical_waste_festronik_chain_of_custody_and_tamper_detection(): void
    {
        $wasteService = app(MedicalWasteManifestService::class);

        // 1. Pencatatan limbah di ruangan ICU
        $record = $wasteService->registerWasteFromWard(
            wasteCategory: 'SHARPS',
            weightKg: 5.25,
            sourceWardCode: 'WARD-ICU-A',
            handlerStaffName: 'Ns. Ratna Sari, S.Kep'
        );
        $this->assertSame('SHARPS', $record->wasteCategory);
        $this->assertSame('LOGGED_IN_WARD', $record->status);

        $manifest = MedicalWasteManifest::where('manifest_number', $record->manifestNumber)->firstOrFail();

        // 2. Penerimaan & penimbangan di TPS internal
        $tpsRecord = $wasteService->receiveAtInternalTps(
            manifest: $manifest,
            reweighedKg: 5.30,
            tpsOfficerName: 'Joko Prabowo (Petugas TPS)',
            tpsBinCode: 'BIN-TPS-SHARPS-01'
        );
        $this->assertSame('RECEIVED_AT_TPS', $tpsRecord->status);

        // 3. Serah terima ke transporter berizin KLHK
        $handoverRecord = $wasteService->handoverToTransporter(
            manifest: $manifest,
            transporterCompanyName: 'PT Wastec International',
            vehiclePlateNo: 'B 9876 WST',
            driverName: 'Surya Kencana',
            handoverWeightKg: 5.30
        );
        $this->assertSame('HANDED_TO_TRANSPORTER', $handoverRecord->status);

        // 4. Verifikasi keutuhan rantai hash sebelum diubah -> HARUS VALID
        $this->assertTrue($wasteService->verifyCustodyChainIntegrity($manifest));

        // 5. Uji tamper detection: manipulasi salah satu log di basis data
        $firstLog = $manifest->custodyLogs()->first();
        $firstLog->update(['weight_kg' => 99.999]); // Manipulasi data berat tanpa menghitung ulang SHA-256

        $this->assertFalse($wasteService->verifyCustodyChainIntegrity($manifest));
    }

    /**
     * Skenario 7: Analisis Umur Piutang Klaim (Claim Aging & Allowance Calculation).
     */
    public function test_7_claim_aging_analysis_and_provision_calculation(): void
    {
        $agingService = app(ClaimAgingAnalysisService::class);

        $summary = $agingService->generateAgingSummary();
        $this->assertGreaterThanOrEqual(0, $summary->totalOutstandingIdr);
        $this->assertGreaterThanOrEqual(0, $summary->estimatedImpairmentAllowanceIdr);

        $arrayData = $summary->toArray();
        $this->assertArrayHasKey('current_0_to_30_idr', $arrayData);
        $this->assertArrayHasKey('past_due_31_to_60_idr', $arrayData);
        $this->assertArrayHasKey('past_due_61_to_90_idr', $arrayData);
        $this->assertArrayHasKey('past_due_over_90_idr', $arrayData);
    }

    /**
     * Skenario 8: Audit Invarian Dua-Sumber hsp:audit Lolos Tanpa Discrepancy (Exit 0).
     */
    public function test_8_hospital_audit_passes_cleanly_with_phase_88_invariants(): void
    {
        $exitCode = Artisan::call('hsp:audit');
        $this->assertSame(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Hospital audit PASSED with 0 discrepancy', $output);
        $this->assertStringContainsString('HEALTHY', $output);
    }
}
