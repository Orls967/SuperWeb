<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Contracts\VerifiesWalletPin;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Exceptions\InsufficientFundsException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Mall\Domain\Enums\DepositStatus;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Enums\UnitStatus;
use Modules\Mall\Domain\Exceptions\LeaseActivationException;
use Modules\Mall\Domain\Models\Lease;

class ActivateLeaseAction
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly VerifiesWalletPin $verifyPinAction
    ) {}

    public function handle(
        Lease $lease,
        ?User $payerUser = null,
        ?string $pin = null
    ): Lease {
        $user = $payerUser ?? $lease->tenant->user;
        if (! $user) {
            throw new InvalidArgumentException('Pengguna pembayar deposit jaminan sewa tidak ditemukan.');
        }

        if ($pin !== null) {
            $this->verifyPinAction->handle($user, $pin);
        }

        return DB::transaction(function () use ($lease, $user) {
            $locked = Lease::where('id', $lease->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [LeaseStatus::DRAFT, LeaseStatus::SUSPENDED], true)) {
                throw new LeaseActivationException("Status kontrak {$locked->status->value} tidak valid untuk diaktifkan.");
            }

            $depositAmount = $locked->security_deposit_amount;

            // 1. Debet deposit jaminan dari dompet tenant ke pos kewajiban deposit (liability)
            if ($depositAmount > 0) {
                $walletAccount = $user->walletAccount('IDR');
                if (! $walletAccount->canCover($depositAmount)) {
                    throw new InsufficientFundsException(
                        'Saldo dompet tenant tidak mencukupi untuk pembayaran deposit jaminan Rp '.number_format($depositAmount, 0, ',', '.')
                    );
                }

                $liabilityAccount = $this->ensureLiabilityAccountExists(
                    'liability:mall:tenant_deposit:IDR',
                    'Titipan Deposit Jaminan Sewa Tenant Mall'
                );

                $depositBd = BigDecimal::of($depositAmount);

                $this->ledger->post(new PostingDTO(
                    type: TransactionType::LEASE_DEPOSIT->value,
                    description: "Setoran deposit jaminan sewa unit {$locked->unit->unit_number} (#{$locked->lease_number})",
                    idempotencyKey: "mall:lease:activate:{$locked->id}:".Str::uuid(),
                    entries: [
                        PostingEntryDTO::forCode($walletAccount->code, 'IDR', $depositBd->negated()),
                        PostingEntryDTO::forCode($liabilityAccount->code, 'IDR', $depositBd),
                    ],
                    referenceType: 'mall_lease',
                    referenceId: $locked->id,
                    createdBy: $user->id
                ));
            }

            // 2. Perbarui status kontrak & unit
            $locked->update([
                'status' => LeaseStatus::ACTIVE,
                'deposit_status' => DepositStatus::HELD,
                'activated_at' => now(),
            ]);

            $locked->unit->update([
                'status' => UnitStatus::LEASED,
            ]);

            return $locked;
        });
    }

    private function ensureLiabilityAccountExists(string $code, string $name): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'asset_code' => 'IDR',
                'kind' => AccountKind::LIABILITY->value,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
