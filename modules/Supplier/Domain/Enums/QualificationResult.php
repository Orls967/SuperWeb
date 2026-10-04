<?php

declare(strict_types=1);

namespace Modules\Supplier\Domain\Enums;

enum QualificationResult: string
{
    case Pending = 'pending';
    case Pass = 'pass';
    case Fail = 'fail';
}
