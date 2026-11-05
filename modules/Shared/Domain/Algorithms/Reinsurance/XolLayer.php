<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Reinsurance;

use InvalidArgumentException;

/**
 * Satu layer excess-of-loss: menanggung bagian kerugian di atas $attachmentIdr sampai $limitIdr.
 * Reinstatement: tiap elemen $reinstatementRatesBps adalah tarif (bps dari premi layer) untuk
 * memulihkan satu kali limit ("pro rata as to amount", tanpa komponen waktu).
 */
final readonly class XolLayer
{
    /**
     * @param  list<int>  $reinstatementRatesBps
     */
    public function __construct(
        public string $code,
        public int $attachmentIdr,
        public int $limitIdr,
        public array $reinstatementRatesBps = [],
        public int $layerPremiumIdr = 0,
    ) {
        if ($code === '' || $attachmentIdr < 0 || $limitIdr <= 0 || $layerPremiumIdr < 0) {
            throw new InvalidArgumentException("Layer XoL '{$code}' tidak valid.");
        }

        foreach ($reinstatementRatesBps as $rate) {
            if ($rate < 0 || $rate > 10000) {
                throw new InvalidArgumentException('Tarif reinstatement harus 0–10000 bps.');
            }
        }
    }

    public function exhaustionIdr(): int
    {
        return $this->attachmentIdr + $this->limitIdr;
    }

    /** Limit agregat tahunan = limit × (1 + jumlah reinstatement). */
    public function aggregateLimitIdr(): int
    {
        return $this->limitIdr * (1 + count($this->reinstatementRatesBps));
    }
}
