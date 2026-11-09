<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

/**
 * Verifier Sertifikat Graf Keterjangkauan Kelas A3 (W8-D04).
 */
final class ClassReachabilityCertificateVerifier
{
    private const SECRET = 'PlatformQualitySecretKey_A3';

    /**
     * @return array{is_valid: bool, reason: string}
     */
    public function verify(ClassReachabilityCertificate $cert): array
    {
        // 1. Verifikasi HMAC
        $payload = json_encode([
            'graph_hash' => $cert->graphHash,
            'nodes' => $cert->totalNodes,
            'edges' => $cert->totalEdges,
            'roots' => $cert->roots,
            'reachable' => $cert->reachable,
            'orphans' => $cert->orphans,
            'sccs' => $cert->stronglyConnectedComponents,
            'orphan_ratio' => $cert->orphanRatio,
            'issued_at' => $cert->issuedAt,
        ], JSON_THROW_ON_ERROR);

        $expectedSig = hash_hmac('sha256', (string) $payload, self::SECRET);
        if (! hash_equals($expectedSig, $cert->signature)) {
            return [
                'is_valid' => false,
                'reason' => 'Tanda tangan HMAC sertifikat graf keterjangkauan tidak cocok',
            ];
        }

        // 2. Verifikasi himpunan keterjangkauan
        $totalClasses = count($cert->reachable) + count($cert->orphans);
        if ($totalClasses !== $cert->totalNodes) {
            return [
                'is_valid' => false,
                'reason' => sprintf('Jumlah kelas reachable (%d) + orphan (%d) tidak sama dengan total nodes (%d)', count($cert->reachable), count($cert->orphans), $cert->totalNodes),
            ];
        }

        $expectedRatio = $cert->totalNodes > 0
            ? round(count($cert->orphans) / $cert->totalNodes, 4)
            : 0.0;

        if (abs($expectedRatio - $cert->orphanRatio) > 0.0001) {
            return [
                'is_valid' => false,
                'reason' => 'Rasio orphan tidak sesuai dengan perhitungan simpul graf',
            ];
        }

        return [
            'is_valid' => true,
            'reason' => 'Sertifikat keterjangkauan kelas A3 valid dan terverifikasi',
        ];
    }
}
