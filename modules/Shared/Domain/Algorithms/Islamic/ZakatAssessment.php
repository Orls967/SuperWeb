<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * B2 Islamic\ZakatAssessment
 *
 * Menghitung zakat mal berbasis nisab emas 85 gram dan haul kalender hijriah (354 hari).
 * Nisab = 85 gram x harga emas/gram.
 * Basis = Harta kena zakat akhir haul - utang jatuh tempo.
 * Zakat = 2.5% (250 bps) dari basis (HalfUp) bila basis >= nisab dan haul terpenuhi.
 *
 * Kompleksitas: O(1).
 */
final class ZakatAssessment
{
    public const NISAB_GOLD_GRAMS = 85;

    public const HIJRIAH_HAUL_DAYS = 354;

    public const ZAKAT_RATE_BPS = 250; // 2.50%

    /**
     * @param  int  $goldPricePerGramIdr  Harga emas per gram (IDR)
     * @param  int  $qualifyingWealthIdr  Harta kena zakat akhir haul (IDR)
     * @param  int  $dueDebtsIdr  Utang jatuh tempo pengurang basis (IDR)
     * @param  int  $holdingDays  Lama kepemilikan harta dalam hari
     */
    public function assess(
        int $goldPricePerGramIdr,
        int $qualifyingWealthIdr,
        int $dueDebtsIdr = 0,
        int $holdingDays = self::HIJRIAH_HAUL_DAYS
    ): ZakatAssessmentResult {
        if ($goldPricePerGramIdr <= 0) {
            throw new InvalidArgumentException("Harga emas harus bernilai positif: {$goldPricePerGramIdr}");
        }
        if ($qualifyingWealthIdr < 0 || $dueDebtsIdr < 0) {
            throw new InvalidArgumentException('Harta dan utang tidak boleh bernilai negatif');
        }
        if ($holdingDays < 0) {
            throw new InvalidArgumentException("Hari kepemilikan tidak boleh negatif: {$holdingDays}");
        }

        $nisabIdr = self::NISAB_GOLD_GRAMS * $goldPricePerGramIdr;
        $netZakatBaseIdr = max(0, $qualifyingWealthIdr - $dueDebtsIdr);
        $haulFulfilled = ($holdingDays >= self::HIJRIAH_HAUL_DAYS);
        $reachesNisab = ($netZakatBaseIdr >= $nisabIdr);

        $zakatAmountIdr = 0;
        if ($reachesNisab && $haulFulfilled) {
            // 2.5% = basis * 250 / 10000 dengan pembulatan HalfUp
            $zakatAmountIdr = BigDecimal::of($netZakatBaseIdr)
                ->multipliedBy(self::ZAKAT_RATE_BPS)
                ->dividedBy(10000, 0, RoundingMode::HalfUp)
                ->toInt();
        }

        $result = new ZakatAssessmentResult(
            goldPricePerGramIdr: $goldPricePerGramIdr,
            nisabIdr: $nisabIdr,
            qualifyingWealthIdr: $qualifyingWealthIdr,
            dueDebtsIdr: $dueDebtsIdr,
            netZakatBaseIdr: $netZakatBaseIdr,
            holdingDays: $holdingDays,
            haulFulfilled: $haulFulfilled,
            reachesNisab: $reachesNisab,
            isObligated: $reachesNisab && $haulFulfilled,
            zakatAmountIdr: $zakatAmountIdr
        );

        (new ZakatAssessmentCertificateVerifier)->verify($result);

        return $result;
    }
}
