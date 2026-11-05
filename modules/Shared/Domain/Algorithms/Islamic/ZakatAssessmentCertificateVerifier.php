<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

/**
 * Verifikasi sertifikat zakat mal (PR2):
 *  1. Nisab = 85 x harga emas
 *  2. Basis = max(0, harta - utang)
 *  3. Zakat = 2.5% jika basis >= nisab & haul terpenuhi, selain itu 0.
 */
final class ZakatAssessmentCertificateVerifier
{
    public function verify(ZakatAssessmentResult $result): true
    {
        $expectedNisab = 85 * $result->goldPricePerGramIdr;
        if ($result->nisabIdr !== $expectedNisab) {
            throw new InvalidCertificateException("Nisab ({$result->nisabIdr}) tidak sama dengan 85 x harga emas ({$expectedNisab})");
        }

        $expectedBase = max(0, $result->qualifyingWealthIdr - $result->dueDebtsIdr);
        if ($result->netZakatBaseIdr !== $expectedBase) {
            throw new InvalidCertificateException('Basis zakat tidak sesuai dengan formula harta - utang');
        }

        $expectedObligated = ($result->netZakatBaseIdr >= $result->nisabIdr) && ($result->holdingDays >= 354);
        if ($result->isObligated !== $expectedObligated) {
            throw new InvalidCertificateException('Status kewajiban zakat tidak selaras dengan syarat nisab dan haul');
        }

        $expectedZakat = 0;
        if ($expectedObligated) {
            $expectedZakat = BigDecimal::of($result->netZakatBaseIdr)
                ->multipliedBy(250)
                ->dividedBy(10000, 0, RoundingMode::HalfUp)
                ->toInt();
        }

        if ($result->zakatAmountIdr !== $expectedZakat) {
            throw new InvalidCertificateException("Nominal zakat ({$result->zakatAmountIdr}) tidak sama dengan 2.5% HalfUp ({$expectedZakat})");
        }

        return true;
    }
}
