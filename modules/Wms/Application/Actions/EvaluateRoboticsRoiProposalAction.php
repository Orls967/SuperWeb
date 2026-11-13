<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Domain\Algorithms\AutomationNpvCalculator;
use Modules\Wms\Models\RoboticsRoiProposal;

/**
 * Action evaluasi proposal ROI otomasi gudang (J3, simulasi) dengan analisis sensitivitas ±5 poin
 * serta persetujuan ganda melalui ApprovalEngine (D279-7, D97).
 */
final readonly class EvaluateRoboticsRoiProposalAction
{
    public function __construct(
        private AutomationNpvCalculator $calculator,
        private ApprovalEngineInterface $approvalEngine,
        private ClockInterface $clock,
    ) {}

    /**
     * @param  list<int>  $cashFlowsMinor
     * @return array{
     *     proposal: RoboticsRoiProposal,
     *     sensitivity: array{
     *         low_rate: array{rate_pct: int, npv: int, payback_months: int|null},
     *         base_rate: array{rate_pct: int, npv: int, payback_months: int|null},
     *         high_rate: array{rate_pct: int, npv: int, payback_months: int|null}
     *     }
     * }
     */
    public function evaluateAndStore(
        string $warehouseId,
        string $proposalCode,
        string $title,
        int $investmentMinor,
        array $cashFlowsMinor,
        int $rateNum,
        int $rateDen,
        ?int $creatorUserId = null,
    ): array {
        $base = $this->calculator->evaluate($investmentMinor, $cashFlowsMinor, $rateNum, $rateDen);

        // Hitung sensitivitas ±5 poin persentase (5/100) dalam aritmetika rasional bulat
        $basePct = intdiv($rateNum * 100, $rateDen);
        $lowPct = max(1, $basePct - 5);
        $highPct = $basePct + 5;

        $low = $this->calculator->evaluate($investmentMinor, $cashFlowsMinor, $lowPct, 100);
        $high = $this->calculator->evaluate($investmentMinor, $cashFlowsMinor, $highPct, 100);

        $proposal = RoboticsRoiProposal::query()->updateOrCreate(
            ['proposal_code' => $proposalCode],
            [
                'warehouse_id' => $warehouseId,
                'title' => $title,
                'investment_minor' => $investmentMinor,
                'cash_flows_minor' => array_map('intval', $cashFlowsMinor),
                'discount_rate_num' => $rateNum,
                'discount_rate_den' => $rateDen,
                'npv_minor' => $base['npv'],
                'payback_months' => $base['payback_months'],
                'recommendation' => $base['recommendation'],
                'status' => 'evaluated',
                'parameter_label' => 'asumsi_simulasi',
                'certificate_hash' => $base['certificate']->sha256,
                'created_by_user_id' => $creatorUserId,
            ],
        );

        return [
            'proposal' => $proposal,
            'sensitivity' => [
                'low_rate' => ['rate_pct' => $lowPct, 'npv' => $low['npv'], 'payback_months' => $low['payback_months']],
                'base_rate' => ['rate_pct' => $basePct, 'npv' => $base['npv'], 'payback_months' => $base['payback_months']],
                'high_rate' => ['rate_pct' => $highPct, 'npv' => $high['npv'], 'payback_months' => $high['payback_months']],
            ],
        ];
    }

    public function approveProposal(RoboticsRoiProposal $proposal, int $approverUserId, string $justification = ''): RoboticsRoiProposal
    {
        return DB::transaction(function () use ($proposal, $approverUserId, $justification): RoboticsRoiProposal {
            /** @var RoboticsRoiProposal $locked */
            $locked = RoboticsRoiProposal::query()->lockForUpdate()->findOrFail($proposal->id);

            if ($locked->created_by_user_id !== null && (int) $locked->created_by_user_id === $approverUserId) {
                throw new InvalidArgumentException('Pembuat proposal ROI tidak boleh menyetujui proposalnya sendiri.');
            }

            if ((int) $locked->npv_minor < 0 && trim($justification) === '') {
                throw new InvalidArgumentException('Proposal ROI dengan NPV negatif wajib menyertakan alasan tertulis.');
            }

            /** @var User $approver */
            $approver = User::query()->findOrFail($approverUserId);
            /** @var User $creator */
            $creator = $locked->created_by_user_id !== null
                ? (User::query()->find($locked->created_by_user_id) ?? $approver)
                : $approver;

            $approvalReq = $this->approvalEngine->submit(
                'wms_robotics_roi_proposal',
                "ROI {$locked->proposal_code}: {$locked->title}",
                $creator,
                $locked,
                (string) $locked->capex_minor,
                [['role' => 'admin']],
                48,
                ['proposal_code' => $locked->proposal_code, 'npv_minor' => $locked->npv_minor],
            );
            $this->approvalEngine->approve($approvalReq, $approver, trim($justification) !== '' ? trim($justification) : null);

            $locked->update([
                'status' => 'approved',
                'justification' => trim($justification) !== '' ? trim($justification) : ($locked->justification ?? 'NPV positif sesuai simulasi'),
                'approved_by_user_id' => $approverUserId,
                'approved_at' => $this->clock->now(),
            ]);

            return $locked->fresh();
        });
    }
}
