<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use InvalidArgumentException;
use Modules\Treasury\Domain\Models\Revaluation;

class RunMultiCurrencyFxRevaluationAction
{
    /**
     * Run multi-currency foreign exchange revaluation.
     * Exchange rate is scaled by 10,000 (e.g. 15,500 IDR/USD = 155,000,000 scaled).
     *
     * @return array{revaluation: Revaluation, gain_loss_idr: int, status: string}
     */
    public function execute(
        string $period,
        string $currency,
        int $foreignBalanceMinor,
        int $bookFunctionalIdr,
        int $spotRateScaled
    ): array {
        if ($foreignBalanceMinor < 0 || $bookFunctionalIdr < 0 || $spotRateScaled <= 0) {
            throw new InvalidArgumentException('Saldo valas, saldo buku IDR, dan kurs spot harus bernilai positif.');
        }

        // e.g. 1000 USD (100,000 cents) * 15,500 IDR/USD (155,000,000 scaled by 10000)
        // revalued = (foreignMinor * spotRateScaled) / (100 * 10000)
        $revaluedFunctionalIdr = (int) intdiv($foreignBalanceMinor * $spotRateScaled, 1000000);
        $gainLossIdr = $revaluedFunctionalIdr - $bookFunctionalIdr;

        $reval = Revaluation::create([
            'period' => $period,
            'currency' => strtoupper($currency),
            'foreign_balance' => $foreignBalanceMinor,
            'book_functional_idr' => $bookFunctionalIdr,
            'revalued_functional_idr' => $revaluedFunctionalIdr,
            'gain_loss_idr' => $gainLossIdr,
            'status' => 'posted',
        ]);

        return [
            'revaluation' => $reval,
            'gain_loss_idr' => $gainLossIdr,
            'status' => 'posted',
        ];
    }
}
