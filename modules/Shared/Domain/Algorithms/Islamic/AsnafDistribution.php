<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

use InvalidArgumentException;
use Modules\Shared\Domain\Algorithms\Apportionment\LargestRemainder;

/**
 * B3 Islamic\AsnafDistribution
 *
 * Menghitung pembagian dana zakat ke 8 asnaf sesuai ketentuan syariah:
 *  - Fakir, Miskin, Amil, Mualaf, Riqab, Gharimin, Fisabilillah, Ibnu Sabil
 *  - Amil dibatasi maksimal 1250 bps (12.50% / seperdelapan)
 *  - Total bobot = 10000 bps
 *  - Alokasi integer presisi tanpa sisa pembulatan menggunakan LargestRemainder (Hamilton).
 *
 * Kompleksitas: O(K log K) di mana K = 8 asnaf.
 */
final class AsnafDistribution
{
    public const MAX_AMIL_BPS = 1250;

    public const TOTAL_BPS = 10000;

    public const ASNAF_FAKIR = 'fakir';

    public const ASNAF_MISKIN = 'miskin';

    public const ASNAF_AMIL = 'amil';

    public const ASNAF_MUALAF = 'mualaf';

    public const ASNAF_RIQAB = 'riqab';

    public const ASNAF_GHARIMIN = 'gharimin';

    public const ASNAF_FISABILILLAH = 'fisabilillah';

    public const ASNAF_IBNU_SABIL = 'ibnu_sabil';

    public function __construct(
        private readonly LargestRemainder $largestRemainder = new LargestRemainder
    ) {}

    /**
     * @param  int  $totalZakatFundIdr  Total dana zakat terkumpul untuk disalurkan (IDR)
     * @param  array<string, int>  $weightsBps  Peta asnaf => bobot bps (total wajib 10.000 bps)
     */
    public function distribute(int $totalZakatFundIdr, array $weightsBps): AsnafDistributionResult
    {
        if ($totalZakatFundIdr < 0) {
            throw new InvalidArgumentException("Total dana zakat tidak boleh negatif: {$totalZakatFundIdr}");
        }

        $expectedAsnaf = [
            self::ASNAF_FAKIR,
            self::ASNAF_MISKIN,
            self::ASNAF_AMIL,
            self::ASNAF_MUALAF,
            self::ASNAF_RIQAB,
            self::ASNAF_GHARIMIN,
            self::ASNAF_FISABILILLAH,
            self::ASNAF_IBNU_SABIL,
        ];

        $sumWeight = 0;
        $items = [];
        foreach ($expectedAsnaf as $asnaf) {
            $weight = $weightsBps[$asnaf] ?? 0;
            if ($weight < 0) {
                throw new InvalidArgumentException("Bobot asnaf {$asnaf} tidak boleh negatif: {$weight}");
            }
            if ($asnaf === self::ASNAF_AMIL && $weight > self::MAX_AMIL_BPS) {
                throw new InvalidArgumentException("Hak amil ({$weight} bps) melebihi batas syariah maksimal 1250 bps");
            }
            $sumWeight += $weight;
            $items[] = [
                'key' => $asnaf,
                'weight' => $weight,
            ];
        }

        if ($sumWeight !== self::TOTAL_BPS) {
            throw new InvalidArgumentException("Total bobot asnaf ({$sumWeight} bps) harus tepat 10000 bps");
        }

        $allocations = $this->largestRemainder->apportion($totalZakatFundIdr, $items);

        $result = new AsnafDistributionResult(
            totalZakatFundIdr: $totalZakatFundIdr,
            weightsBps: $weightsBps,
            allocations: $allocations
        );

        (new AsnafDistributionCertificateVerifier)->verify($result);

        return $result;
    }
}
