<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Insurance\Domain\Models\InsurancePolicy;
use Modules\Insurance\Domain\Models\InsurancePolicyEarning;
use Modules\Insurance\Domain\Models\InsurancePolicySequence;
use Modules\Insurance\Domain\Models\InsuranceProduct;
use Modules\Insurance\Ledger\InsuranceAccounts;
use Modules\Shared\Domain\Math\CumulativeRounding;

class InsurancePolicyService
{
    public function __construct(
        protected SimClockInterface $simClock,
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array{product_id: int, user_id: int, subject_ref_type: string, subject_ref_id: string, duration_days: int}  $data
     */
    public function issuePolicy(array $data, string $idempotencyKey): InsurancePolicy
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        return DB::transaction(function () use ($data) {
            $product = InsuranceProduct::findOrFail($data['product_id']);
            $user = User::findOrFail($data['user_id']);
            $wallet = $user->walletAccount('IDR');

            $balance = (int) ($wallet->cached_balance ?? 0);
            $premium = (int) $product->premium_amount_idr;

            if ($balance < $premium) {
                throw new DomainException("Insufficient cash balance for policy premium. Required: {$premium}, available: {$balance}.");
            }

            // Sequential gapless policy numbering per product per year (I-1)
            $year = (int) $this->simClock->now()->format('Y');
            $seqRecord = InsurancePolicySequence::lockForUpdate()->firstOrCreate(
                ['product_id' => $product->id, 'year' => $year],
                ['last_sequence' => 0]
            );
            $seqRecord->last_sequence++;
            $seqRecord->save();

            $seqFormatted = sprintf('%06d', $seqRecord->last_sequence);
            $policyNumber = "POL-{$product->product_code}-{$year}-{$seqFormatted}";

            $durationDays = (int) ($data['duration_days'] ?? 30);
            $now = $this->simClock->now();

            $policy = InsurancePolicy::create([
                'policy_number' => $policyNumber,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'subject_ref_type' => $data['subject_ref_type'],
                'subject_ref_id' => $data['subject_ref_id'],
                'premium_paid_idr' => $premium,
                'coverage_limit_idr' => $product->max_payout_idr,
                'starts_at' => $now,
                'expires_at' => $now->copy()->addDays($durationDays),
                'status' => 'active',
            ]);

            // Deduct from customer cash wallet
            $wallet->cached_balance = (string) ($balance - $premium);
            $wallet->save();

            // Credit unearned premium liability
            $unearnedAcct = InsuranceAccounts::unearnedPremium();
            $unearnedAcct->cached_balance = (string) (((int) $unearnedAcct->cached_balance) + $premium);
            $unearnedAcct->save();

            return $policy;
        });
    }

    /**
     * @return array{daily_earned_idr: int, cumulative_earned_idr: int}
     */
    public function recognizeDailyEarning(int $policyId, int $dayIndex, string $idempotencyKey): array
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        return DB::transaction(function () use ($policyId, $dayIndex, $idempotencyKey) {
            $policy = InsurancePolicy::lockForUpdate()->findOrFail($policyId);
            $durationDays = max(1, (int) $policy->starts_at->diffInDays($policy->expires_at));
            $premium = (int) $policy->premium_paid_idr;

            $schedule = CumulativeRounding::schedule($premium, $durationDays);
            $stepIndex = min(count($schedule) - 1, max(0, $dayIndex - 1));
            $dailyEarned = $schedule[$stepIndex];

            // Cumulative earned up to dayIndex
            $cumulativeEarned = 0;
            for ($i = 0; $i <= $stepIndex; $i++) {
                $cumulativeEarned += $schedule[$i];
            }

            // Append-only fact record (PR5)
            InsurancePolicyEarning::create([
                'policy_id' => $policy->id,
                'earning_date' => $this->simClock->now()->toDateString(),
                'day_index' => $dayIndex,
                'daily_amount_idr' => $dailyEarned,
                'cumulative_earned_idr' => $cumulativeEarned,
                'idempotency_key' => $idempotencyKey,
                'created_at' => $this->simClock->now(),
            ]);

            // Transfer from Unearned to Earned Premium Revenue
            $unearned = InsuranceAccounts::unearnedPremium();
            $unearned->cached_balance = (string) (((int) $unearned->cached_balance) - $dailyEarned);
            $unearned->save();

            $earned = InsuranceAccounts::earnedPremiumRevenue();
            $earned->cached_balance = (string) (((int) $earned->cached_balance) + $dailyEarned);
            $earned->save();

            return [
                'daily_earned_idr' => $dailyEarned,
                'cumulative_earned_idr' => $cumulativeEarned,
            ];
        });
    }

    public function expireLapsedPolicy(int $policyId, string $idempotencyKey): void
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        DB::transaction(function () use ($policyId) {
            $policy = InsurancePolicy::lockForUpdate()->findOrFail($policyId);
            $policy->status = 'expired';
            $policy->save();
        });
    }
}
