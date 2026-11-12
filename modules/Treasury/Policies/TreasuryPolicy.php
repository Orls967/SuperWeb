<?php

declare(strict_types=1);

namespace Modules\Treasury\Policies;

use App\Models\User;
use Modules\Treasury\Domain\Enums\HedgeStatus;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\CashPool;
use Modules\Treasury\Domain\Models\CovenantWaiver;
use Modules\Treasury\Domain\Models\CreditFacility;
use Modules\Treasury\Domain\Models\ForwardContract;
use Modules\Treasury\Domain\Models\PettyCashVoucher;
use Modules\Treasury\Domain\Models\Revaluation;

/**
 * Kebijakan Otorisasi Modul Treasury & Multi-Currency (Fase 48).
 *
 * Menegakkan pemisahan wewenang (RBAC & Four-Eyes Segregation of Duties):
 *   - admin: akses penuh ke pembacaan dan eksekusi (kecuali menyetujui transaksi buatannya sendiri)
 *   - treasury: dealer & manajer perbendaharaan (dapat membuat draf, mengeksekusi sweep, rekonsiliasi;
 *     persetujuan kontrak lindung nilai, voucher kas kecil, dan covenant waiver tunduk pada Four-Eyes)
 *   - auditor: akses baca penuh ke laporan, subledger, sertifikat algoritma, dan audit trail;
 *     dilarang mengeksekusi mutasi uang atau perubahan state
 */
final class TreasuryPolicy
{
    /** @var list<string> */
    private const VIEW_ROLES = ['admin', 'treasury', 'auditor'];

    /** @var list<string> */
    private const EXECUTE_ROLES = ['admin', 'treasury'];

    public function viewAny(User $user): bool
    {
        return in_array((string) $user->role, self::VIEW_ROLES, true);
    }

    public function viewOperationalDesk(User $user): bool
    {
        return in_array((string) $user->role, self::EXECUTE_ROLES, true);
    }

    public function manageExchangeRates(User $user): bool
    {
        return in_array((string) $user->role, self::EXECUTE_ROLES, true);
    }

    public function runRevaluation(User $user, ?Revaluation $revaluation = null): bool
    {
        if (! in_array((string) $user->role, self::EXECUTE_ROLES, true)) {
            return false;
        }

        if ($revaluation !== null && $revaluation->is_reversed) {
            return false;
        }

        return true;
    }

    public function reconcileBankAccount(User $user, ?BankAccount $account = null): bool
    {
        if (! in_array((string) $user->role, self::EXECUTE_ROLES, true)) {
            return false;
        }

        if ($account !== null && ! $account->is_active) {
            return false;
        }

        return true;
    }

    public function createForwardContract(User $user): bool
    {
        return in_array((string) $user->role, self::EXECUTE_ROLES, true);
    }

    /**
     * Four-Eyes Segregation of Duties: Penyetuju kontrak forward tidak boleh sama dengan pembuat draf.
     */
    public function approveForwardContract(User $user, ForwardContract $contract): bool
    {
        if (! in_array((string) $user->role, self::EXECUTE_ROLES, true)) {
            return false;
        }

        $status = $contract->status instanceof HedgeStatus ? $contract->status : HedgeStatus::tryFrom((string) $contract->status);
        if ($status !== HedgeStatus::DRAFT) {
            return false;
        }

        if ($contract->creator_id !== null && (int) $contract->creator_id === (int) $user->id) {
            return false;
        }

        return true;
    }

    public function settleForwardContract(User $user, ForwardContract $contract): bool
    {
        if (! in_array((string) $user->role, self::EXECUTE_ROLES, true)) {
            return false;
        }

        $status = $contract->status instanceof HedgeStatus ? $contract->status : HedgeStatus::tryFrom((string) $contract->status);

        return in_array($status, [HedgeStatus::ACTIVE, HedgeStatus::MATURED], true);
    }

    public function drawCreditFacility(User $user, CreditFacility $facility): bool
    {
        if (! in_array((string) $user->role, self::EXECUTE_ROLES, true)) {
            return false;
        }

        return $facility->status === 'active' && $facility->availableLimitIdr() > 0;
    }

    /**
     * Four-Eyes Segregation of Duties pada persetujuan keringanan covenant (Covenant Waiver).
     */
    public function approveCovenantWaiver(User $user, CovenantWaiver $waiver): bool
    {
        if (! in_array((string) $user->role, self::EXECUTE_ROLES, true)) {
            return false;
        }

        if ($waiver->status !== 'pending') {
            return false;
        }

        if ($waiver->requested_by !== null && (int) $waiver->requested_by === (int) $user->id) {
            return false;
        }

        return true;
    }

    public function executeCashPoolSweep(User $user, ?CashPool $pool = null): bool
    {
        return in_array((string) $user->role, self::EXECUTE_ROLES, true);
    }

    /**
     * Four-Eyes Segregation of Duties pada pencairan voucher kas kecil imprest.
     */
    public function approvePettyCashVoucher(User $user, PettyCashVoucher $voucher): bool
    {
        if (! in_array((string) $user->role, self::EXECUTE_ROLES, true)) {
            return false;
        }

        if ($voucher->status !== 'submitted') {
            return false;
        }

        if ($voucher->created_by !== null && (int) $voucher->created_by === (int) $user->id) {
            return false;
        }

        return true;
    }
}
