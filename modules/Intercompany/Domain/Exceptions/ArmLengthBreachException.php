<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Exceptions;

use RuntimeException;

class ArmLengthBreachException extends RuntimeException
{
    public function __construct(int $actualMarginBps, int $q1Bps, int $q3Bps, string $category)
    {
        parent::__construct(
            "Pelanggaran prinsip Arm's Length (PMK 172/2023): Margin aktual [{$actualMarginBps} bps] berada di luar rentang interkuartil [{$q1Bps} bps - {$q3Bps} bps] untuk kategori [{$category}]."
        );
    }
}
