<?php

declare(strict_types=1);

namespace Modules\Marine\Contracts;

/**
 * G7-K28: VerifiedHarvestReader.
 *
 * Kontrak pembacaan hasil panen akuakultur yang terverifikasi mutu laboratorium
 * dan neraca massanya untuk modul logistik rantai pasok ekspor seafood, ESG, dan escrow.
 */
interface VerifiedHarvestReader
{
    /**
     * Dapatkan detail panen terverifikasi berdasarkan nomor lot ekspor.
     *
     * @return array{
     *     lot_number: string,
     *     species: string,
     *     harvest_date: string,
     *     pond_identifier: string,
     *     net_weight_kg: float,
     *     merkle_root_hash: string,
     *     is_assay_passed: bool,
     *     health_certificate_grade: string,
     *     origin_hatchery: string,
     *     fcr_achieved: float
     * }|null
     */
    public function getVerifiedHarvestLot(string $lotNumber): ?array;

    /**
     * Periksa apakah lot ekspor memenuhi kriteria pelepasan escrow petani / nelayan tambak.
     */
    public function isEligibleForEscrowRelease(string $lotNumber): bool;
}
