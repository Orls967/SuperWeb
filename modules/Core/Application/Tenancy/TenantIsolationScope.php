<?php

declare(strict_types=1);

namespace Modules\Core\Application\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;

class TenantIsolationScope implements Scope
{
    /**
     * Cache column names per table to avoid repeated schema checks.
     *
     * @var array<string, array<string, bool>>
     */
    private static array $tableColumnsCache = [];

    public function apply(Builder $builder, Model $model): void
    {
        if (TenantScopeRegistry::isBypassed()) {
            return;
        }

        $context = TenantContext::current();
        if ($context === null) {
            // If strict enforcement is active and no context exists, enforce tenant isolation failure
            if (TenantScopeRegistry::isStrict()) {
                $builder->whereRaw('1 = 0');
            }

            return;
        }

        $table = $model->getTable();
        $columns = $this->getTableColumns($model);

        if (isset($columns['tenant_id'])) {
            $allowed = ! empty($context->allowedTenantIds) ? $context->allowedTenantIds : [$context->tenantId];
            $builder->whereIn($table.'.tenant_id', $allowed);
        }

        if (isset($columns['legal_entity_id']) && ! empty($context->allowedEntityIds)) {
            $builder->whereIn($table.'.legal_entity_id', $context->allowedEntityIds);
        }

        if (isset($columns['party_id']) && ! empty($context->allowedPartyIds)) {
            $builder->whereIn($table.'.party_id', $context->allowedPartyIds);
        }
    }

    /**
     * @return array<string, bool>
     */
    private function getTableColumns(Model $model): array
    {
        $table = $model->getTable();
        if (! isset(self::$tableColumnsCache[$table])) {
            $connection = $model->getConnectionName();
            try {
                $cols = Schema::connection($connection)->getColumnListing($table);
                self::$tableColumnsCache[$table] = array_fill_keys($cols, true);
            } catch (\Throwable) {
                self::$tableColumnsCache[$table] = [];
            }
        }

        return self::$tableColumnsCache[$table];
    }
}
