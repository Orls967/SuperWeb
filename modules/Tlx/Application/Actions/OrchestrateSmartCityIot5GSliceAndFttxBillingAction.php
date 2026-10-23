<?php

declare(strict_types=1);

namespace Modules\Tlx\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class OrchestrateSmartCityIot5GSliceAndFttxBillingAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Orchestrates 5G network slicing for smart city/IoT, manages retail FTTX billing caps, and clears interconnect settlements.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $operationCode = (string) ($params['operation_code'] ?? '5G-SLICE-'.hash('sha256', (string) ($params['slice_id'] ?? 'default')));
            $sliceId = (string) ($params['slice_id'] ?? 'SLICE-SMART-CITY-01');

            // 5G Network Slice Provisioning & Billing
            $sliceMonthlyFeeMinor = (int) ($params['slice_monthly_fee_minor'] ?? 50000000);
            if ($sliceMonthlyFeeMinor > 0) {
                $sliceKey = hash('sha256', "TLX:5G-SLICE:{$operationCode}:{$sliceMonthlyFeeMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'TELECOM_5G_SLICE_PROVISION',
                    description: "Smart city 5G slice orchestration for {$sliceId}",
                    idempotencyKey: $sliceKey,
                    entries: Posting::lines()
                        ->debit('tlx:telecom_receivable:IDR', $sliceMonthlyFeeMinor)
                        ->credit('tlx:network_slice_revenue:IDR', $sliceMonthlyFeeMinor)
                        ->toEntries('IDR'),
                    referenceType: 'TLX_5G_SLICE',
                    referenceId: $operationCode,
                ));
            }

            // Retail FTTX / SIM Subscriber Usage and auto-throttle/pause
            $usageGb = (int) ($params['subscriber_usage_gb'] ?? 100);
            $quotaLimitGb = (int) ($params['subscriber_quota_gb'] ?? 80);
            $subscriberBalanceMinor = (int) ($params['subscriber_balance_minor'] ?? 0);
            $planRenewalCostMinor = (int) ($params['plan_renewal_cost_minor'] ?? 300000);

            $throttled = $usageGb > $quotaLimitGb;
            $subscriptionStatus = 'ACTIVE';

            // Auto-renew check: If balance insufficient, service pauses rather than staying free
            if ($subscriberBalanceMinor < $planRenewalCostMinor) {
                $subscriptionStatus = 'PAUSED_INSUFFICIENT_FUNDS';
            }

            // Intercarrier Roaming / Interconnect Settlement
            $inboundTrafficMb = (int) ($params['inbound_traffic_mb'] ?? 50000);
            $outboundTrafficMb = (int) ($params['outbound_traffic_mb'] ?? 40000);
            $ratePerMbMinor = (int) ($params['rate_per_mb_minor'] ?? 10);

            // Net balance: (Inbound - Outbound) * rate
            $netInterconnectMinor = ($inboundTrafficMb - $outboundTrafficMb) * $ratePerMbMinor;

            if ($netInterconnectMinor > 0) {
                $interconnectKey = hash('sha256', "TLX:INTERCONNECT:{$operationCode}:{$netInterconnectMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'TELECOM_INTERCONNECT_SETTLEMENT',
                    description: "Intercarrier roaming interconnect clearing {$operationCode}",
                    idempotencyKey: $interconnectKey,
                    entries: Posting::lines()
                        ->debit('tlx:interconnect_clearing:IDR', $netInterconnectMinor)
                        ->credit('tlx:interconnect_net_settlement:IDR', $netInterconnectMinor)
                        ->toEntries('IDR'),
                    referenceType: 'TLX_INTERCONNECT',
                    referenceId: $operationCode,
                ));
            }

            // Churn risk calculation: deterministic formula based on usage drops & tickets
            $ticketsCount = (int) ($params['open_complaint_tickets'] ?? 0);
            $usageDropPct = (int) ($params['usage_drop_pct'] ?? 10);
            $churnRiskScore = min(100, ($ticketsCount * 20) + $usageDropPct);

            $result = [
                'operation_code' => $operationCode,
                'slice_id' => $sliceId,
                'slice_fee_minor' => $sliceMonthlyFeeMinor,
                'subscriber_usage_gb' => $usageGb,
                'is_throttled' => $throttled,
                'subscription_status' => $subscriptionStatus,
                'inbound_traffic_mb' => $inboundTrafficMb,
                'outbound_traffic_mb' => $outboundTrafficMb,
                'net_interconnect_minor' => $netInterconnectMinor,
                'churn_risk_score' => $churnRiskScore,
            ];

            $this->audit(
                action: 'tlx.smart_city.slice_and_fttx_orchestrated',
                newValues: [
                    'operation_code' => $operationCode,
                    'slice_id' => $sliceId,
                    'subscription_status' => $subscriptionStatus,
                    'churn_risk' => $churnRiskScore,
                ],
                correlationId: "tlx_slice_{$operationCode}",
                impactType: 'telecom_orchestration'
            );

            $this->outbox(
                eventType: 'SmartCitySliceAndFttxOrchestrated',
                payload: [
                    'operation_code' => $operationCode,
                    'slice_id' => $sliceId,
                    'subscription_status' => $subscriptionStatus,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:5G:{$operationCode}")
            );

            return $result;
        });
    }
}
