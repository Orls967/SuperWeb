<?php

declare(strict_types=1);

namespace Modules\Logistics\Contracts;

/**
 * K6 (KATALOG KONTRAK G6) — verifikasi hash dokumen pengiriman terhadap rantai custody.
 */
interface CustodyVerifier
{
    /**
     * @param  string  $documentType  'BL' atau 'POD'
     * @param  string  $sha256Hex  hash SHA-256 dokumen (hex 64 karakter)
     * @return array{shipment_id:int, custody_event_id:int, chain_valid:bool}|null null bila hash tidak tercatat
     */
    public function verifyDocumentHash(string $documentType, string $sha256Hex): ?array;
}
