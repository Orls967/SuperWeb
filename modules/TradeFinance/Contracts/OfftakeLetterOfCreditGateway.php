<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Contracts;

use Carbon\CarbonImmutable;

/**
 * Kontrak Integrasi Lintas Modul: Mining -> TradeFinance (G7-K11).
 * Menghubungkan pembukaan Letter of Credit (L/C) untuk pengapalan offtake komoditas.
 */
interface OfftakeLetterOfCreditGateway
{
    /**
     * Terbitkan atau daftarkan Letter of Credit baru untuk kontrak offtake.
     *
     * @return int ID referensi L/C yang diterbitkan
     */
    public function issue(
        int $applicantPartyId,
        int $beneficiaryPartyId,
        int $amountMinor,
        string $currency,
        CarbonImmutable $expiresAt,
        string $idempotencyKey
    ): int;

    /**
     * Periksa status keabsahan dokumen L/C.
     *
     * @return array{status: string, is_discrepant: bool, discrepancies: list<string>}
     */
    public function verifyDocuments(int $lcId, array $documentHashes): array;
}
