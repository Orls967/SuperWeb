<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Exceptions;

use RuntimeException;

final class InvalidAuditEvidenceManifestException extends RuntimeException
{
    public function __construct(string $filename, string $expectedSha256, string $actualSha256)
    {
        parent::__construct(
            "Integritas bukti audit gagal untuk berkas '{$filename}': Checksum diharapkan {$expectedSha256}, aktual {$actualSha256}."
        );
    }
}

