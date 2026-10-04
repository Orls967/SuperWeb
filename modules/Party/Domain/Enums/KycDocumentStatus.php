<?php

declare(strict_types=1);

namespace Modules\Party\Domain\Enums;

enum KycDocumentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
