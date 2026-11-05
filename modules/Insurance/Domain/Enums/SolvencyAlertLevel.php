<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Enums;

enum SolvencyAlertLevel: string
{
    case Healthy = 'healthy';
    case EarlyWarning = 'early_warning';
    case Regulator = 'regulator';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Sehat',
            self::EarlyWarning => 'Peringatan dini (< 150%)',
            self::Regulator => 'Ambang regulator (< 120%)',
        };
    }
}
