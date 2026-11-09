<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Algorithms;

use InvalidArgumentException;

final class CriticalIncidentReplayEngine
{
    /**
     * Membangun urutan audit trail deterministik (deterministic replay) atas kejadian
     * henti jantung atau kegawatan darurat klinis (Code Blue / Sentinel Event).
     * Memverifikasi rantai hash peristiwa untuk mendeteksi pemalsuan urutan waktu (tampering).
     *
     * @param list<array{
     *     sequence: int,
     *     event_type: string,
     *     timestamp: string,
     *     actor_id: int,
     *     actor_role: string,
     *     payload: array<string, mixed>,
     *     prev_hash: string
     * }> $timeline
     * @return array{
     *     is_chain_valid: bool,
     *     total_events: int,
     *     first_cpr_interval_sec: ?int,
     *     first_defib_interval_sec: ?int,
     *     first_epinephrine_interval_sec: ?int,
     *     audit_status: string,
     *     corrupt_event_index: ?int,
     *     certificate: array{
     *         algorithm: string,
     *         version: string,
     *         final_merkle_root: string,
     *         signature: string
     *     }
     * }
     */
    public function replay(array $timeline): array
    {
        if (empty($timeline)) {
            throw new InvalidArgumentException("Timeline peristiwa kegawatan darurat tidak boleh kosong.");
        }

        $prevHash = 'GENESIS_CRITICAL_INCIDENT_HASH_2026';
        $corruptIndex = null;
        $isChainValid = true;

        $activationTs = null;
        $firstCprTs = null;
        $firstDefibTs = null;
        $firstEpiTs = null;

        foreach ($timeline as $idx => $event) {
            $expectedPrevHash = $prevHash;
            if (($event['prev_hash'] ?? '') !== $expectedPrevHash) {
                $isChainValid = false;
                $corruptIndex = $idx;
                break;
            }

            $serialized = json_encode([
                'sequence' => $event['sequence'] ?? $idx,
                'event_type' => $event['event_type'] ?? '',
                'timestamp' => $event['timestamp'] ?? '',
                'actor_id' => $event['actor_id'] ?? 0,
                'actor_role' => $event['actor_role'] ?? '',
                'payload' => $event['payload'] ?? [],
                'prev_hash' => $expectedPrevHash,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $prevHash = hash('sha256', $serialized);

            // Periksa interval waktu klinis
            $ts = strtotime((string) ($event['timestamp'] ?? 'now'));
            $type = (string) ($event['event_type'] ?? '');

            if ($type === 'CODE_BLUE_ACTIVATED' && $activationTs === null) {
                $activationTs = $ts;
            } elseif ($type === 'CPR_INITIATED' && $firstCprTs === null) {
                $firstCprTs = $ts;
            } elseif ($type === 'SHOCK_DELIVERED' && $firstDefibTs === null) {
                $firstDefibTs = $ts;
            } elseif ($type === 'EPINEPHRINE_GIVEN' && $firstEpiTs === null) {
                $firstEpiTs = $ts;
            }
        }

        $cprInterval = ($activationTs && $firstCprTs) ? max(0, $firstCprTs - $activationTs) : null;
        $defibInterval = ($activationTs && $firstDefibTs) ? max(0, $firstDefibTs - $activationTs) : null;
        $epiInterval = ($activationTs && $firstEpiTs) ? max(0, $firstEpiTs - $activationTs) : null;

        $auditStatus = $isChainValid ? 'VERIFIED_AUDIT_TRAIL' : 'CHAIN_TAMPERED_AT_SEQUENCE_' . $corruptIndex;

        $sigPayload = "CIRE-V1:{$prevHash}:{$auditStatus}:" . count($timeline);
        $signature = hash_hmac('sha256', $sigPayload, 'HSP_SECRET_INCIDENT_REPLAY_2026');

        return [
            'is_chain_valid' => $isChainValid,
            'total_events' => count($timeline),
            'first_cpr_interval_sec' => $cprInterval,
            'first_defib_interval_sec' => $defibInterval,
            'first_epinephrine_interval_sec' => $epiInterval,
            'audit_status' => $auditStatus,
            'corrupt_event_index' => $corruptIndex,
            'certificate' => [
                'algorithm' => 'CriticalIncidentReplayEngine',
                'version' => '1.0.0',
                'final_merkle_root' => $prevHash,
                'signature' => $signature,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @return bool
     */
    public function verifyCertificate(array $result): bool
    {
        $cert = $result['certificate'] ?? [];
        $root = $cert['final_merkle_root'] ?? '';
        $auditStatus = $result['audit_status'] ?? '';
        $total = $result['total_events'] ?? 0;

        $sigPayload = "CIRE-V1:{$root}:{$auditStatus}:{$total}";
        $expectedSig = hash_hmac('sha256', $sigPayload, 'HSP_SECRET_INCIDENT_REPLAY_2026');

        return hash_equals($expectedSig, (string) ($cert['signature'] ?? ''));
    }
}
