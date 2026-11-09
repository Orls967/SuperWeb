<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Certificates;

final class MigrationSafetyCertificate
{
    /**
     * @param  list<string>  $verifiedFiles
     * @param  list<string>  $passedRules
     */
    public function __construct(
        public readonly string $certificateId,
        public readonly array $verifiedFiles,
        public readonly array $passedRules,
        public readonly int $violationCount,
        public readonly int $timestamp,
        public readonly string $sha256Hash
    ) {}

    public function isValid(): bool
    {
        return $this->violationCount === 0;
    }
}
