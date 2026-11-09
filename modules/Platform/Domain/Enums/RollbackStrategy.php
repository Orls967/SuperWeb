<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Enums;

enum RollbackStrategy: string
{
    case BLUE_GREEN_SWITCH = 'blue_green_switch';
    case FEATURE_FLAG_DISABLE = 'feature_flag_disable';
    case GIT_REVERT = 'git_revert';
    case DATABASE_DOWN_MIGRATION = 'database_down_migration';
}
