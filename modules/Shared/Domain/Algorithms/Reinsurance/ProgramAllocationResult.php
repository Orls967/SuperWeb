<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Reinsurance;

/**
 * Hasil alokasi program reasuransi atas satu kerugian, beserta sertifikat untuk verifier terpisah.
 */
final readonly class ProgramAllocationResult
{
    /**
     * @param  array<string, int>  $layerCededIdr  kode layer => ceded
     * @param  array<string, int>  $reinstatementPremiumIdr  kode layer => premi reinstatement
     * @param  array<string, int>  $layerUsedBeforeIdr  kode layer => pemakaian agregat sebelum kerugian ini
     * @param  list<XolLayer>  $layers
     */
    public function __construct(
        public int $grossIdr,
        public int $quotaShareBps,
        public int $quotaShareCededIdr,
        public int $netAfterQuotaShareIdr,
        public array $layerCededIdr,
        public array $reinstatementPremiumIdr,
        public array $layerUsedBeforeIdr,
        public int $retainedIdr,
        public array $layers,
    ) {}

    public function totalCededIdr(): int
    {
        return $this->quotaShareCededIdr + array_sum($this->layerCededIdr);
    }

    public function totalReinstatementPremiumIdr(): int
    {
        return array_sum($this->reinstatementPremiumIdr);
    }
}
