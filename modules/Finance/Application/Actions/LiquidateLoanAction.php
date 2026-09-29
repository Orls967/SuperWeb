<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Actions;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Exception;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Crypto\Contracts\PriceFeed;
use Modules\Finance\Domain\Enums\LoanStatus;
use Modules\Finance\Domain\Models\Loan;
use Modules\Shared\Application\BaseAction;

/**
 * LTV melewati ambang likuidasi: jual kolateral secukupnya di exchange,
 * lunasi sisa pokok, sisa hasil jual dan kolateral dikembalikan ke peminjam.
 * Seluruhnya dalam satu transaksi ledger.
 */
class LiquidateLoanAction extends BaseAction
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly PriceFeed $priceFeed,
    ) {}

    public function execute(Loan $loan, string $reason = 'LTV melewati ambang likuidasi'): Loan
    {
        if (! $loan->status->isOpen()) {
            throw new Exception("Pinjaman berstatus {$loan->status->label()} tidak dapat dilikuidasi.");
        }

        $loan->loadMissing(['user', 'collateralAsset']);
        $asset = $loan->collateralAsset;
        $symbol = $asset->symbol;
        $decimals = (int) $asset->decimals;
        $price = $this->priceFeed->currentPrice($symbol);

        if ($price->isZero()) {
            throw new Exception("Harga {$symbol} tidak tersedia untuk likuidasi.");
        }

        $collateralQty = $loan->collateralQty();
        $outstanding = (int) $loan->outstanding_principal;

        // Jual secukupnya untuk menutup sisa pokok, dibulatkan ke atas
        $neededQty = BigDecimal::of((string) $outstanding)->dividedBy($price, $decimals, RoundingMode::Up);
        $soldQty = $neededQty->isGreaterThan($collateralQty) ? $collateralQty : $neededQty;
        $remainingQty = $collateralQty->minus($soldQty);

        // Hasil penjualan dibulatkan ke bawah ke rupiah utuh
        $proceeds = $soldQty->multipliedBy($price)->toScale(0, RoundingMode::Down);
        $proceedsInt = (int) $proceeds->__toString();
        $repaid = min($outstanding, $proceedsInt);
        $surplus = $proceedsInt - $repaid;

        return $this->transaction(function () use (
            $loan,
            $symbol,
            $soldQty,
            $remainingQty,
            $proceeds,
            $repaid,
            $surplus,
            $price,
            $reason
        ) {
            $user = $loan->user;
            $cryptoAccount = $user->walletAccount($symbol);
            $idrAccount = $user->walletAccount('IDR');

            $entries = [];

            if ($soldQty->isPositive()) {
                // Kolateral dijual ke likuiditas exchange
                $entries[] = PostingEntryDTO::forCode("collateral:crypto:{$symbol}", $symbol, $soldQty->negated());
                $entries[] = PostingEntryDTO::forCode("exchange:{$symbol}", $symbol, $soldQty);

                // Rupiah keluar dari exchange: menutup pokok, sisanya ke peminjam
                $entries[] = PostingEntryDTO::forCode('exchange:IDR', 'IDR', $proceeds->negated());

                if ($repaid > 0) {
                    $entries[] = PostingEntryDTO::forCode('loan_receivable:IDR', 'IDR', (string) $repaid);
                }

                if ($surplus > 0) {
                    $entries[] = PostingEntryDTO::forAccount($idrAccount->id, 'IDR', (string) $surplus);
                }
            }

            if ($remainingQty->isPositive()) {
                // Kolateral sisa kembali ke dompet kripto peminjam
                $entries[] = PostingEntryDTO::forCode("collateral:crypto:{$symbol}", $symbol, $remainingQty->negated());
                $entries[] = PostingEntryDTO::forAccount($cryptoAccount->id, $symbol, $remainingQty);
            }

            if ($entries !== []) {
                $this->ledger->post(new PostingDTO(
                    type: TransactionType::LIQUIDATION->value,
                    description: "Likuidasi kolateral {$symbol} pembiayaan {$loan->uuid}: {$reason}",
                    idempotencyKey: 'loan_liquidation_'.$loan->uuid,
                    entries: $entries,
                    referenceType: 'fin_loan',
                    referenceId: $loan->id,
                    meta: [
                        'loan_id' => $loan->id,
                        'sold_qty' => $soldQty->__toString(),
                        'returned_qty' => $remainingQty->__toString(),
                        'price_idr' => $price->__toString(),
                        'repaid_principal' => $repaid,
                        'surplus_to_user' => $surplus,
                        'reason' => $reason,
                    ],
                    createdBy: $loan->user_id,
                    postedAt: now(),
                ));
            }

            $loan->outstanding_principal = max(0, $loan->outstanding_principal - $repaid);
            $loan->collateral_qty = BigDecimal::zero()->__toString();
            $loan->closed_at = now();
            $loan->save();

            // Cicilan yang belum terbayar tidak ditandai lunas: pinjaman ditutup oleh
            // likuidasi, dan scheduler hanya memproses pinjaman berstatus terbuka.
            $loan->transitionTo(
                $loan->outstanding_principal > 0 ? LoanStatus::Defaulted : LoanStatus::Liquidated
            );

            return $loan->fresh(['installments', 'collateralAsset']);
        });
    }
}
