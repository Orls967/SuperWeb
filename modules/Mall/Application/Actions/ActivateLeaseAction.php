<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Contracts\VerifiesWalletPin;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Exceptions\InsufficientFundsException;
use Modules\Mall\Domain\Enums\DepositStatus;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Enums\UnitStatus;
use Modules\Mall\Domain\Exceptions\LeaseActivationException;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Ledger\MallAccounts;
use Modules\Shared\Support\Posting;

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

                $liabilityAccount = MallAccounts::tenantDepositLiabilityAccount();

                $entries = Posting::lines()
                    ->debit($walletAccount->code, $depositAmount, 'IDR')
                    ->credit($liabilityAccount->code, $depositAmount, 'IDR')
                    ->toEntries('IDR');

                $this->ledger->post(new PostingDTO(
                    type: TransactionType::LEASE_DEPOSIT->value,
                    description: "Setoran deposit jaminan sewa unit {$locked->unit->unit_number} (#{$locked->lease_number})",
                    idempotencyKey: "mall:lease:activate:{$locked->id}",
                    entries: $entries,
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
}
