<?php

declare(strict_types=1);

namespace Modules\Wealth\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Wealth\Domain\Models\WmProfile;
use Modules\Wealth\Domain\Models\WmSweepConfig;
use Modules\Wealth\Ledger\WealthAccounts;

class CashSweepService
{
    public function __construct(
        protected SimClockInterface $simClock,
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array{user_id: int, threshold_cash_idr: int, sweep_percentage_bps: int}  $data
     */
    public function configureSweep(array $data, string $idempotencyKey): WmSweepConfig
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        $bps = (int) ($data['sweep_percentage_bps'] ?? 10000);
        if ($bps < 0 || $bps > 10000) {
            throw new InvalidArgumentException('Sweep percentage bps must be between 0 and 10000.');
        }

        $threshold = (int) ($data['threshold_cash_idr'] ?? 0);
        if ($threshold < 0) {
            throw new InvalidArgumentException('Threshold cash must be positive or zero.');
        }

        return WmSweepConfig::updateOrCreate(
            ['user_id' => $data['user_id']],
            [
                'threshold_cash_idr' => (int) $data['threshold_cash_idr'],
                'sweep_percentage_bps' => $bps,
                'is_active' => true,
                'idempotency_key' => $idempotencyKey,
            ]
        );
    }

    /**
     * @return array{swept_amount_idr: int}
     */
    public function evaluateAndSweep(int $userId, string $idempotencyKey): array
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        return DB::transaction(function () use ($userId) {
            $config = WmSweepConfig::where('user_id', $userId)->where('is_active', true)->first();
            if (! $config) {
                return ['swept_amount_idr' => 0];
            }

            $user = User::findOrFail($userId);
            $wallet = $user->walletAccount('IDR');
            $currentBalance = (int) ($wallet->cached_balance ?? 0);

            $surplus = $currentBalance - (int) $config->threshold_cash_idr;
            if ($surplus <= 0) {
                return ['swept_amount_idr' => 0];
            }

            $sweptAmount = intdiv($surplus * (int) $config->sweep_percentage_bps, 10000);
            if ($sweptAmount <= 0) {
                return ['swept_amount_idr' => 0];
            }

            // Deduct cash from user wallet
            $wallet->cached_balance = (string) ($currentBalance - $sweptAmount);
            $wallet->save();

            // Credit custody account
            $profile = WmProfile::where('user_id', $userId)->first();
            $custody = WealthAccounts::portfolioCustody($profile->id ?? 'default');
            $custody->cached_balance = (string) (((int) $custody->cached_balance) + $sweptAmount);
            $custody->save();

            return ['swept_amount_idr' => $sweptAmount];
        });
    }

    /**
     * @param  array{user_id: int, payroll_id?: int, amount_idr?: int}  $eventPayload
     */
    public function handlePayrollDisbursedEvent(array $eventPayload, string $idempotencyKey): void
    {
        $userId = (int) $eventPayload['user_id'];
        $this->evaluateAndSweep($userId, $idempotencyKey);
    }
}
