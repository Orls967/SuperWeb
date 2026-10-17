<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Scale;

use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Domain\Scale\ScaleProvisionerInterface;
use Modules\Core\Ledger\CoreTransactionTypes;

final class LedgerScaleProvisioner implements ScaleProvisionerInterface
{
    public function moduleName(): string
    {
        return 'ledger';
    }

    public function provision(
        string $tier,
        int $targetCount,
        int $chunkSize,
        int $seed,
        ?callable $onProgress = null
    ): int {
        $checkpoint = DB::table('sim_scale_checkpoints')
            ->where('provisioner_key', $this->moduleName())
            ->where('tier', $tier)
            ->where('seed', $seed)
            ->first();

        $startStep = 0;
        $totalInserted = 0;

        if ($checkpoint !== null) {
            $startStep = (int) $checkpoint->last_processed_step;
            $totalInserted = (int) $checkpoint->total_inserted;
            if ($totalInserted >= $targetCount) {
                return $totalInserted;
            }
        } else {
            DB::table('sim_scale_checkpoints')->insert([
                'provisioner_key' => $this->moduleName(),
                'tier' => $tier,
                'seed' => $seed,
                'last_processed_step' => 0,
                'total_inserted' => 0,
                'status' => 'in_progress',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Ensure source and destination clearing accounts exist
        $accountSource = LedgerAccount::firstOrCreate(
            ['code' => 'clearing:golden_scenario:IDR'],
            [
                'name' => 'Golden Scenario Clearing Account',
                'kind' => AccountKind::CLEARING->value,
                'asset_code' => 'IDR',
                'allow_negative' => true,
                'is_active' => true,
                'cached_balance' => '0',
            ]
        );

        $accountDest = LedgerAccount::firstOrCreate(
            ['code' => 'clearing:external:IDR'],
            [
                'name' => 'Rekening Kliring Eksternal IDR',
                'kind' => AccountKind::CLEARING->value,
                'asset_code' => 'IDR',
                'allow_negative' => true,
                'is_active' => true,
                'cached_balance' => '0',
            ]
        );

        $now = Carbon::now();
        $type = CoreTransactionTypes::GOLDEN_SCENARIO_CONSOLIDATION->value;

        mt_srand($seed + $startStep);

        for ($step = $startStep; $step < $targetCount; $step += $chunkSize) {
            $batchSize = min($chunkSize, $targetCount - $step);

            DB::transaction(function () use (
                $step,
                $batchSize,
                $seed,
                $now,
                $type,
                $accountSource,
                $accountDest,
                $tier,
                &$totalInserted
            ) {
                $transactions = [];
                $entries = [];

                for ($i = 0; $i < $batchSize; $i++) {
                    $itemIndex = $step + $i;
                    $idempotencyKey = 'SCALE-LEDGER-'.$tier.'-'.$seed.'-'.$itemIndex;
                    $txId = (int) DB::table('bank_ledger_transactions')->insertGetId([
                        'uuid' => (string) Str::uuid(),
                        'type' => $type,
                        'reference_type' => 'scale_provision',
                        'reference_id' => 'REF-SCALE-'.$itemIndex,
                        'idempotency_key' => $idempotencyKey,
                        'payload_hash' => hash('sha256', $idempotencyKey),
                        'description' => "Scale Provisioner Tier {$tier} Step {$itemIndex}",
                        'meta' => json_encode(['tier' => $tier, 'seed' => $seed, 'index' => $itemIndex]),
                        'posted_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $amount = '100000.00';
                    $negAmount = '-100000.00';

                    $entries[] = [
                        'transaction_id' => $txId,
                        'account_id' => $accountSource->id,
                        'asset_code' => 'IDR',
                        'amount' => $amount,
                        'balance_after' => '0.00',
                        'created_at' => $now,
                    ];

                    $entries[] = [
                        'transaction_id' => $txId,
                        'account_id' => $accountDest->id,
                        'asset_code' => 'IDR',
                        'amount' => $negAmount,
                        'balance_after' => '0.00',
                        'created_at' => $now,
                    ];
                }

                if (! empty($entries)) {
                    DB::table('bank_ledger_entries')->insert($entries);
                }

                $batchDelta = BigDecimal::of('100000.00')->multipliedBy($batchSize);
                $newSourceBal = BigDecimal::of($accountSource->cached_balance ?: '0')->plus($batchDelta);
                $newDestBal = BigDecimal::of($accountDest->cached_balance ?: '0')->minus($batchDelta);

                $accountSource->update(['cached_balance' => (string) $newSourceBal]);
                $accountDest->update(['cached_balance' => (string) $newDestBal]);

                $totalInserted += $batchSize;
                $currentStep = $step + $batchSize;

                DB::table('sim_scale_checkpoints')
                    ->where('provisioner_key', $this->moduleName())
                    ->where('tier', $tier)
                    ->where('seed', $seed)
                    ->update([
                        'last_processed_step' => $currentStep,
                        'total_inserted' => $totalInserted,
                        'status' => ($totalInserted >= $currentStep) ? 'in_progress' : 'completed',
                        'updated_at' => now(),
                    ]);
            });

            if ($onProgress !== null) {
                $onProgress($totalInserted, $targetCount, "Ledger provisioned {$totalInserted}/{$targetCount} records");
            }
        }

        DB::table('sim_scale_checkpoints')
            ->where('provisioner_key', $this->moduleName())
            ->where('tier', $tier)
            ->where('seed', $seed)
            ->update([
                'status' => 'completed',
                'updated_at' => now(),
            ]);

        return $totalInserted;
    }
}
