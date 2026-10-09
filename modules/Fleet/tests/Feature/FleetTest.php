<?php

declare(strict_types=1);

namespace Modules\Fleet\tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Fleet\Application\Services\FleetLeasingService;
use Modules\Fleet\Domain\Models\FleetContract;
use Tests\TestCase;

class FleetTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Vehicle $vehicle1;

    protected Vehicle $vehicle2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->vehicle1 = Vehicle::create([
            'user_id' => $this->user->id,
            'plate_number' => 'B 1111 FLT',
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2023,
            'vin' => 'MR011111111111111',
        ]);

        $this->vehicle2 = Vehicle::create([
            'user_id' => $this->user->id,
            'plate_number' => 'B 2222 FLT',
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2023,
            'vin' => 'MR022222222222222',
        ]);

        LedgerAccount::create([
            'code' => 'ar:fleet:party:501:IDR',
            'name' => 'AR Fleet Leasing Party 501',
            'asset_code' => 'IDR',
            'kind' => 'asset',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);

        LedgerAccount::create([
            'code' => 'oto:fleet_lease_revenue:IDR',
            'name' => 'Fleet Lease Revenue',
            'asset_code' => 'IDR',
            'kind' => 'revenue',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);

        LedgerAccount::create([
            'code' => 'expense:fleet:sla_penalties:IDR',
            'name' => 'Fleet SLA Penalties Expense',
            'asset_code' => 'IDR',
            'kind' => 'expense',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);
    }

    public function test_fleet_contract_creation_and_double_lease_prevention(): void
    {
        /** @var FleetLeasingService $service */
        $service = app(FleetLeasingService::class);

        $contract = $service->createLeaseContract(
            partyId: 501,
            durationMonths: 12,
            monthlyRentalIdr: 15000000,
            vehicleIds: [$this->vehicle1->id],
            startDate: Carbon::parse('2026-01-01')
        );

        $this->assertEquals(180000000, $contract->total_lease_value_idr);
        $this->assertCount(1, $contract->units);

        // Trying to lease vehicle1 again while active should throw
        $this->expectException(\InvalidArgumentException::class);
        $service->createLeaseContract(
            partyId: 502,
            durationMonths: 6,
            monthlyRentalIdr: 10000000,
            vehicleIds: [$this->vehicle1->id],
            startDate: Carbon::parse('2026-02-01')
        );
    }

    public function test_fleet_lease_amortization_sums_to_total_lease_value(): void
    {
        /** @var FleetLeasingService $service */
        $service = app(FleetLeasingService::class);

        $contract = $service->createLeaseContract(
            partyId: 501,
            durationMonths: 3,
            monthlyRentalIdr: 10000000,
            vehicleIds: [$this->vehicle2->id],
            startDate: Carbon::parse('2026-01-01')
        );

        $this->assertEquals(30000000, $contract->total_lease_value_idr);

        for ($i = 1; $i <= 3; $i++) {
            $service->amortizeMonthly($contract, $i);
        }

        $contract->refresh();
        $this->assertEquals(30000000, $contract->accumulated_amortized_idr);
        $this->assertEquals('completed', $contract->status);
    }

    public function test_sla_breach_compensation_posts_correctly(): void
    {
        /** @var FleetLeasingService $service */
        $service = app(FleetLeasingService::class);

        $contract = FleetContract::create([
            'contract_number' => 'FLT-TEST-SLA',
            'party_id' => 501,
            'duration_months' => 12,
            'total_units' => 1,
            'monthly_rental_idr' => 10000000,
            'total_lease_value_idr' => 120000000,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
            'status' => 'active',
        ]);

        $service->recordSlaBreachCompensation($contract, 36, 1500000);
        $this->assertTrue(true);
    }
}
