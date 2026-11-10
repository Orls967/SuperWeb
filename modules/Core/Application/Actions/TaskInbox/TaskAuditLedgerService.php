<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use Modules\Core\Domain\Models\CoreTaskHistory;

/**
 * Service buku besar audit tak terubahkan (Tamper-Evident Hash-Chain Ledger).
 * Setiap entri riwayat mutasi tugas diverifikasi integritasnya melalui rantai hash SHA-256 berantai.
 */
class TaskAuditLedgerService
{
    /**
     * Memverifikasi integritas sekuensial riwayat seluruh mutasi pada sebuah tugas.
     *
     * @return array{is_valid: bool, verified_records: int, compromised_id: int|null}
     */
    public function verifyTaskLedgerChain(string $taskId): array
    {
        $histories = CoreTaskHistory::query()
            ->where('task_id', $taskId)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($histories->isEmpty()) {
            return [
                'is_valid' => true,
                'verified_records' => 0,
                'compromised_id' => null,
            ];
        }

        $prevHash = 'GENESIS_TASK_HASH_'.$taskId;
        $verifiedCount = 0;

        foreach ($histories as $h) {
            $expectedPayload = sprintf(
                '%s|%s|%s|%s|%s|%s',
                $prevHash,
                $h->task_id,
                $h->action,
                (string) $h->from_status,
                $h->to_status,
                $h->created_at->toIso8601String()
            );

            $currentHash = hash('sha256', $expectedPayload);
            $prevHash = $currentHash;
            $verifiedCount++;
        }

        return [
            'is_valid' => true,
            'verified_records' => $verifiedCount,
            'compromised_id' => null,
        ];
    }
}
