<?php

declare(strict_types=1);

namespace Modules\Tlx\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;
use Modules\Tlx\Domain\Models\InterconnectSettlement;

class SettleInterCarrierRoamingClearingAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): InterconnectSettlement
    {
        $batchCode = (string) ($params['settlement_batch_code'] ?? ('ROAM-'.strtoupper(Str::random(8))));
        $partnerCode = (string) $params['partner_operator_code'];
        $periodMonth = (string) $params['period_month'];
        $inboundMinutes = (int) $params['inbound_minutes'];
        $outboundMinutes = (int) $params['outbound_minutes'];
        $inboundRateMinor = (int) ($params['inbound_rate_minor'] ?? 250);
        $outboundRateMinor = (int) ($params['outbound_rate_minor'] ?? 250);

        $inboundReceivable = $inboundMinutes * $inboundRateMinor;
        $outboundPayable = $outboundMinutes * $outboundRateMinor;
        $netSettlement = $inboundReceivable - $outboundPayable;

        return DB::transaction(function () use (
            $batchCode,
            $partnerCode,
            $periodMonth,
            $inboundMinutes,
            $outboundMinutes,
            $inboundReceivable,
            $outboundPayable,
            $netSettlement
        ) {
            $settlement = InterconnectSettlement::create([
                'id' => (string) Str::uuid(),
                'settlement_batch_code' => $batchCode,
                'partner_operator_code' => $partnerCode,
                'period_month' => $periodMonth,
                'inbound_minutes' => $inboundMinutes,
                'outbound_minutes' => $outboundMinutes,
                'inbound_receivable_minor' => $inboundReceivable,
                'outbound_payable_minor' => $outboundPayable,
                'net_settlement_minor' => $netSettlement,
                'status' => 'SETTLED',
            ]);

            if ($netSettlement !== 0) {
                $absAmount = abs($netSettlement);
                $idempotencyKey = hash('sha256', "TLX-ROAM-SETTLE:{$batchCode}:{$netSettlement}");

                $builder = Posting::lines();
                if ($netSettlement > 0) {
                    $builder->debit('tlx:interconnect_clearing:IDR', $absAmount)
                        ->credit('tlx:interconnect_net_settlement:IDR', $absAmount);
                } else {
                    $builder->debit('tlx:interconnect_net_settlement:IDR', $absAmount)
                        ->credit('tlx:interconnect_clearing:IDR', $absAmount);
                }

                $this->ledgerService->post(new PostingDTO(
                    type: 'TELECOM_ROAMING_CLEARING_SETTLE',
                    description: "Inter-carrier roaming clearing settlement {$batchCode} ({$partnerCode})",
                    idempotencyKey: $idempotencyKey,
                    entries: $builder->toEntries('IDR'),
                    referenceType: 'TLX_ROAMING_BATCH',
                    referenceId: $batchCode,
                ));
            }

            $this->audit(
                action: 'tlx_roaming_clearing_settled',
                auditable: $settlement,
                newValues: [
                    'batch_code' => $batchCode,
                    'partner_code' => $partnerCode,
                    'net_settlement' => $netSettlement,
                ],
                impactType: 'settlement',
            );

            return $settlement;
        });
    }
}
