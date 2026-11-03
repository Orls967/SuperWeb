<?php

declare(strict_types=1);

namespace Tests\Acceptance\Property;

use App\Models\User;
use Brick\Math\BigRational;
use Database\Seeders\LedgerAccountsSeeder;
use Database\Seeders\RbacConglomerateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Epc\Application\Services\MonthlyCertificateService;
use Modules\Epc\Domain\Models\EpcProgressCertificate;
use Modules\Epc\Domain\Models\EpcProject;
use Modules\Proptech\Application\Services\FlexSpaceService;
use Modules\Proptech\Domain\Models\BuildingZone;
use Modules\Proptech\Domain\Models\Flex\FlexBooking;
use Modules\Proptech\Domain\Models\Flex\FlexSpace;
use Modules\Proptech\Domain\Models\ZoneUtilityBilling;
use Modules\Shared\Domain\Math\CumulativeRoundingCertificate;
use Modules\Shared\Domain\Math\CumulativeRoundingCertificateVerifier;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;
use Modules\Shared\Domain\Math\WeightedTree;
use Modules\Shared\Domain\Math\WeightedTreeNode;
use Tests\TestCase;

/**
 * L6.5: Self-Sabotage & Mutation Testing Suite for Property MVP.
 * 9 intentional sabotages across Proptech Smart Building & Flex Space (1-4),
 * Shared Math Certificate Verifiers (5-6), and EPC Asset Capitalization (7-9).
 * Proves that every domain invariant, safety rule, and two-source audit actively catches malicious attacks.
 */
class PropertySelfSabotageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected BuildingZone $zone;

    protected FlexSpace $space;

    protected EpcProject $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacConglomerateSeeder::class);
        $this->seed(LedgerAccountsSeeder::class);

        $this->user = User::factory()->create([
            'email' => 'saboteur@superweb.test',
            'name' => 'Dr. Saboteur',
        ]);

        $this->zone = BuildingZone::create([
            'zone_code' => 'ZN-SAB-01',
            'building_code' => 'BLD-SAB-01',
            'name' => 'Sabotage Target Wing',
            'floor_level' => '1',
            'area_sqm' => 500.0,
            'current_temp_c' => 24.0,
            'current_occupancy' => 20,
            'current_kwh_rate' => 2000.0,
            'baseline_kwh_per_hour' => 10.0,
        ]);

        $this->space = FlexSpace::create([
            'space_code' => 'FLX-SAB-ROOM',
            'name' => 'Target Flex Room',
            'space_type' => 'meeting_room',
            'zone_id' => $this->zone->id,
            'capacity_persons' => 8,
            'rate_per_hour_idr' => 200_000,
            'status' => 'AVAILABLE',
        ]);

        $this->project = EpcProject::create([
            'id' => (string) Str::uuid(),
            'project_code' => 'EPC-SAB-PRJ',
            'project_name' => 'Project Under Sabotage',
            'project_type' => 'mall_extension',
            'client_entity_id' => (string) Str::uuid(),
            'total_rab_budget_idr' => 1_000_000_000,
            'target_physical_progress_pct' => 100.0,
            'actual_physical_progress_pct' => 0.0,
            'accumulated_cip_cost_idr' => 0,
            'capitalized_asset_value_idr' => 0,
            'start_date' => now()->toDateString(),
            'target_completion_date' => now()->addMonths(6)->toDateString(),
            'status' => 'in_progress',
        ]);
    }

    public function test_sabotage_01_tampered_utility_billing_amount_fails_prp_audit(): void
    {
        // 100 kWh * 2000 rate should be 200,000 IDR, tampered to 999,999,999 IDR
        ZoneUtilityBilling::create([
            'billing_code' => 'BLG-SAB-01',
            'zone_id' => $this->zone->id,
            'tenant_id' => $this->user->id,
            'billing_date' => now()->toDateString(),
            'total_kwh' => 100.0,
            'total_amount_idr' => 999_999_999,
            'carbon_emission_kg' => 85.0,
            'status' => 'BILLED',
            'idempotency_key' => 'SAB-KEY-01',
        ]);

        $this->artisan('prp:audit')
            ->assertFailed()
            ->expectsOutputToContain('Proptech audit FAILED');
    }

    public function test_sabotage_02_tampered_ghg_carbon_emission_fails_prp_audit(): void
    {
        // 100 kWh * 0.85 should be 85.0 kg CO2e, tampered to 9999.99 kg
        ZoneUtilityBilling::create([
            'billing_code' => 'BLG-SAB-02',
            'zone_id' => $this->zone->id,
            'tenant_id' => $this->user->id,
            'billing_date' => now()->toDateString(),
            'total_kwh' => 100.0,
            'total_amount_idr' => 200_000,
            'carbon_emission_kg' => 9999.99,
            'status' => 'BILLED',
            'idempotency_key' => 'SAB-KEY-02',
        ]);

        $this->artisan('prp:audit')
            ->assertFailed()
            ->expectsOutputToContain('Proptech audit FAILED');
    }

    public function test_sabotage_03_injected_overlapping_flex_booking_fails_flex_audit(): void
    {
        $service = app(FlexSpaceService::class);

        // Booking 1: 10:00 to 12:00
        FlexBooking::create([
            'booking_code' => 'FBK-SAB-01',
            'flex_space_id' => $this->space->id,
            'user_id' => $this->user->id,
            'start_time' => now()->setTime(10, 0),
            'end_time' => now()->setTime(12, 0),
            'deposit_amount_idr' => 120_000,
            'total_price_idr' => 400_000,
            'status' => 'HELD',
        ]);

        // Directly insert conflicting booking: 11:00 to 13:00 (bypassing service)
        FlexBooking::create([
            'booking_code' => 'FBK-SAB-OVERLAP',
            'flex_space_id' => $this->space->id,
            'user_id' => $this->user->id,
            'start_time' => now()->setTime(11, 0),
            'end_time' => now()->setTime(13, 0),
            'deposit_amount_idr' => 120_000,
            'total_price_idr' => 400_000,
            'status' => 'HELD',
        ]);

        $audit = $service->auditFlex();

        expect($audit['passed'])->toBeFalse();
        expect(implode(' ', $audit['discrepancies']))->toContain('overlaps with');
    }

    public function test_sabotage_04_tampered_flex_ledger_revenue_balance_fails_flex_audit(): void
    {
        $service = app(FlexSpaceService::class);

        // Checked in booking for 400,000 IDR
        FlexBooking::create([
            'booking_code' => 'FBK-SAB-CAP',
            'flex_space_id' => $this->space->id,
            'user_id' => $this->user->id,
            'start_time' => now()->setTime(14, 0),
            'end_time' => now()->setTime(16, 0),
            'deposit_amount_idr' => 120_000,
            'total_price_idr' => 400_000,
            'status' => 'CHECKED_IN',
        ]);

        // Tamper ledger cached balance to 0 (stealing revenue)
        $table = Schema::hasTable('bank_ledger_accounts') ? 'bank_ledger_accounts' : 'bk_ledger_accounts';
        DB::table($table)
            ->where('code', 'prp:flex_revenue:IDR')
            ->update(['cached_balance' => 0]);

        $audit = $service->auditFlex();

        expect($audit['passed'])->toBeFalse();
        expect(implode(' ', $audit['discrepancies']))->toContain('Ledger revenue mismatch');
    }

    public function test_sabotage_05_tampered_cumulative_rounding_certificate_fails_verifier(): void
    {
        $total = 500_000;
        $weights = [5000, 5000];
        $exactRatio = BigRational::of('250000');

        // Sabotage: allocations sum to 450,000 != 500,000 (missing 50,000 IDR!)
        $tampered = new CumulativeRoundingCertificate(
            total: $total,
            periods: 2,
            schedule: [200_000, 250_000],
            exactPortions: [$exactRatio, $exactRatio],
            weights: $weights
        );

        expect(fn () => CumulativeRoundingCertificateVerifier::verify($tampered))
            ->toThrow(InvalidCertificateException::class);
    }

    public function test_sabotage_06_tampered_wbs_tree_sibling_weights_fails_verification(): void
    {
        // Sabotage: sibling weights sum to 9,000 bps != 10,000 bps
        $leaf1 = new WeightedTreeNode('leaf1', 4_000, 5_000);
        $leaf2 = new WeightedTreeNode('leaf2', 5_000, 5_000);
        $root = new WeightedTreeNode('root', 10_000, null, [$leaf1, $leaf2]);

        expect(fn () => WeightedTree::computeProgress($root))
            ->toThrow(InvalidArgumentException::class, 'must sum to 10000 bps');
    }

    public function test_sabotage_07_inflated_project_progress_above_100_fails_epc_audit(): void
    {
        // Corrupt project actual progress to 150.00%
        $this->project->update([
            'actual_physical_progress_pct' => 150.0,
        ]);

        $this->artisan('epc:audit')
            ->assertFailed()
            ->expectsOutputToContain('actual progress exceeds 100%');
    }

    public function test_sabotage_08_tampered_cip_cost_discrepancy_fails_epc_audit(): void
    {
        // Create an MC with 400,000,000 IDR gross claim
        EpcProgressCertificate::create([
            'project_id' => $this->project->id,
            'certificate_number' => 'MC-SAB-01',
            'period_month' => 11,
            'period_year' => 2026,
            'certified_cumulative_progress_pct' => 40.0,
            'certified_incremental_progress_pct' => 40.0,
            'gross_claim_amount_idr' => 400_000_000,
            'retention_deduction_idr' => 20_000_000,
            'net_payable_amount_idr' => 380_000_000,
            'supervising_consultant_name' => 'PT Konsultan',
            'certified_at' => now(),
            'status' => 'approved',
        ]);

        // Sabotage: tamper project CIP cost in database to 100,000,000 (300M discrepancy!)
        $this->project->update([
            'accumulated_cip_cost_idr' => 100_000_000,
            'actual_physical_progress_pct' => 40.0,
        ]);

        $this->artisan('epc:audit')
            ->assertFailed()
            ->expectsOutputToContain('CIP discrepancy');
    }

    public function test_sabotage_09_premature_capitalization_without_bast_final_rejected(): void
    {
        $mcService = app(MonthlyCertificateService::class);

        // Project has CIP but status is in_progress (no BAST Final issued)
        $this->project->update([
            'accumulated_cip_cost_idr' => 500_000_000,
            'actual_physical_progress_pct' => 60.0,
            'status' => 'in_progress',
        ]);

        expect(fn () => $mcService->capitalizeProjectToAsset($this->project))
            ->toThrow(InvalidArgumentException::class, 'Cannot capitalize CIP before BAST Final is approved');
    }
}
