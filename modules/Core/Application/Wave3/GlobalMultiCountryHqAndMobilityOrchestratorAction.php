<?php

declare(strict_types=1);

namespace Modules\Core\Application\Wave3;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class GlobalMultiCountryHqAndMobilityOrchestratorAction extends BaseAction
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Orchestrates multi-country regional HQ readiness gates, cross-border shadow payroll & tax equalization,
     * multi-sourcing resilience failover, ethical migrant workforce protection, and pandemic BCP circuit breakers (Fase 151-155).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params): array {
            $operationCode = (string) ($params['operation_code'] ?? 'W3-GHQ-001');
            $regionCode = (string) ($params['region_code'] ?? 'APAC');
            $countryCode = (string) ($params['country_code'] ?? 'SG');
            $targetStage = (string) ($params['target_stage'] ?? 'LIVE');

            // 1. Fase 151: Market entry 4-pillar compliance gate before LIVE transition
            $licenseStatus = (string) ($params['license_status'] ?? 'READY');
            $taxRegistrationStatus = (string) ($params['tax_registration_status'] ?? 'READY');
            $laborComplianceStatus = (string) ($params['labor_compliance_status'] ?? 'READY');
            $dataResidencyStatus = (string) ($params['data_residency_status'] ?? 'READY');

            if ($targetStage === 'LIVE') {
                $pillars = [
                    'license' => $licenseStatus,
                    'tax_registration' => $taxRegistrationStatus,
                    'labor_compliance' => $laborComplianceStatus,
                    'data_residency' => $dataResidencyStatus,
                ];
                foreach ($pillars as $pillar => $status) {
                    if ($status !== 'READY') {
                        throw new DomainException("Market entry gate blocked for {$countryCode}: pillar '{$pillar}' is '{$status}' (must be READY)");
                    }
                }
            }

            // 2. Fase 154: Ethical migrant workforce sourcing (Employer-Pays Principle & zero document withholding)
            $workerRecruitmentFeeMinor = (int) ($params['worker_recruitment_fee_minor'] ?? 0);
            if ($workerRecruitmentFeeMinor > 0) {
                throw new DomainException("Ethical sourcing violation: charging recruitment fee ({$workerRecruitmentFeeMinor} minor) to migrant worker is strictly prohibited");
            }

            $documentCustody = (string) ($params['travel_document_custody'] ?? 'WORKER_HELD');
            if ($documentCustody === 'EMPLOYER_WITHHELD') {
                throw new DomainException('Ethical workforce violation: employer withholding of worker identity or travel documents is prohibited');
            }

            $workPermitState = (string) ($params['work_permit_state'] ?? 'VALID');
            if ($workPermitState !== 'VALID') {
                throw new DomainException("Cross-border mobility blocked: work permit state is '{$workPermitState}'");
            }

            // 3. Fase 152: Cross-border shadow payroll & tax equalization in integer minor units
            $baseSalaryMinor = (int) ($params['base_salary_minor'] ?? 150000000);
            $colaAllowanceMinor = (int) ($params['cola_allowance_minor'] ?? 25000000);
            $homeHypoTaxMinor = (int) ($params['home_hypo_tax_minor'] ?? 30000000);
            $hostActualTaxMinor = (int) ($params['host_actual_tax_minor'] ?? 42000000);

            if ($baseSalaryMinor < 0 || $colaAllowanceMinor < 0 || $homeHypoTaxMinor < 0 || $hostActualTaxMinor < 0) {
                throw new DomainException('Cross-border payroll amounts cannot be negative');
            }

            $taxEqualizationTopupMinor = max(0, $hostActualTaxMinor - $homeHypoTaxMinor);
            $totalMobilityCostMinor = $baseSalaryMinor + $colaAllowanceMinor + $taxEqualizationTopupMinor;

            // 4. Fase 153 & 155: Multi-sourcing resilience & cross-border pandemic BCP failover
            $primaryCorridorState = (string) ($params['primary_corridor_state'] ?? 'HEALTHY');
            $publicHealthAlertLevel = (string) ($params['public_health_alert_level'] ?? 'NORMAL');

            $primaryAllocationPct = 70;
            $secondaryAllocationPct = 30;
            $bcpMode = 'STANDARD_DUAL_SOURCE';

            if ($primaryCorridorState !== 'HEALTHY' || $publicHealthAlertLevel === 'PANDEMIC_BORDER_CLOSURE') {
                $primaryAllocationPct = 0;
                $secondaryAllocationPct = 100;
                $bcpMode = 'FAILOVER_SECONDARY_CORRIDOR_ACTIVE';
            }

            $previousHash = (string) ($params['previous_hash'] ?? hash('sha256', 'GENESIS-WAVE3-GLOBAL-HQ'));
            $chainHash = hash('sha256', implode('|', [
                $operationCode,
                $regionCode,
                $countryCode,
                $targetStage,
                (string) $totalMobilityCostMinor,
                $bcpMode,
                $previousHash,
            ]));

            if ($totalMobilityCostMinor > 0 && $this->ledger !== null) {
                $idempotencyKey = hash('sha256', "W3:GHQ:MOBILITY:{$operationCode}:{$totalMobilityCostMinor}");
                $this->ledger->post(new PostingDTO(
                    type: 'WAVE3_GLOBAL_HQ_MOBILITY',
                    description: "Global HQ {$regionCode}/{$countryCode} cross-border mobility & tax equalization {$operationCode}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('wave3:global_mobility_expense:IDR', $totalMobilityCostMinor)
                        ->credit('wave3:global_tax_equalization_payable:IDR', $totalMobilityCostMinor)
                        ->toEntries('IDR'),
                    referenceType: 'WAVE3_GLOBAL_HQ',
                    referenceId: $operationCode,
                ));
            }

            $result = [
                'operation_code' => $operationCode,
                'region_code' => $regionCode,
                'country_code' => $countryCode,
                'country_ops_stage' => $targetStage,
                'base_salary_minor' => $baseSalaryMinor,
                'cola_allowance_minor' => $colaAllowanceMinor,
                'home_hypo_tax_minor' => $homeHypoTaxMinor,
                'host_actual_tax_minor' => $hostActualTaxMinor,
                'tax_equalization_topup_minor' => $taxEqualizationTopupMinor,
                'total_mobility_cost_minor' => $totalMobilityCostMinor,
                'primary_allocation_pct' => $primaryAllocationPct,
                'secondary_allocation_pct' => $secondaryAllocationPct,
                'total_sourcing_allocation_pct' => $primaryAllocationPct + $secondaryAllocationPct,
                'bcp_mode' => $bcpMode,
                'ethical_workforce_status' => 'EMPLOYER_PAYS_VERIFIED',
                'chain_hash' => $chainHash,
            ];

            $this->audit(
                action: 'core.wave3.global_hq_and_mobility_orchestrated',
                newValues: [
                    'operation_code' => $operationCode,
                    'country_code' => $countryCode,
                    'total_mobility_cost_minor' => $totalMobilityCostMinor,
                    'bcp_mode' => $bcpMode,
                ],
                correlationId: "w3_ghq_{$operationCode}",
                impactType: 'global_multi_country_hq'
            );

            $this->outbox(
                eventType: 'Wave3GlobalHqMobilitySettled',
                payload: [
                    'operation_code' => $operationCode,
                    'country_code' => $countryCode,
                    'chain_hash' => $chainHash,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:W3:GHQ:{$operationCode}")
            );

            return $result;
        });
    }
}
