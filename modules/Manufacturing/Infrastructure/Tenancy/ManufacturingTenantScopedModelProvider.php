<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Infrastructure\Tenancy;

use Modules\Core\Contracts\TenantScopedModelProvider;
use Modules\Manufacturing\Domain\Models\Plant;

class ManufacturingTenantScopedModelProvider implements TenantScopedModelProvider
{
    public function moduleName(): string
    {
        return 'Manufacturing';
    }

    public function scopedModels(): array
    {
        return [
            [
                'model' => Plant::class,
                'column' => 'tenant_id',
                'kind' => 'tenant',
            ],
        ];
    }
}
