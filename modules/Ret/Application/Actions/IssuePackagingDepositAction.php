<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Support\Posting;
use Modules\Ret\Domain\Models\RetOrder;
use Modules\Ret\Domain\Models\RetPackagingDeposit;
use Modules\Shared\Application\BaseAction;

class IssuePackagingDepositAction extends BaseAction
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
            throw new InvalidArgumentException('Idempotency-Key is required to issue packaging deposit.');
        }

        $idempotencyKey = (string) $params['idempotency_key'];

        $existing = RetPackagingDeposit::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        $order = RetOrder::findOrFail($params['order_id']);
        $customerId = (string) $params['customer_id'];
        $packageCode = (string) $params['package_code'];
        $depositAmountMinor = (int) $params['deposit_amount_minor'];

        return DB::transaction(function () use ($order, $customerId, $packageCode, $depositAmountMinor, $idempotencyKey) {
            $this->ensureDepositAccounts();

            // Post Ledger: Receivable Debit -> Liability Credit
            $this->ledgerService->post(new PostingDTO(
                type: 'RETAIL_PACKAGING_DEPOSIT_RECEIPT',
                description: "Reusable packaging deposit for {$packageCode}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit('ret:escrow_receivable:IDR', $depositAmountMinor)
                    ->credit('ret:deposit_liability:IDR', $depositAmountMinor)
                    ->toEntries('IDR'),
                referenceType: 'RET_PACKAGING_DEPOSIT',
                referenceId: $packageCode,
            ));

            $depositCode = 'DEP-'.strtoupper(Str::random(10));

            $deposit = RetPackagingDeposit::create([
                'id' => (string) Str::uuid(),
                'deposit_code' => $depositCode,
                'order_id' => $order->id,
                'customer_id' => $customerId,
                'package_code' => $packageCode,
                'packaging_type' => 'REUSABLE_TOTE',
                'deposit_amount_minor' => $depositAmountMinor,
                'status' => 'HELD',
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->audit(
                action: 'ret_packaging_deposit_issued',
                auditable: $deposit,
                newValues: ['package_code' => $packageCode, 'deposit_minor' => $depositAmountMinor],
                impactType: 'packaging_sustainability',
            );

            return $deposit;
        });
    }

    protected function ensureDepositAccounts(): void
    {
        LedgerAccount::firstOrCreate(
            ['code' => 'ret:deposit_liability:IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Retail Reusable Packaging Deposit Liability',
                'asset_code' => 'IDR',
                'kind' => AccountKind::LIABILITY->value,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
