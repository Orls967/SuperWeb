<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Enums;

enum CessionKind: string
{
    case Premium = 'premium';
    case Claim = 'claim';
    case TreatyPremium = 'treaty_premium';
    case Commutation = 'commutation';
}
