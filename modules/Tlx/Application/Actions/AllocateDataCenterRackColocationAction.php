<?php

declare(strict_types=1);

namespace Modules\Tlx\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;
use Modules\Tlx\Domain\Models\ColocationContract;
use Modules\Tlx\Domain\Models\TelecomDataCenter;
use RuntimeException;

class AllocateDataCenterRackColocationAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): ColocationContract
    {
        $dc = TelecomDataCenter::findOrFail($params['dc_id']);
        $racks = (int) $params['rack_units_allocated'];
        $tenantId = (string) $params['tenant_party_id'];
        $monthlyFee = (int) ($params['monthly_rack_fee_minor'] ?? ($racks * 15000000));

        if ($dc->occupied_racks + $racks > $dc->total_racks_capacity) {
            throw new RuntimeException("Data center [{$dc->dc_code}] has insufficient rack capacity.");
        }

        return DB::transaction(function () use ($dc, $racks, $tenantId, $monthlyFee) {
            $dc->increment('occupied_racks', $racks);

            $contractNum = 'COLO-'.strtoupper(substr(hash('sha256', "{$dc->id}:{$tenantId}:{$racks}:".microtime()), 0, 10));

            $contract = ColocationContract::create([
                'id' => (string) Str::uuid(),
                'contract_number' => $contractNum,
                'tenant_party_id' => $tenantId,
                'dc_id' => $dc->id,
                'rack_units_allocated' => $racks,
                'monthly_rack_fee_minor' => $monthlyFee,
                'power_rate_per_kwh_minor' => 1800,
                'monthly_kwh_consumed' => 0.0,
                'port_status' => 'UP',
                'is_overdue' => false,
            ]);

            if ($monthlyFee > 0) {
                $idempotencyKey = hash('sha256', "DC-COLO:{$contractNum}:{$monthlyFee}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'TELECOM_DC_RACK_COLOCATION',
                    description: "Colocation rack allocation for {$tenantId} ({$contractNum})",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('tlx:telecom_receivable:IDR', $monthlyFee)
                        ->credit('tlx:colocation_revenue:IDR', $monthlyFee)
                        ->toEntries('IDR'),
                    referenceType: 'TLX_COLO_CONTRACT',
                    referenceId: $contractNum,
                ));
            }

            $this->audit(
                action: 'tlx_rack_colocation_allocated',
                auditable: $contract,
                newValues: ['dc_code' => $dc->dc_code, 'racks' => $racks, 'fee' => $monthlyFee],
                impactType: 'datacenter_ops',
            );

            return $contract;
        });
    }
}
