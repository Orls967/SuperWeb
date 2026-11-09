<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

use Modules\Platform\Domain\Models\SandboxEnvironment;

/**
 * Service orkestrator data seeding modular untuk sandbox environment.
 */
class SandboxSeederService
{
    public function __construct(
        protected DeterministicDataFactory $factory = new DeterministicDataFactory()
    ) {
    }

    /**
     * @param list<string> $modules Daftar modul yang ingin di-seed (mis. ['banking', 'inventory'])
     * @return array<string, int> Jumlah record yang berhasil di-seed per domain
     */
    public function seedDomains(SandboxEnvironment $sandbox, array $modules): array
    {
        $results = [];

        foreach ($modules as $module) {
            $batch = $this->factory->generateBatch(
                domain: $module,
                count: 50,
                seedKey: (string) $sandbox->idempotency_key
            );

            $results[$module] = count($batch);
        }

        return $results;
    }
}

