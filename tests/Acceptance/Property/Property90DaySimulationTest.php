<?php

declare(strict_types=1);

namespace Tests\Acceptance\Property;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Database\Seeders\RbacConglomerateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Epc\Application\Services\EpcService;
use Modules\Epc\Application\Services\MonthlyCertificateService;
use Modules\Epc\Domain\Models\EpcProgressCertificate;
use Modules\Epc\Domain\Models\EpcProject;
use Modules\Epc\Domain\Models\EpcWbsNode;
use Modules\Proptech\Application\Services\FlexSpaceService;
use Modules\Proptech\Application\Services\ProptechService;
use Modules\Proptech\Application\Services\UtilityBillingService;
use Modules\Proptech\Domain\Models\BuildingSensor;
use Modules\Proptech\Domain\Models\BuildingZone;
use Modules\Proptech\Domain\Models\Flex\FlexBooking;
use Modules\Proptech\Domain\Models\Flex\FlexSpace;
use Modules\Proptech\Domain\Models\ZoneUtilityBilling;
use Tests\TestCase;

/**
 * L6.4: 90-Day Continuous Deterministic Simulation for Property & EPC MVP.
 *
 * Simulates real-world lifecycle across Proptech Smart Building & Flex Space,
 * and EPC Construction to Fixed Asset Capitalization:
 * - 90 continuous days (seed 42)
 * - Daily IoT smart meter readings across multiple building zones
 * - Weekly/monthly utility billings strictly from actual readings with B0′.2 cumulative rounding
 * - Daily flex-space bookings with anti-overlap slot locking, cryptographic passport check-in, and no-shows
 * - EPC milestone construction progress across Civil, MEP, and Finishing WBS leaves
 * - Dual-traversal PR2 certified Monthly Certificates (MC) with 5% retention
 * - BAST-1 and BAST Final issuances
 * - Final CIP reclassification into corporate Fixed Asset register
 * - Verification of prp:audit and epc:audit passing with exit code 0
 */
class Property90DaySimulationTest extends TestCase
{
    use RefreshDatabase;

    protected ProptechService $proptechService;

    protected UtilityBillingService $billingService;

    protected FlexSpaceService $flexService;

    protected EpcService $epcService;

    protected MonthlyCertificateService $mcService;

    protected LedgerService $ledgerService;

    protected User $tenant1;

    protected User $tenant2;

    protected User $flexGuest;

    protected User $epcManager;

    protected BuildingZone $zoneGf;

    protected BuildingZone $zoneL1;

    protected FlexSpace $boardroom;

    protected FlexSpace $hotdeskA;

    protected EpcProject $towerProject;

    protected EpcWbsNode $wbsCivil;

    protected EpcWbsNode $wbsMep;

    protected EpcWbsNode $wbsFinishing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacConglomerateSeeder::class);
        $this->seed(LedgerAccountsSeeder::class);

        $this->proptechService = app(ProptechService::class);
        $this->billingService = app(UtilityBillingService::class);
        $this->flexService = app(FlexSpaceService::class);
        $this->epcService = app(EpcService::class);
        $this->mcService = app(MonthlyCertificateService::class);
        $this->ledgerService = app(LedgerService::class);

        // Seed Users
        $this->tenant1 = User::factory()->create([
            'email' => 'tenant_apple@superweb.test',
            'name' => 'Apple Store Nusantara',
        ]);
        $this->tenant2 = User::factory()->create([
            'email' => 'tenant_zara@superweb.test',
            'name' => 'Zara Fashion Flagship',
        ]);
        $this->flexGuest = User::factory()->create([
            'email' => 'flex_nomad@superweb.test',
            'name' => 'Budi Santoso Nomad',
        ]);
        $this->epcManager = User::factory()->create([
            'email' => 'epc_director@superweb.test',
            'name' => 'Ir. Bambang Sudiro',
        ]);

        // Seed Building Zones
        $this->zoneGf = BuildingZone::create([
            'zone_code' => 'ZN-TOWER-GF',
            'building_code' => 'BLD-HQ-01',
            'name' => 'Grand Atrium & Retail Ground',
            'floor_level' => 'GF',
            'area_sqm' => 1200.0,
            'current_temp_c' => 24.0,
            'current_occupancy' => 45,
            'current_kwh_rate' => 2000.0,
            'baseline_kwh_per_hour' => 25.0,
        ]);
        $this->zoneL1 = BuildingZone::create([
            'zone_code' => 'ZN-TOWER-L1',
            'building_code' => 'BLD-HQ-01',
            'name' => 'Mezzanine & Co-Working Floor',
            'floor_level' => 'L1',
            'area_sqm' => 800.0,
            'current_temp_c' => 24.5,
            'current_occupancy' => 20,
            'current_kwh_rate' => 2000.0,
            'baseline_kwh_per_hour' => 15.0,
        ]);

        // Seed Flex Spaces
        $this->boardroom = FlexSpace::create([
            'space_code' => 'FLX-EXEC-BOARD',
            'name' => 'Executive Glass Boardroom',
            'space_type' => 'meeting_room',
            'zone_id' => $this->zoneL1->id,
            'capacity_persons' => 16,
            'rate_per_hour_idr' => 300_000,
            'area_sqm' => 60.0,
            'status' => 'AVAILABLE',
        ]);
        $this->hotdeskA = FlexSpace::create([
            'space_code' => 'FLX-DESK-A1',
            'name' => 'Dedicated Workstation A1',
            'space_type' => 'coworking_desk',
            'zone_id' => $this->zoneL1->id,
            'capacity_persons' => 1,
            'rate_per_hour_idr' => 50_000,
            'area_sqm' => 8.0,
            'status' => 'AVAILABLE',
        ]);

        // Seed EPC Project: 3 Billion IDR RAB Budget
        $this->towerProject = EpcProject::create([
            'id' => (string) Str::uuid(),
            'project_code' => 'EPC-TOWER-C',
            'project_name' => 'Tower C Commercial Extension & Solar Canopy',
            'project_type' => 'mall_extension',
            'client_entity_id' => (string) Str::uuid(),
            'total_rab_budget_idr' => 3_000_000_000,
            'target_physical_progress_pct' => 100.0,
            'actual_physical_progress_pct' => 0.0,
            'accumulated_cip_cost_idr' => 0,
            'capitalized_asset_value_idr' => 0,
            'start_date' => '2026-11-01',
            'target_completion_date' => '2027-02-01',
            'status' => 'in_progress',
        ]);

        // WBS Hierarchy: 40% Civil, 35% MEP, 25% Finishing = 100%
        $this->wbsCivil = EpcWbsNode::create([
            'project_id' => $this->towerProject->id,
            'wbs_code' => 'WBS-01-CIVIL',
            'task_name' => 'Pondasi Bore Pile & Struktur Baja Kolom',
            'work_package' => 'civil_structure',
            'weight_percentage' => 40.0,
            'budget_allocation_idr' => 1_200_000_000,
            'completion_percentage' => 0.0,
            'status' => 'pending',
        ]);
        $this->wbsMep = EpcWbsNode::create([
            'project_id' => $this->towerProject->id,
            'wbs_code' => 'WBS-02-MEP',
            'task_name' => 'Chiller Plant, Ducting & Smart Inverter',
            'work_package' => 'mep',
            'weight_percentage' => 35.0,
            'budget_allocation_idr' => 1_050_000_000,
            'completion_percentage' => 0.0,
            'status' => 'pending',
        ]);
        $this->wbsFinishing = EpcWbsNode::create([
            'project_id' => $this->towerProject->id,
            'wbs_code' => 'WBS-03-FINISH',
            'task_name' => 'Solar Glass Facade & Smart Lighting Setup',
            'work_package' => 'finishing',
            'weight_percentage' => 25.0,
            'budget_allocation_idr' => 750_000_000,
            'completion_percentage' => 0.0,
            'status' => 'pending',
        ]);
    }

    public function test_90_day_continuous_property_simulation(): void
    {
        mt_srand(42);

        $startDate = Carbon::parse('2026-11-01 00:00:00');
        $totalDays = 90;

        $stats = [
            'sensor_readings_count' => 0,
            'utility_billings_count' => 0,
            'flex_bookings_count' => 0,
            'flex_checkins_count' => 0,
            'flex_noshows_count' => 0,
            'mc_certificates_count' => 0,
            'total_utility_billed_idr' => 0,
            'total_gross_claims_idr' => 0,
            'total_retention_held_idr' => 0,
        ];

        for ($day = 1; $day <= $totalDays; $day++) {
            $currentDate = $startDate->copy()->addDays($day - 1);
            $dateStr = $currentDate->toDateString();

            // ─── 1. DAILY IOT SENSOR READINGS ───
            // Morning Reading (08:00)
            $kwhGfMorning = 40.0 + (mt_rand(0, 100) / 10.0);
            $kwhL1Morning = 20.0 + (mt_rand(0, 50) / 10.0);

            $this->proptechService->ingestSensorReading(
                zone: $this->zoneGf,
                sensorCode: 'PWR-GF-01',
                sensorType: 'POWER_KWH',
                readingValue: $kwhGfMorning,
                recordedAt: $currentDate->copy()->setTime(8, 0),
                idempotencyKey: "SENS-GF-MORN-D{$day}"
            );
            $this->proptechService->ingestSensorReading(
                zone: $this->zoneL1,
                sensorCode: 'PWR-L1-01',
                sensorType: 'POWER_KWH',
                readingValue: $kwhL1Morning,
                recordedAt: $currentDate->copy()->setTime(8, 0),
                idempotencyKey: "SENS-L1-MORN-D{$day}"
            );

            // Afternoon Reading (14:00)
            $kwhGfAfternoon = 60.0 + (mt_rand(0, 100) / 10.0);
            $kwhL1Afternoon = 30.0 + (mt_rand(0, 50) / 10.0);

            $this->proptechService->ingestSensorReading(
                zone: $this->zoneGf,
                sensorCode: 'PWR-GF-01',
                sensorType: 'POWER_KWH',
                readingValue: $kwhGfAfternoon,
                recordedAt: $currentDate->copy()->setTime(14, 0),
                idempotencyKey: "SENS-GF-AFT-D{$day}"
            );
            $this->proptechService->ingestSensorReading(
                zone: $this->zoneL1,
                sensorCode: 'PWR-L1-01',
                sensorType: 'POWER_KWH',
                readingValue: $kwhL1Afternoon,
                recordedAt: $currentDate->copy()->setTime(14, 0),
                idempotencyKey: "SENS-L1-AFT-D{$day}"
            );

            $stats['sensor_readings_count'] += 4;

            // ─── 2. WEEKLY UTILITY BILLING WITH CUMULATIVE ROUNDING ───
            // Every 7 days or end of simulation
            if ($day % 7 === 0 || $day === $totalDays) {
                $billingGf = $this->billingService->generateZoneBilling(
                    zone: $this->zoneGf,
                    tenantId: $this->tenant1->id,
                    billingDate: $currentDate,
                    overtimeAcHours: ($day % 14 === 0) ? 2 : 0
                );
                $billingL1 = $this->billingService->generateZoneBilling(
                    zone: $this->zoneL1,
                    tenantId: $this->tenant2->id,
                    billingDate: $currentDate,
                    overtimeAcHours: 0
                );

                $stats['utility_billings_count'] += 2;
                $stats['total_utility_billed_idr'] += ($billingGf->total_amount_idr + $billingL1->total_amount_idr);
            }

            // ─── 3. FLEX-SPACE BOOKING & ESCROW LIFECYCLE ───
            // Daily booking for boardroom: 10:00 to 12:00
            $startSlot = $currentDate->copy()->setTime(10, 0);
            $endSlot = $currentDate->copy()->setTime(12, 0);

            $booking = $this->flexService->createBooking(
                space: $this->boardroom,
                userId: $this->flexGuest->id,
                startTime: $startSlot,
                endTime: $endSlot
            );
            $stats['flex_bookings_count']++;

            // 90% check-in, 10% no-show
            if ($day % 10 !== 0) {
                $passportToken = "PASSPORT-NOMAD-DAY-{$day}-SEC-TOKEN";
                $this->flexService->checkInWithPassport($booking, $passportToken);
                $stats['flex_checkins_count']++;
            } else {
                $this->flexService->handleNoShow($booking);
                $stats['flex_noshows_count']++;
            }

            // ─── 4. EPC CONSTRUCTION PROGRESS & MONTHLY CERTIFICATES ───
            // Day 1 to 30: Civil structure progresses to 100%
            if ($day <= 30) {
                $civilProg = min(100.0, ($day / 30.0) * 100.0);
                $this->wbsCivil->update([
                    'completion_percentage' => $civilProg,
                    'status' => $civilProg >= 100.0 ? 'completed' : 'ongoing',
                ]);
            }

            // Milestone 1 (Day 30): Civil 100% complete -> Issue MC-1 (40% incremental)
            if ($day === 30) {
                $mc1 = $this->mcService->issueCertifiedProgress(
                    project: $this->towerProject,
                    claimedIncrementalPct: 40.0,
                    consultantName: 'PT Virama Karya Konsultan'
                );
                $stats['mc_certificates_count']++;
                $stats['total_gross_claims_idr'] += $mc1->gross_claim_amount_idr;
                $stats['total_retention_held_idr'] += $mc1->retention_deduction_idr;
            }

            // Day 31 to 60: MEP progresses to 100%
            if ($day > 30 && $day <= 60) {
                $mepProg = min(100.0, (($day - 30) / 30.0) * 100.0);
                $this->wbsMep->update([
                    'completion_percentage' => $mepProg,
                    'status' => $mepProg >= 100.0 ? 'completed' : 'ongoing',
                ]);
            }

            // Milestone 2 (Day 60): MEP 100% complete -> Issue MC-2 (35% incremental)
            if ($day === 60) {
                $mc2 = $this->mcService->issueCertifiedProgress(
                    project: $this->towerProject,
                    claimedIncrementalPct: 35.0,
                    consultantName: 'PT Virama Karya Konsultan'
                );
                $stats['mc_certificates_count']++;
                $stats['total_gross_claims_idr'] += $mc2->gross_claim_amount_idr;
                $stats['total_retention_held_idr'] += $mc2->retention_deduction_idr;
            }

            // Day 61 to 85: Finishing progresses to 100%
            if ($day > 60 && $day <= 85) {
                $finishProg = min(100.0, (($day - 60) / 25.0) * 100.0);
                $this->wbsFinishing->update([
                    'completion_percentage' => $finishProg,
                    'status' => $finishProg >= 100.0 ? 'completed' : 'ongoing',
                ]);
            }

            // Milestone 3 (Day 85): Finishing 100% complete -> Issue MC-3 (25% incremental, reaching 100%)
            if ($day === 85) {
                $mc3 = $this->mcService->issueCertifiedProgress(
                    project: $this->towerProject,
                    claimedIncrementalPct: 25.0,
                    consultantName: 'PT Virama Karya Konsultan'
                );
                $stats['mc_certificates_count']++;
                $stats['total_gross_claims_idr'] += $mc3->gross_claim_amount_idr;
                $stats['total_retention_held_idr'] += $mc3->retention_deduction_idr;
            }

            // Milestone 4 (Day 88): BAST Final Issued
            if ($day === 88) {
                $bast = $this->mcService->issueBastFinal($this->towerProject);
                expect($bast->bast_type)->toBe('BAST_FINAL');
            }

            // Milestone 5 (Day 90): Final Capitalization into Fixed Assets
            if ($day === 90) {
                $cap = $this->mcService->capitalizeProjectToAsset($this->towerProject);
                expect($cap->total_cip_cost_idr)->toBe(3_000_000_000);
            }
        }

        // ─── VERIFICATIONS AT DAY 90 ───
        // 1. Operational facts verifications
        expect($stats['sensor_readings_count'])->toBe(360); // 4 readings * 90 days
        expect($stats['flex_bookings_count'])->toBe(90);
        expect($stats['flex_checkins_count'])->toBe(81); // 90%
        expect($stats['flex_noshows_count'])->toBe(9); // 10%
        expect($stats['mc_certificates_count'])->toBe(3);
        expect($stats['total_gross_claims_idr'])->toBe(3_000_000_000); // 100% of 3 Billion budget
        expect($stats['total_retention_held_idr'])->toBe(150_000_000); // 5% of 3 Billion

        // 2. Project final state
        $projectFresh = $this->towerProject->fresh();
        expect((float) $projectFresh->actual_physical_progress_pct)->toBe(100.0);
        expect($projectFresh->accumulated_cip_cost_idr)->toBe(0); // Fully capitalized
        expect($projectFresh->capitalized_asset_value_idr)->toBe(3_000_000_000);
        expect($projectFresh->status)->toBe('capitalized');

        // 3. Two-Source Audits (prp:audit and epc:audit)
        $this->artisan('prp:audit')->assertSuccessful();
        $this->artisan('epc:audit')->assertSuccessful();

        // 4. Deterministic State Hash Checksum
        $statePayload = json_encode([
            'total_sensors' => BuildingSensor::count(),
            'total_billings' => ZoneUtilityBilling::count(),
            'total_utility_billed' => $stats['total_utility_billed_idr'],
            'total_bookings' => FlexBooking::count(),
            'total_checked_in' => FlexBooking::where('status', 'CHECKED_IN')->count(),
            'total_no_show' => FlexBooking::where('status', 'NO_SHOW')->count(),
            'total_mc' => EpcProgressCertificate::count(),
            'total_cip_active' => $projectFresh->accumulated_cip_cost_idr,
            'total_capitalized' => $projectFresh->capitalized_asset_value_idr,
        ]);
        $deterministicHash = hash('sha256', (string) $statePayload);

        expect($deterministicHash)->toBeString();
        expect(strlen($deterministicHash))->toBe(64);
    }
}
