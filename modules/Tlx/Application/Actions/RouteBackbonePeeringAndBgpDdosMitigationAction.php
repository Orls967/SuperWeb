<?php

declare(strict_types=1);

namespace Modules\Tlx\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class RouteBackbonePeeringAndBgpDdosMitigationAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Routes backbone traffic, checks link capacity, mitigates BGP/DDoS anomalies, and settles IoT connectivity billing.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $routingCode = (string) ($params['routing_code'] ?? 'BGP-'.hash('sha256', (string) ($params['link_id'] ?? 'default')));
            $linkId = (string) ($params['link_id'] ?? 'LINK-JKT-SBY-01');

            $capacityGbps = (int) ($params['capacity_gbps'] ?? 100);
            $requestedTrafficGbps = (int) ($params['requested_traffic_gbps'] ?? 80);

            if ($requestedTrafficGbps > $capacityGbps) {
                throw new DomainException("Backbone capacity oversubscription rejected: Requested {$requestedTrafficGbps} Gbps exceeds link limit {$capacityGbps} Gbps on {$linkId}");
            }

            // BGP / DDoS telemetry
            $ddosDetected = (string) ($params['ddos_threat_level'] ?? 'NONE');
            $mitigationTicketId = null;

            if ($ddosDetected === 'HIGH' || $ddosDetected === 'CRITICAL') {
                $mitigationTicketId = 'NOC-TKT-'.hash('sha256', "DDOS:{$routingCode}:{$ddosDetected}");
            }

            // IoT Backbone Connectivity Billing across 17 lines
            /** @var list<array{device_id: string, entity_id: string, line_name: string, mb_consumed: int, rate_per_mb_minor: int}> $devices */
            $devices = $params['iot_devices'] ?? [];

            $totalIotBilledMinor = 0;
            $billingDetails = [];

            foreach ($devices as $dev) {
                $mb = (int) $dev['mb_consumed'];
                $rateMinor = (int) $dev['rate_per_mb_minor'];
                $amountMinor = $mb * $rateMinor;

                $totalIotBilledMinor += $amountMinor;
                $billingDetails[] = [
                    'device_id' => $dev['device_id'],
                    'entity_id' => $dev['entity_id'],
                    'line_name' => $dev['line_name'],
                    'mb_consumed' => $mb,
                    'amount_minor' => $amountMinor,
                ];
            }

            if ($totalIotBilledMinor > 0) {
                $idempotencyKey = hash('sha256', "TLX:IOT-BILLING:{$routingCode}:{$totalIotBilledMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'TELECOM_IOT_CONNECTIVITY_BILLING',
                    description: "IoT backbone connectivity billing for {$routingCode}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('tlx:telecom_receivable:IDR', $totalIotBilledMinor)
                        ->credit('tlx:iot_service_revenue:IDR', $totalIotBilledMinor)
                        ->toEntries('IDR'),
                    referenceType: 'TLX_IOT_BILLING',
                    referenceId: $routingCode,
                ));
            }

            $uptimePct = (int) ($params['sla_uptime_pct'] ?? 99);
            $slaCompliant = $uptimePct >= 99;

            $result = [
                'routing_code' => $routingCode,
                'link_id' => $linkId,
                'capacity_gbps' => $capacityGbps,
                'requested_traffic_gbps' => $requestedTrafficGbps,
                'available_headroom_gbps' => $capacityGbps - $requestedTrafficGbps,
                'ddos_threat_level' => $ddosDetected,
                'mitigation_ticket_id' => $mitigationTicketId,
                'total_iot_billed_minor' => $totalIotBilledMinor,
                'billing_details' => $billingDetails,
                'sla_uptime_pct' => $uptimePct,
                'sla_compliant' => $slaCompliant,
            ];

            $this->audit(
                action: 'tlx.backbone.routed_and_billed',
                newValues: [
                    'routing_code' => $routingCode,
                    'link_id' => $linkId,
                    'ddos_level' => $ddosDetected,
                    'iot_billed_minor' => $totalIotBilledMinor,
                ],
                correlationId: "tlx_route_{$routingCode}",
                impactType: 'telecom_routing_mitigation'
            );

            $this->outbox(
                eventType: 'BackbonePeeringAndMitigationExecuted',
                payload: [
                    'routing_code' => $routingCode,
                    'link_id' => $linkId,
                    'mitigation_ticket_id' => $mitigationTicketId,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:TLX:{$routingCode}")
            );

            return $result;
        });
    }
}
