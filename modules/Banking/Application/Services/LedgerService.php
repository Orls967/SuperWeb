<?php

declare(strict_types=1);

namespace Modules\Banking\Application\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Exceptions\AccountFrozenException;
use Modules\Banking\Domain\Exceptions\InsufficientFundsException;
use Modules\Banking\Domain\Exceptions\UnbalancedTransactionException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Banking\Domain\Models\LedgerTransaction;

class LedgerService implements Ledger
{
    public function post(PostingDTO $dto): LedgerTransaction
    {
        if (count($dto->entries) < 2) {
            throw new InvalidArgumentException('Transaksi ledger harus memiliki minimal 2 entri.');
        }

        // Validate balance per asset_code before DB lock
        $assetSums = [];
        foreach ($dto->entries as $entry) {
            $code = strtoupper(trim($entry->assetCode));
            if (! isset($assetSums[$code])) {
                $assetSums[$code] = BigDecimal::zero();
            }
            $assetSums[$code] = $assetSums[$code]->plus($entry->amount);
        }

        foreach ($assetSums as $assetCode => $sum) {
            if (! $sum->isZero()) {
                throw new UnbalancedTransactionException($assetCode, $sum->__toString());
            }
        }

        return DB::transaction(function () use ($dto) {
            // Idempotency check: if transaction exists, return it
            $existing = LedgerTransaction::with('entries.account')
                ->where('idempotency_key', $dto->idempotencyKey)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            // Resolve account IDs
            $accountMap = [];
            foreach ($dto->entries as $entry) {
                if ($entry->accountId !== null) {
                    $accountMap[] = ['type' => 'id', 'val' => $entry->accountId];
                } elseif ($entry->accountCode !== null) {
                    $accountMap[] = ['type' => 'code', 'val' => $entry->accountCode];
                } else {
                    throw new InvalidArgumentException('Setiap entri harus memiliki accountId atau accountCode.');
                }
            }

            $rawIds = [];
            $rawCodes = [];
            foreach ($accountMap as $item) {
                if ($item['type'] === 'id') {
                    $rawIds[] = (int) $item['val'];
                } else {
                    $rawCodes[] = (string) $item['val'];
                }
            }

            if (! empty($rawCodes)) {
                $codeIds = LedgerAccount::whereIn('code', array_unique($rawCodes))->pluck('id', 'code');
                foreach ($rawCodes as $code) {
                    if (! isset($codeIds[$code])) {
                        throw new InvalidArgumentException("Akun dengan kode '{$code}' tidak ditemukan.");
                    }
                    $rawIds[] = $codeIds[$code];
                }
            }

            $uniqueAccountIds = array_values(array_unique($rawIds));
            sort($uniqueAccountIds, SORT_NUMERIC);

            // Lock accounts in ascending ID order to prevent deadlocks
            $accounts = LedgerAccount::whereIn('id', $uniqueAccountIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Check if all accounts exist
            foreach ($uniqueAccountIds as $id) {
                if (! isset($accounts[$id])) {
                    throw new InvalidArgumentException("Akun dengan ID {$id} tidak ditemukan.");
                }
            }

            // Map code-based entries to their account ID
            $resolvedEntries = [];
            foreach ($dto->entries as $entry) {
                $accountId = $entry->accountId;
                if ($accountId === null && $entry->accountCode !== null) {
                    $found = $accounts->firstWhere('code', $entry->accountCode);
                    $accountId = $found->id;
                }
                $resolvedEntries[] = [
                    'account_id' => $accountId,
                    'asset_code' => strtoupper(trim($entry->assetCode)),
                    'amount' => $entry->amount,
                ];
            }

            // Check frozen status
            foreach ($accounts as $account) {
                if ($account->is_frozen) {
                    throw new AccountFrozenException($account->code);
                }
            }

            // Track running balances and validate sufficient funds
            $runningBalances = [];
            foreach ($accounts as $account) {
                $runningBalances[$account->id] = BigDecimal::of($account->cached_balance ?: '0');
            }

            $calculatedEntries = [];
            foreach ($resolvedEntries as $item) {
                $accId = $item['account_id'];
                $account = $accounts[$accId];
                $amount = $item['amount'];

                $currentBal = $runningBalances[$accId];
                $balanceAfter = $currentBal->plus($amount);

                if (! $account->allow_negative && $balanceAfter->isNegative()) {
                    throw new InsufficientFundsException(
                        $account->code,
                        $currentBal->toScale(2, RoundingMode::HalfUp)->__toString(),
                        $amount->abs()->toScale(2, RoundingMode::HalfUp)->__toString()
                    );
                }

                $runningBalances[$accId] = $balanceAfter;

                $calculatedEntries[] = [
                    'account_id' => $accId,
                    'asset_code' => $item['asset_code'],
                    'amount' => $amount->toScale(18, RoundingMode::HalfUp)->__toString(),
                    'balance_after' => $balanceAfter->toScale(18, RoundingMode::HalfUp)->__toString(),
                ];
            }

            // Create transaction
            $transaction = LedgerTransaction::create([
                'uuid' => (string) Str::uuid(),
                'type' => $dto->type,
                'reference_type' => $dto->referenceType,
                'reference_id' => $dto->referenceId,
                'idempotency_key' => $dto->idempotencyKey,
                'description' => $dto->description,
                'meta' => $dto->meta,
                'posted_at' => $dto->postedAt ?? now(),
                'created_by' => $dto->createdBy,
            ]);

            // Save entries & update account cached balances
            $now = now();
            foreach ($calculatedEntries as $entryData) {
                LedgerEntry::create([
                    'transaction_id' => $transaction->id,
                    'account_id' => $entryData['account_id'],
                    'asset_code' => $entryData['asset_code'],
                    'amount' => $entryData['amount'],
                    'balance_after' => $entryData['balance_after'],
                    'created_at' => $now,
                ]);
            }

            foreach ($accounts as $accId => $account) {
                $finalBalance = $runningBalances[$accId];
                $account->cached_balance = $finalBalance->toScale(18, RoundingMode::HalfUp)->__toString();
                $account->save();
            }

            return $transaction->load('entries.account');
        });
    }
}
