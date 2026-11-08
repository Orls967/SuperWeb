<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Hotel\Domain\Models\HtlWeddingMilestoneSchedule;
use Modules\Hotel\Domain\Models\MiceContract;

final class ProcessWeddingMilestonePaymentAction
{
    public function execute(
        MiceContract $contract,
        int $milestoneIndex,
        int $amountPaidIdr,
        string $paymentReference,
        ?DateTimeImmutable $paidAt = null
    ): HtlWeddingMilestoneSchedule {
        return DB::transaction(function () use (
            $contract,
            $milestoneIndex,
            $amountPaidIdr,
            $paymentReference,
            $paidAt
        ) {
            if ($milestoneIndex > 1) {
                $prevPending = HtlWeddingMilestoneSchedule::where('mice_contract_id', $contract->id)
                    ->where('milestone_index', '<', $milestoneIndex)
                    ->where('status', '!=', 'PAID')
                    ->exists();

                if ($prevPending) {
                    throw new DomainException("Cannot process milestone {$milestoneIndex} before preceding milestones are fully paid.");
                }
            }

            /** @var HtlWeddingMilestoneSchedule $milestone */
            $milestone = HtlWeddingMilestoneSchedule::where('mice_contract_id', $contract->id)
                ->where('milestone_index', $milestoneIndex)
                ->lockForUpdate()
                ->firstOrFail();

            if ($milestone->status === 'PAID' && $milestone->payment_reference === $paymentReference) {
                return $milestone;
            }

            if ($amountPaidIdr < $milestone->amount_idr) {
                throw new DomainException('Underpayment rejected: Payment amount must cover full milestone amount.');
            }

            $date = $paidAt ?? new DateTimeImmutable;
            $milestone->paid_amount_idr = $amountPaidIdr;
            $milestone->paid_at = $date->format('Y-m-d H:i:s');
            $milestone->payment_reference = $paymentReference;
            $milestone->status = 'PAID';
            $milestone->save();

            return $milestone;
        });
    }
}
