<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\StateMachines;

use InvalidArgumentException;

/**
 * State Machine Mode Operasi Microgrid Kawasan.
 * Mengatur transisi aman antara Grid-Connected, Islanded, Blackout, dan Restoring.
 */
final class MicrogridOperatingModeStateMachine
{
    public const MODE_GRID_CONNECTED = 'grid_connected';

    public const MODE_ISLANDED = 'islanded';

    public const MODE_BLACKOUT = 'blackout';

    public const MODE_RESTORING = 'restoring';

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        self::MODE_GRID_CONNECTED => [
            self::MODE_ISLANDED,    // Transisi terencana / gangguan grid luar
            self::MODE_BLACKOUT,    // Pemadaman mendadak tanpa waktu shedding
        ],
        self::MODE_ISLANDED => [
            self::MODE_GRID_CONNECTED, // Resinkronisasi ke jaringan utama PLN
            self::MODE_BLACKOUT,       // Kelebihan beban / kegagalan genset pulau
        ],
        self::MODE_BLACKOUT => [
            self::MODE_RESTORING,      // Memulai prosedur black-start mandiri
        ],
        self::MODE_RESTORING => [
            self::MODE_ISLANDED,       // Stabil sebagai pulau mandiri setelah black-start
            self::MODE_GRID_CONNECTED, // Langsung sinkron ke grid nasional jika pulih
            self::MODE_BLACKOUT,       // Kegagalan energisasi / trip proteksi
        ],
    ];

    public function canTransition(string $currentMode, string $targetMode): bool
    {
        return in_array($targetMode, self::ALLOWED_TRANSITIONS[$currentMode] ?? [], true);
    }

    public function transition(string $currentMode, string $targetMode): string
    {
        if (! $this->canTransition($currentMode, $targetMode)) {
            throw new InvalidArgumentException(
                "Transisi mode operasi microgrid dari '{$currentMode}' ke '{$targetMode}' tidak diizinkan."
            );
        }

        return $targetMode;
    }
}
