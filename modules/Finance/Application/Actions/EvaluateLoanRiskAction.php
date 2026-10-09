<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Crypto\Contracts\PriceFeed;
use Modules\Finance\Domain\Enums\LoanStatus;
use Modules\Finance\Domain\Models\Loan;

/**
 * Pantau rasio LTV setiap pinjaman terbuka: naikkan ke margin call,
 * likuidasi otomatis, atau pulihkan ke aktif bila harga kolateral membaik.
 *
 * @phpstan-type RiskOutcome 'healthy'|'margin_call'|'recovered'|'liquidated'
 */
class EvaluateLoanRiskAction
{
    public function __construct(
        private readonly PriceFeed $priceFeed,
        private readonly LiquidateLoanAction $liquidateLoan,
    ) {}

    /**
     * Evaluasi seluruh pinjaman terbuka.
     *
     * @return array<int, array{loan_id: int, ltv: float, outcome: string}>
     */
    public function evaluateAll(): array
    {
        $loans = Loan::whereIn('status', [LoanStatus::Active->value, LoanStatus::MarginCall->value])
            ->with(['collateralAsset', 'user'])
            ->get();

        $results = [];

        foreach ($loans as $loan) {
            $results[] = $this->evaluate($loan);
        }

        return $results;
    }

    /**
     * @return array{loan_id: int, ltv: float, outcome: string}
     */
    public function evaluate(Loan $loan): array
    {
        $loan->loadMissing(['collateralAsset', 'user']);
        $price = $this->priceFeed->currentPrice($loan->collateralAsset->symbol);
        $ltv = $loan->currentLtv($price);

        if ($loan->outstanding_principal <= 0) {
            return ['loan_id' => $loan->id, 'ltv' => $ltv, 'outcome' => 'healthy'];
        }

        if ($ltv >= Loan::LIQUIDATION_LTV) {
            $this->liquidateLoan->execute(
                $loan,
                sprintf('LTV %.1f%% melewati ambang likuidasi %d%%', $ltv * 100, (int) (Loan::LIQUIDATION_LTV * 100))
            );

            return ['loan_id' => $loan->id, 'ltv' => $ltv, 'outcome' => 'liquidated'];
        }

        if ($ltv >= Loan::MARGIN_CALL_LTV) {
            if ($loan->status !== LoanStatus::MarginCall) {
                DB::transaction(function () use ($loan): void {
                    $loan->margin_called_at = now();
                    $loan->save();
                    $loan->transitionTo(LoanStatus::MarginCall);
                });
            }

            return ['loan_id' => $loan->id, 'ltv' => $ltv, 'outcome' => 'margin_call'];
        }

        if ($loan->status === LoanStatus::MarginCall) {
            DB::transaction(function () use ($loan): void {
                $loan->margin_called_at = null;
                $loan->save();
                $loan->transitionTo(LoanStatus::Active);
            });

            return ['loan_id' => $loan->id, 'ltv' => $ltv, 'outcome' => 'recovered'];
        }

        return ['loan_id' => $loan->id, 'ltv' => $ltv, 'outcome' => 'healthy'];
    }
}
