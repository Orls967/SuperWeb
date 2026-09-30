<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use RuntimeException;

class MissingSolasVgmException extends RuntimeException
{
    public static function forLoad(string $loadNumber, string $containerNumber): self
    {
        return new self("Pemuatan kontainer '{$containerNumber}' (Load '{$loadNumber}') ke kapal ditolak: Data Verified Gross Mass (VGM) sesuai SOLAS Chapter VI wajib tercatat sebelum muat.");
    }
}
