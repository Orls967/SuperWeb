<?php

declare(strict_types=1);

namespace Modules\Tlx\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Tlx\Domain\Models\CloudComputeInstance;
use Modules\Tlx\Domain\Models\ColocationContract;
use Modules\Tlx\Domain\Models\DrRestoreDrill;
use Modules\Tlx\Domain\Models\TelecomDataCenter;
use RuntimeException;

class DataCenterAndCloudOpsService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function recordDataCenterMetrics(array $params): TelecomDataCenter
    {
        $totalPower = (float) $params['total_facility_power_kw'];
        $itLoad = (float) $params['it_load_power_kw'];

        if ($itLoad <= 0.0) {
            throw new RuntimeException('IT load power must be strictly positive to compute PUE.');
        }

        $pue = round($totalPower / $itLoad, 2);

        return TelecomDataCenter::create([
            'id' => (string) Str::uuid(),
            'dc_code' => $params['dc_code'],
            'name' => $params['name'],
            'city' => $params['city'],
            'total_facility_power_kw' => $totalPower,
            'it_load_power_kw' => $itLoad,
            'measured_pue' => $pue,
            'total_racks_capacity' => (int) $params['total_racks_capacity'],
            'occupied_racks' => (int) $params['occupied_racks'],
        ]);
    }

    public function createColocationContract(array $params): ColocationContract
    {
        return ColocationContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => $params['contract_number'] ?? 'COLO-'.strtoupper(Str::random(8)),
            'tenant_party_id' => $params['tenant_party_id'],
            'dc_id' => $params['dc_id'],
            'rack_units_allocated' => (int) $params['rack_units_allocated'],
            'monthly_rack_fee_minor' => (int) $params['monthly_rack_fee_minor'],
            'power_rate_per_kwh_minor' => (int) $params['power_rate_per_kwh_minor'],
            'monthly_kwh_consumed' => (float) ($params['monthly_kwh_consumed'] ?? 0.0),
            'port_status' => 'UP',
            'is_overdue' => false,
        ]);
    }

    public function checkAndSuspendOverdueColo(string $contractId, bool $isOverdue): ColocationContract
    {
        $colo = ColocationContract::findOrFail($contractId);
        $colo->is_overdue = $isOverdue;

        if ($isOverdue) {
            $colo->port_status = 'SUSPENDED'; // escape hatch: port suspended
        } else {
            $colo->port_status = 'UP';
        }
        $colo->save();

        return $colo;
    }

    public function provisionCloudComputeInstance(array $params): CloudComputeInstance
    {
        return CloudComputeInstance::create([
            'id' => (string) Str::uuid(),
            'instance_code' => 'VM-'.strtoupper(Str::random(8)),
            'consumer_entity_id' => $params['consumer_entity_id'],
            'sku_flavor' => $params['sku_flavor'],
            'vcpus' => (int) $params['vcpus'],
            'ram_gb' => (int) $params['ram_gb'],
            'storage_gb' => (int) $params['storage_gb'],
            'hourly_rate_minor' => (int) $params['hourly_rate_minor'],
            'running_hours_billed' => 0,
            'total_chargeback_minor' => 0,
            'status' => 'RUNNING',
        ]);
    }

    public function processCloudChargeback(string $instanceId, int $hours): CloudComputeInstance
    {
        $vm = CloudComputeInstance::findOrFail($instanceId);
        $chargeAmount = $hours * $vm->hourly_rate_minor;

        return DB::transaction(function () use ($vm, $hours, $chargeAmount) {
            $tx = $this->ledgerService->post(new PostingDTO(
                type: 'TELECOM_CLOUD_COMPUTE_CHARGEBACK',
                description: "Internal compute chargeback for {$vm->consumer_entity_id} ({$vm->instance_code})",
                idempotencyKey: 'TLX-VM-'.$vm->instance_code.'-H'.$hours,
                entries: [
                    PostingEntryDTO::forCode('tlx:cloud_chargeback_receivable:IDR', 'IDR', $chargeAmount),
                    PostingEntryDTO::forCode('tlx:cloud_infrastructure_revenue:IDR', 'IDR', -$chargeAmount),
                ],
                referenceType: 'CLOUD_INSTANCE',
                referenceId: $vm->instance_code,
            ));

            $vm->running_hours_billed += $hours;
            $vm->total_chargeback_minor += $chargeAmount;
            $vm->save();

            return $vm;
        });
    }

    public function recordDrRestoreDrill(array $params): DrRestoreDrill
    {
        $targetRpo = (int) $params['target_rpo_minutes'];
        $targetRto = (int) $params['target_rto_minutes'];
        $actualRpo = (int) $params['actual_data_loss_minutes'];
        $actualRto = (int) $params['actual_recovery_time_minutes'];

        $isCompliant = ($actualRpo <= $targetRpo) && ($actualRto <= $targetRto);

        return DrRestoreDrill::create([
            'id' => (string) Str::uuid(),
            'drill_code' => 'DRILL-'.strtoupper(Str::random(8)),
            'dataset_class' => $params['dataset_class'],
            'target_rpo_minutes' => $targetRpo,
            'target_rto_minutes' => $targetRto,
            'actual_data_loss_minutes' => $actualRpo,
            'actual_recovery_time_minutes' => $actualRto,
            'is_sla_compliant' => $isCompliant,
            'drill_conducted_at' => Carbon::now(),
        ]);
    }
}
