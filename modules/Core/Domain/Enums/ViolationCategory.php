<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Enums;

enum ViolationCategory: string
{
    case DOMAIN_IMPORT = 'domain_import';
    case TABLE_PREFIX = 'table_prefix';
    case CIRCULAR_DEPENDENCY = 'circular_dependency';
    case SHARED_KERNEL = 'shared_kernel';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
