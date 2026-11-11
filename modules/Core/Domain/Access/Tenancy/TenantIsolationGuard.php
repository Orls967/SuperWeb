<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Tenancy;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Infrastructure\Tenancy\TenantScopeRegistry;

/**
 * Tenant Isolation Boundary & Integrity Guard (W8-D54, W8-D58).
 *
 * Verifies that database queries, model instances, and memory states remain strictly partitioned
 * by tenant. Prevents IDOR and cross-tenant data leakage.
 */
final class TenantIsolationGuard
{
    /**
     * Assert that a model instance belongs to the currently active tenant.
     *
     * @throws DomainException If model belongs to a different tenant
     */
    public function assertTenantOwnership(Model $model, string $activeTenantId): void
    {
        $modelTenantId = $model->getAttribute('tenant_id');

        if ($modelTenantId === null) {
            // If model is registered as tenant-scoped, missing tenant_id is an integrity violation
            if ($this->isModelTenantScoped(get_class($model))) {
                throw new DomainException(
                    sprintf('Tenant-scoped model [%s] has null tenant_id.', get_class($model))
                );
            }

            return;
        }

        if ((string) $modelTenantId !== $activeTenantId) {
            throw new DomainException(
                sprintf(
                    'Cross-tenant isolation violation: Model [%s #%s] belongs to tenant [%s], but active tenant is [%s].',
                    get_class($model),
                    (string) $model->getKey(),
                    (string) $modelTenantId,
                    $activeTenantId
                )
            );
        }
    }

    /**
     * Check if a model class is registered in TenantScopeRegistry.
     */
    public function isModelTenantScoped(string $modelClass): bool
    {
        $registered = TenantScopeRegistry::registeredModels();

        return in_array($modelClass, $registered, true);
    }

    /**
     * Validate an array of query filters to ensure tenant isolation clause is present.
     *
     * @param  array<string, mixed>  $criteria
     */
    public function validateQueryCriteria(array $criteria, string $expectedTenantId): bool
    {
        if (! isset($criteria['tenant_id'])) {
            return false;
        }

        return (string) $criteria['tenant_id'] === $expectedTenantId;
    }
}
