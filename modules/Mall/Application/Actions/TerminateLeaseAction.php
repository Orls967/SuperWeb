<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Mall\Domain\Enums\DepositStatus;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Enums\UnitStatus;
use Modules\Mall\Domain\Models\Lease;

class TerminateLeaseAction
{
    public function __construct(
        private readonly Ledger $ledger
    ) {}

    public function handle(
        Lease $lease,
        int $outstandingDeductions = 0,
        string $reason = 'Kontrak sewa selesai'
    ): Lease {
        return DB::transaction(function () use ($lease, $outstandingDeductions, $reason) {
            $locked = Lease::where('id', $lease->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [LeaseStatus::ACTIVE, LeaseStatus::SUSPENDED, LeaseStatus::DRAFT], true)) {
                throw new InvalidArgumentException("Status kontrak sewa {$locked->status->value} tidak valid untuk diterminasi.");
            }

            $depositAmount = $locked->security_deposit_amount;
            $user = $locked->tenant->user;

            // Jika deposit sedang tertahan, selesaikan liabilitas deposit
            if ($locked->deposit_status === DepositStatus::HELD && $depositAmount > 0 && $user) {
                $deductAmount = min($outstandingDeductions, $depositAmount);
                $refundAmount = $depositAmount - $deductAmount;

                $liabilityAccount = $this->ensureLedgerAccountExists(
                    'liability:mall:tenant_deposit:IDR',
                    'Titipan Deposit Jaminan Sewa Tenant Mall',
                    AccountKind::LIABILITY,
                    true
                );

                $walletAccount = $user->walletAccount('IDR');

                $entries = [
                    PostingEntryDTO::forCode($liabilityAccount->code, 'IDR', BigDecimal::of($depositAmount)->negated()),
                ];

                if ($deductAmount > 0) {
                    $revAccount = $this->ensureLedgerAccountExists(
                        'revenue:mall:rent_settlement:IDR',
                        'Penyelesaian Tunggakan Sewa Tenant',
                        AccountKind::REVENUE,
                        false
                    );
                    $entries[] = PostingEntryDTO::forCode($revAccount->code, 'IDR', BigDecimal::of($deductAmount));
                }

                if ($refundAmount > 0) {
                    $entries[] = PostingEntryDTO::forCode($walletAccount->code, 'IDR', BigDecimal::of($refundAmount));
                }

                $this->ledger->post(new PostingDTO(
                    type: TransactionType::RELEASE->value,
                    description: "Pelepasan deposit sewa unit {$locked->unit->unit_number} (Tunggakan: Rp ".number_format($deductAmount, 0, ',', '.').', Refund: Rp '.number_format($refundAmount, 0, ',', '.').')',
                    idempotencyKey: "mall:lease:term:{$locked->id}:".Str::uuid(),
                    entries: $entries,
                    referenceType: 'mall_lease',
                    referenceId: $locked->id,
                    createdBy: auth()->id()
                ));

                $depositStatus = $refundAmount === 0
                    ? DepositStatus::PARTIALLY_APPLIED
                    : ($deductAmount > 0 ? DepositStatus::PARTIALLY_APPLIED : DepositStatus::REFUNDED);
            } else {
                $depositStatus = $locked->deposit_status;
            }

            $locked->update([
                'status' => LeaseStatus::TERMINATED,
                'deposit_status' => $depositStatus,
                'terminated_at' => now(),
                'termination_reason' => $reason,
            ]);

            $locked->unit->update([
                'status' => UnitStatus::AVAILABLE,
            ]);

            return $locked;
        });
    }

    private function ensureLedgerAccountExists(string $code, string $name, AccountKind $kind, bool $allowNegative = false): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'asset_code' => 'IDR',
                'kind' => $kind->value,
                'allow_negative' => $allowNegative,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
