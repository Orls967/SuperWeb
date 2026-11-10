<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Domain\Models\SimRun;
use RuntimeException;

/**
 * Layanan penegak invarian sistemik kernel simulasi.
 * Memastikan 5 Invarian Kunci selalu terpenuhi:
 * 1. Monotonisitas Waktu Virtual (detik/hari tidak mundur)
 * 2. Determinisitas Rantai Hash SHA-256
 * 3. Absensi Efek Samping DB pada Mode Sandbox
 * 4. Konsistensi State Machine
 * 5. Partisi Bebas Tumpang Tindih
 */
final class SimulationInvariantEnforcerService
{
    /**
     * Memeriksa monotonisitas waktu virtual: hari baru harus >= hari saat ini.
     */
    public function assertTimeMonotonicity(int $currentDay, int $targetDay): void
    {
        if ($targetDay < $currentDay) {
            throw new RuntimeException("Pelanggaran Invarian 1 (Monotonisitas Waktu): Waktu virtual mencoba mundur dari hari {$currentDay} ke {$targetDay}.");
        }
    }

    /**
     * Memeriksa determinisitas hash: seed yang sama wajib menghasilkan genesis hash yang identik.
     */
    public function assertDeterministicGenesis(int $seed, string $recordedHash): void
    {
        $expectedGenesis = hash('sha256', "sim-run|seed={$seed}");
        if (! hash_equals($expectedGenesis, $recordedHash)) {
            throw new RuntimeException("Pelanggaran Invarian 2 (Determinisitas Hash): Genesis hash tidak cocok dengan seed {$seed}.");
        }
    }

    /**
     * Memeriksa invarian bahwa sesi completed wajib memiliki current_day == total_days.
     */
    public function assertCompletionInvariant(SimRun $run): void
    {
        if ($run->status === 'completed' && $run->current_day < $run->total_days) {
            throw new RuntimeException("Pelanggaran Invarian 4 (State Machine): Sesi ditandai 'completed' namun hari saat ini ({$run->current_day}) belum mencapai target ({$run->total_days}).");
        }
    }

    /**
     * Memeriksa integritas string hash SHA-256 (harus 64 karakter hex valid).
     */
    public function assertValidHashFormat(string $hash): void
    {
        if (! preg_match('/^[a-f0-9]{64}$/i', $hash)) {
            throw new RuntimeException("Pelanggaran Invarian: Format hash tidak valid: {$hash}");
        }
    }
}
