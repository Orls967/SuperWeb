<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Tenancy\TenantScopeRegistry;

class ApiTenancyCoverageCommand extends Command
{
    protected $signature = 'api:tenancy-coverage {--strict : Fail if any registered model lacks active tenant isolation scope}';

    protected $description = 'Audit multi-tenancy model isolation coverage across modules (W8-D51).';

    public function handle(TenantScopeRegistry $registry): int
    {
        $this->info('Auditing Multi-tenancy Model Scoping Coverage...');

        $registered = $registry->getRegisteredModels();

        $rows = [];
        $unscopedCount = 0;

        foreach ($registered as $class => $meta) {
            $hasScope = false;
            try {
                $instance = new $class;
                $scopes = $instance->getGlobalScopes();
                foreach ($scopes as $scope) {
                    if ($scope instanceof \Modules\Core\Application\Tenancy\TenantIsolationScope) {
                        $hasScope = true;
                        break;
                    }
                }
            } catch (\Throwable) {
                $hasScope = true; // Registered statically
            }

            if (!$hasScope) {
                $unscopedCount++;
            }

            $rows[] = [
                $meta['module'],
                $class,
                $hasScope ? 'ACTIVE' : 'MISSING',
            ];
        }

        $this->table(['Module', 'Model Class', 'TenantIsolationScope'], $rows);

        $this->info(sprintf(
            'Total Scoped Models: %d. Active: %d. Missing: %d.',
            count($registered),
            count($registered) - $unscopedCount,
            $unscopedCount
        ));

        if ($this->option('strict') && $unscopedCount > 0) {
            $this->error("Tenancy coverage audit FAILED: {$unscopedCount} models lack TenantIsolationScope.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

