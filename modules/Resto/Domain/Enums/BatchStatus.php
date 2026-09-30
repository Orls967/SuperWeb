<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum BatchStatus: string
{
    case PLANNED = 'planned';
    case COOKING = 'cooking';
    case READY = 'ready';
    case ON_DISPLAY = 'on_display';
    case DEPLETED = 'depleted';
    case DISCARDED = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Direncanakan',
            self::COOKING => 'Sedang Dimasak',
            self::READY => 'Siap / Matang',
            self::ON_DISPLAY => 'Dipajang di Etalase',
            self::DEPLETED => 'Habis Terjual',
            self::DISCARDED => 'Dibuang / Waste',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::PLANNED => in_array($target, [self::COOKING, self::READY, self::DISCARDED], true),
            self::COOKING => in_array($target, [self::READY, self::DISCARDED], true),
            self::READY => in_array($target, [self::ON_DISPLAY, self::DEPLETED, self::DISCARDED], true),
            self::ON_DISPLAY => in_array($target, [self::DEPLETED, self::DISCARDED], true),
            self::DEPLETED => false,
            self::DISCARDED => false,
        };
    }
}
