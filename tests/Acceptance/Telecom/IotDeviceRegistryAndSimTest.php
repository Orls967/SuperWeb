<?php

declare(strict_types=1);

namespace Tests\Acceptance\Telecom;

use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Tlx\Contracts\IotDeviceRegistry;
use Modules\Tlx\Domain\Models\IotDevice;
use Modules\Tlx\Domain\Models\SimProfile;
use Tests\TestCase;

class IotDeviceRegistryAndSimTest extends TestCase
{
    use RefreshDatabase;

    protected IotDeviceRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->registry = app(IotDeviceRegistry::class);
    }

    public function test_device_registration_creates_active_record_in_single_registry(): void
    {
        $device = $this->registry->registerDevice([
            'device_code' => 'DEV-IOT-0001',
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => 'ENT-LOGISTICS-01',
            'using_module' => 'Telematics',
            'monthly_fee_minor' => 30000,
            'idempotency_key' => 'TLX-REG:DEV-IOT-0001',
        ]);

        expect($device['device_code'])->toBe('DEV-IOT-0001')
            ->and($device['status'])->toBe('PROVISIONED')
            ->and($device['owner_entity_id'])->toBe('ENT-LOGISTICS-01')
            ->and($device['monthly_fee_minor'])->toBe(30000);

        $dbDevice = IotDevice::where('device_code', 'DEV-IOT-0001')->first();
        expect($dbDevice)->not->toBeNull()
            ->and($dbDevice->status)->toBe('PROVISIONED');
    }

    public function test_duplicate_device_registration_with_same_key_is_idempotent(): void
    {
        $payload = [
            'device_code' => 'DEV-IOT-0002',
            'device_type' => 'BUILDING_SENSOR',
            'owner_entity_id' => 'ENT-PROPTECH-01',
            'using_module' => 'Proptech',
            'monthly_fee_minor' => 25000,
            'idempotency_key' => 'TLX-REG:DEV-IOT-0002',
        ];

        $first = $this->registry->registerDevice($payload);
        $second = $this->registry->registerDevice($payload);

        expect($first['id'])->toBe($second['id'])
            ->and(IotDevice::where('device_code', 'DEV-IOT-0002')->count())->toBe(1);
    }

    public function test_device_registration_without_idempotency_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registry->registerDevice([
            'device_code' => 'DEV-NO-KEY',
            'device_type' => 'MINING_SENSOR',
            'owner_entity_id' => 'ENT-MINING-01',
            'using_module' => 'Mining',
            'monthly_fee_minor' => 40000,
        ]);
    }

    public function test_sim_activation_allocates_iccid_to_device(): void
    {
        $this->registry->registerDevice([
            'device_code' => 'DEV-SIM-01',
            'device_type' => 'ENERGY_METER',
            'owner_entity_id' => 'ENT-ENERGY-01',
            'using_module' => 'Energy',
            'monthly_fee_minor' => 35000,
            'idempotency_key' => 'TLX-REG:DEV-SIM-01',
        ]);

        SimProfile::create([
            'iccid' => '8962000000000001',
            'imsi' => '510100000000001',
            'msisdn' => '628990000001',
            'plan_code' => 'IOT-DATA-5GB',
            'monthly_quota_mb' => 5000,
            'status' => 'AVAILABLE',
        ]);

        $activated = $this->registry->activateSim('DEV-SIM-01', '8962000000000001', 'TLX-ACT:DEV-SIM-01');

        expect($activated['active_sim_iccid'])->toBe('8962000000000001')
            ->and($activated['status'])->toBe('ACTIVE');

        $sim = SimProfile::where('iccid', '8962000000000001')->first();
        expect($sim->status)->toBe('ALLOCATED')
            ->and($sim->assigned_device_id)->toBe($activated['id']);
    }

    public function test_only_one_active_sim_allowed_per_device(): void
    {
        $this->registry->registerDevice([
            'device_code' => 'DEV-ONE-SIM',
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => 'ENT-MOBILITY-01',
            'using_module' => 'Mobility',
            'monthly_fee_minor' => 30000,
            'idempotency_key' => 'TLX-REG:DEV-ONE-SIM',
        ]);

        SimProfile::create([
            'iccid' => '8962000000000002',
            'imsi' => '510100000000002',
            'msisdn' => '628990000002',
            'plan_code' => 'IOT-DATA-10GB',
            'monthly_quota_mb' => 10000,
            'status' => 'AVAILABLE',
        ]);

        SimProfile::create([
            'iccid' => '8962000000000003',
            'imsi' => '510100000000003',
            'msisdn' => '628990000003',
            'plan_code' => 'IOT-DATA-10GB',
            'monthly_quota_mb' => 10000,
            'status' => 'AVAILABLE',
        ]);

        $this->registry->activateSim('DEV-ONE-SIM', '8962000000000002', 'TLX-ACT:DEV-ONE-SIM-1');

        $this->expectException(InvalidArgumentException::class);
        $this->registry->activateSim('DEV-ONE-SIM', '8962000000000003', 'TLX-ACT:DEV-ONE-SIM-2');
    }

    public function test_device_lifecycle_status_transitions(): void
    {
        $this->registry->registerDevice([
            'device_code' => 'DEV-LIFECYCLE',
            'device_type' => 'PATIENT_MONITOR',
            'owner_entity_id' => 'ENT-HEALTH-01',
            'using_module' => 'Hospital',
            'monthly_fee_minor' => 50000,
            'idempotency_key' => 'TLX-REG:DEV-LIFECYCLE',
        ]);

        SimProfile::create([
            'iccid' => '8962000000000004',
            'imsi' => '510100000000004',
            'msisdn' => '628990000004',
            'plan_code' => 'IOT-HEALTH-MED',
            'monthly_quota_mb' => 2000,
            'status' => 'AVAILABLE',
        ]);

        $active = $this->registry->activateSim('DEV-LIFECYCLE', '8962000000000004', 'TLX-ACT:DEV-LIFECYCLE');
        expect($active['status'])->toBe('ACTIVE');

        $suspended = $this->registry->suspendDevice('DEV-LIFECYCLE', 'Overdue billing payment', 'TLX-SUS:DEV-LIFECYCLE');
        expect($suspended['status'])->toBe('SUSPENDED');

        $reactivated = $this->registry->activateSim('DEV-LIFECYCLE', '8962000000000004', 'TLX-REACT:DEV-LIFECYCLE');
        expect($reactivated['status'])->toBe('ACTIVE');

        $retired = $this->registry->retireDevice('DEV-LIFECYCLE', 'TLX-RET:DEV-LIFECYCLE');
        expect($retired['status'])->toBe('RETIRED');
    }

    public function test_illegal_status_transition_is_rejected(): void
    {
        $this->registry->registerDevice([
            'device_code' => 'DEV-ILLEGAL',
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => 'ENT-FLEET-01',
            'using_module' => 'Mobility',
            'monthly_fee_minor' => 30000,
            'idempotency_key' => 'TLX-REG:DEV-ILLEGAL',
        ]);

        $this->registry->retireDevice('DEV-ILLEGAL', 'TLX-RET:DEV-ILLEGAL');

        $this->expectException(InvalidArgumentException::class);
        $this->registry->activateSim('DEV-ILLEGAL', '8962000000000001', 'TLX-ACT:DEV-ILLEGAL');
    }

    public function test_assert_active_contract_verifies_device_state(): void
    {
        $this->registry->registerDevice([
            'device_code' => 'DEV-ASSERT',
            'device_type' => 'BUILDING_SENSOR',
            'owner_entity_id' => 'ENT-MALL-01',
            'using_module' => 'Proptech',
            'monthly_fee_minor' => 20000,
            'idempotency_key' => 'TLX-REG:DEV-ASSERT',
        ]);

        SimProfile::create([
            'iccid' => '8962000000000005',
            'imsi' => '510100000000005',
            'msisdn' => '628990000005',
            'plan_code' => 'IOT-MALL-SENS',
            'monthly_quota_mb' => 1000,
            'status' => 'AVAILABLE',
        ]);

        $now = CarbonImmutable::parse('2026-10-15 12:00:00');

        expect($this->registry->assertActive('DEV-ASSERT', $now))->toBeFalse();

        $this->registry->activateSim('DEV-ASSERT', '8962000000000005', 'TLX-ACT:DEV-ASSERT');
        expect($this->registry->assertActive('DEV-ASSERT', $now))->toBeTrue();

        $this->registry->suspendDevice('DEV-ASSERT', 'Maintenance', 'TLX-SUS:DEV-ASSERT');
        expect($this->registry->assertActive('DEV-ASSERT', $now))->toBeFalse();
    }

    public function test_find_device_by_code_or_sim_iccid(): void
    {
        $this->registry->registerDevice([
            'device_code' => 'DEV-FIND-01',
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => 'ENT-LOGISTICS-02',
            'using_module' => 'Telematics',
            'monthly_fee_minor' => 30000,
            'idempotency_key' => 'TLX-REG:DEV-FIND-01',
        ]);

        SimProfile::create([
            'iccid' => '8962000000000006',
            'imsi' => '510100000000006',
            'msisdn' => '628990000006',
            'plan_code' => 'IOT-5GB',
            'monthly_quota_mb' => 5000,
            'status' => 'AVAILABLE',
        ]);

        $this->registry->activateSim('DEV-FIND-01', '8962000000000006', 'TLX-ACT:DEV-FIND-01');

        $byCode = $this->registry->findDevice('DEV-FIND-01');
        $byIccid = $this->registry->findDevice('8962000000000006');
        $unknown = $this->registry->findDevice('NON_EXISTENT_IDENTIFIER');

        expect($byCode)->not->toBeNull()
            ->and($byCode['device_code'])->toBe('DEV-FIND-01')
            ->and($byIccid)->not->toBeNull()
            ->and($byIccid['device_code'])->toBe('DEV-FIND-01')
            ->and($unknown)->toBeNull();
    }
}
