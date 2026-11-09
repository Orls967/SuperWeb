<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Carbon\Carbon;

/**
 * Telemedicine Clinical Action & Audit Trail Logger.
 * Memelihara jejak audit klinis tak terhapuskan (tamper-evident immutable log):
 * 1. Pencatatan setiap aksi klinis telemedis: pembacaan EMR, pengabaian alert CDSE dengan justifikasi DPJP.
 * 2. Hash-chaining berurutan pada setiap entri audit trail.
 * 3. Verifikasi integritas log untuk inspeksi komite medis rumah sakit dan audit eksternal Kemenkes.
 */
class TelemedicineClinicalAuditLogger
{
    /**
     * @var array<int, array{
     *     entry_id: string,
     *     actor_id: string,
     *     actor_role: string,
     *     action_type: string,
     *     entity_type: string,
     *     entity_id: string,
     *     justification: string|null,
     *     timestamp: string,
     *     prev_hash: string,
     *     entry_hash: string
     * }>
     */
    private static array $auditLogBuffer = [];

    /**
     * Mencatat aksi klinis ke dalam audit trail.
     */
    public function logAction(
        string $actorId,
        string $actorRole,
        string $actionType,
        string $entityType,
        string $entityId,
        ?string $justification = null
    ): array {
        $timestamp = Carbon::now()->toIso8601String();
        $prevHash = ! empty(self::$auditLogBuffer)
            ? self::$auditLogBuffer[count(self::$auditLogBuffer) - 1]['entry_hash']
            : 'GENESIS-HSP-AUDIT-2026';

        $entryId = 'AUD-'.strtoupper(bin2hex(random_bytes(4)));

        $entryHash = hash('sha256', implode('|', [
            $entryId,
            $actorId,
            $actorRole,
            $actionType,
            $entityType,
            $entityId,
            (string) $justification,
            $timestamp,
            $prevHash,
        ]));

        $entry = [
            'entry_id' => $entryId,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'justification' => $justification,
            'timestamp' => $timestamp,
            'prev_hash' => $prevHash,
            'entry_hash' => $entryHash,
        ];

        self::$auditLogBuffer[] = $entry;

        return $entry;
    }

    /**
     * Memverifikasi keutuhan rantai hash buffer audit trail.
     */
    public function verifyChainIntegrity(): bool
    {
        $count = count(self::$auditLogBuffer);
        if ($count === 0) {
            return true;
        }

        $expectedPrev = 'GENESIS-HSP-AUDIT-2026';

        for ($i = 0; $i < $count; $i++) {
            $e = self::$auditLogBuffer[$i];

            if ($e['prev_hash'] !== $expectedPrev) {
                return false;
            }

            $calculated = hash('sha256', implode('|', [
                $e['entry_id'],
                $e['actor_id'],
                $e['actor_role'],
                $e['action_type'],
                $e['entity_type'],
                $e['entity_id'],
                (string) $e['justification'],
                $e['timestamp'],
                $e['prev_hash'],
            ]));

            if (! hash_equals($e['entry_hash'], $calculated)) {
                return false;
            }

            $expectedPrev = $e['entry_hash'];
        }

        return true;
    }

    /**
     * Mengambil seluruh buffer log yang terekam.
     */
    public function getLogs(): array
    {
        return self::$auditLogBuffer;
    }
}
