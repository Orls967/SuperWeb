<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Enums;

enum MetricCategory: string
{
    case LINES_OF_CODE = 'lines_of_code';
    case CYCLOMATIC_DENSITY = 'cyclomatic_density';
    case DUPLICATION = 'duplication';
    case REACHABILITY = 'reachability';
    case TEST_COVERAGE = 'test_coverage';
}

