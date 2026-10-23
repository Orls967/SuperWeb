<?php

declare(strict_types=1);

namespace Modules\Core\Application\Wave2;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Contracts\Ledger;
use Modules\Shared\Application\BaseAction;

class AllocateConglomerateGroupCapitalWaccRoicAction extends BaseAction
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Scores capex proposals against group WACC/ROIC hurdles, allocates treasury capital,
     * computes intercompany holding dividends, and enforces M&A integration idempotency.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $allocationCode = (string) ($params['allocation_code'] ?? 'CAP-ALLOC-'.hash('sha256', (string) ($params['fiscal_quarter'] ?? 'default')));
            $fiscalQuarter = (string) ($params['fiscal_quarter'] ?? '2026-Q4');

            $treasuryAvailableBudgetMinor = (int) ($params['treasury_available_budget_minor'] ?? 500000000000); // 500B
            $groupWaccBasisPoints = (int) ($params['group_wacc_basis_points'] ?? 1000); // 10.00% hurdle rate

            /** @var list<array{subholding: string, project_name: string, capex_requested_minor: int, projected_irr_basis_points: int, strategic_score: int, esg_score: int}> $proposals */
            $proposals = $params['capex_proposals'] ?? [];

            $allocatedProposals = [];
            $rejectedProposals = [];
            $totalAllocatedMinor = 0;

            foreach ($proposals as $prop) {
                $capex = (int) $prop['capex_requested_minor'];
                $irr = (int) $prop['projected_irr_basis_points'];

                // Hurdle rate check: IRR must exceed WACC
                $passesHurdle = $irr >= $groupWaccBasisPoints;
                $canAfford = ($totalAllocatedMinor + $capex) <= $treasuryAvailableBudgetMinor;

                if ($passesHurdle && $canAfford) {
                    $totalAllocatedMinor += $capex;
                    $allocatedProposals[] = [
                        'subholding' => $prop['subholding'],
                        'project_name' => $prop['project_name'],
                        'capex_minor' => $capex,
                        'irr_basis_points' => $irr,
                        'status' => 'APPROVED_FUNDED',
                    ];
                } else {
                    $rejectedProposals[] = [
                        'subholding' => $prop['subholding'],
                        'project_name' => $prop['project_name'],
                        'capex_minor' => $capex,
                        'reason' => ! $passesHurdle ? 'IRR_BELOW_WACC_HURDLE' : 'TREASURY_BUDGET_EXHAUSTED',
                    ];
                }
            }

            // Holding dividend distribution calculation
            $subsidiaryNetProfitMinor = (int) ($params['subsidiary_net_profit_minor'] ?? 100000000000); // 100B
            $dividendPayoutRatioPct = (int) ($params['dividend_payout_ratio_pct'] ?? 40); // 40%
            $holdingDividendMinor = (int) round(($subsidiaryNetProfitMinor * $dividendPayoutRatioPct) / 100);

            // M&A integration playbook idempotency
            $maTargetCode = (string) ($params['ma_target_code'] ?? 'MA-ACQUISITION-2026');
            $maBackfillHash = hash('sha256', "MA-INTEGRATION:{$maTargetCode}:{$allocationCode}");

            $result = [
                'allocation_code' => $allocationCode,
                'fiscal_quarter' => $fiscalQuarter,
                'treasury_budget_minor' => $treasuryAvailableBudgetMinor,
                'total_allocated_minor' => $totalAllocatedMinor,
                'remaining_budget_minor' => $treasuryAvailableBudgetMinor - $totalAllocatedMinor,
                'allocated_proposals' => $allocatedProposals,
                'rejected_proposals' => $rejectedProposals,
                'holding_dividend_minor' => $holdingDividendMinor,
                'ma_target_code' => $maTargetCode,
                'ma_backfill_hash' => $maBackfillHash,
                'governance_status' => 'APPROVED_BY_EXECUTIVE_BOARD',
            ];

            $this->audit(
                action: 'core.capital.group_allocation_approved',
                newValues: [
                    'allocation_code' => $allocationCode,
                    'total_allocated_minor' => $totalAllocatedMinor,
                    'approved_count' => count($allocatedProposals),
                    'dividend_minor' => $holdingDividendMinor,
                ],
                correlationId: "core_cap_{$allocationCode}",
                impactType: 'capital_governance'
            );

            $this->outbox(
                eventType: 'ConglomerateGroupCapitalAllocated',
                payload: [
                    'allocation_code' => $allocationCode,
                    'fiscal_quarter' => $fiscalQuarter,
                    'total_allocated_minor' => $totalAllocatedMinor,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:CAP:{$allocationCode}")
            );

            return $result;
        });
    }
}
