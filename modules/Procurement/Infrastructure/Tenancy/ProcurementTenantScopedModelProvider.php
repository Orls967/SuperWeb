<?php

declare(strict_types=1);

namespace Modules\Procurement\Infrastructure\Tenancy;

use Modules\Core\Contracts\TenantScopedModelProvider;
use Modules\Procurement\Domain\Models\Requisition;

class ProcurementTenantScopedModelProvider implements TenantScopedModelProvider
{
    public function moduleName(): string
    {
        return 'Procurement';
    }

    public function scopedModels(): array
    {
        return [
            [
                'model' => Requisition::class,
                'column' => 'tenant_id',
                'kind' => 'tenant',
            ],
        ];
    }
}

