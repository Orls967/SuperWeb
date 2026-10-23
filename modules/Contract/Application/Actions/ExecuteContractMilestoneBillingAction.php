<?php

declare(strict_types=1);

namespace Modules\Contract\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Support\Posting;
use Modules\Contract\Domain\Enums\MilestoneStatus;
use Modules\Contract\Domain\Enums\PaymentScheduleKind;
use Modules\Contract\Domain\Enums\PaymentScheduleStatus;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\ContractMilestone;
use Modules\Contract\Domain\Models\PaymentSchedule;
use Modules\Shared\Application\BaseAction;

class ExecuteContractMilestoneBillingAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): PaymentSchedule
    {
        return DB::transaction(function () use ($params) {
            $contract = Contract::findOrFail($params['contract_id']);
            $milestoneId = $params['milestone_id'] ?? null;
            $amountIdr = (int) $params['billing_amount_idr'];
            $retentionPct = (int) ($params['retention_percent'] ?? $contract->retention_percent ?? 0);
            $retentionAmount = (int) round(($amountIdr * $retentionPct) / 100);

            $milestone = null;
            if ($milestoneId !== null) {
                $milestone = ContractMilestone::where('contract_id', $contract->id)->find($milestoneId);
                if ($milestone !== null) {
                    $milestone->update([
                        'status' => MilestoneStatus::Completed,
                        'completed_at' => Carbon::now(),
                    ]);
                }
            }

            $schedule = PaymentSchedule::create([
                'id' => (string) Str::uuid(),
                'contract_id' => $contract->id,
                'milestone_id' => $milestone?->id,
                'kind' => $milestone ? PaymentScheduleKind::Milestone : PaymentScheduleKind::Term,
                'due_date' => Carbon::now()->toDateString(),
                'amount_idr' => $amountIdr,
                'retention_amount_idr' => $retentionAmount,
                'paid_amount_idr' => $amountIdr,
                'status' => PaymentScheduleStatus::Paid,
                'notes' => (string) ($params['notes'] ?? 'Milestone billing executed'),
                'paid_at' => Carbon::now(),
            ]);

            $contract->increment('used_value_idr', $amountIdr);

            if ($amountIdr > 0) {
                $idempotencyKey = hash('sha256', "CTR-BILL:{$contract->contract_number}:{$schedule->id}:{$amountIdr}");
                $this->ledgerService->post(new PostingDTO(
                    type: TransactionType::CONTRACT_PAYMENT->value,
                    description: "Penagihan milestone kontrak {$contract->contract_number}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('asset:test_cash:IDR', $amountIdr)
                        ->credit('revenue:group_conglomerate:IDR', $amountIdr)
                        ->toEntries('IDR'),
                    referenceType: 'CTR_PAYMENT_SCHEDULE',
                    referenceId: $schedule->id,
                ));
            }

            $this->audit(
                action: 'contract_milestone_billed',
                auditable: $schedule,
                newValues: ['contract_number' => $contract->contract_number, 'amount_idr' => $amountIdr, 'retention' => $retentionAmount],
                impactType: 'contract_billing',
            );

            return $schedule;
        });
    }
}
