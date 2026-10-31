<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Ret\Domain\Exceptions\PackagingAlreadyReturnedException;
use Modules\Ret\Domain\Models\RetPackagingDeposit;
use Modules\Shared\Application\BaseAction;

class RefundPackagingDepositAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): RetPackagingDeposit
    {
        if (empty($params['idempotency_key'])) {
            throw new InvalidArgumentException('Idempotency-Key is required to refund packaging deposit.');
        }

        $idempotencyKey = (string) $params['idempotency_key'];

        $existing = RetPackagingDeposit::where('idempotency_key', $idempotencyKey)->first();
        if ($existing && $existing->status === 'REFUNDED') {
            return $existing;
        }

        $deposit = RetPackagingDeposit::findOrFail($params['deposit_id']);

        if ($deposit->status === 'REFUNDED') {
            throw new PackagingAlreadyReturnedException($deposit->package_code ?? $deposit->id);
        }

        return DB::transaction(function () use ($deposit, $params, $idempotencyKey) {
            $amountMinor = (int) $deposit->deposit_amount_minor;

            // Post Ledger: Liability Debit -> Receivable Credit
            $this->ledgerService->post(new PostingDTO(
                type: 'RETAIL_PACKAGING_DEPOSIT_REFUND',
                description: "Refund packaging deposit for {$deposit->package_code}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit('ret:deposit_liability:IDR', $amountMinor)
                    ->credit('ret:escrow_receivable:IDR', $amountMinor)
                    ->toEntries('IDR'),
                referenceType: 'RET_PACKAGING_REFUND',
                referenceId: (string) ($deposit->package_code ?? $deposit->id),
            ));

            $deposit->update([
                'status' => 'REFUNDED',
                'refunded_at' => Carbon::now(),
                'return_station_id' => $params['return_station_id'] ?? null,
                'inspected_condition' => $params['inspected_condition'] ?? 'GOOD',
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->audit(
                action: 'ret_packaging_deposit_refunded',
                auditable: $deposit,
                newValues: ['status' => 'REFUNDED', 'condition' => $params['inspected_condition'] ?? 'GOOD'],
                impactType: 'packaging_sustainability',
            );

            return $deposit;
        });
    }
}
