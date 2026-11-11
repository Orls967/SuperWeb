<?php

declare(strict_types=1);

namespace Modules\Distribution\Infrastructure\Tenancy;

use Modules\Core\Contracts\TenantScopedModelProvider;
use Modules\Distribution\Domain\Models\Distributor;

class DistributionTenantScopedModelProvider implements TenantScopedModelProvider
{
    public function moduleName(): string
    {
        return 'Distribution';
    }

    public function scopedModels(): array
    {
        return [
            [
                'model' => Distributor::class,
                'column' => 'tenant_id',
                'kind' => 'tenant',
            ],
        ];
    }
}
