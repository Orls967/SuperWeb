<?php

declare(strict_types=1);

namespace Modules\Tlx\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Tlx\Domain\Models\ChurnPrediction;
use Modules\Tlx\Domain\Models\InterconnectSettlement;
use Modules\Tlx\Domain\Models\IspSubscription;
use Modules\Tlx\Domain\Models\SimSubscription;
use Modules\Tlx\Domain\Models\SmartCityService;

class IspAndMobileServices
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * 132.1 ISP subscription creation and usage tracking with throttling/overdue pause.
     */
    public function createIspSubscription(array $params): IspSubscription
    {
        return IspSubscription::create([
            'id' => (string) Str::uuid(),
            'subscription_code' => $params['subscription_code'] ?? 'ISP-'.strtoupper(Str::random(8)),
            'customer_id' => $params['customer_id'],
            'plan_type' => $params['plan_type'],
            'speed_mbps' => (float) $params['speed_mbps'],
            'monthly_usage_cap_gb' => (float) $params['monthly_usage_cap_gb'],
            'current_usage_gb' => 0.0,
            'monthly_fee_minor' => (int) $params['monthly_fee_minor'],
            'billing_type' => $params['billing_type'],
            'service_status' => 'ACTIVE',
            'is_overdue' => false,
        ]);
    }

    public function recordIspUsage(string $subscriptionId, float $addedGb): IspSubscription
    {
        $sub = IspSubscription::findOrFail($subscriptionId);
        $sub->current_usage_gb += $addedGb;

        if ($sub->current_usage_gb >= $sub->monthly_usage_cap_gb) {
            $sub->service_status = 'THROTTLED';
        }

        $sub->save();

        return $sub;
    }

    public function setIspOverdueStatus(string $subscriptionId, bool $isOverdue): IspSubscription
    {
        $sub = IspSubscription::findOrFail($subscriptionId);
        $sub->is_overdue = $isOverdue;

        if ($isOverdue) {
            $sub->service_status = 'PAUSED'; // escape hatch: pause service when overdue
        } elseif ($sub->current_usage_gb < $sub->monthly_usage_cap_gb) {
            $sub->service_status = 'ACTIVE';
        } else {
            $sub->service_status = 'THROTTLED';
        }

        $sub->save();

        return $sub;
    }

    /**
     * 132.2 SIM / mobile plan auto-renewal.
     * When wallet balance is insufficient, pause service instead of free usage.
     */
    public function registerSimSubscriber(array $params): SimSubscription
    {
        return SimSubscription::create([
            'id' => (string) Str::uuid(),
            'msisdn' => $params['msisdn'],
            'iccid' => $params['iccid'],
            'account_tier' => $params['account_tier'] ?? 'PARENT',
            'parent_msisdn' => $params['parent_msisdn'] ?? null,
            'wallet_id' => $params['wallet_id'],
            'plan_code' => $params['plan_code'],
            'quota_allowance_gb' => (float) $params['quota_allowance_gb'],
            'quota_remaining_gb' => (float) $params['quota_allowance_gb'],
            'rollover_quota_gb' => 0.0,
            'auto_renew_fee_minor' => (int) $params['auto_renew_fee_minor'],
            'status' => 'ACTIVE',
        ]);
    }

    public function autoRenewSimPlan(string $msisdn, int $availableWalletBalanceMinor): SimSubscription
    {
        $sim = SimSubscription::where('msisdn', $msisdn)->firstOrFail();

        if ($availableWalletBalanceMinor < $sim->auto_renew_fee_minor) {
            // Insufficient wallet: pause service, NEVER give free service
            $sim->status = 'PAUSED';
            $sim->save();

            return $sim;
        }

        // Renew with quota rollover
        $rollover = min($sim->quota_remaining_gb, $sim->quota_allowance_gb);
        $sim->rollover_quota_gb = $rollover;
        $sim->quota_remaining_gb = $sim->quota_allowance_gb + $rollover;
        $sim->status = 'ACTIVE';
        $sim->save();

        return $sim;
    }

    /**
     * 132.2 Interconnect operator settlement with balanced ledger entry.
     */
    public function settleInterconnect(array $params): InterconnectSettlement
    {
        $inboundMin = (int) $params['inbound_minutes'];
        $outboundMin = (int) $params['outbound_minutes'];
        $ratePerMinMinor = (int) $params['rate_per_minute_minor'];

        $inboundReceivable = $inboundMin * $ratePerMinMinor;
        $outboundPayable = $outboundMin * $ratePerMinMinor;
        $netSettlement = $inboundReceivable - $outboundPayable;

        return DB::transaction(function () use ($params, $inboundMin, $outboundMin, $inboundReceivable, $outboundPayable, $netSettlement) {
            $batchCode = 'IC-'.strtoupper(Str::random(8));

            // Balanced ledger posting (sum = 0)
            $this->ledgerService->post(new PostingDTO(
                type: 'TELECOM_INTERCONNECT_SETTLEMENT',
                description: "Interconnect settlement for {$params['partner_operator_code']} period {$params['period_month']}",
                idempotencyKey: 'TLX-'.$batchCode,
                entries: [
                    PostingEntryDTO::forCode('tlx:interconnect_clearing:IDR', 'IDR', $inboundReceivable),
                    PostingEntryDTO::forCode('tlx:interconnect_clearing:IDR', 'IDR', -$outboundPayable),
                    PostingEntryDTO::forCode('tlx:interconnect_net_settlement:IDR', 'IDR', -$netSettlement),
                ],
                referenceType: 'INTERCONNECT',
                referenceId: $batchCode,
            ));

            return InterconnectSettlement::create([
                'id' => (string) Str::uuid(),
                'settlement_batch_code' => $batchCode,
                'partner_operator_code' => $params['partner_operator_code'],
                'period_month' => $params['period_month'],
                'inbound_minutes' => $inboundMin,
                'outbound_minutes' => $outboundMin,
                'inbound_receivable_minor' => $inboundReceivable,
                'outbound_payable_minor' => $outboundPayable,
                'net_settlement_minor' => $netSettlement,
                'status' => 'SETTLED',
            ]);
        });
    }

    /**
     * 132.3 Smart city IoT & connectivity SLA report.
     */
    public function registerSmartCityService(array $params): SmartCityService
    {
        return SmartCityService::create([
            'id' => (string) Str::uuid(),
            'service_code' => 'SC-'.strtoupper(Str::random(8)),
            'municipality_name' => $params['municipality_name'],
            'service_type' => $params['service_type'],
            'active_sensor_count' => (int) $params['active_sensor_count'],
            'sla_target_pct' => (float) ($params['sla_target_pct'] ?? 99.90),
            'sla_achieved_pct' => (float) ($params['sla_achieved_pct'] ?? 100.00),
            'monthly_contract_value_minor' => (int) $params['monthly_contract_value_minor'],
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * 132.5 Deterministic churn risk prediction.
     */
    public function calculateChurnRisk(string $subscriberId, string $type, float $usageDeclinePct, int $supportTickets): ChurnPrediction
    {
        // Deterministic scoring: 0.7 * usageDecline (normalized 0..1) + 0.3 * (tickets / 5 clamped)
        $declineFactor = max(0.0, min(1.0, $usageDeclinePct / 100.0));
        $ticketFactor = max(0.0, min(1.0, $supportTickets / 5.0));

        $score = round((0.7 * $declineFactor) + (0.3 * $ticketFactor), 2);

        if ($score >= 0.75) {
            $riskLevel = 'CRITICAL';
            $action = 'PROACTIVE_CALL';
        } elseif ($score >= 0.50) {
            $riskLevel = 'HIGH';
            $action = 'RETENTION_DISCOUNT';
        } elseif ($score >= 0.25) {
            $riskLevel = 'MEDIUM';
            $action = 'UPGRADE_OFFER';
        } else {
            $riskLevel = 'LOW';
            $action = 'STANDARD_NURTURE';
        }

        return ChurnPrediction::updateOrCreate(
            ['subscriber_id' => $subscriberId],
            [
                'id' => (string) Str::uuid(),
                'subscriber_type' => $type,
                'usage_decline_pct' => $usageDeclinePct,
                'support_tickets_count' => $supportTickets,
                'churn_risk_score' => $score,
                'risk_level' => $riskLevel,
                'recommended_action' => $action,
            ]
        );
    }
}
