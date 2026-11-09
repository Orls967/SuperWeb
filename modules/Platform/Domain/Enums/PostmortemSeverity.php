<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Enums;

enum PostmortemSeverity: string
{
    case SEV1_CRITICAL = 'sev1_critical';
    case SEV2_MAJOR = 'sev2_major';
    case SEV3_MINOR = 'sev3_minor';
}

