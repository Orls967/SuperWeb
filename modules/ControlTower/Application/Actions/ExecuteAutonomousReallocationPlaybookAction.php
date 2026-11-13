<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\ControlTower\Domain\Models\DisruptionAlert;
use Modules\ControlTower\Domain\Models\EchelonStock;

class ExecuteAutonomousReallocationPlaybookAction
{
    /**
     * @param  string  $fromNode  E.g. 'DC'
     * @param  string  $toNode  E.g. 'PLANT' or 'OUTLET'
     * @return array{
     *     item_code: string,
     *     from_node: string,
     *     to_node: string,
     *     transfer_qty: int,
     *     from_node_remaining_on_hand: int,
     *     to_node_new_on_hand: int,
     *     mitigated_alert_code: string|null
     * }
     */
    public function execute(
        string $itemCode,
        string $fromNode,
        string $toNode,
        int $transferQty
    ): array {
        if ($transferQty <= 0) {
            throw new InvalidArgumentException('Reallocation transfer quantity must be greater than zero.');
        }

        if ($fromNode === $toNode) {
            throw new InvalidArgumentException('Source and destination echelon nodes cannot be identical.');
        }

        $source = EchelonStock::where('item_code', $itemCode)->where('echelon_node', $fromNode)->first();
        if (! $source || $source->on_hand_qty < $transferQty) {
            throw new InvalidArgumentException("Insufficient available stock at source node {$fromNode} to reallocate {$transferQty} units.");
        }

        $dest = EchelonStock::firstOrCreate(
            ['item_code' => $itemCode, 'echelon_node' => $toNode],
            [
                'item_name' => $source->item_name,
                'on_hand_qty' => 0,
                'in_transit_qty' => 0,
                'reserved_qty' => 0,
                'safety_stock_qty' => $source->safety_stock_qty,
                'abc_class' => $source->abc_class,
                'xyz_class' => $source->xyz_class,
            ]
        );

        $source->decrement('on_hand_qty', $transferQty);
        $dest->increment('on_hand_qty', $transferQty);

        // Check and mitigate alert if destination node now meets safety stock
        $mitigatedAlertCode = null;
        $alertCode = 'ALT-'.strtoupper($itemCode).'-'.strtoupper($toNode);
        $alert = DisruptionAlert::where('alert_code', $alertCode)->where('status', 'active')->first();
        if ($alert && (($dest->fresh()->on_hand_qty + $dest->in_transit_qty - $dest->reserved_qty) >= $dest->safety_stock_qty)) {
            $alert->update(['status' => 'mitigated']);
            $mitigatedAlertCode = $alert->alert_code;
        }

        return [
            'item_code' => $itemCode,
            'from_node' => $fromNode,
            'to_node' => $toNode,
            'transfer_qty' => $transferQty,
            'from_node_remaining_on_hand' => $source->fresh()->on_hand_qty,
            'to_node_new_on_hand' => $dest->fresh()->on_hand_qty,
            'mitigated_alert_code' => $mitigatedAlertCode,
        ];
    }

    /**
     * Membentuk usulan realokasi antar-node yang menunggu persetujuan planner tanpa langsung memindahkan stok.
     *
     * @return array{
     *     action: string,
     *     status: string,
     *     item_code: string,
     *     from_node: string,
     *     to_node: string,
     *     transfer_qty: int,
     *     alert_id: int|null,
     *     alert_code: string|null
     * }
     */
    public function proposeReallocation(
        string $itemCode,
        string $fromNode,
        string $toNode,
        int $transferQty,
        ?DisruptionAlert $alert = null,
    ): array {
        if ($transferQty <= 0) {
            throw new InvalidArgumentException('Reallocation transfer quantity must be greater than zero.');
        }

        if ($fromNode === $toNode) {
            throw new InvalidArgumentException('Source and destination echelon nodes cannot be identical.');
        }

        $source = EchelonStock::where('item_code', $itemCode)->where('echelon_node', $fromNode)->first();
        if (! $source || $source->on_hand_qty < $transferQty) {
            throw new InvalidArgumentException("Insufficient available stock at source node {$fromNode} to propose {$transferQty} units.");
        }

        $proposal = [
            'action' => 'reallocation',
            'status' => 'pending_approval',
            'item_code' => $itemCode,
            'from_node' => $fromNode,
            'to_node' => $toNode,
            'transfer_qty' => $transferQty,
        ];

        if ($alert !== null) {
            $alert->update(['mitigation_proposal_json' => $proposal]);
        }

        return [
            ...$proposal,
            'alert_id' => $alert?->id,
            'alert_code' => $alert?->alert_code,
        ];
    }

    /**
     * Menyetujui usulan realokasi pada alert dan mengeksekusi pemindahan stok secara transaksional.
     *
     * @return array{
     *     item_code: string,
     *     from_node: string,
     *     to_node: string,
     *     transfer_qty: int,
     *     from_node_remaining_on_hand: int,
     *     to_node_new_on_hand: int,
     *     mitigated_alert_code: string|null,
     *     alert_id: int,
     *     approved_by_user_id: int,
     *     status: string
     * }
     */
    public function approveReallocation(DisruptionAlert $alert, int $approverUserId): array
    {
        if ($approverUserId <= 0) {
            throw new InvalidArgumentException('Approver user ID must be a positive integer.');
        }

        $proposal = is_array($alert->mitigation_proposal_json) ? $alert->mitigation_proposal_json : [];

        if (($proposal['status'] ?? null) === 'approved') {
            throw new InvalidArgumentException('Reallocation proposal for this alert has already been approved.');
        }

        if (
            ! isset($proposal['item_code'], $proposal['from_node'], $proposal['to_node'], $proposal['transfer_qty'])
            || (int) $proposal['transfer_qty'] <= 0
        ) {
            throw new InvalidArgumentException('Alert does not contain a valid reallocation proposal.');
        }

        return DB::transaction(function () use ($alert, $approverUserId, $proposal): array {
            $lockedAlert = DisruptionAlert::query()->lockForUpdate()->findOrFail($alert->id);
            $lockedProposal = is_array($lockedAlert->mitigation_proposal_json) ? $lockedAlert->mitigation_proposal_json : $proposal;

            if (($lockedProposal['status'] ?? null) === 'approved') {
                throw new InvalidArgumentException('Reallocation proposal for this alert has already been approved.');
            }

            $result = $this->execute(
                (string) $lockedProposal['item_code'],
                (string) $lockedProposal['from_node'],
                (string) $lockedProposal['to_node'],
                (int) $lockedProposal['transfer_qty'],
            );

            $lockedProposal['status'] = 'approved';

            $lockedAlert->update([
                'status' => 'mitigated',
                'approved_by_user_id' => $approverUserId,
                'mitigation_proposal_json' => $lockedProposal,
            ]);

            return [
                ...$result,
                'alert_id' => $lockedAlert->id,
                'approved_by_user_id' => $approverUserId,
                'status' => 'mitigated',
            ];
        });
    }
}
