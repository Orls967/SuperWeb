<?php

declare(strict_types=1);

namespace Modules\Core\Domain\StateMachines;

enum SimulationRunStatus: string
{
    case DRAFT = 'draft';
    case PROVISIONED = 'provisioned';
    case RUNNING = 'running';
    case PAUSED = 'paused';
    case CHECKPOINTED = 'checkpointed';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case ARCHIVED = 'archived';

    /**
     * Label manusiawi untuk antarmuka pengguna.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Konsep (Draft)',
            self::PROVISIONED => 'Tersedia (Provisioned)',
            self::RUNNING => 'Berjalan (Running)',
            self::PAUSED => 'Dijeda (Paused)',
            self::CHECKPOINTED => 'Tersimpan (Checkpointed)',
            self::COMPLETED => 'Selesai (Completed)',
            self::FAILED => 'Gagal (Failed)',
            self::ARCHIVED => 'Diarsipkan (Archived)',
        };
    }

    /**
     * Warna badge representatif untuk komponen Design System x-ds.badge.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::DRAFT => 'neutral',
            self::PROVISIONED => 'info',
            self::RUNNING => 'primary',
            self::PAUSED => 'warning',
            self::CHECKPOINTED => 'success',
            self::COMPLETED => 'success',
            self::FAILED => 'danger',
            self::ARCHIVED => 'neutral',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::ARCHIVED;
    }
}
