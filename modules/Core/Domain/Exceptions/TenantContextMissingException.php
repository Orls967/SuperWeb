<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use DomainException;

/**
 * Thrown when tenant-enforced execution lacks an active TenantContext (fail-closed W8-D51).
 */
final class TenantContextMissingException extends DomainException
{
    public static function forOperation(string $operation): self
    {
        return new self("TENANT_CONTEXT_REQUIRED: Operasi [{$operation}] ditolak (fail-closed) karena konteks tenant tidak ada.");
    }
}

