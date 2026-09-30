<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum TrayStatus: string
{
    case ON_DISPLAY = 'on_display';
    case IN_SERVICE = 'in_service';
    case RETURNED = 'returned';
    case DISCARDED = 'discarded';
    case DEPLETED = 'depleted';

    public function label(): string
    {
        return match ($this) {
            self::ON_DISPLAY => 'Di Etalase',
            self::IN_SERVICE => 'Dihidang di Meja',
            self::RETURNED => 'Kembali Utuh',
            self::DISCARDED => 'Dibuang / Waste',
            self::DEPLETED => 'Habis Terjual',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::ON_DISPLAY => in_array($target, [self::IN_SERVICE, self::DISCARDED, self::DEPLETED], true),
            self::IN_SERVICE => in_array($target, [self::RETURNED, self::DEPLETED, self::DISCARDED], true),
            self::RETURNED => in_array($target, [self::ON_DISPLAY, self::DISCARDED], true),
            self::DISCARDED => false,
            self::DEPLETED => false,
        };
    }
}
