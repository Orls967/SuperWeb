<?php

declare(strict_types=1);

namespace Modules\Banking\Application\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Registry\TransactionTypeRegistry;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Exceptions\AccountFrozenException;
use Modules\Banking\Domain\Exceptions\AssetMismatchException;
use Modules\Banking\Domain\Exceptions\IdempotencyConflictException;
use Modules\Banking\Domain\Exceptions\InsufficientFundsException;
use Modules\Banking\Domain\Exceptions\UnbalancedTransactionException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Banking\Domain\Models\LedgerTransaction;

class LedgerService implements Ledger
{
    public function post(PostingDTO $dto): LedgerTransaction
    {
        if (strlen($dto->type) > 32) {
            throw new InvalidArgumentException("Tipe transaksi '{$dto->type}' melebihi batas 32 karakter.");
        }

        if (! TransactionTypeRegistry::isValid($dto->type)) {
            throw new InvalidArgumentException("Tipe transaksi '{$dto->type}' tidak terdaftar di registry enum tipe transaksi.");
        }

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

        $payloadHash = $this->computePayloadHash($dto);

        try {
            return $this->executeTransaction(function () use ($dto, $payloadHash) {
                // Idempotency check: if transaction exists, check payload_hash
                $existing = LedgerTransaction::with('entries.account')
                    ->where('idempotency_key', $dto->idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    if ($existing->payload_hash !== null && $existing->payload_hash !== $payloadHash) {
                        throw new IdempotencyConflictException(
                            $dto->idempotencyKey,
                            (string) $existing->payload_hash,
                            $payloadHash
                        );
                    }

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

                // Map code-based entries to their account ID and validate asset_code
                $resolvedEntries = [];
                foreach ($dto->entries as $entry) {
                    $accountId = $entry->accountId;
                    if ($accountId === null && $entry->accountCode !== null) {
                        $found = $accounts->firstWhere('code', $entry->accountCode);
                        $accountId = $found->id;
                    }

                    $account = $accounts[$accountId] ?? null;
                    $entryAssetCode = strtoupper(trim($entry->assetCode));
                    if ($account !== null && strtoupper(trim($account->asset_code)) !== $entryAssetCode) {
                        throw new AssetMismatchException($entryAssetCode, $account->code, (string) $account->asset_code);
                    }

                    $resolvedEntries[] = [
                        'account_id' => $accountId,
                        'asset_code' => $entryAssetCode,
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
                    'payload_hash' => $payloadHash,
                    'description' => $dto->description,
                    'meta' => $dto->meta,
                    'posted_at' => $dto->postedAt ?? now(),
                    'created_by' => $dto->createdBy,
                ]);

                // Save entries & update account cached balances
                $now = now();
                foreach ($calculatedEntries as $entryData) {
                    $acc = $accounts[$entryData['account_id']];
                    $rawAmount = BigDecimal::of($entryData['amount']);
                    $debitAmount = $rawAmount->isNegative() ? $rawAmount->abs()->__toString() : '0';
                    $creditAmount = $rawAmount->isPositive() ? $rawAmount->__toString() : '0';

                    LedgerEntry::create([
                        'transaction_id' => $transaction->id,
                        'account_id' => $entryData['account_id'],
                        'account_code' => $acc->code,
                        'asset_code' => $entryData['asset_code'],
                        'amount' => $entryData['amount'],
                        'debit_amount' => $debitAmount,
                        'credit_amount' => $creditAmount,
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
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                $winner = LedgerTransaction::with('entries.account')
                    ->where('idempotency_key', $dto->idempotencyKey)
                    ->first();

                if ($winner !== null) {
                    if ($winner->payload_hash !== null && $winner->payload_hash !== $payloadHash) {
                        throw new IdempotencyConflictException(
                            $dto->idempotencyKey,
                            (string) $winner->payload_hash,
                            $payloadHash
                        );
                    }

                    return $winner;
                }
            }

            throw $e;
        }
    }

    protected function executeTransaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());
        $code = (string) $e->getCode();

        $isConstraintViolation = in_array($code, ['23000', '23505'], true)
            || ($e->errorInfo[1] ?? null) === 1062
            || ($e->errorInfo[1] ?? null) === 19;

        if (! $isConstraintViolation) {
            return false;
        }

        return str_contains($message, 'bank_ledger_transactions')
            || str_contains($message, 'idempotency_key');
    }

    public function computePayloadHash(PostingDTO $dto): string
    {
        $canonicalEntries = [];
        foreach ($dto->entries as $entry) {
            $e = [
                'account_code' => $entry->accountCode,
                'account_id' => $entry->accountId !== null ? (string) $entry->accountId : null,
                'amount' => (string) $entry->amount,
                'asset_code' => strtoupper(trim($entry->assetCode)),
            ];
            ksort($e, SORT_STRING);
            $canonicalEntries[] = $e;
        }

        usort($canonicalEntries, static fn (array $a, array $b): int => json_encode($a) <=> json_encode($b));

        $payload = [
            'description' => $dto->description,
            'entries' => $canonicalEntries,
            'idempotency_key' => $dto->idempotencyKey,
            'meta' => $dto->meta !== null ? $this->canonicalizeMeta($dto->meta) : null,
            'reference_id' => $dto->referenceId !== null ? (string) $dto->referenceId : null,
            'reference_type' => $dto->referenceType,
            'type' => $dto->type,
        ];
        ksort($payload, SORT_STRING);

        $json = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $json);
    }

    private function canonicalizeMeta(mixed $data): mixed
    {
        if (! is_array($data)) {
            if (is_int($data) || is_float($data)) {
                return (string) $data;
            }

            return $data;
        }

        $isAssoc = ! array_is_list($data);
        $result = [];
        foreach ($data as $k => $v) {
            $result[$k] = $this->canonicalizeMeta($v);
        }
        if ($isAssoc) {
            ksort($result, SORT_STRING);
        }

        return $result;
    }

    public function getCachedBalance(string $code): ?string
    {
        return LedgerAccount::where('code', $code)->value('cached_balance');
    }

    public function findAccountByCode(string $code): ?\Modules\Banking\Contracts\LedgerAccount
    {
        $account = LedgerAccount::where('code', $code)->first();
        if ($account === null) {
            return null;
        }

        return \Modules\Banking\Contracts\LedgerAccount::find($account->id);
    }
}
