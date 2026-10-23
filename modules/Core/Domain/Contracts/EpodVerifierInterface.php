<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts;

use Modules\Core\Domain\Models\CoreMobileEpodRecord;

interface EpodVerifierInterface
{
    /**
     * Verify whether recorded signature hash matches recalculated SHA-256 hash.
     */
    public function verifySignature(CoreMobileEpodRecord $record): bool;

    /**
     * Audit and flag tampering on an e-POD record.
     */
    public function flagTampering(string $epodCode): bool;
}
