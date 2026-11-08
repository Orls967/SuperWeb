<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use DomainException;
use Modules\Marine\Domain\Models\AquacultureExportLot;

/**
 * Service pemformat payload QR Code & label karton ekspor rantai dingin (Fase 171).
 */
class AquacultureTraceabilityQrPayloadFormatter
{
    /**
     * Hasilkan payload QR Code terkompresi dan terotentikasi secara digital untuk stiker karton ekspor.
     *
     * @return array{
     *     qr_payload_version: string,
     *     lot_tracking_number: string,
     *     canonical_qr_data: string,
     *     base64_url_safe_token: string,
     *     sha256_authenticity_hash: string,
     *     direct_verification_url: string
     * }
     */
    public function formatCartonQrPayload(int $exportLotId, string $baseDomain = 'https://trace.samudera.id'): array
    {
        $lot = AquacultureExportLot::with(['harvestOperation.batch.hatchery', 'traceabilityNodes'])->findOrFail($exportLotId);

        $firstNode = $lot->traceabilityNodes()->first();
        $merkleRoot = $firstNode ? $firstNode->merkle_root_hash : 'UNANCHORED';

        $rawPayload = [
            'ver' => '2.0-ASC',
            'lot' => $lot->lot_tracking_number,
            'spec' => $lot->harvestOperation?->batch?->species_scientific_name ?? 'Litopenaeus vannamei',
            'form' => $lot->product_form,
            'grade' => $lot->commercial_grade,
            'root' => $merkleRoot,
            'net_kg' => (float) $lot->net_lot_weight_kg,
            'ts' => now()->timestamp,
        ];

        $canonicalJson = json_encode($rawPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($canonicalJson === false) {
            throw new DomainException('Gagal melakukan serialisasi JSON canonical QR payload.');
        }

        $base64Token = rtrim(strtr(base64_encode($canonicalJson), '+/', '-_'), '=');
        $authenticityHash = hash('sha256', $canonicalJson);
        $verificationUrl = rtrim($baseDomain, '/').'/scan/'.$base64Token;

        return [
            'qr_payload_version' => '2.0-ASC',
            'lot_tracking_number' => $lot->lot_tracking_number,
            'canonical_qr_data' => $canonicalJson,
            'base64_url_safe_token' => $base64Token,
            'sha256_authenticity_hash' => $authenticityHash,
            'direct_verification_url' => $verificationUrl,
        ];
    }

    /**
     * Dekode dan validasi payload QR token dari scanner konsumen / auditor.
     *
     * @return array<string, mixed>
     */
    public function decodeAndVerifyToken(string $base64Token, string $expectedLotNumber): array
    {
        $padded = strtr($base64Token, '-_', '+/');
        $decodedJson = base64_decode($padded, true);

        if ($decodedJson === false) {
            throw new DomainException('Token QR base64 tidak valid.');
        }

        $payload = json_decode($decodedJson, true);
        if (! is_array($payload) || ($payload['lot'] ?? '') !== $expectedLotNumber) {
            throw new DomainException('Data QR payload tidak cocok dengan nomor lot yang diuji.');
        }

        return $payload;
    }
}
