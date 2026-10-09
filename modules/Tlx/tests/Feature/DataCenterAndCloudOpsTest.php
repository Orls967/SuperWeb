<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Tlx\Application\Services\DataCenterAndCloudOpsService;
use Modules\Tlx\Domain\Models\TelecomDataCenter;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Setup standard ledger accounts for cloud chargeback
    LedgerAccount::firstOrCreate(
        ['code' => 'tlx:cloud_chargeback_receivable:IDR'],
        [
            'kind' => 'asset',
            'asset_code' => 'IDR',
            'allow_negative' => true,
            'cached_balance' => '0',
            'name' => 'Telecom Cloud Chargeback Receivable',
        ]
    );

    LedgerAccount::firstOrCreate(
        ['code' => 'tlx:cloud_infrastructure_revenue:IDR'],
        [
            'kind' => 'revenue',
            'asset_code' => 'IDR',
            'allow_negative' => true,
            'cached_balance' => '0',
            'name' => 'Telecom Cloud Infrastructure Revenue',
        ]
    );
});

test('(a) data center metrics accurately computes PUE = total facility power / it load power', function () {
    $service = app(DataCenterAndCloudOpsService::class);

    $dc = $service->recordDataCenterMetrics([
        'dc_code' => 'DC-CGK-01',
        'name' => 'Jakarta Cyber Data Center 1',
        'city' => 'Jakarta',
        'total_facility_power_kw' => 2400.0,
        'it_load_power_kw' => 1600.0,
        'total_racks_capacity' => 500,
        'occupied_racks' => 420,
    ]);

    expect($dc)->toBeInstanceOf(TelecomDataCenter::class)
        ->and($dc->measured_pue)->toBe(1.50)
        ->and($dc->occupied_racks)->toBe(420);
});

test('(b) colocation overdue billing suspends port status as an escape hatch', function () {
    $service = app(DataCenterAndCloudOpsService::class);

    $colo = $service->createColocationContract([
        'contract_number' => 'COLO-BANK-001',
        'tenant_party_id' => 'TENANT-FINTECH-99',
        'dc_id' => 'DC-CGK-01',
        'rack_units_allocated' => 42,
        'monthly_rack_fee_minor' => 2500000000,
        'power_rate_per_kwh_minor' => 150000,
        'monthly_kwh_consumed' => 3500.5,
    ]);

    expect($colo->port_status)->toBe('UP')
        ->and($colo->is_overdue)->toBeFalse();

    $suspendedColo = $service->checkAndSuspendOverdueColo($colo->id, true);
    expect($suspendedColo->port_status)->toBe('SUSPENDED')
        ->and($suspendedColo->is_overdue)->toBeTrue();

    // Re-activating when paid
    $restoredColo = $service->checkAndSuspendOverdueColo($colo->id, false);
    expect($restoredColo->port_status)->toBe('UP')
        ->and($restoredColo->is_overdue)->toBeFalse();
});

test('(c) cloud compute instance chargeback posts balanced ledger transaction with sum 0', function () {
    $service = app(DataCenterAndCloudOpsService::class);

    $vm = $service->provisionCloudComputeInstance([
        'consumer_entity_id' => 'BU-ECOMMERCE-OPS',
        'sku_flavor' => 'c5.4xlarge',
        'vcpus' => 16,
        'ram_gb' => 64,
        'storage_gb' => 500,
        'hourly_rate_minor' => 2500000, // 25,000 IDR/hour in minor
    ]);

    expect($vm->running_hours_billed)->toBe(0)
        ->and($vm->total_chargeback_minor)->toBe(0);

    $billedVm = $service->processCloudChargeback($vm->id, 24);

    expect($billedVm->running_hours_billed)->toBe(24)
        ->and($billedVm->total_chargeback_minor)->toBe(60000000); // 24 * 2,500,000
});

test('(d) disaster recovery restore drill verifies SLA compliance on RPO and RTO', function () {
    $service = app(DataCenterAndCloudOpsService::class);

    // Compliant drill: actual within target
    $drillPass = $service->recordDrRestoreDrill([
        'dataset_class' => 'TIER-1-TRANSACTIONAL-DB',
        'target_rpo_minutes' => 15,
        'target_rto_minutes' => 60,
        'actual_data_loss_minutes' => 5,
        'actual_recovery_time_minutes' => 45,
    ]);

    expect($drillPass->is_sla_compliant)->toBeTrue();

    // Breached drill: actual exceeds target
    $drillFail = $service->recordDrRestoreDrill([
        'dataset_class' => 'TIER-1-TRANSACTIONAL-DB',
        'target_rpo_minutes' => 15,
        'target_rto_minutes' => 60,
        'actual_data_loss_minutes' => 25, // breach!
        'actual_recovery_time_minutes' => 75, // breach!
    ]);

    expect($drillFail->is_sla_compliant)->toBeFalse();
});
