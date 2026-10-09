<?php

declare(strict_types=1);

namespace Modules\Party\Domain\Enums;

enum KybStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
