<?php

declare(strict_types=1);

namespace Modules\Core\Application\Wave3;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class ContainerPortBerthCraneAndFashionAssortmentAction extends BaseAction
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Enforces Container Port SOLAS VGM & berth draft safety gates, STS crane & laytime demurrage/despatch math,
     * IMO CII vessel carbon rating, and Fashion Open-To-Buy (OTB) & circular garment take-back settlement (Fase 180-183).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params): array {
            $callCode = (string) ($params['call_code'] ?? 'W3-PORT-FSH-001');

            // 1. Fase 179-180: Container Port Berth Draft, SOLAS VGM Gate & Laytime Demurrage/Despatch
            $vesselDraftCm = (int) ($params['vessel_draft_cm'] ?? 1350);
            $berthMaxDraftCm = (int) ($params['berth_max_draft_cm'] ?? 1500);
            $missingVgmContainerCount = (int) ($params['missing_vgm_container_count'] ?? 0);

            if ($vesselDraftCm > $berthMaxDraftCm) {
                throw new DomainException("Port berth safety rejection: vessel draft ({$vesselDraftCm} cm) exceeds max berth depth ({$berthMaxDraftCm} cm)");
            }

            if ($missingVgmContainerCount > 0) {
                throw new DomainException("SOLAS VGM safety gate blocked crane loading: {$missingVgmContainerCount} container(s) lack Verified Gross Mass certification");
            }

            $teuHandledCount = (int) ($params['teu_handled_count'] ?? 1800);
            $thcPerTeuMinor = (int) ($params['thc_per_teu_minor'] ?? 150000);
            $terminalHandlingRevenueMinor = $teuHandledCount * $thcPerTeuMinor;

            $allowedLaytimeHours = (int) ($params['allowed_laytime_hours'] ?? 36);
            $actualPortStayHours = (int) ($params['actual_port_stay_hours'] ?? 40);
            $demurrageHourlyRateMinor = (int) ($params['demurrage_hourly_rate_minor'] ?? 5000000);

            $demurrageMinor = $actualPortStayHours > $allowedLaytimeHours
                ? ($actualPortStayHours - $allowedLaytimeHours) * $demurrageHourlyRateMinor
                : 0;
            // Despatch rebate is conventionally 50% of demurrage rate for saved laytime hours
            $despatchRebateMinor = $actualPortStayHours < $allowedLaytimeHours
                ? intdiv(($allowedLaytimeHours - $actualPortStayHours) * $demurrageHourlyRateMinor, 2)
                : 0;

            $ciiGramsCo2PerDwtNm = (int) ($params['cii_grams_co2_per_dwt_nm'] ?? 42);
            $imoCiiRating = match (true) {
                $ciiGramsCo2PerDwtNm <= 35 => 'A',
                $ciiGramsCo2PerDwtNm <= 45 => 'B',
                $ciiGramsCo2PerDwtNm <= 55 => 'C',
                $ciiGramsCo2PerDwtNm <= 68 => 'D',
                default => 'E',
            };

            // 2. Fase 181-183: Fashion Open-To-Buy (OTB) Ceiling, Tier-Supplier Audit & Circular Take-Back
            $otbBudgetCeilingMinor = (int) ($params['otb_budget_ceiling_minor'] ?? 400000000);
            $assortmentCommitMinor = (int) ($params['assortment_commit_minor'] ?? 320000000);
            if ($assortmentCommitMinor > $otbBudgetCeilingMinor) {
                throw new DomainException("Fashion merchandising OTB breach: assortment commitment ({$assortmentCommitMinor}) exceeds approved OTB ceiling ({$otbBudgetCeilingMinor})");
            }

            $supplierLaborAuditState = (string) ($params['supplier_labor_audit_state'] ?? 'LIVING_WAGE_VERIFIED');
            if ($supplierLaborAuditState === 'FORCED_OR_CHILD_LABOR_DETECTED') {
                throw new DomainException('Apparel ethical sourcing blocked: critical labor violation detected in garment factory tier');
            }

            $garmentTakebackKg = (int) ($params['garment_takeback_kg'] ?? 250);
            $takebackCreditPerKgMinor = (int) ($params['takeback_credit_per_kg_minor'] ?? 20000);
            $circularTakebackCreditMinor = $garmentTakebackKg * $takebackCreditPerKgMinor;
            $netPortAndFashionRevenueMinor = max(0, $terminalHandlingRevenueMinor + $demurrageMinor + $assortmentCommitMinor - $despatchRebateMinor - $circularTakebackCreditMinor);

            $previousHash = (string) ($params['previous_hash'] ?? hash('sha256', 'GENESIS-WAVE3-PORT-FASHION'));
            $portFashionChainHash = hash('sha256', implode('|', [
                $callCode,
                (string) $teuHandledCount,
                $imoCiiRating,
                (string) $netPortAndFashionRevenueMinor,
                $previousHash,
            ]));

            if ($netPortAndFashionRevenueMinor > 0 && $this->ledger !== null) {
                $idempotencyKey = hash('sha256', "W3:PORTFSH:SETTLE:{$callCode}:{$netPortAndFashionRevenueMinor}");
                $this->ledger->post(new PostingDTO(
                    type: 'WAVE3_PORT_TERMINAL_FASHION',
                    description: "Container port stevedoring & circular fashion retail settlement {$callCode}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('wave3:port_fashion_receivable:IDR', $netPortAndFashionRevenueMinor)
                        ->credit('wave3:port_fashion_revenue:IDR', $netPortAndFashionRevenueMinor)
                        ->toEntries('IDR'),
                    referenceType: 'WAVE3_PORT_FASHION',
                    referenceId: $callCode,
                ));
            }

            $result = [
                'call_code' => $callCode,
                'teu_handled_count' => $teuHandledCount,
                'terminal_handling_revenue_minor' => $terminalHandlingRevenueMinor,
                'demurrage_minor' => $demurrageMinor,
                'despatch_rebate_minor' => $despatchRebateMinor,
                'imo_cii_rating' => $imoCiiRating,
                'remaining_otb_budget_minor' => $otbBudgetCeilingMinor - $assortmentCommitMinor,
                'circular_takeback_credit_minor' => $circularTakebackCreditMinor,
                'net_port_and_fashion_revenue_minor' => $netPortAndFashionRevenueMinor,
                'port_fashion_chain_hash' => $portFashionChainHash,
            ];

            $this->audit(
                action: 'core.wave3.port_terminal_and_fashion_settled',
                newValues: [
                    'call_code' => $callCode,
                    'imo_cii_rating' => $imoCiiRating,
                    'net_revenue_minor' => $netPortAndFashionRevenueMinor,
                ],
                correlationId: "w3_portfsh_{$callCode}",
                impactType: 'port_ocean_fashion'
            );

            $this->outbox(
                eventType: 'Wave3PortTerminalAndFashionSettled',
                payload: [
                    'call_code' => $callCode,
                    'imo_cii_rating' => $imoCiiRating,
                    'port_fashion_chain_hash' => $portFashionChainHash,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:W3:PORTFSH:{$callCode}")
            );

            return $result;
        });
    }
}
