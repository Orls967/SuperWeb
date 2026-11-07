<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

/**
 * Data Nilai Sertifikat Hasil Perutean Folio Split.
 */
final class FolioSplitCertificate
{
    /**
     * @param  array<int, array{line_id: string, category: string, amount_idr: int, payer: string}>  $allocations
     */
    public function __construct(
        public readonly string $folioId,
        public readonly int $totalAmountIdr,
        public readonly int $corporateAmountIdr,
        public readonly int $personalAmountIdr,
        public readonly array $allocations,
        public readonly string $checksum,
    ) {}
}
