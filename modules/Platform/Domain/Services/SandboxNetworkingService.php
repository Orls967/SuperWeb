<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

use Modules\Platform\Domain\Models\SandboxEnvironment;

/**
 * Service alokasi virtual networking dan isolasi port sandbox.
 */
class SandboxNetworkingService
{
    private const BASE_PORT = 18000;

    /**
     * Mengalokasikan port lokal virtual deterministik untuk sandbox
     */
    public function allocatePort(SandboxEnvironment $sandbox): int
    {
        $offset = ($sandbox->id * 7) % 1000;

        return self::BASE_PORT + $offset;
    }

    /**
     * Membangun URL akses virtual developer portal sandbox
     */
    public function buildVirtualAccessUrl(SandboxEnvironment $sandbox): string
    {
        $port = $this->allocatePort($sandbox);

        return sprintf('http://%s.sandbox.typeshit.local:%d', $sandbox->slug, $port);
    }
}

