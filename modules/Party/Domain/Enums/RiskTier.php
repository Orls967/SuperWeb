<?php

declare(strict_types=1);

namespace Modules\Party\Domain\Enums;

enum RiskTier: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Blacklisted = 'blacklisted';
}
