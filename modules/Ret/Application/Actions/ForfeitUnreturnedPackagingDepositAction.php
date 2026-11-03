<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Support\Posting;
use Modules\Ret\Domain\Models\RetPackagingDeposit;
use Modules\Shared\Application\BaseAction;

class ForfeitUnreturnedPackagingDepositAction extends BaseAction
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
            throw new InvalidArgumentException('Idempotency-Key is required to forfeit packaging deposit.');
        }

        $idempotencyKey = (string) $params['idempotency_key'];

        $existing = RetPackagingDeposit::where('idempotency_key', $idempotencyKey)->first();
        if ($existing && $existing->status === 'FORFEITED') {
            return $existing;
        }

        $deposit = RetPackagingDeposit::findOrFail($params['deposit_id']);

        if ($deposit->status !== 'HELD') {
            throw new DomainException("Cannot forfeit packaging deposit with status [{$deposit->status}].");
        }

        return DB::transaction(function () use ($deposit, $params, $idempotencyKey) {
            $amountMinor = (int) $deposit->deposit_amount_minor;

            $this->ensureRevenueAccount();

            // Post Ledger: Liability Debit -> Revenue Credit
            $this->ledgerService->post(new PostingDTO(
                type: 'RETAIL_PACKAGING_FORFEITURE',
                description: "Forfeit unreturned packaging deposit for {$deposit->package_code}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit('ret:deposit_liability:IDR', $amountMinor)
                    ->credit('ret:omnichannel_sales_revenue:IDR', $amountMinor)
                    ->toEntries('IDR'),
                referenceType: 'RET_PACKAGING_FORFEIT',
                referenceId: (string) ($deposit->package_code ?? $deposit->id),
            ));

            $deposit->update([
                'status' => 'FORFEITED',
                'forfeited_at' => Carbon::now(),
                'forfeit_reason' => $params['reason'] ?? 'Retention period expired',
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->audit(
                action: 'ret_packaging_deposit_forfeited',
                auditable: $deposit,
                newValues: ['status' => 'FORFEITED', 'amount' => $amountMinor],
                impactType: 'packaging_sustainability',
            );

            return $deposit;
        });
    }

    protected function ensureRevenueAccount(): void
    {
        LedgerAccount::firstOrCreate(
            ['code' => 'ret:omnichannel_sales_revenue:IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Omnichannel Sales Revenue',
                'asset_code' => 'IDR',
                'kind' => AccountKind::REVENUE->value,
                'allow_negative' => false,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
