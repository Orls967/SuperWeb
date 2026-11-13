<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Actions;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\SafetyStockPolicy;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\ClockInterface;

final class DecideSafetyStockProposalAction
{
    public function __construct(
        private readonly ApprovalEngineInterface $approvalEngine,
        private readonly ClockInterface $clock,
    ) {}

    public function execute(
        SafetyStockPolicy $policy,
        User $approver,
        string $decision,
        ?string $notes = null,
    ): SafetyStockPolicy {
        $normalized = strtolower(trim($decision));

        return match ($normalized) {
            'approve', 'approved' => $this->approve($policy, $approver, $notes),
            'reject', 'rejected' => $this->reject($policy, $approver, (string) $notes),
            default => throw new InvalidArgumentException("Keputusan tidak dikenal: {$decision}."),
        };
    }

    public function approve(SafetyStockPolicy $policy, User $approver, ?string $notes = null): SafetyStockPolicy
    {
        return DB::transaction(function () use ($policy, $approver, $notes): SafetyStockPolicy {
            /** @var SafetyStockPolicy $locked */
            $locked = SafetyStockPolicy::query()->lockForUpdate()->findOrFail($policy->id);

            if ($locked->status !== 'pending_approval') {
                throw new DomainException("Kebijakan safety stock {$locked->item_code} tidak sedang menunggu persetujuan.");
            }

            if ($locked->proposed_by_user_id !== null && (int) $locked->proposed_by_user_id === (int) $approver->id) {
                throw new DomainException('Prinsip four-eyes dilanggar: pengusul tidak boleh menyetujui proposalnya sendiri.');
            }

            if ($locked->approval_id !== null) {
                $this->approvalEngine->approve((int) $locked->approval_id, $approver, $notes);
            }

            $appliedAbc = $locked->proposed_abc_class ?? $locked->abc_class;
            $appliedXyz = $locked->proposed_xyz_class ?? $locked->xyz_class;
            $appliedServiceLevel = $locked->proposed_service_level_bps ?? $locked->service_level_bps;
            $appliedSafetyQty = $locked->proposed_safety_stock_qty ?? $locked->safety_stock_qty;
            $appliedRopQty = $locked->proposed_reorder_point_qty ?? $locked->reorder_point_qty;
            $appliedBreakdown = $locked->proposed_echelon_breakdown ?? $locked->echelon_breakdown;

            $locked->update([
                'abc_class' => $appliedAbc,
                'xyz_class' => $appliedXyz,
                'service_level_bps' => $appliedServiceLevel,
                'safety_stock_qty' => $appliedSafetyQty,
                'reorder_point_qty' => $appliedRopQty,
                'echelon_breakdown' => $appliedBreakdown,
                'status' => 'approved',
                'decided_by_user_id' => (int) $approver->id,
                'decided_at' => $this->clock->now(),
                'decision_notes' => $notes,
            ]);

            $breakdownMap = is_array($appliedBreakdown) ? $appliedBreakdown : [];
            $stocks = EchelonStock::query()
                ->where('item_code', $locked->item_code)
                ->lockForUpdate()
                ->get();

            foreach ($stocks as $stock) {
                $nodeData = $breakdownMap[$stock->echelon_node] ?? null;
                $nodeSafetyQty = is_array($nodeData) && isset($nodeData['safety_stock_qty'])
                    ? (int) $nodeData['safety_stock_qty']
                    : (int) $appliedSafetyQty;

                $stock->update([
                    'safety_stock_qty' => $nodeSafetyQty,
                    'abc_class' => $appliedAbc,
                    'xyz_class' => $appliedXyz,
                ]);
            }

            return $locked->fresh();
        });
    }

    public function reject(SafetyStockPolicy $policy, User $approver, string $reason): SafetyStockPolicy
    {
        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw new InvalidArgumentException('Alasan penolakan kebijakan safety stock wajib diisi.');
        }

        return DB::transaction(function () use ($policy, $approver, $cleanReason): SafetyStockPolicy {
            /** @var SafetyStockPolicy $locked */
            $locked = SafetyStockPolicy::query()->lockForUpdate()->findOrFail($policy->id);

            if ($locked->status !== 'pending_approval') {
                throw new DomainException("Kebijakan safety stock {$locked->item_code} tidak sedang menunggu persetujuan.");
            }

            if ($locked->proposed_by_user_id !== null && (int) $locked->proposed_by_user_id === (int) $approver->id) {
                throw new DomainException('Prinsip four-eyes dilanggar: pengusul tidak boleh menolak proposalnya sendiri.');
            }

            if ($locked->approval_id !== null) {
                $this->approvalEngine->reject((int) $locked->approval_id, $approver, $cleanReason);
            }

            $locked->update([
                'status' => 'rejected',
                'decided_by_user_id' => (int) $approver->id,
                'decided_at' => $this->clock->now(),
                'decision_notes' => $cleanReason,
            ]);

            return $locked->fresh();
        });
    }
}
