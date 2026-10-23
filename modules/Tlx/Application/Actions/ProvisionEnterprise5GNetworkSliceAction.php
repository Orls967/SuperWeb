<?php

declare(strict_types=1);

namespace Modules\Tlx\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;
use Modules\Tlx\Domain\Models\TelecomLink;
use RuntimeException;

class ProvisionEnterprise5GNetworkSliceAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        $link = TelecomLink::findOrFail($params['link_id']);
        $bandwidthGbps = (float) $params['slice_bandwidth_gbps'];
        $sliceName = (string) $params['slice_name'];
        $enterpriseEntityId = (string) $params['enterprise_entity_id'];
        $monthlyFeeMinor = (int) ($params['monthly_fee_minor'] ?? 25000000);

        if ($link->allocated_bandwidth_gbps + $bandwidthGbps > $link->bandwidth_capacity_gbps) {
            throw new RuntimeException("Link [{$link->link_code}] cannot satisfy slice bandwidth. Capacity exceeded.");
        }

        return DB::transaction(function () use ($link, $bandwidthGbps, $sliceName, $enterpriseEntityId, $monthlyFeeMinor) {
            $link->increment('allocated_bandwidth_gbps', $bandwidthGbps);

            $sliceCode = 'SLICE-5G-'.strtoupper(substr(hash('sha256', "{$link->id}:{$sliceName}:{$bandwidthGbps}"), 0, 10));

            if ($monthlyFeeMinor > 0) {
                $idempotencyKey = hash('sha256', "5G-SLICE:{$sliceCode}:{$monthlyFeeMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'TELECOM_5G_SLICE_PROVISION',
                    description: "5G Network Slice provisioning for {$enterpriseEntityId} ({$sliceCode})",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('tlx:telecom_receivable:IDR', $monthlyFeeMinor)
                        ->credit('tlx:network_slice_revenue:IDR', $monthlyFeeMinor)
                        ->toEntries('IDR'),
                    referenceType: 'TLX_5G_SLICE',
                    referenceId: $sliceCode,
                ));
            }

            $this->audit(
                action: 'tlx_5g_slice_provisioned',
                auditable: $link,
                newValues: ['slice_code' => $sliceCode, 'bandwidth_gbps' => $bandwidthGbps, 'fee_minor' => $monthlyFeeMinor],
                impactType: 'telecom_provisioning',
            );

            return [
                'slice_code' => $sliceCode,
                'link_id' => $link->id,
                'enterprise_entity_id' => $enterpriseEntityId,
                'allocated_gbps' => $bandwidthGbps,
                'monthly_fee_minor' => $monthlyFeeMinor,
                'status' => 'ACTIVE',
            ];
        });
    }
}
