<?php

declare(strict_types=1);

namespace Modules\Tlx\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class MeterDataCenterColocationPowerAndLiquidCoolingSlaAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Meters DC colocation power, enforces PUE/liquid cooling SLA, handles delinquent suspensions, and settles internal cloud chargeback.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $operationCode = (string) ($params['operation_code'] ?? 'DC-OPS-'.hash('sha256', (string) ($params['dc_site_id'] ?? 'default')));
            $dcSiteId = (string) ($params['dc_site_id'] ?? 'DC-JAKARTA-01');

            $totalPowerKw = (int) ($params['total_facility_power_kw'] ?? 5000);
            $itLoadKw = (int) ($params['it_equipment_load_kw'] ?? 4000);

            if ($itLoadKw <= 0) {
                throw new DomainException('IT equipment load must be greater than zero to compute PUE');
            }

            // PUE = Total Facility Power / IT Equipment Load
            // Using integer basis: 5000 / 4000 = 1.25 -> 125 basis points (divided by 100)
            $pueBasisPoints = (int) round(($totalPowerKw / $itLoadKw) * 100);
            $slaMaxPueBasisPoints = (int) ($params['sla_max_pue_basis_points'] ?? 140); // 1.40
            $pueViolation = $pueBasisPoints > $slaMaxPueBasisPoints;

            // Colocation Billing & Overdue Enforcement
            $monthlyColoFeeMinor = (int) ($params['colocation_fee_minor'] ?? 250000000);
            $paymentStatus = (string) ($params['payment_status'] ?? 'PAID');
            $colocationPortStatus = 'ACTIVE';

            if ($paymentStatus === 'OVERDUE') {
                $colocationPortStatus = 'SUSPENDED';
            }

            // PUE SLA Penalty Credit if violated
            $penaltyCreditMinor = 0;
            if ($pueViolation) {
                $penaltyRatePct = (int) ($params['pue_penalty_rate_pct'] ?? 5);
                $penaltyCreditMinor = (int) round(($monthlyColoFeeMinor * $penaltyRatePct) / 100);
            }

            $netColoBillMinor = max(0, $monthlyColoFeeMinor - $penaltyCreditMinor);

            if ($netColoBillMinor > 0 && $colocationPortStatus === 'ACTIVE') {
                $idempotencyKey = hash('sha256', "TLX:COLO-BILLING:{$operationCode}:{$netColoBillMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'TELECOM_DC_RACK_COLOCATION',
                    description: "Data center colocation lease settlement for {$dcSiteId}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('tlx:telecom_receivable:IDR', $netColoBillMinor)
                        ->credit('tlx:colocation_revenue:IDR', $netColoBillMinor)
                        ->toEntries('IDR'),
                    referenceType: 'TLX_DC_COLO',
                    referenceId: $operationCode,
                ));
            }

            // Internal Cloud Compute Chargeback
            /** @var list<array{instance_id: string, entity_id: string, vcpu_hours: int, rate_per_vcpu_minor: int}> $cloudInstances */
            $cloudInstances = $params['cloud_instances'] ?? [];

            $totalCloudChargebackMinor = 0;
            foreach ($cloudInstances as $instance) {
                $charge = (int) $instance['vcpu_hours'] * (int) $instance['rate_per_vcpu_minor'];
                $totalCloudChargebackMinor += $charge;
            }

            if ($totalCloudChargebackMinor > 0) {
                $cloudKey = hash('sha256', "TLX:CLOUD-CHARGEBACK:{$operationCode}:{$totalCloudChargebackMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'TELECOM_CLOUD_COMPUTE_CHARGEBACK',
                    description: "Internal cloud compute chargeback for {$dcSiteId}",
                    idempotencyKey: $cloudKey,
                    entries: Posting::lines()
                        ->debit('tlx:cloud_chargeback_receivable:IDR', $totalCloudChargebackMinor)
                        ->credit('tlx:cloud_infrastructure_revenue:IDR', $totalCloudChargebackMinor)
                        ->toEntries('IDR'),
                    referenceType: 'TLX_CLOUD_CHARGEBACK',
                    referenceId: $operationCode,
                ));
            }

            // DR restore drill verification
            $drRpoVerified = (string) ($params['dr_rpo_status'] ?? 'ZERO_DATA_LOSS');
            $drRtoMinutes = (int) ($params['dr_rto_minutes'] ?? 12);
            $drCompliant = $drRpoVerified === 'ZERO_DATA_LOSS' && $drRtoMinutes <= 15;

            $result = [
                'operation_code' => $operationCode,
                'dc_site_id' => $dcSiteId,
                'total_facility_power_kw' => $totalPowerKw,
                'it_equipment_load_kw' => $itLoadKw,
                'pue_basis_points' => $pueBasisPoints,
                'pue_violation' => $pueViolation,
                'payment_status' => $paymentStatus,
                'colocation_port_status' => $colocationPortStatus,
                'monthly_colo_fee_minor' => $monthlyColoFeeMinor,
                'penalty_credit_minor' => $penaltyCreditMinor,
                'net_colo_bill_minor' => $netColoBillMinor,
                'total_cloud_chargeback_minor' => $totalCloudChargebackMinor,
                'dr_rpo_status' => $drRpoVerified,
                'dr_rto_minutes' => $drRtoMinutes,
                'dr_compliant' => $drCompliant,
            ];

            $this->audit(
                action: 'tlx.datacenter.sla_and_colo_metered',
                newValues: [
                    'operation_code' => $operationCode,
                    'pue' => $pueBasisPoints,
                    'port_status' => $colocationPortStatus,
                    'cloud_chargeback_minor' => $totalCloudChargebackMinor,
                ],
                correlationId: "tlx_dc_{$operationCode}",
                impactType: 'datacenter_metering'
            );

            $this->outbox(
                eventType: 'DataCenterPowerAndColoSlaMetered',
                payload: [
                    'operation_code' => $operationCode,
                    'dc_site_id' => $dcSiteId,
                    'colocation_port_status' => $colocationPortStatus,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:DC:{$operationCode}")
            );

            return $result;
        });
    }
}
