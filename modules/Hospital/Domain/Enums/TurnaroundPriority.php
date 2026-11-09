<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Enums;

enum TurnaroundPriority: string
{
    case STAT = 'STAT';
    case CITO = 'CITO';
    case ROUTINE = 'ROUTINE';

    public function maxTurnaroundMinutes(): int
    {
        return match ($this) {
            self::STAT => 30,
            self::CITO => 60,
            self::ROUTINE => 1440,
        };
    }
}
