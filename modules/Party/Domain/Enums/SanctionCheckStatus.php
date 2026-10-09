<?php

declare(strict_types=1);

namespace Modules\Party\Domain\Enums;

enum SanctionCheckStatus: string
{
    case Clear = 'clear';
    case Hit = 'hit';
    case ManualReview = 'manual_review';
}
