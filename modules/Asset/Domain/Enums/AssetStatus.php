<?php

declare(strict_types=1);

namespace Modules\Asset\Domain\Enums;

enum AssetStatus: string
{
    case InUse = 'in_use';
    case Idle = 'idle';
    case UnderMaintenance = 'under_maintenance';
    case Disposed = 'disposed';

    public function label(): string
    {
        return match ($this) {
            self::InUse => 'Digunakan',
            self::Idle => 'Menganggur',
            self::UnderMaintenance => 'Dalam Perawatan',
            self::Disposed => 'Disposal',
        };
    }
}
