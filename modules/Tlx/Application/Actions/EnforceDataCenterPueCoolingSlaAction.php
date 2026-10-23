<?php

declare(strict_types=1);

namespace Modules\Tlx\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;
use Modules\Tlx\Domain\Models\ColocationContract;
use Modules\Tlx\Domain\Models\TelecomDataCenter;

class EnforceDataCenterPueCoolingSlaAction extends BaseAction
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
        $dc = TelecomDataCenter::findOrFail($params['dc_id']);
        $measuredPue = (float) $params['measured_pue'];
        $slaThreshold = (float) ($params['sla_threshold'] ?? 1.50);

        return DB::transaction(function () use ($dc, $measuredPue, $slaThreshold) {
            $dc->update(['measured_pue' => $measuredPue]);

            $isBreached = $measuredPue > $slaThreshold;
            $penaltyMinor = 0;

            if ($isBreached) {
                $totalActiveRackFees = (int) ColocationContract::where('dc_id', $dc->id)
                    ->sum('monthly_rack_fee_minor');

                // Excess ratio e.g. (1.80 - 1.50) / 1.50 = 0.20 (20% penalty credit to tenants)
                $excessRatio = ($measuredPue - $slaThreshold) / $slaThreshold;
                $penaltyMinor = (int) round($totalActiveRackFees * $excessRatio * 0.5);

                if ($penaltyMinor > 0) {
                    $idempotencyKey = hash('sha256', "TLX-PUE-SLA:{$dc->dc_code}:{$measuredPue}:{$penaltyMinor}");
                    $this->ledgerService->post(new PostingDTO(
                        type: 'TELECOM_PUE_SLA_PENALTY_CREDIT',
                        description: "Data center {$dc->dc_code} PUE SLA breach penalty credit",
                        idempotencyKey: $idempotencyKey,
                        entries: Posting::lines()
                            ->debit('tlx:sla_penalty_expense:IDR', $penaltyMinor)
                            ->credit('tlx:sla_penalty_payable:IDR', $penaltyMinor)
                            ->toEntries('IDR'),
                        referenceType: 'TLX_DC_PUE_SLA',
                        referenceId: $dc->dc_code,
                    ));
                }
            }

            $this->audit(
                action: 'tlx_pue_cooling_sla_evaluated',
                auditable: $dc,
                newValues: [
                    'measured_pue' => $measuredPue,
                    'sla_threshold' => $slaThreshold,
                    'is_breached' => $isBreached,
                    'penalty_minor' => $penaltyMinor,
                ],
                impactType: 'compliance',
            );

            return [
                'dc_id' => $dc->id,
                'dc_code' => $dc->dc_code,
                'measured_pue' => $measuredPue,
                'sla_threshold' => $slaThreshold,
                'sla_breached' => $isBreached,
                'penalty_minor' => $penaltyMinor,
            ];
        });
    }
}
