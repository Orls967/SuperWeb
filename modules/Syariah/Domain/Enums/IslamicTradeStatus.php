<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Enums;

enum IslamicTradeStatus: string
{
    case DRAFT = 'draft';
    case PENDING_VERIFICATION = 'pending_verification';
    case ACTIVE = 'active';
    case IN_DELIVERY = 'in_delivery';
    case COMPLETED = 'completed';
    case DEFAULTED = 'defaulted';
    case CANCELLED = 'cancelled';
}
