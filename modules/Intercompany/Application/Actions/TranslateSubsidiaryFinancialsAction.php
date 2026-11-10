<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Actions;

use InvalidArgumentException;
use Modules\Intercompany\Application\Services\IntercompanyLedgerPoster;
use Modules\Treasury\Contracts\ExchangeRateLookup;

class TranslateSubsidiaryFinancialsAction
{
    public function __construct(
        protected readonly ?ExchangeRateLookup $exchangeRateLookup = null,
        protected readonly ?IntercompanyLedgerPoster $ledgerPoster = null
    ) {}

    /**
     * @return array{
     *     subsidiary: string,
     *     period: string,
     *     closing_rate: float,
     *     average_rate: float,
     *     translated_net_assets_idr: int,
     *     translated_net_income_idr: int,
     *     fctr_difference_idr: int
     * }
     */
    public function execute(
        string $subsidiaryEntity,
        string $currency,
        int $foreignNetAssetsMinor,
        int $foreignNetIncomeMinor,
        string $period,
        ?int $userId = null
    ): array {
        if ($currency === 'IDR') {
            throw new InvalidArgumentException('Translasi FCTR hanya berlaku untuk entitas dengan mata uang fungsional selain IDR.');
        }

        // Ambil kurs dari modul Treasury bila terdaftar
        $closingRate = 15700.0;
        $averageRate = 15500.0;

        if ($this->exchangeRateLookup !== null) {
            $closing = $this->exchangeRateLookup->getExchangeRate($currency, 'IDR', "{$period}-28");
            if ($closing !== null) {
                $closingRate = $closing->rateNumerator / $closing->rateDenominator;
            }
        }

        $translatedAssets = (int) round(($foreignNetAssetsMinor / 100.0) * $closingRate);
        $translatedIncome = (int) round(($foreignNetIncomeMinor / 100.0) * $averageRate);
        $fctrDifference = (int) round(($foreignNetAssetsMinor / 100.0) * ($closingRate - $averageRate));

        if ($this->ledgerPoster !== null && $fctrDifference !== 0) {
            $this->ledgerPoster->postFctrTranslation(
                subsidiaryEntity: $subsidiaryEntity,
                translationDifferenceIdr: $fctrDifference,
                period: $period,
                idempotencyKey: "IC:FCTR:{$period}:{$subsidiaryEntity}",
                userId: $userId
            );
        }

        return [
            'subsidiary' => $subsidiaryEntity,
            'period' => $period,
            'closing_rate' => $closingRate,
            'average_rate' => $averageRate,
            'translated_net_assets_idr' => $translatedAssets,
            'translated_net_income_idr' => $translatedIncome,
            'fctr_difference_idr' => $fctrDifference,
        ];
    }
}
