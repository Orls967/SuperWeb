<?php

declare(strict_types=1);

namespace Modules\Asset\Infrastructure\Tenancy;

use Modules\Asset\Domain\Models\Asset;
use Modules\Core\Contracts\TenantScopedModelProvider;

class AssetTenantScopedModelProvider implements TenantScopedModelProvider
{
    public function moduleName(): string
    {
        return 'Asset';
    }

    public function scopedModels(): array
    {
        return [
            [
                'model' => Asset::class,
                'column' => 'tenant_id',
                'kind' => 'tenant',
            ],
        ];
    }
}
