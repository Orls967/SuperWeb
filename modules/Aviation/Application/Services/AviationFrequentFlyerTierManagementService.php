<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Models\AviationLoyaltyAccount;
use Modules\Aviation\Domain\Models\AviationLoyaltyTransaction;

class AviationFrequentFlyerTierManagementService
{
    /**
     * Jalankan evaluasi tahunan pembaruan tingkat tier (Annual Tier Audit & Soft Landing).
     *
     * @return array{accounts_evaluated: int, upgraded: int, maintained: int, soft_landed: int}
     */
    public function executeAnnualTierAudit(): array
    {
        return DB::transaction(function () {
            $accounts = AviationLoyaltyAccount::where('status', 'active')->get();

            $upgraded = 0;
            $maintained = 0;
            $softLanded = 0;

            foreach ($accounts as $acc) {
                $miles = $acc->tier_miles;
                $current = $acc->current_tier;

                $targetTier = match (true) {
                    $miles >= 65000 => 'platinum',
                    $miles >= 35000 => 'gold',
                    $miles >= 15000 => 'silver',
                    default => 'classic',
                };

                if ($targetTier === $current) {
                    $maintained++;
                } elseif ($this->tierRank($targetTier) > $this->tierRank($current)) {
                    $acc->current_tier = $targetTier;
                    $upgraded++;
                } else {
                    // Kebijakan perlindungan soft landing: Turun maksimal 1 tingkat
                    $softTier = match ($current) {
                        'platinum' => 'gold',
                        'gold' => 'silver',
                        default => 'classic',
                    };
                    $acc->current_tier = $softTier;
                    $softLanded++;
                }

                // Reset tier miles tahunan dan perbarui tanggal kedaluwarsa
                $acc->tier_miles = 0;
                $acc->tier_expiry_date = Carbon::now()->addYear()->toDateString();
                $acc->save();
            }

            return [
                'accounts_evaluated' => $accounts->count(),
                'upgraded' => $upgraded,
                'maintained' => $maintained,
                'soft_landed' => $softLanded,
            ];
        });
    }

    /**
     * Eksekusi penghapusan poin kedaluwarsa (Breakage recognition) untuk miles yang pasif > 36 bulan.
     */
    public function purgeExpiredMiles(): array
    {
        return DB::transaction(function () {
            $cutoff = Carbon::now()->subMonths(36);

            $staleTransactions = AviationLoyaltyTransaction::where('created_at', '<', $cutoff)
                ->where('transaction_type', 'flight_accrual')
                ->where('miles_delta', '>', 0)
                ->get();

            $totalExpired = 0;
            $affectedAccounts = [];

            foreach ($staleTransactions as $tx) {
                $acc = $tx->loyaltyAccount;
                if ($acc !== null && $acc->redeemable_miles > 0) {
                    $expireAmount = min($acc->redeemable_miles, (int) $tx->miles_delta);
                    if ($expireAmount > 0) {
                        $acc->redeemable_miles -= $expireAmount;
                        $acc->save();

                        $ref = sprintf('TX-EXP-%s-%s', date('Ymd'), strtoupper(substr(uniqid('', true), -6)));
                        AviationLoyaltyTransaction::create([
                            'loyalty_account_id' => $acc->id,
                            'transaction_reference' => $ref,
                            'transaction_type' => 'expiry',
                            'miles_delta' => -$expireAmount,
                            'tier_miles_delta' => 0,
                            'running_balance' => $acc->redeemable_miles,
                            'nonce_claim_hash' => hash('sha256', $ref.$tx->id),
                        ]);

                        $totalExpired += $expireAmount;
                        $affectedAccounts[$acc->id] = true;
                    }
                }
            }

            return [
                'expired_miles_total' => $totalExpired,
                'accounts_affected' => count($affectedAccounts),
            ];
        });
    }

    protected function tierRank(string $tier): int
    {
        return match (strtolower($tier)) {
            'platinum' => 4,
            'gold' => 3,
            'silver' => 2,
            default => 1,
        };
    }
}
