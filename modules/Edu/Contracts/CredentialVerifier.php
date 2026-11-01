<?php

declare(strict_types=1);

namespace Modules\Edu\Contracts;

use Carbon\CarbonImmutable;

interface CredentialVerifier
{
    /**
     * Checks if a user possesses an active, unexpired, and unrevoked credential certificate.
     */
    public function hasValid(int|string $userId, string $credentialCode, ?CarbonImmutable $at = null): bool;
}
