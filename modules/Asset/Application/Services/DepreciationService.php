<?php

declare(strict_types=1);

namespace Modules\Asset\Application\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Asset\Domain\Enums\AssetEventType;
use Modules\Asset\Domain\Enums\DepreciationMethod;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\Depreciation;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;

/**
 * Penyusutan aset (31.1) + fiskal/komersial (31.2), PSAK 16 simulasi.
 *
 * Semua nominal IDR tetap integer/BigDecimal; jadwal idempoten per
 * (asset, period, method, book) dan ledger memakai key deterministik.
 */
class DepreciationService
{
    private const ACCUMULATED = 'ast:accumulated_depreciation';

    private const EXPENSE = 'ast:depreciation_expense';

    private const FISCAL_ACCUMULATED = 'ast:fiscal_accumulated_depreciation';

    private const FISCAL_EXPENSE = 'ast:fiscal_depreciation_expense';

    public function __construct(
        private readonly Ledger $ledger,
        private readonly AssetService $assets,
    ) {}

    /**
     * Hitung penyusutan untuk satu aset/periode/buku.
     *
     * @return array{amount_idr: int, book_value_after_idr: int, record: Depreciation|null}
     */
    public function depreciate(
        Asset $asset,
        string $period,
        ?DepreciationMethod $method = null,
        string $book = 'commercial',
        ?int $periodUnits = null,
        ?int $totalUnits = null,
        ?int $approvedByUserId = null,
    ): array {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new InvalidArgumentException('Periode harus berformat YYYY-MM.');
        }

        if (! in_array($book, ['commercial', 'fiscal'], true)) {
            throw new InvalidArgumentException('Buku penyusutan harus commercial atau fiscal.');
        }

        return DB::transaction(function () use (
            $asset, $period, $method, $book, $periodUnits, $totalUnits, $approvedByUserId
        ) {
            /** @var Asset $locked */
            $locked = Asset::query()->with('category')->lockForUpdate()->findOrFail($asset->getKey());

            $method ??= DepreciationMethod::tryFrom((string) $locked->category?->depreciation_method)
                ?? DepreciationMethod::StraightLine;

            $existing = Depreciation::query()
                ->where('asset_id', $locked->id)
                ->where('period', $period)
                ->where('method', $method->value)
                ->where('book', $book)
                ->first();

            if ($existing !== null) {
                return [
                    'amount_idr' => (int) $existing->amount_idr,
                    'book_value_after_idr' => (int) $locked->book_value_idr,
                    'record' => $existing,
                ];
            }

            if ($locked->source_type === 'legacy_backfill') {
                // Aset legacy: biaya sudah tercatat di modul asal, tidak masuk
                // ledger modul Asset — penyusutan tidak boleh menciptakan
                // akumulasi tanpa perolehan (subledger-only).
                return ['amount_idr' => 0, 'book_value_after_idr' => (int) $locked->book_value_idr, 'record' => null];
            }

            if ($locked->status->value === 'disposed') {
                return ['amount_idr' => 0, 'book_value_after_idr' => (int) $locked->book_value_idr, 'record' => null];
            }

            $cost = (int) $locked->acquisition_cost_idr + (int) $locked->landed_cost_idr;
            $life = (int) ($locked->category?->useful_life_years ?? 0);
            $salvage = (int) ($locked->salvage_value_idr ?? 0);
            $depreciable = max(0, $cost - $salvage);
            $bookValue = max(0, (int) $locked->book_value_idr);

            if ($method === DepreciationMethod::None || $depreciable === 0 || $life <= 0 || $bookValue <= $salvage) {
                return ['amount_idr' => 0, 'book_value_after_idr' => $bookValue, 'record' => null];
            }

            $amount = match ($method) {
                DepreciationMethod::StraightLine => $this->straightLine($depreciable, $life),
                DepreciationMethod::DecliningBalance => $this->decliningBalance($bookValue, $salvage, $life),
                DepreciationMethod::UnitsOfProduction => $this->unitsOfProduction(
                    $depreciable,
                    $periodUnits ?? 0,
                    $totalUnits ?? 0
                ),
                DepreciationMethod::None => 0,
            };

            $amount = min($amount, max(0, $bookValue - $salvage));
            if ($amount <= 0) {
                return ['amount_idr' => 0, 'book_value_after_idr' => $bookValue, 'record' => null];
            }

            $accounts = $this->ensureAccounts($book);
            $key = "ast:depreciate:{$locked->id}:{$period}:{$book}";

            $transaction = $this->ledger->post(new PostingDTO(
                type: TransactionType::ASSET_DEPRECIATION->value,
                description: "Penyusutan aset {$locked->asset_number} periode {$period} ({$book})",
                idempotencyKey: $key,
                entries: [
                    PostingEntryDTO::forAccount($accounts['expense']->id, 'IDR', BigDecimal::of($amount)),
                    PostingEntryDTO::forAccount($accounts['accumulated']->id, 'IDR', BigDecimal::of($amount)->negated()),
                ],
                referenceType: Asset::class,
                referenceId: $locked->id,
                meta: [
                    'asset_id' => $locked->id,
                    'period' => $period,
                    'method' => $method->value,
                    'book' => $book,
                    'amount_idr' => $amount,
                    'approved_by_user_id' => $approvedByUserId,
                ],
                createdBy: $approvedByUserId,
                postedAt: now(),
            ));

            $newAccumulated = (int) $locked->accumulated_depreciation_idr + $amount;
            $newBookValue = max($salvage, $cost - $newAccumulated);

            // Commercial book updates the asset's single book value; fiscal book is tracked separately.
            if ($book === 'commercial') {
                $locked->accumulated_depreciation_idr = $newAccumulated;
                $locked->book_value_idr = $newBookValue;
                $locked->save();
            }

            $record = Depreciation::create([
                'asset_id' => $locked->id,
                'period' => $period,
                'method' => $method->value,
                'book' => $book,
                'amount_idr' => $amount,
                'accumulated_after_idr' => $newAccumulated,
                'ledger_transaction_id' => $transaction->id,
                'meta' => [
                    'cost_basis_idr' => $cost,
                    'salvage_value_idr' => $salvage,
                    'useful_life_years' => $life,
                    'period_units' => $periodUnits,
                    'total_units' => $totalUnits,
                    'simulated_tax_book' => $book === 'fiscal',
                ],
            ]);

            // Catat ke rantai hash lewat AssetService (urutan & prev_hash terkunci).
            $this->assets->recordEvent(
                asset: $locked,
                type: AssetEventType::Depreciation,
                payload: ['period' => $period, 'book' => $book, 'amount_idr' => $amount, 'method' => $method->value],
                actorName: $approvedByUserId !== null ? 'user#'.$approvedByUserId : 'system',
            );

            return ['amount_idr' => $amount, 'book_value_after_idr' => $newBookValue, 'record' => $record];
        });
    }

    /** @return array{accumulated: LedgerAccount, expense: LedgerAccount} */
    private function ensureAccounts(string $book): array
    {
        $accCode = $book === 'fiscal' ? self::FISCAL_ACCUMULATED : self::ACCUMULATED;
        $expCode = $book === 'fiscal' ? self::FISCAL_EXPENSE : self::EXPENSE;

        $accumulated = LedgerAccount::firstOrCreate(
            ['code' => $accCode, 'asset_code' => 'IDR'],
            [
                'uuid' => (string) Str::uuid(), 'name' => 'Akumulasi Penyusutan Aset',
                'kind' => AccountKind::CONTRA_ASSET->value, 'allow_negative' => true,
                'cached_balance' => '0', 'is_frozen' => false,
            ]
        );
        $expense = LedgerAccount::firstOrCreate(
            ['code' => $expCode, 'asset_code' => 'IDR'],
            [
                'uuid' => (string) Str::uuid(), 'name' => 'Beban Penyusutan Aset',
                'kind' => AccountKind::EXPENSE->value, 'allow_negative' => false,
                'cached_balance' => '0', 'is_frozen' => false,
            ]
        );

        return compact('accumulated', 'expense');
    }

    private function straightLine(int $depreciable, int $lifeYears): int
    {
        return (int) BigDecimal::of($depreciable)
            ->dividedBy($lifeYears * 12, 0, RoundingMode::HalfUp)
            ->__toString();
    }

    private function decliningBalance(int $bookValue, int $salvage, int $lifeYears): int
    {
        // Saldo menurun ganda (double-declining), laju 2/life per tahun dibagi bulanan.
        $annualRate = BigDecimal::of(2)->dividedBy($lifeYears, 12, RoundingMode::HalfUp);
        $monthlyRate = $annualRate->dividedBy(12, 12, RoundingMode::HalfUp);

        return (int) BigDecimal::of(max(0, $bookValue - $salvage))
            ->multipliedBy($monthlyRate)
            ->toScale(0, RoundingMode::HalfUp)
            ->__toString();
    }

    private function unitsOfProduction(int $depreciable, int $periodUnits, int $totalUnits): int
    {
        if ($periodUnits <= 0 || $totalUnits <= 0) {
            return 0;
        }

        return (int) BigDecimal::of($depreciable)
            ->multipliedBy($periodUnits)
            ->dividedBy($totalUnits, 0, RoundingMode::HalfUp)
            ->__toString();
    }
}
