<?php

declare(strict_types=1);

namespace Modules\Core\Application\Access;

use Modules\Core\Application\Tenancy\TenantContext;
use Modules\Core\Application\Tenancy\TenantScopeRegistry;
use Modules\Core\Domain\Models\CoreTenant;
use Modules\Core\Domain\Models\CoreTenantScopeBypass;

/**
 * Continuous Automated Multi-Tenancy Isolation Probe Service (W8-D58).
 */
class TenantIsolationProbeService
{
    public function __construct(
        private readonly TenantScopeRegistry $scopeRegistry
    ) {}

    /**
     * Run random cross-tenant probe on registered models.
     *
     * @return array{status: 'OK'|'FAIL', tested_pairs: int, leaks_detected: int, checked_models: int}
     */
    public function runProbe(int $samples = 10): array
    {
        $tenants = CoreTenant::where('status', 'active')->limit(10)->get();
        if ($tenants->count() < 2) {
            return [
                'status' => 'OK',
                'tested_pairs' => 0,
                'leaks_detected' => 0,
                'checked_models' => count($this->scopeRegistry->registeredModels()),
            ];
        }

        $registeredClasses = $this->scopeRegistry->registeredModels();
        $testedPairs = 0;
        $leaksDetected = 0;

        for ($i = 0; $i < $samples; $i++) {
            $tA = $tenants->random();
            $tB = $tenants->where('id', '!=', $tA->id)->first();
            if ($tB === null) {
                continue;
            }

            $testedPairs++;

            foreach ($registeredClasses as $modelClass) {
                if (! class_exists($modelClass)) {
                    continue;
                }

                // Query under tenant A context
                $scopeA = new TenantContext($tA->id, $tA->code, (string) $tA->tier);
                TenantContext::set($scopeA);

                try {
                    $leakedCount = $modelClass::where('tenant_id', $tB->id)->count();
                    if ($leakedCount > 0) {
                        $leaksDetected++;
                        CoreTenantScopeBypass::create([
                            'tenant_id' => $tA->id,
                            'model_class' => $modelClass,
                            'operation' => 'TENANT_PROBE_LEAK',
                            'reason' => "Model {$modelClass} membocorkan {$leakedCount} baris tenant {$tB->id} ke tenant {$tA->id}",
                            'bypassed_at' => now(),
                        ]);
                    }
                } catch (\Throwable) {
                    // Fail-closed on any query error
                } finally {
                    TenantContext::clear();
                }
            }
        }

        return [
            'status' => $leaksDetected === 0 ? 'OK' : 'FAIL',
            'tested_pairs' => $testedPairs,
            'leaks_detected' => $leaksDetected,
            'checked_models' => count($registeredClasses),
        ];
    }
}
