<?php

declare(strict_types=1);

namespace Modules\Banking\Support;

use Brick\Math\BigDecimal;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Domain\Exceptions\UnbalancedTransactionException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Shared\Domain\ValueObjects\Money;
use Traversable;

final class PostingLines implements Countable, IteratorAggregate
{
    /** @var list<array{account: string|int|LedgerAccount, amount: BigDecimal, assetCode: ?string, side: string}> */
    private array $lines = [];

    public function debit(string|int|LedgerAccount $account, BigDecimal|string|int|float|Money $amount, ?string $assetCode = null): self
    {
        return $this->addLine($account, $amount, $assetCode, 'debit');
    }

    public function credit(string|int|LedgerAccount $account, BigDecimal|string|int|float|Money $amount, ?string $assetCode = null): self
    {
        return $this->addLine($account, $amount, $assetCode, 'credit');
    }

    private function addLine(string|int|LedgerAccount $account, BigDecimal|string|int|float|Money $amount, ?string $assetCode, string $side): self
    {
        $decimal = $this->resolveAmount($amount);

        if ($decimal->isLessThanOrEqualTo(0)) {
            throw new InvalidArgumentException('Nominal posting harus lebih besar dari 0.');
        }

        // Single sign convention (KONSEP §A2.1): debit = -, credit = +
        $signedAmount = $side === 'debit' ? $decimal->negated() : $decimal;

        $asset = $assetCode;
        if ($asset === null) {
            if ($amount instanceof Money) {
                $asset = $amount->assetCode;
            } elseif ($account instanceof LedgerAccount) {
                $asset = $account->asset_code;
            } elseif (is_string($account) && preg_match('/:([A-Za-z0-9]+)$/', $account, $matches)) {
                $candidate = strtoupper($matches[1]);
                // If it looks like a standard asset code (e.g. IDR, USD, BTC, PTS, USDT)
                if (in_array($candidate, ['IDR', 'USD', 'USDT', 'BTC', 'ETH', 'PTS', 'EUR', 'SGD'], true)) {
                    $asset = $candidate;
                }
            }
        }

        $this->lines[] = [
            'account' => $account,
            'amount' => $signedAmount,
            'assetCode' => $asset,
            'side' => $side,
        ];

        return $this;
    }

    /**
     * @return list<PostingEntryDTO>
     */
    public function toEntries(?string $defaultAssetCode = 'IDR'): array
    {
        if (count($this->lines) < 2) {
            throw new InvalidArgumentException('Transaksi ledger harus memiliki minimal 2 entri.');
        }

        $sums = [];
        $entries = [];

        foreach ($this->lines as $line) {
            $asset = $line['assetCode'] ?? $defaultAssetCode;
            if ($asset === null || $asset === '') {
                throw new InvalidArgumentException('Kode aset harus ditentukan untuk setiap entri posting.');
            }
            $asset = strtoupper(trim($asset));

            if (! isset($sums[$asset])) {
                $sums[$asset] = BigDecimal::zero();
            }
            $sums[$asset] = $sums[$asset]->plus($line['amount']);

            $acc = $line['account'];
            if ($acc instanceof LedgerAccount) {
                $entries[] = PostingEntryDTO::forCode($acc->code, $asset, $line['amount']);
            } elseif (is_int($acc)) {
                $entries[] = PostingEntryDTO::forAccount($acc, $asset, $line['amount']);
            } else {
                $entries[] = PostingEntryDTO::forCode((string) $acc, $asset, $line['amount']);
            }
        }

        foreach ($sums as $asset => $sum) {
            if (! $sum->isZero()) {
                throw new UnbalancedTransactionException($asset, (string) $sum);
            }
        }

        return $entries;
    }

    /**
     * @return list<PostingEntryDTO>
     */
    public function toArray(?string $defaultAssetCode = 'IDR'): array
    {
        return $this->toEntries($defaultAssetCode);
    }

    /**
     * @return list<PostingEntryDTO>
     */
    public function get(?string $defaultAssetCode = 'IDR'): array
    {
        return $this->toEntries($defaultAssetCode);
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->toEntries());
    }

    public function count(): int
    {
        return count($this->lines);
    }

    private function resolveAmount(BigDecimal|string|int|float|Money $amount): BigDecimal
    {
        if ($amount instanceof Money) {
            return $amount->amount;
        }

        if ($amount instanceof BigDecimal) {
            return $amount;
        }

        return BigDecimal::of((string) $amount);
    }
}
