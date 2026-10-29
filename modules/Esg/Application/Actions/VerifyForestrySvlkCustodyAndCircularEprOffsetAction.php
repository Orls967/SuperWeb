<?php

declare(strict_types=1);

namespace Modules\Esg\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class VerifyForestrySvlkCustodyAndCircularEprOffsetAction extends BaseAction
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Owner-module Esg action for Lini 23 Forestry SVLK chain-of-custody, Nature Finance restoration,
     * Lini 24 Circular EPR recovery mass-balance, and Fase 286-289 Product Repairability Stewardship.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params): array {
            $certificateCode = (string) ($params['certificate_code'] ?? 'ESG-W3-SVLK-001');
            $felledLogsM3 = (int) ($params['felled_logs_m3'] ?? 900);
            $aacCeilingM3 = (int) ($params['aac_ceiling_m3'] ?? 1200);
            if ($felledLogsM3 > $aacCeilingM3) {
                throw new DomainException("SVLK concession ceiling exceeded: {$felledLogsM3} m3 > {$aacCeilingM3} m3");
            }

            $repairabilityScore100 = (int) ($params['repairability_score_100'] ?? 82);
            $minimumRepairability100 = (int) ($params['minimum_repairability_100'] ?? 60);
            if ($repairabilityScore100 < $minimumRepairability100) {
                throw new DomainException("Product stewardship EPR gate failed: repairability index {$repairabilityScore100} < {$minimumRepairability100}");
            }

            $recoveredWasteKg = (int) ($params['recovered_waste_kg'] ?? 20000);
            $creditPerKgMinor = (int) ($params['credit_per_kg_minor'] ?? 3000);
            $eprSettlementMinor = $recoveredWasteKg * $creditPerKgMinor;

            if ($eprSettlementMinor > 0 && $this->ledger !== null) {
                $this->ledger->post(new PostingDTO(
                    type: 'WAVE3_FORESTRY_SVLK_EPR',
                    description: "ESG SVLK forestry & circular EPR credit {$certificateCode}",
                    idempotencyKey: hash('sha256', "ESG:W3:SVLK:{$certificateCode}:{$eprSettlementMinor}"),
                    entries: Posting::lines()
                        ->debit('wave3:forestry_epr_receivable:IDR', $eprSettlementMinor)
                        ->credit('wave3:forestry_circular_credit_revenue:IDR', $eprSettlementMinor)
                        ->toEntries('IDR'),
                    referenceType: 'ESG_SVLK_EPR',
                    referenceId: $certificateCode,
                ));
            }

            $custodyHash = hash('sha256', "{$certificateCode}:{$felledLogsM3}:{$recoveredWasteKg}:{$repairabilityScore100}");

            $this->audit(
                action: 'esg.wave3.svlk_and_circular_epr_verified',
                newValues: ['certificate_code' => $certificateCode, 'epr_settlement_minor' => $eprSettlementMinor],
                correlationId: "esg_w3_{$certificateCode}",
                impactType: 'esg_nature_circular'
            );

            return [
                'certificate_code' => $certificateCode,
                'remaining_aac_m3' => $aacCeilingM3 - $felledLogsM3,
                'repairability_score_100' => $repairabilityScore100,
                'epr_settlement_minor' => $eprSettlementMinor,
                'custody_hash' => $custodyHash,
            ];
        });
    }
}
